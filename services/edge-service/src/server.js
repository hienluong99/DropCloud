require('dotenv').config();
const fs = require('fs');
const path = require('path');
const Fastify = require('fastify');
const Redis = require('ioredis');
const { SQSClient, SendMessageCommand, GetQueueAttributesCommand } = require('@aws-sdk/client-sqs');
const { Server: SocketIOServer } = require('socket.io');
const { v4: uuidv4 } = require('uuid');

const PORT = parseInt(process.env.PORT || '3001', 10);
const REDIS_HOST = process.env.REDIS_HOST || '127.0.0.1';
const REDIS_PORT = parseInt(process.env.REDIS_PORT || '6379', 10);
const SQS_QUEUE_URL = process.env.SQS_ORDERS_QUEUE_URL || 'http://127.0.0.1:4566/000000000000/clouddrop-orders-queue';
const CORE_SERVICE_URL = process.env.CORE_SERVICE_URL || 'http://127.0.0.1:8001';

const fastify = Fastify({ logger: false });

// 1. Redis Clients (Command client + Pub/Sub subscriber)
const redis = new Redis({
    host: REDIS_HOST,
    port: REDIS_PORT,
    maxRetriesPerRequest: 2,
    enableReadyCheck: true,
});

const redisSub = new Redis({
    host: REDIS_HOST,
    port: REDIS_PORT,
    maxRetriesPerRequest: null,
});

redis.on('error', (err) => {
    console.error('[Edge Redis Error]', err.message);
});

redisSub.on('error', (err) => {
    console.error('[Edge RedisSub Error]', err.message);
});

// Load & register atomic Lua script (ioredis uses EVALSHA automatically)
const reserveLuaScript = fs.readFileSync(
    path.join(__dirname, 'lua', 'reserve_ticket.lua'),
    'utf8'
);

redis.defineCommand('reserveTicket', {
    numberOfKeys: 2,
    lua: reserveLuaScript,
});

// 2. AWS SQS Client (Pointing to LocalStack in dev or real AWS in prod)
const sqsClient = new SQSClient({
    region: process.env.AWS_REGION || 'us-east-1',
    endpoint: process.env.AWS_ENDPOINT || 'http://127.0.0.1:4566',
    credentials: {
        accessKeyId: process.env.AWS_ACCESS_KEY_ID || 'test',
        secretAccessKey: process.env.AWS_SECRET_ACCESS_KEY || 'test',
    },
});

// 3. Socket.io Realtime Server attached to Fastify HTTP server
const io = new SocketIOServer(fastify.server, {
    cors: { origin: '*' },
});

io.on('connection', (socket) => {
    const userId = socket.handshake.query?.user_id;
    if (userId) {
        socket.join(`user:${userId}`);
    }
    socket.emit('edge:connected', { status: 'connected', socket_id: socket.id });
});

// Listen to Laravel Worker events via Redis Pub/Sub
redisSub.subscribe('clouddrop:order-events', (err) => {
    if (err) {
        console.error('[Edge] Failed to subscribe to Redis Pub/Sub:', err.message);
    } else {
        console.log('[Edge] Subscribed to Redis Pub/Sub channel: clouddrop:order-events');
    }
});

redisSub.on('message', (channel, message) => {
    if (channel !== 'clouddrop:order-events') return;
    try {
        const payload = JSON.parse(message);
        // Notify specific user room & broadcast to monitors
        if (payload.user_id) {
            io.to(`user:${payload.user_id}`).emit('order:ready', payload);
        }
        io.emit('worker:event', payload);
    } catch (e) {
        console.error('[Edge] Invalid Pub/Sub message:', e.message);
    }
});

