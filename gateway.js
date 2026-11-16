const http = require('http');
const net = require('net');

// Cụm PHP-FPM / Laravel Core Workers (V1, V2 & Core API)
const PHP_BACKENDS = [
    { host: '127.0.0.1', port: 8001 },
    { host: '127.0.0.1', port: 8002 },
    { host: '127.0.0.1', port: 8003 },
    { host: '127.0.0.1', port: 8004 },
];

// Node.js Edge Service (V3: Fastify + Redis Lua + SQS + Socket.io)
const EDGE_BACKEND = { host: '127.0.0.1', port: 3001 };

let currentIdx = 0;

function pickTarget(url = '') {
    // Path-based routing (Mô phỏng AWS Application Load Balancer)
    // - /api/v3/orders/* đi về Laravel Core Service (Thanh toán, Tra cứu đơn)
    // - /api/v3/* (reserve, warm-up, reset, status, health) & /socket.io/* đi về Node.js Edge
    if (
        (url.startsWith('/api/v3') && !url.startsWith('/api/v3/orders')) ||
        url.startsWith('/socket.io')
    ) {
        return EDGE_BACKEND;
    }

    const target = PHP_BACKENDS[currentIdx % PHP_BACKENDS.length];
    currentIdx++;
    return target;
}

const server = http.createServer((req, res) => {
    const target = pickTarget(req.url);

    const proxyReq = http.request({
        hostname: target.host,
        port: target.port,
        path: req.url,
        method: req.method,
        headers: req.headers,
    }, (proxyRes) => {
        res.writeHead(proxyRes.statusCode, proxyRes.headers);
        proxyRes.pipe(res, { end: true });
    });

    proxyReq.on('error', (err) => {
        res.writeHead(502, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify({
            error: 'Bad Gateway',
            target: `${target.host}:${target.port}`,
            details: err.message,
        }));
    });

    req.pipe(proxyReq, { end: true });
});

// Hỗ trợ WebSocket Upgrade cho Socket.io (/socket.io/*) -> Node.js Edge (3001)
server.on('upgrade', (req, socket, head) => {
    const target = pickTarget(req.url);
    const proxySocket = net.connect(target.port, target.host, () => {
        const reqHeaders = Object.entries(req.headers)
            .map(([k, v]) => `${k}: ${v}`)
            .join('\r\n');
        proxySocket.write(
            `${req.method} ${req.url} HTTP/${req.httpVersion}\r\n${reqHeaders}\r\n\r\n`
        );
        if (head && head.length) {
            proxySocket.write(head);
        }
        proxySocket.pipe(socket);
        socket.pipe(proxySocket);
    });

    proxySocket.on('error', () => {
        socket.destroy();
    });
});

const PORT = 3000;
server.listen(PORT, () => {
    console.log(`[CloudDrop ALB Gateway] Đang lắng nghe tại http://localhost:${PORT}`);
    console.log(`  ├─ /api/v3/* & /socket.io/*  --> Node.js Edge (127.0.0.1:3001)`);
    console.log(`  └─ /*                        --> 4x Laravel Workers (8001-8004)`);
});
