<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phòng Thử Nghiệm Tải & So Sánh Kiến Trúc | CloudDrop V1 vs V2 vs V3</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="/socket.io/socket.io.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-[#090d16] text-slate-100 min-h-screen py-8 px-4" x-data="stressTestApp()" x-init="init()">
    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Top Banner Nav -->
        <div class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <a href="/" class="text-xs text-slate-400 hover:text-white flex items-center gap-1 transition">
                    &larr; Về Trang Mua Vé Khách Hàng
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs text-purple-400 font-bold uppercase tracking-wider">Architecture Benchmark Center</span>
                <span class="text-[11px] px-2 py-0.5 rounded-full border"
                      :class="wsConnected ? 'bg-emerald-950/60 border-emerald-700 text-emerald-300' : 'bg-slate-900 border-slate-700 text-slate-500'">
                    <span x-text="wsConnected ? '● Socket.io Live' : '○ Socket.io Offline'"></span>
                </span>
            </div>
            
            <!-- Version Switcher Pills -->
            <div class="flex flex-wrap items-center gap-2 bg-slate-900 p-1 rounded-xl border border-slate-800">
                <button @click="setVersion('v1')"
                        :class="version === 'v1' ? 'bg-red-950/80 text-red-300 border border-red-700 shadow-md' : 'text-slate-400 hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>🔴 V1: Legacy (Không khóa)</span>
                </button>
                <button @click="setVersion('v2')"
                        :class="version === 'v2' ? 'bg-amber-950/80 text-amber-300 border border-amber-600 shadow-md' : 'text-slate-400 hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>🟡 V2: DB Lock (FOR UPDATE)</span>
                </button>
                <button @click="setVersion('v3')"
                        :class="version === 'v3' ? 'bg-emerald-950/80 text-emerald-300 border border-emerald-600 shadow-md' : 'text-slate-400 hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>🟢 V3: Edge + Redis Lua + SQS</span>
                </button>
            </div>
        </div>

        <!-- Header Info -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                    <span>🔬 Phòng Thử Nghiệm Tải & So Sánh Kiến Trúc</span>
                </h1>
                <p class="text-slate-400 text-xs md:text-sm mt-1 max-w-3xl">
                    So sánh trực quan 3 thế hệ kiến trúc:
                    <strong class="text-red-400">V1 (Bán lố ~230%)</strong> &rarr;
                    <strong class="text-amber-400">V2 (0% Bán lố nhưng nghẽn DB Lock)</strong> &rarr;
                    <strong class="text-emerald-400">V3 (0% Bán lố &amp; Siêu tốc &lt; 15ms nhờ Node.js + Redis Lua + AWS SQS)</strong>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-3 py-1.5 rounded-lg border font-mono"
                      :class="version === 'v1' ? 'bg-red-950/40 border-red-800 text-red-300' : (version === 'v2' ? 'bg-amber-950/40 border-amber-800 text-amber-300' : 'bg-emerald-950/40 border-emerald-800 text-emerald-300')">
                    Target: <span class="font-bold" x-text="targetEndpoint"></span>
                </span>
            </div>
        </div>

        <!-- 4 Cột Đo Lường Trực Tiếp (Live Metrics) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <!-- Metric 1: Hạn mức kho / Redis RAM -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden">
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider"
                     x-text="version === 'v3' ? 'Redis RAM Stock (Edge)' : 'Hạn Mức Kho Ban Đầu'"></div>
                <div class="text-4xl font-black mt-2"
                     :class="version === 'v3' ? 'text-emerald-400' : 'text-white'"
                     x-text="version === 'v3' ? (status.redis_stock ?? '--') : status.total_tickets">--</div>
                <div class="text-[11px] text-slate-500 mt-2" x-show="version !== 'v3'">Tổng số vé thực tế BTC phát hành</div>
                <div class="text-[11px] text-slate-400 mt-2" x-show="version === 'v3'">
                    Tổng phát hành: <strong class="text-white" x-text="status.total_tickets"></strong> |
                    SQS Queue: <strong class="text-purple-400" x-text="status.sqs_queued_messages ?? 0"></strong>
                </div>
            </div>

            <!-- Metric 2: Số vé còn lại trên DB -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden">
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Số Vé Còn Lại Trong DB</div>
                <div class="text-4xl font-black mt-2"
                     :class="status.available_tickets < 0 ? 'text-red-500' : (status.available_tickets == 0 ? 'text-amber-400' : 'text-emerald-400')"
                     x-text="status.available_tickets">--</div>
                <div class="text-[11px] mt-2 text-slate-500" x-show="status.available_tickets >= 0">Số lượng DB ghi nhận còn lại</div>
                <div class="text-[11px] mt-2 text-red-400 font-bold" x-show="status.available_tickets < 0">⚠️ ĐÃ BỊ ÂM KHO!</div>
            </div>

            <!-- Metric 3: Số đơn hàng thực tế tạo ra -->
            <div class="rounded-xl p-5 shadow-lg relative overflow-hidden transition-all duration-300 border"
                 :class="status.is_oversold ? 'bg-red-950/25 border-red-500/80' : 'bg-slate-900/80 border-slate-800'">
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Số Đơn Thực Tế Trong DB</div>
                <div class="text-4xl font-black mt-2"
                     :class="status.is_oversold ? 'text-red-400 animate-pulse' : 'text-cyan-400'"
                     x-text="status.orders_created">--</div>
                
                <div class="text-[11px] mt-2 font-bold flex items-center gap-1.5"
                     :class="status.is_oversold ? 'text-red-400' : (status.orders_created == status.total_tickets ? 'text-emerald-400' : 'text-slate-500')">
                    <span x-show="!status.is_oversold && status.orders_created == 0">Chưa có đơn hàng</span>
                    <span x-show="!status.is_oversold && status.orders_created > 0 && status.orders_created <= status.total_tickets">✅ CHÍNH XÁC (0% Bán lố)</span>
                    <span x-show="status.is_oversold">❌ BÁN LỐ: <span class="underline" x-text="status.oversold_count"></span> VÉ!</span>
                </div>
            </div>

            <!-- Metric 4: Độ trễ đợt test gần nhất (Latency) -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden">
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Độ Trễ Test Cuối</div>
                <div class="text-4xl font-black mt-2"
                     :class="lastLatency.max > 1500 ? 'text-amber-400' : (lastLatency.p95 !== '--' && lastLatency.p95 < 50 ? 'text-emerald-400' : 'text-indigo-400')">
                    <span x-text="lastLatency.avg">--</span> <span class="text-xs font-normal text-slate-400">ms avg</span>
                </div>
                <div class="text-[11px] text-slate-500 mt-2">
                    Max: <span class="text-slate-300 font-bold" x-text="lastLatency.max">--</span> ms | 
                    p95: <span class="text-slate-300 font-bold" x-text="lastLatency.p95">--</span> ms
                </div>
            </div>

        </div>

        <!-- Control Playground -->
        <div class="bg-[#0f1424] border border-slate-800 rounded-xl p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                    <span>⚡ Bảng Điều Khiển Tải (Stress Test Controller)</span>
                    <span class="text-xs px-2 py-0.5 rounded font-normal"
                          :class="version === 'v1' ? 'bg-red-950 text-red-300 border border-red-800' : (version === 'v2' ? 'bg-amber-950 text-amber-300 border border-amber-800' : 'bg-emerald-950 text-emerald-300 border border-emerald-800')">
                        Chế độ: <strong x-text="version === 'v1' ? 'V1 (Legacy Không Khóa)' : (version === 'v2' ? 'V2 (Pessimistic DB Lock)' : 'V3 (Node.js + Redis Lua + AWS SQS)')"></strong>
                    </span>
                </div>
                <span class="text-xs text-slate-400 font-mono">Endpoint: <code class="text-purple-300" x-text="targetEndpoint"></code></span>
            </div>

            <div class="flex flex-wrap gap-3 items-center pt-2">
                
                <!-- Bắn 1 request -->
                <button @click="triggerSingleBuy()" :disabled="loading"
                        class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition">
                    🛒 Bắn 1 Request
                </button>

                <!-- Bắn 20 requests cùng lúc -->
                <button @click="triggerConcurrentBatch(20)" :disabled="loading"
                        class="px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-md">
                    ⚡ Bắn 20 Requests Đồng Thời
                </button>

                <!-- Bắn 60 requests cùng lúc -->
                <button @click="triggerConcurrentBatch(60)" :disabled="loading"
                        :class="version === 'v1' ? 'bg-rose-600 hover:bg-rose-500 shadow-rose-950' : (version === 'v2' ? 'bg-amber-600 hover:bg-amber-500 shadow-amber-950' : 'bg-emerald-600 hover:bg-emerald-500 shadow-emerald-950')"
                        class="px-5 py-2.5 rounded-lg text-white text-xs font-extrabold transition shadow-lg flex items-center gap-1.5">
                    <span x-text="version === 'v1' ? '🔥 Bắn 60 Requests (Kích hoạt Bán Lố V1)' : (version === 'v2' ? '🔒 Bắn 60 Requests (Khóa Dòng DB V2)' : '🚀 Bắn 60 Requests (Siêu Tốc V3 Edge + SQS)')"></span>
                </button>

                <!-- Reset Kho vé -->
                <button @click="resetEvent()" :disabled="loading"
                        class="ml-auto px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition flex items-center gap-1.5">
                    <span x-text="version === 'v3' ? '🔄 Reset DB & Warm-up Redis (20 vé)' : '🔄 Reset kho về 20 vé'"></span>
                </button>

            </div>

            <!-- Loading indicator -->
            <div x-show="loading" class="flex items-center gap-2 text-xs text-amber-400 pt-2" x-cloak>
                <svg class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                <span x-text="loadingText">Đang gửi requests...</span>
            </div>
        </div>

        <!-- Terminal Console Logs -->
        <div class="bg-black/80 border border-slate-800 rounded-xl p-5 font-mono text-xs">
            <div class="flex justify-between items-center pb-2 mb-3 border-b border-slate-800/80 text-slate-400">
                <span class="font-bold text-slate-300">🖥️ Nhật Ký Phản Hồi Từ Mạng, Edge &amp; AWS SQS Worker (Live Logs)</span>
                <button @click="logs = []" class="text-xs hover:text-white underline">Xóa log</button>
            </div>

            <div class="h-64 overflow-y-auto space-y-1.5 pr-2">
                <template x-for="(log, idx) in logs" :key="idx">
                    <div :class="log.type === 'error' ? 'text-red-400' : (log.type === 'success' ? 'text-emerald-400' : (log.type === 'warning' ? 'text-amber-400' : (log.type === 'worker' ? 'text-cyan-400' : 'text-slate-300')))">
                        <span class="text-slate-600" x-text="log.time"></span>
                        <span class="font-bold" x-text="'[' + log.badge + ']'"></span>
                        <span x-text="log.text"></span>
                    </div>
                </template>
                <div x-show="logs.length === 0" class="text-slate-600 italic">Chưa có lượt test nào. Hãy chọn phiên bản và bấm các nút ở trên.</div>
            </div>
        </div>

        <!-- Comparison Talking Points for Interviews -->
        <div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5 space-y-3 text-xs">
            <div class="font-bold uppercase tracking-wider text-purple-300 flex items-center gap-2">
                <span>💡 Bảng So Sánh 3 Thế Hệ Kiến Trúc (Architecture Evolution &amp; Talking Points):</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-1">
                <!-- V1 Card -->
                <div class="bg-red-950/20 border border-red-900/50 rounded-lg p-3 space-y-1.5">
                    <div class="font-bold text-red-300">🔴 V1: Legacy Monolith</div>
                    <ul class="list-disc pl-4 space-y-1 text-slate-300">
                        <li><strong>Cơ chế:</strong> <code>SELECT</code> rồi <code>UPDATE</code> không khóa.</li>
                        <li><strong>Kết quả:</strong> ❌ Bán lố 200–400% do Race Condition.</li>
                        <li><strong>Bài học:</strong> Tuyệt đối không check-then-act ngoài Transaction.</li>
                    </ul>
                </div>

                <!-- V2 Card -->
                <div class="bg-amber-950/20 border border-amber-900/50 rounded-lg p-3 space-y-1.5">
                    <div class="font-bold text-amber-300">🟡 V2: Pessimistic DB Lock</div>
                    <ul class="list-disc pl-4 space-y-1 text-slate-300">
                        <li><strong>Cơ chế:</strong> <code>SELECT ... FOR UPDATE</code>.</li>
                        <li><strong>Kết quả:</strong> ✅ 0% Bán lố.</li>
                        <li><strong>Điểm nghẽn:</strong> ⚠️ <strong>Lock Contention</strong> khiến p95 vọt lên hàng giây, nghẽn DB Connection Pool.</li>
                    </ul>
                </div>

                <!-- V3 Card -->
                <div class="bg-emerald-950/20 border border-emerald-900/50 rounded-lg p-3 space-y-1.5">
                    <div class="font-bold text-emerald-300">🟢 V3: Edge + Redis Lua + AWS SQS</div>
                    <ul class="list-disc pl-4 space-y-1 text-slate-300">
                        <li><strong>Cơ chế:</strong> Trừ kho Atomic trên RAM (Redis Lua) &rarr; Đẩy AWS SQS &rarr; Laravel Worker ghi DB Idempotent.</li>
                        <li><strong>Kết quả:</strong> ✅ <strong>0% Bán lố</strong>, độ trễ <strong>&lt; 15ms</strong>, bảo vệ DB tuyệt đối!</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>

    <!-- Script Alpine cho Stress Test Lab -->
    <script>
        function stressTestApp() {
            return {
                version: 'v3', // Mặc định mở V3 để trải nghiệm kiến trúc mới nhất
                wsConnected: false,
                socket: null,
                status: {
                    total_tickets: 20,
                    available_tickets: 20,
                    orders_created: 0,
                    is_oversold: false,
                    oversold_count: 0,
                    redis_stock: null,
                    sqs_queued_messages: 0
                },
                lastLatency: {
                    avg: '--',
                    max: '--',
                    p95: '--'
                },
                loading: false,
                loadingText: '',
                logs: [],

                get targetEndpoint() {
                    return this.version === 'v3' ? '/api/v3/reserve' : `/api/${this.version}/buy`;
                },

                async init() {
                    await this.fetchStatus();
                    this.initSocket();
                },

                initSocket() {
                    if (typeof io === 'undefined') return;
                    try {
                        this.socket = io('/', { transports: ['websocket', 'polling'] });
                        this.socket.on('connect', () => {
                            this.wsConnected = true;
                        });
                        this.socket.on('disconnect', () => {
                            this.wsConnected = false;
                        });
                        this.socket.on('stock:updated', (payload) => {
                            if (payload && typeof payload.redis_stock !== 'undefined') {
                                this.status.redis_stock = payload.redis_stock;
                            }
                        });
                        this.socket.on('worker:event', (payload) => {
                            if (payload.type === 'ORDER_READY') {
                                this.addLog('SQS_WORKER', `Laravel Worker đã lưu Order #${payload.order_id} (${payload.user_id}) & phát Socket.io!`, 'worker');
                                this.fetchStatus();
                            }
                        });
                    } catch (e) {
                        console.warn('Socket.io init skipped:', e);
                    }
                },

                setVersion(ver) {
                    this.version = ver;
                    this.addLog('CONFIG', `Đã chuyển sang kiểm thử: ${ver.toUpperCase()} (Target: ${this.targetEndpoint})`, 'info');
                    this.fetchStatus();
                },

                addLog(badge, text, type = 'info') {
                    const time = new Date().toLocaleTimeString();
                    this.logs.unshift({ time, badge, text, type });
                    if (this.logs.length > 150) this.logs.pop();
                },

                async fetchStatus() {
                    try {
                        const res = await fetch(`/api/${this.version}/events/1/status`);
                        if (res.ok) {
                            const data = await res.json();
                            this.status = data;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                },

                async triggerSingleBuy() {
                    this.loading = true;
                    this.loadingText = `Đang gửi 1 request tới ${this.targetEndpoint}...`;
                    const userId = 'tester_' + Math.floor(Math.random() * 9000 + 1000);
                    const startTime = Date.now();
                    try {
                        const res = await fetch(this.targetEndpoint, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ event_id: 1, user_id: userId })
                        });
                        const duration = Date.now() - startTime;
                        const data = await res.json();
                        if (res.ok && data.success) {
                            const label = this.version === 'v3' ? `Token ${data.data.reservation_token.slice(0,8)}...` : `#${data.data.order_id}`;
                            this.addLog('SUCCESS', `[${this.version.toUpperCase()}] User [${userId}] giữ/mua thành công (${label}) trong ${duration}ms, Vé còn: ${data.data.remaining_tickets}`, 'success');
                        } else {
                            this.addLog('REJECTED', `[${this.version.toUpperCase()}] User [${userId}] bị từ chối (${res.status}): ${data.message} (${duration}ms)`, 'warning');
                        }
                    } catch (e) {
                        this.addLog('ERROR', e.message, 'error');
                    } finally {
                        await this.fetchStatus();
                        this.loading = false;
                    }
                },

                async triggerConcurrentBatch(count) {
                    this.loading = true;
                    this.loadingText = `Đang đồng thời bắn ${count} requests vào ${this.targetEndpoint}...`;
                    this.addLog('BATCH_START', `Bắt đầu gửi đồng loạt ${count} requests vào ${this.version.toUpperCase()}...`, 'info');

                    const reqs = [];
                    for (let i = 1; i <= count; i++) {
                        const uid = `${this.version}_user_${i}_` + Math.floor(Math.random() * 900 + 100);
                        const reqStartTime = Date.now();
                        reqs.push(
                            fetch(this.targetEndpoint, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ event_id: 1, user_id: uid })
                            }).then(async res => {
                                const duration = Date.now() - reqStartTime;
                                const data = await res.json();
                                return { ok: res.ok, status: res.status, data, uid, duration };
                            }).catch(err => ({ ok: false, error: err.message, uid, duration: Date.now() - reqStartTime }))
                        );
                    }

                    const results = await Promise.all(reqs);
                    const successCount = results.filter(r => r.ok && r.data?.success).length;
                    const failCount = results.filter(r => !r.ok).length;

                    const durations = results.map(r => r.duration).sort((a, b) => a - b);
                    const avg = Math.round(durations.reduce((a, b) => a + b, 0) / durations.length);
                    const max = durations[durations.length - 1];
                    const p95 = durations[Math.floor(durations.length * 0.95)];
                    this.lastLatency = { avg, max, p95 };

                    if (this.version === 'v1') {
                        this.addLog('BATCH_DONE', `[V1] ${count} requests: ${successCount} Thành công, ${failCount} Thất bại. (Avg: ${avg}ms, Max: ${max}ms)`, successCount > this.status.total_tickets ? 'error' : 'info');
                    } else if (this.version === 'v2') {
                        this.addLog('BATCH_DONE', `[V2] ${count} requests: ${successCount} Thành công (Chuẩn: ${this.status.total_tickets}), ${failCount} Hết vé. (Avg: ${avg}ms, Max: ${max}ms, p95: ${p95}ms)`, 'success');
                    } else {
                        this.addLog('BATCH_DONE', `[V3 EDGE] ${count} requests: ${successCount} Accepted (202 -> SQS), ${failCount} Rejected (409). (Avg: ${avg}ms, Max: ${max}ms, p95: ${p95}ms)`, 'success');
                        // Poll thêm vài nhịp để thấy SQS Worker xả đơn vào DB
                        setTimeout(() => this.fetchStatus(), 800);
                        setTimeout(() => this.fetchStatus(), 2000);
                    }

                    await this.fetchStatus();
                    this.loading = false;
                },

                async resetEvent() {
                    this.loading = true;
                    this.loadingText = this.version === 'v3' ? 'Đang reset DB & Warm-up Redis Cache...' : 'Đang reset kho vé...';
                    try {
                        const res = await fetch(`/api/${this.version}/events/reset`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ total_tickets: 20 })
                        });
                        if (res.ok) {
                            this.addLog('RESET', this.version === 'v3' ? 'Đã reset DB và Warm-up Redis Cache về 20 vé!' : 'Đã xóa toàn bộ đơn hàng và khôi phục kho về 20 vé!', 'info');
                            this.lastLatency = { avg: '--', max: '--', p95: '--' };
                        }
                    } catch (e) {
                        this.addLog('ERROR', e.message, 'error');
                    } finally {
                        await this.fetchStatus();
                        this.loading = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
