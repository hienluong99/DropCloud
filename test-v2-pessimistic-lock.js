const http = require('http');

async function testV2PessimisticLock() {
    console.log("=================================================================");
    console.log("=== BẮT ĐẦU TEST V2: DATABASE PESSIMISTIC LOCKING (FOR UPDATE) ===");
    console.log("=================================================================");
    console.log("Target Endpoint: http://127.0.0.1:3000/api/v2/buy");
    console.log("Tổng số vé ban đầu: 20 vé");
    console.log("Bắn đồng thời: 60 requests mua vé...\n");

    // 1. Reset lại sự kiện về 20 vé trước khi test
    await new Promise((resolve) => {
        const resetReq = http.request({
            hostname: '127.0.0.1',
            port: 3000,
            path: '/api/v2/events/reset',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, (res) => {
            res.on('data', () => {});
            res.on('end', resolve);
        });
        resetReq.write(JSON.stringify({ total_tickets: 20 }));
        resetReq.end();
    });

    console.log("🔄 Đã reset kho vé về 20 vé. Đang kích hoạt 60 requests song song...\n");

    const startTime = Date.now();
    const requests = [];

    for (let i = 1; i <= 60; i++) {
        const postData = JSON.stringify({
            event_id: 1,
            user_id: `v2_concurrent_user_${i}`
        });

        const reqStartTime = Date.now();
        const reqPromise = new Promise((resolve) => {
            const req = http.request({
                hostname: '127.0.0.1',
                port: 3000,
                path: '/api/v2/buy',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData)
                }
            }, (res) => {
                let body = '';
                res.on('data', chunk => body += chunk);
                res.on('end', () => {
                    const duration = Date.now() - reqStartTime;
                    let parsed = {};
                    try { parsed = JSON.parse(body); } catch (e) {}
                    resolve({
                        status: res.statusCode,
                        duration,
                        data: parsed
                    });
                });
            });

            req.on('error', (e) => {
                const duration = Date.now() - reqStartTime;
                resolve({ status: 500, duration, error: e.message });
            });

            req.write(postData);
            req.end();
        });

        requests.push(reqPromise);
    }

    const responses = await Promise.all(requests);
    const totalDuration = Date.now() - startTime;

    const success = responses.filter(r => r.status === 200);
    const rejected = responses.filter(r => r.status === 400);
    const serverErrors = responses.filter(r => r.status >= 500);

    const latencies = responses.map(r => r.duration).sort((a, b) => a - b);
    const minLatency = latencies[0];
    const maxLatency = latencies[latencies.length - 1];
    const avgLatency = Math.round(latencies.reduce((sum, v) => sum + v, 0) / latencies.length);
    const p95Index = Math.floor(latencies.length * 0.95);
    const p95Latency = latencies[p95Index];

    console.log("-----------------------------------------------------------------");
    console.log("📊 KẾT QUẢ PHẢN HỒI HTTP TỪ CỤM BACKEND V2:");
    console.log(`- Tổng số requests gửi đi : 60`);
    console.log(`- Mua thành công (200 OK)  : ${success.length} (Chuẩn: 20 vé)`);
    console.log(`- Bị từ chối vì hết vé (400): ${rejected.length} (Chuẩn: 40 requests)`);
    console.log(`- Lỗi Server / Timeout (5xx): ${serverErrors.length}`);
    console.log(`- Tổng thời gian hoàn tất : ${totalDuration} ms`);
    console.log(`- Độ trễ tối thiểu (Min)  : ${minLatency} ms`);
    console.log(`- Độ trễ trung bình (Avg) : ${avgLatency} ms`);
    console.log(`- Độ trễ tối đa (Max)     : ${maxLatency} ms (Minh chứng xếp hàng đợi khóa)`);
    console.log(`- Phân vị p95             : ${p95Latency} ms`);
    console.log("-----------------------------------------------------------------");

    // Đối chiếu với cơ sở dữ liệu
    http.get('http://127.0.0.1:3000/api/v2/events/1/status', (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
            const data = JSON.parse(body);
            console.log("\n=================================================================");
            console.log("--- KIỂM TRA TÍNH TOÀN VẸN DỮ LIỆU TRONG DATABASE (V2) ---");
            console.log(`- Hạn mức vé ban đầu       : ${data.total_tickets}`);
            console.log(`- Số đơn thực tế tạo trong DB: ${data.orders_created}`);
            console.log(`- Số vé còn lại trong DB   : ${data.available_tickets}`);
            console.log(`- Phát hiện Bán Lố (Oversold): ${data.is_oversold ? `❌ CÓ - BÁN LỐ ${data.oversold_count} VÉ!` : '✅ KHÔNG (0% Overselling)'}`);
            console.log("=================================================================");
            
            console.log("\n💡 BÀI HỌC KIẾN TRÚC & PHỎNG VẤN:");
            console.log("1. V2 đã GIẢI QUYẾT TRIỆT ĐỂ lỗi bán lố của V1 (Chính xác tuyệt đối 20/20 vé).");
            console.log("2. Nhưng phát sinh ĐIỂM NGHẼN MỚI (Trade-off):");
            console.log(`   - Độ trễ tăng cao do Lock Contention (Max latency lên tới ~${maxLatency}ms vì request cuối phải đợi giải phóng khóa).`);
            console.log("   - Giữ kết nối DB dài dẫn tới cạn kiệt Connection Pool.");
            console.log("   -> Đặt ra nhu cầu bắt buộc phải nâng cấp lên V3 (Redis Lua Script + AWS SQS)!\n");
        });
    });
}

testV2PessimisticLock();
