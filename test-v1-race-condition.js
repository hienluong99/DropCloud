const http = require('http');

async function testRaceCondition() {
    console.log("=== BẮT ĐẦU TEST RACE CONDITION (V1 LEGACY QUA GATEWAY) ===");
    console.log("Cổng Gateway: http://127.0.0.1:3000 (Round-Robin tới 4 PHP Workers)");
    console.log("Sự kiện có: 20 vé");
    console.log("Bắn đồng thời: 60 requests mua vé...\n");

    // Tự động reset kho vé về 20 trước khi test
    await new Promise((resolve) => {
        const resetReq = http.request({
            hostname: '127.0.0.1',
            port: 3000,
            path: '/api/v1/events/reset',
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, (res) => {
            res.on('data', () => {});
            res.on('end', resolve);
        });
        resetReq.write(JSON.stringify({ total_tickets: 20 }));
        resetReq.end();
    });

    const requests = [];

    for (let i = 1; i <= 60; i++) {
        const postData = JSON.stringify({
            event_id: 1,
            user_id: `user_concurrent_${i}`
        });

        const reqPromise = new Promise((resolve) => {
            const req = http.request({
                hostname: '127.0.0.1',
                port: 3000,
                path: '/api/v1/buy',
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData)
                }
            }, (res) => {
                let body = '';
                res.on('data', chunk => body += chunk);
                res.on('end', () => {
                    resolve({ status: res.statusCode, data: body });
                });
            });

            req.on('error', (e) => {
                resolve({ status: 500, error: e.message });
            });

            req.write(postData);
            req.end();
        });

        requests.push(reqPromise);
    }

    const responses = await Promise.all(requests);
    const success = responses.filter(r => r.status === 200).length;
    const failed = responses.filter(r => r.status !== 200).length;

    console.log(`Kết quả trả về từ API:`);
    console.log(`- Thành công (200 OK): ${success}`);
    console.log(`- Thất bại / Hết vé (400): ${failed}`);

    // Kiểm tra lại status từ DB
    http.get('http://127.0.0.1:3000/api/v1/events/1/status', (res) => {
        let body = '';
        res.on('data', chunk => body += chunk);
        res.on('end', () => {
            const data = JSON.parse(body);
            console.log("\n=============================================");
            console.log("--- THỰC TRẠNG TRONG DATABASE SAU ĐỢT TẢI ---");
            console.log(`- Tổng vé ban đầu: ${data.total_tickets}`);
            console.log(`- Số đơn hàng thực tế đã tạo: ${data.orders_created}`);
            console.log(`- Số vé còn lại (available_tickets): ${data.available_tickets}`);
            console.log(`- Phát hiện Bán Lố (Oversold): ${data.is_oversold ? `❌ CÓ - BÁN LỐ ${data.oversold_count} VÉ!` : 'KHÔNG'}`);
            console.log("=============================================");
        });
    });
}

testRaceCondition();