// 4. Health Check Endpoint
fastify.get('/api/v3/health', async () => {
    let redisOk = false;
    let sqsOk = false;
    let queueDepth = null;

    try {
        const pong = await redis.ping();
        redisOk = pong === 'PONG';
    } catch (_) {}

    try {
        const attrs = await sqsClient.send(
            new GetQueueAttributesCommand({
                QueueUrl: SQS_QUEUE_URL,
                AttributeNames: ['ApproximateNumberOfMessages', 'ApproximateNumberOfMessagesNotVisible'],
            })
        );
        sqsOk = true;
        queueDepth = {
            visible: parseInt(attrs.Attributes?.ApproximateNumberOfMessages || '0', 10),
            in_flight: parseInt(attrs.Attributes?.ApproximateNumberOfMessagesNotVisible || '0', 10),
        };
    } catch (_) {}

    return {
        service: 'clouddrop-edge-service',
        version: 'v3',
        redis: redisOk ? 'connected' : 'disconnected',
        sqs: sqsOk ? 'connected' : 'disconnected',
        queue_depth: queueDepth,
    };
});

// 5. V3 Flash Sale Reserve Endpoint (Node.js + Redis Lua + AWS SQS)
fastify.post('/api/v3/reserve', async (request, reply) => {
    const startNs = process.hrtime.bigint();
    const { event_id: eventId, user_id: userId } = request.body || {};

    if (!eventId || !userId) {
        return reply.code(422).send({
            success: false,
            version: 'v3-edge-sqs',
            message: 'Thiếu event_id hoặc user_id.',
        });
    }

    const stockKey = `event:${eventId}:stock`;
    const buyersKey = `event:${eventId}:buyers`;

    try {
        // Step 1: Atomic check & decrement in Redis RAM (< 1ms)
        const luaResult = await redis.reserveTicket(stockKey, buyersKey, String(userId));
        const durationMs = Number(process.hrtime.bigint() - startNs) / 1e6;

        if (luaResult === -2) {
            return reply.code(409).send({
                success: false,
                version: 'v3-edge-sqs',
                code: 'CACHE_NOT_WARMED_UP',
                message: 'Kho vé trên Redis chưa được Warm-up! Hãy bấm Reset / Warm-up Cache trước.',
                latency_ms: Number(durationMs.toFixed(2)),
            });
        }

        if (luaResult === -1) {
            return reply.code(409).send({
                success: false,
                version: 'v3-edge-sqs',
                code: 'DUPLICATE_USER',
                message: 'Bạn đã giữ chỗ thành công cho sự kiện này rồi!',
                latency_ms: Number(durationMs.toFixed(2)),
            });
        }

        if (luaResult === 0) {
            return reply.code(409).send({
                success: false,
                version: 'v3-edge-sqs',
                code: 'SOLD_OUT',
                message: 'Hết vé! (Sold out in Redis)',
                latency_ms: Number(durationMs.toFixed(2)),
            });
        }

        const remainingStock = luaResult - 1;
        const reservationToken = uuidv4();

        // Step 2: Push message to AWS SQS (Decoupling Edge from MySQL/SQLite)
        const sqsPayload = {
            reservation_token: reservationToken,
            event_id: Number(eventId),
            user_id: String(userId),
            reserved_at: new Date().toISOString(),
            version: 'v3',
        };

        try {
            await sqsClient.send(
                new SendMessageCommand({
                    QueueUrl: SQS_QUEUE_URL,
                    MessageBody: JSON.stringify(sqsPayload),
                })
            );
        } catch (sqsErr) {
            // Rollback Redis if SQS fails (Compensating action to avoid phantom stock loss)
            await redis.incr(stockKey);
            await redis.srem(buyersKey, String(userId));
            throw sqsErr;
        }

        const totalDurationMs = Number(process.hrtime.bigint() - startNs) / 1e6;

        // Broadcast stock update via Socket.io
        io.emit('stock:updated', {
            event_id: Number(eventId),
            redis_stock: remainingStock,
        });

        // Step 3: Return 202 Accepted immediately (< 15ms)
        return reply.code(202).send({
            success: true,
            version: 'v3-edge-sqs',
            message: 'Đã giữ chỗ thành công! Đơn hàng đang được xử lý bất đồng bộ qua AWS SQS.',
            latency_ms: Number(totalDurationMs.toFixed(2)),
            data: {
                reservation_token: reservationToken,
                user_id: String(userId),
                remaining_tickets: remainingStock,
                status: 'QUEUED_IN_SQS',
            },
        });
    } catch (err) {
        const totalDurationMs = Number(process.hrtime.bigint() - startNs) / 1e6;
        return reply.code(500).send({
            success: false,
            version: 'v3-edge-sqs',
            error: 'Edge / SQS Error',
            details: err.message,
            latency_ms: Number(totalDurationMs.toFixed(2)),
        });
    }
});

