<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phòng Thử Nghiệm Tải & So Sánh Kiến Trúc | CloudDrop V1 vs V2</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
            </div>
            
            <!-- Version Switcher Pills -->
            <div class="flex items-center gap-2 bg-slate-900 p-1 rounded-xl border border-slate-800">
                <button @click="setVersion('v1')"
                        :class="version === 'v1' ? 'bg-red-950/80 text-red-300 border border-red-700 shadow-md' : 'text-slate-400 hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>🔴 V1: Legacy (Không khóa)</span>
                </button>
                <button @click="setVersion('v2')"
                        :class="version === 'v2' ? 'bg-amber-950/80 text-amber-300 border border-amber-600 shadow-md' : 'text-slate-400 hover:text-slate-200'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5">
                    <span>🟡 V2: DB Lock (SELECT FOR UPDATE)</span>
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
                    Chạy đồng thời hàng chục requests từ nhiều người dùng vào Gateway để so sánh trực quan giữa 
                    <strong class="text-red-400">V1 (Bán lố ~230%)</strong> và 
                    <strong class="text-amber-400">V2 (0% Bán lố nhưng tăng độ trễ do Lock Contention)</strong>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs px-3 py-1.5 rounded-lg border font-mono"
                      :class="version === 'v1' ? 'bg-red-950/40 border-red-800 text-red-300' : 'bg-amber-950/40 border-amber-800 text-amber-300'">
                    Target: <span class="font-bold" x-text="targetEndpoint"></span>
                </span>
            </div>
        </div>

        <!-- 3 Cột Đo Lường Trực Tiếp (Live Metrics) -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            
            <!-- Metric 1: Hạn mức kho -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-xl p-5 shadow-lg relative overflow-hidden">
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Hạn Mức Kho Ban Đầu</div>
                <div class="text-4xl font-black text-white mt-2" x-text="status.total_tickets">--</div>
                <div class="text-[11px] text-slate-500 mt-2">Tổng số vé thực tế BTC phát hành</div>
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
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Số Đơn Thực Tế Đã Tạo</div>
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
                     :class="lastLatency.max > 1500 ? 'text-amber-400' : 'text-indigo-400'">
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
                          :class="version === 'v1' ? 'bg-red-950 text-red-300 border border-red-800' : 'bg-amber-950 text-amber-300 border border-amber-800'">
                        Chế độ: <strong x-text="version === 'v1' ? 'V1 (Legacy Không Khóa)' : 'V2 (Pessimistic DB Lock)'"></strong>
                    </span>
                </div>
                <span class="text-xs text-slate-400 font-mono">Endpoint: <code class="text-purple-300" x-text="targetEndpoint"></code></span>
            </div>

            <div class="flex flex-wrap gap-4 items-center pt-2">
                
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
                        :class="version === 'v1' ? 'bg-rose-600 hover:bg-rose-500 shadow-rose-950' : 'bg-amber-600 hover:bg-amber-500 shadow-amber-950'"
                        class="px-5 py-2.5 rounded-lg text-white text-xs font-extrabold transition shadow-lg flex items-center gap-1.5">
                    <span x-text="version === 'v1' ? '🔥 Bắn 60 Requests (Kích hoạt Bán Lố V1)' : '🔒 Bắn 60 Requests (Khóa Dòng DB V2)'"></span>
                </button>

                <!-- Reset Kho vé -->
                <button @click="resetEvent()" :disabled="loading"
                        class="ml-auto px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold border border-slate-700 transition flex items-center gap-1.5">
                    <span>🔄 Reset kho về 20 vé</span>
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
                <span class="font-bold text-slate-300">🖥️ Nhật Ký Phản Hồi Từ Mạng & Server (Live Logs)</span>
                <button @click="logs = []" class="text-xs hover:text-white underline">Xóa log</button>
            </div>

            <div class="h-64 overflow-y-auto space-y-1.5 pr-2">
                <template x-for="(log, idx) in logs" :key="idx">
                    <div :class="log.type === 'error' ? 'text-red-400' : (log.type === 'success' ? 'text-emerald-400' : (log.type === 'warning' ? 'text-amber-400' : 'text-slate-300'))">
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
                <span>💡 Bảng So Sánh Kiến Trúc (Architecture Comparison & Talking Points):</span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 pt-1">
                <!-- V1 Card -->
                <div class="bg-red-950/20 border border-red-900/50 rounded-lg p-3 space-y-1.5">
                    <div class="font-bold text-red-300">🔴 V1: Legacy Monolith (Không khóa)</div>
                    <ul class="list-disc pl-4 space-y-1 text-slate-300">
                        <li><strong>Cơ chế:</strong> <code>SELECT</code> rồi <code>UPDATE</code> không đồng bộ (Check-then-Act).</li>
                        <li><strong>Kết quả:</strong> ❌ Bán lố 200 - 400% do Race Condition.</li>
                        <li><strong>Độ trễ:</strong> Nhanh giả tạo vì không phải xếp hàng chờ khóa.</li>
                    </ul>
                </div>

                <!-- V2 Card -->
                <div class="bg-amber-950/20 border border-amber-900/50 rounded-lg p-3 space-y-1.5">
                    <div class="font-bold text-amber-300">🟡 V2: Database Lock (SELECT ... FOR UPDATE)</div>
                    <ul class="list-disc pl-4 space-y-1 text-slate-300">
                        <li><strong>Cơ chế:</strong> Khóa chặt bản ghi sự kiện trong Transaction.</li>
                        <li><strong>Kết quả:</strong> ✅ <strong>0% Bán lố</strong> (Bán chính xác 20 vé).</li>
                        <li><strong>Vấn đề mới:</strong> ⚠️ <strong>Lock Contention</strong> làm tăng độ trễ (Latency vọt cao vì các request phải xếp hàng tuần tự chờ DB ghi ổ cứng).</li>
                    </ul>
                </div>
            </div>

            <div class="text-[11px] text-slate-400 pt-2 border-t border-slate-800 flex items-center justify-between">
                <span>🚀 <em>Tại sao cần tiếp tục lên V3?</em> V3 sẽ đưa <strong>Redis Lua Script + AWS SQS</strong> ra tuyến đầu để đạt cả 2 mục tiêu: <strong>0% Bán lố</strong> VÀ <strong>độ trễ siêu tốc &lt; 15ms</strong>!</span>
            </div>
        </div>

    </div>

    <!-- Script Alpine cho Stress Test Lab -->
    <script>
        function stressTestApp() {
            return {
                version: 'v2', // Mặc định mở V2 để người dùng trải nghiệm ngay
                status: {
                    total_tickets: 20,
                    available_tickets: 20,
                    orders_created: 0,
                    is_oversold: false,
                    oversold_count: 0
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
                    return `/api/${this.version}/buy`;
                },

                async init() {
                    await this.fetchStatus();
                },

                setVersion(ver) {
                    this.version = ver;
                    this.addLog('CONFIG', `Đã chuyển sang kiểm thử môi trường: ${ver.toUpperCase()} (Target: /api/${ver}/buy)`, 'info');
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
                    this.loadingText = 'Đang gửi 1 request mua vé...';
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
                            this.addLog('SUCCESS', `[${this.version.toUpperCase()}] User [${userId}] mua thành công (#${data.data.order_id}) trong ${duration}ms, Vé còn lại: ${data.data.remaining_tickets}`, 'success');
                        } else {
                            this.addLog('REJECTED', `[${this.version.toUpperCase()}] User [${userId}] bị từ chối: ${data.message} (${duration}ms)`, 'warning');
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

                    // Tính toán độ trễ
                    const durations = results.map(r => r.duration).sort((a, b) => a - b);
                    const avg = Math.round(durations.reduce((a, b) => a + b, 0) / durations.length);
                    const max = durations[durations.length - 1];
                    const p95 = durations[Math.floor(durations.length * 0.95)];
                    this.lastLatency = { avg, max, p95 };

                    if (this.version === 'v1') {
                        this.addLog('BATCH_DONE', `[V1] ${count} requests: ${successCount} Thành công, ${failCount} Thất bại. (Avg: ${avg}ms, Max: ${max}ms)`, successCount > this.status.total_tickets ? 'error' : 'info');
                    } else {
                        this.addLog('BATCH_DONE', `[V2] ${count} requests: ${successCount} Thành công (Chuẩn: ${this.status.total_tickets}), ${failCount} Hết vé. (Avg: ${avg}ms, Max: ${max}ms, p95: ${p95}ms)`, 'success');
                    }

                    await this.fetchStatus();
                    this.loading = false;
                },

                async resetEvent() {
                    this.loading = true;
                    this.loadingText = 'Đang reset kho vé...';
                    try {
                        const res = await fetch(`/api/${this.version}/events/reset`, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ total_tickets: 20 })
                        });
                        if (res.ok) {
                            this.addLog('RESET', 'Đã xóa toàn bộ đơn hàng và khôi phục kho về 20 vé!', 'info');
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
