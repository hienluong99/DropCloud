const http = require('http');

// Danh sách các worker PHP backend (Mô phỏng cụm PHP-FPM / Cluster xử lý song song)
const BACKENDS = [
    { host: '127.0.0.1', port: 8001 },
    { host: '127.0.0.1', port: 8002 },
    { host: '127.0.0.1', port: 8003 },
    { host: '127.0.0.1', port: 8004 },
];

let currentIdx = 0;

const server = http.createServer((req, res) => {
    // Round-robin load balancer
    const target = BACKENDS[currentIdx % BACKENDS.length];
    currentIdx++;

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
        res.end(JSON.stringify({ error: 'Bad Gateway', details: err.message }));
    });

    req.pipe(proxyReq, { end: true });
});

const PORT = 3000;
server.listen(PORT, () => {
    console.log(`[CloudDrop Gateway] Đang lắng nghe tại http://localhost:${PORT}`);
    console.log(`[CloudDrop Gateway] Phân tải round-robin tới 4 PHP workers (8001, 8002, 8003, 8004)`);
});