// 6. Warm-up Redis Cache from DB
fastify.post('/api/v3/events/warm-up', async (request, reply) => {
    const { event_id: eventId = 1, total_tickets: totalTickets } = request.body || {};

    let stock = totalTickets;
    if (stock === undefined || stock === null) {
        const dbRes = await fetch(`${CORE_SERVICE_URL}/api/v1/events/${eventId}/status`);
        if (!dbRes.ok) {
            return reply.code(502).send({ message: 'Không thể lấy thông tin sự kiện từ Core Service.' });
        }
        const dbData = await dbRes.json();
        stock = dbData.available_tickets;
    }

    await redis.set(`event:${eventId}:stock`, String(stock));
    await redis.del(`event:${eventId}:buyers`);

    io.emit('stock:updated', { event_id: Number(eventId), redis_stock: Number(stock) });

    return {
        success: true,
        message: `Đã Warm-up Redis Cache cho Event #${eventId} với ${stock} vé!`,
        redis_stock: Number(stock),
    };
});

// 7. Reset both DB & Redis Cache (One-click reset for Stress Test Lab)
fastify.post('/api/v3/events/reset', async (request, reply) => {
    const { total_tickets: totalTickets = 20 } = request.body || {};

    const dbRes = await fetch(`${CORE_SERVICE_URL}/api/v1/events/reset`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ total_tickets: totalTickets }),
    });

    if (!dbRes.ok) {
        return reply.code(502).send({ message: 'Core Service reset thất bại.' });
    }

    await redis.set('event:1:stock', String(totalTickets));
    await redis.del('event:1:buyers');

    io.emit('stock:updated', { event_id: 1, redis_stock: Number(totalTickets) });

    return {
        success: true,
        message: `Đã reset MySQL/SQLite và Warm-up lại Redis Cache về ${totalTickets} vé!`,
        total_tickets: Number(totalTickets),
    };
});

// 8. Unified Status Endpoint (Compares Redis RAM vs DB Persistence + SQS Queue Depth)
fastify.get('/api/v3/events/:id/status', async (request, reply) => {
    const eventId = request.params.id;

    const [redisStockStr, redisBuyersCount, dbRes] = await Promise.all([
        redis.get(`event:${eventId}:stock`),
        redis.scard(`event:${eventId}:buyers`),
        fetch(`${CORE_SERVICE_URL}/api/v1/events/${eventId}/status`),
    ]);

    if (!dbRes.ok) {
        return reply.code(502).send({ message: 'Core Service unreachable' });
    }

    const dbData = await dbRes.json();
    let sqsVisible = 0;

    try {
        const attrs = await sqsClient.send(
            new GetQueueAttributesCommand({
                QueueUrl: SQS_QUEUE_URL,
                AttributeNames: ['ApproximateNumberOfMessages'],
            })
        );
        sqsVisible = parseInt(attrs.Attributes?.ApproximateNumberOfMessages || '0', 10);
    } catch (_) {}

    return {
        ...dbData,
        redis_stock: redisStockStr !== null ? parseInt(redisStockStr, 10) : null,
        redis_buyers: redisBuyersCount,
        sqs_queued_messages: sqsVisible,
    };
});

fastify.listen({ port: PORT, host: '0.0.0.0' }, (err, address) => {
    if (err) {
        console.error(err);
        process.exit(1);
    }
    console.log(`[CloudDrop Edge V3] Listening at ${address}`);
    console.log(`[CloudDrop Edge V3] Redis: ${REDIS_HOST}:${REDIS_PORT} | SQS: ${SQS_QUEUE_URL}`);
});
