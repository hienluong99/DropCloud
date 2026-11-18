const http = require('http');

function requestJson(method, path, body = null, port = 3000) {
    return new Promise((resolve) => {
        const postData = body ? JSON.stringify(body) : null;
        const headers = { 'Content-Type': 'application/json' };
        if (postData) headers['Content-Length'] = Buffer.byteLength(postData);

        const req = http.request({
            hostname: '127.0.0.1',
            port,
            path,
            method,
            headers,
        }, (res) => {
            let data = '';
            res.on('data', (chunk) => (data += chunk));
            res.on('end', () => {
                let parsed = null;
                try { parsed = JSON.parse(data); } catch (_) {}
                resolve({ status: res.statusCode, data: parsed, raw: data });
            });
        });

        req.on('error', (err) => resolve({ status: 500, error: err.message }));
        if (postData) req.write(postData);
        req.end();
    });
}

function fetchUrl(urlString) {
    return new Promise((resolve) => {
        http.get(urlString, (res) => {
            let data = '';
            res.on('data', (chunk) => (data += chunk));
            res.on('end', () => resolve({ status: res.statusCode, body: data }));
        }).on('error', (err) => resolve({ status: 500, error: err.message }));
    });
}

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function testV4Features() {
    console.log('=== KIỂM THỬ V4 (AWS SAA: DELAY QUEUE HOÀN VÉ & S3 PRESIGNED URL) ===\n');

    // 1. Reset về 20 vé
    console.log('[1] Reset DB & Warm-up Redis về 20 vé...');
    await requestJson('POST', '/api/v3/events/reset', { total_tickets: 20 });

    // 2. Test Flow A: Đặt vé với TTL = 5 giây và KHÔNG thanh toán -> Tự động thu hồi vé
    console.log('\n[2] Test Flow A: Giữ vé với ttl_seconds = 5s (Không thanh toán)...');
    const resExpire = await requestJson('POST', '/api/v3/reserve', {
        event_id: 1,
        user_id: 'v4_hoarder_user',
        ttl_seconds: 5,
    });
    const expireToken = resExpire.data?.data?.reservation_token;
    console.log(`    -> Reserved! Token: ${expireToken} | Redis Stock sau trừ: ${resExpire.data?.data?.remaining_tickets}`);

    console.log('    -> Đợi 7 giây để SQS Delay Queue kích hoạt Expiration Worker...');
    await sleep(7000);

    const expiredOrder = await requestJson('GET', `/api/v3/orders/${expireToken}`);
    const statusAfterExpire = await requestJson('GET', '/api/v3/events/1/status');
    console.log(`    -> Trạng thái đơn sau 7s: ${expiredOrder.data?.status}`);
    console.log(`    -> Tồn kho Redis sau thu hồi: ${statusAfterExpire.data?.redis_stock} (Kỳ vọng: 20)`);

    // 3. Test Flow B: Đặt vé -> Thanh toán -> Upload S3 & Tải vé qua Presigned URL
    console.log('\n[3] Test Flow B: Giữ vé -> Thanh toán -> Xuất E-Ticket lên AWS S3 & Tải qua Presigned URL...');
    const resBuy = await requestJson('POST', '/api/v3/reserve', {
        event_id: 1,
        user_id: 'v4_vip_buyer',
        ttl_seconds: 300,
    });
    const buyToken = resBuy.data?.data?.reservation_token;
    console.log(`    -> Reserved! Token: ${buyToken}`);

    // Đợi SQS Worker tạo đơn PENDING trong DB
    for (let i = 0; i < 10; i++) {
        await sleep(500);
        const check = await requestJson('GET', `/api/v3/orders/${buyToken}`);
        if (check.status === 200) break;
    }

    const payRes = await requestJson('POST', `/api/v3/orders/${buyToken}/pay`);
    const presignedUrl = payRes.data?.data?.ticket_download_url;
    console.log(`    -> Thanh toán: ${payRes.data?.data?.status}`);
    console.log(`    -> S3 Key: ${payRes.data?.data?.ticket_s3_key}`);
    console.log(`    -> Presigned URL: ${presignedUrl ? presignedUrl.slice(0, 85) + '...' : 'NONE'}`);

    if (presignedUrl) {
        const s3Download = await fetchUrl(presignedUrl);
        const verified = s3Download.status === 200 && s3Download.body.includes('VERIFIED E-TICKET (AWS S3)');
        console.log(`    -> Tải trực tiếp từ AWS S3 Presigned URL: HTTP ${s3Download.status} | Nội dung hợp lệ: ${verified ? '✅ CÓ' : '❌ KHÔNG'}`);
    }

    console.log('\n=== HOÀN TẤT KIỂM THỬ V4 ===');
}

testV4Features();
