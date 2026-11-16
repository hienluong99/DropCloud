const http = require('http');

function requestJson(method, path, body = null) {
    return new Promise((resolve) => {
        const start = process.hrtime.bigint();
        const postData = body ? JSON.stringify(body) : null;
        const headers = { 'Content-Type': 'application/json' };
        if (postData) headers['Content-Length'] = Buffer.byteLength(postData);

        const req = http.request({
            hostname: '127.0.0.1',
            port: 3000,
            path,
            method,
            headers,
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => (data += chunk));
            res.on('end', () => {
                const durationMs = Number(process.hrtime.bigint() - start) / 1e6;
                let parsed = null;
                try { parsed = JSON.parse(data); } catch (_) {}
                resolve({ status: res.statusCode, data: parsed, durationMs });
            });
        });

        req.on('error', (err) => {
            const durationMs = Number(process.hrtime.bigint() - start) / 1e6;
            resolve({ status: 500, error: err.message, durationMs });
        });

        if (postData) req.write(postData);
        req.end();
    });
}

async function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

async function testV3EdgeAndSqs() {
    console.log('=== BẮT ĐẦU KIỂM THỬ V3: NODE.JS EDGE + REDIS LUA + AWS SQS ===');
    console.log('1. Reset DB & Warm-up Redis Cache về 20 vé...');
    const resetRes = await requestJson('POST', '/api/v3/events/reset', { total_tickets: 20 });
    if (resetRes.status !== 200) {
        console.error('❌ Lỗi Reset/Warm-up:', resetRes);
        process.exit(1);
    }
    console.log('   ->', resetRes.data.message);

    console.log('\n2. Bắn đồng thời 100 requests tới POST /api/v3/reserve...');
    const requests = [];
    for (let i = 1; i <= 100; i++) {
        requests.push(
            requestJson('POST', '/api/v3/reserve', {
                event_id: 1,
                user_id: `v3_concurrent_user_${i}`,
            })
        );
    }

    const responses = await Promise.all(requests);
    const accepted = responses.filter((r) => r.status === 202).length;
    const rejected = responses.filter((r) => r.status === 409).length;
    const errors = responses.filter((r) => r.status >= 500).length;

    const durations = responses.map((r) => r.durationMs).sort((a, b) => a - b);
    const avg = (durations.reduce((a, b) => a + b, 0) / durations.length).toFixed(2);
    const p50 = durations[Math.floor(durations.length * 0.5)].toFixed(2);
    const p95 = durations[Math.floor(durations.length * 0.95)].toFixed(2);
    const max = durations[durations.length - 1].toFixed(2);

    console.log('\n--- KẾT QUẢ TẠI TẦNG EDGE (NODE.JS + REDIS LUA) ---');
    console.log(`- 202 Accepted (Giữ chỗ & Đẩy SQS): ${accepted}`);
    console.log(`- 409 Rejected (Hết vé / Trùng user): ${rejected}`);
    console.log(`- 5xx Errors: ${errors}`);
    console.log(`- Latency Avg: ${avg}ms | p50: ${p50}ms | p95: ${p95}ms | Max: ${max}ms`);

    console.log('\n3. Chờ Laravel SQS Worker tiêu thụ hàng đợi & lưu vào DB...');
    let finalStatus = null;
    for (let attempt = 1; attempt <= 15; attempt++) {
        await sleep(500);
        const statusRes = await requestJson('GET', '/api/v3/events/1/status');
        finalStatus = statusRes.data;
        if (finalStatus && finalStatus.orders_created >= accepted && finalStatus.sqs_queued_messages === 0) {
            break;
        }
    }

    console.log('\n======================================================');
    console.log('--- THỰC TRẠNG SAU KHI LARAVEL WORKER XỬ LÝ XONG ---');
    console.log(`- Tổng vé ban đầu: ${finalStatus.total_tickets}`);
    console.log(`- Tồn kho trên Redis (RAM): ${finalStatus.redis_stock}`);
    console.log(`- Tồn kho trên DB (available_tickets): ${finalStatus.available_tickets}`);
    console.log(`- Số đơn hàng thực tế trong DB: ${finalStatus.orders_created}`);
    console.log(`- Tin nhắn còn trong AWS SQS: ${finalStatus.sqs_queued_messages}`);
    console.log(`- Bán lố (Overselling): ${finalStatus.is_oversold ? '❌ CÓ' : '✅ KHÔNG (0% Overselling!)'}`);
    console.log('======================================================');
}

testV3EdgeAndSqs();
