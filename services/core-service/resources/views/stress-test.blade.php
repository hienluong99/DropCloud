<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Phòng Thử Nghiệm Tải (Stress Test Lab) | CloudDrop V1</title>
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
        <div class="flex items-center justify-between pb-4 border-b border-slate-800">
            <div class="flex items-center gap-3">
                <a href="/" class="text-xs text-slate-400 hover:text-white flex items-center gap-1 transition">
                    &larr; Về Trang Mua Vé Khách Hàng
                </a>
                <span class="text-slate-600">/</span>
                <span class="text-xs text-purple-400 font-bold uppercase tracking-wider">Engineering Benchmark Center</span>
            </div>
            <div>
                <span class="px-2.5 py-1 rounded-full text-xs font-semibold bg-red-950/80 text-red-400 border border-red-800/80">
                    🔴 Đang chạy môi trường: V1 Legacy (No Locking)
                </span>
            </div>
        </div>

        <!-- Header Info -->
        <div>
            <h1 class="text-2xl md:text-3xl font-extrabold text-white tracking-tight flex items-center gap-3">
                <span>🔬 Phòng Thử Nghiệm Tải & Race Condition</span>
            </h1>
            <p class="text-slate-400 text-xs md:text-sm mt-1 max-w-3xl">
                Công cụ dành riêng cho kỹ sư / phỏng vấn để giả lập hàng chục requests đồng thời từ nhiều người dùng vào cổng API <code>/api/v1/buy</code>, qua đó quan sát trực tiếp hiện tượng <strong>Race Condition</strong> làm vỡ kho và bán lố vé.
            </p>
        </div>

        <!-- 3 Cột Đo Lường Trực Tiếp (Live Metrics) -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            
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
                <div class="text-slate-400 text-xs font-semibold uppercase tracking-wider">Số Đơn Hàng Thực Tế Đã Tạo</div>
                <div class="text-4xl font-black mt-2"
                     :class="status.is_oversold ? 'text-red-400 animate-pulse' : 'text-cyan-400'"
                     x-text="status.orders_created">--</div>
                
                <div class="text-[11px] mt-2 font-bold flex items-center gap-1.5"
                     :class="status.is_oversold ? 'text-red-400' : 'text-slate-500'">
                    <span x-show="!status.is_oversold">Chưa phát hiện bán lố</span>
                    <span x-show="status.is_oversold">❌ BÁN LỐ: <span class="underline" x-text="status.oversold_count"></span> VÉ SO VỚI QUY ĐỊNH!</span>
                </div>
            </div>

        </div>

        <!-- Control Playground -->
        <div class="bg-[#0f1424] border border-slate-800 rounded-xl p-6 space-y-4">
            <div class="flex items-center justify-between">
                <div class="text-sm font-bold text-white flex items-center gap-2">
                    <span>⚡ Bảng Điều Khiển Tải (Stress Test Controller)</span>
                </div>
                <span class="text-xs text-slate-400">Target Endpoint: <code class="text-purple-300">POST /api/v1/buy</code></span>
            </div>

            <div class="flex flex-wrap gap-4 items-center pt-2">
                
                <!-- Bắn 1 request -->
                <button @click="triggerSingleBuy()" :disabled="loading"
                        class="px-4 py-2.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold border border-slate-700 transition">
                    🛒 Bắn 1 Request đơn lẻ
                </button>

                <!-- Bắn 20 requests cùng lúc -->
                <button @click="triggerConcurrentBatch(20)" :disabled="loading"
                        class="px-4 py-2.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition shadow-md">
                    ⚡ Bắn 20 Requests Cùng Lúc
                </button>

                <!-- Bắn 60 requests cùng lúc (Kích hoạt Overselling nặng) -->
                <button @click="triggerConcurrentBatch(60)" :disabled="loading"
                        class="px-5 py-2.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-white text-xs font-extrabold transition shadow-lg shadow-rose-950 flex items-center gap-1.5">
                    <span>🔥 Bắn 60 Requests Cùng Lúc (Kích hoạt Bán Lố)</span>
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
                <span class="font-bold text-slate-300">🖥️ Nhật Ký Phản Hồi Từ Mạng & Server</span>
                <button @click="logs = []" class="text-xs hover:text-white underline">Xóa lịch sử log</button>
            </div>

            <div class="h-64 overflow-y-auto space-y-1.5 pr-2">
                <template x-for="(log, idx) in logs" :key="idx">
                    <div :class="log.type === 'error' ? 'text-red-400' : (log.type === 'success' ? 'text-emerald-400' : (log.type === 'warning' ? 'text-amber-400' : 'text-slate-300'))">
                        <span class="text-slate-600" x-text="log.time"></span>
                        <span class="font-bold" x-text="'[' + log.badge + ']'"></span>
                        <span x-text="log.text"></span>
                    </div>
                </template>
                <div x-show="logs.length === 0" class="text-slate-600 italic">Chưa có lượt test nào. Hãy chọn các nút bấm ở trên để kích hoạt.</div>
            </div>
        </div>

        <!-- Interview Talking Points -->
        <div class="bg-amber-950/20 border border-amber-900/60 rounded-xl p-5 space-y-2 text-amber-200 text-xs">
            <div class="font-bold uppercase tracking-wider text-amber-300 flex items-center gap-1.5">
                <span>💡 Kịch Bản Thuyết Trình Phỏng Vấn (Architecture Talking Points):</span>
            </div>
            <ul class="list-disc pl-5 space-y-1 text-slate-300">
                <li><strong class="text-white">Tại sao Race Condition xảy ra ở V1?</strong> Ở code V1, thao tác đọc số lượng vé (<code>SELECT</code>) và thao tác trừ số vé (<code>UPDATE</code>) là hai câu lệnh tách rời nhau. Khi có nhiều request song song (multi-thread/multi-worker), hàng chục request cùng đọc được giá trị vé cũ trước khi bất kỳ request nào kịp lưu giá trị mới.</li>
                <li><strong class="text-white">Tại sao cần tách trang test và trang người dùng?</strong> Nhằm chứng minh tính ứng dụng thực tế: Khách hàng chỉ quan sát giao diện bán vé mượt mà và nhận vé check-in QR, còn đội ngũ kỹ sư có trang Stress Test Lab đo lường số liệu độc lập.</li>
                <li><strong class="text-white">Chuyển tiếp sang V2:</strong> Ở phiên bản tiếp theo, chúng ta sẽ áp dụng <code>SELECT ... FOR UPDATE</code> (Pessimistic Locking) trong MySQL để xem cách loại bỏ 100% lỗi bán lố, nhưng làm phát sinh bài toán mới: <em>Lock Contention</em> và tăng vọt độ trễ.</li>
            </ul>
        </div>

    </div>

    <!-- Script Alpine cho Stress Test Lab -->
    <script>
        function stressTestApp() {
            return {
                status: {
                    total_tickets: 20,
                    available_tickets: 20,
                    orders_created: 0,
                    is_oversold: false,
                    oversold_count: 0
                },
                loading: false,
                loadingText: '',
                logs: [],

                async init() {
                    await this.fetchStatus();
                },

                addLog(badge, text, type = 'info') {
                    const time = new Date().toLocaleTimeString();
                    this.logs.unshift({ time, badge, text, type });
                    if (this.logs.length > 150) this.logs.pop();
                },

                async fetchStatus() {
                    try {
                        const res = await fetch('/api/v1/events/1/status');
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
                    try {
                        const res = await fetch('/api/v1/buy', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ event_id: 1, user_id: userId })
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            this.addLog('SUCCESS', `User [${userId}] mua thành công! Mã đơn #${data.data.order_id}, Vé còn lại: ${data.data.remaining_tickets}`, 'success');
                        } else {
                            this.addLog('REJECTED', `User [${userId}] bị từ chối: ${data.message}`, 'warning');
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
                    this.loadingText = `Đang đồng thời bắn ${count} requests vào Gateway...`;
                    this.addLog('BATCH_START', `Bắt đầu gửi đồng loạt ${count} requests mua vé...`, 'info');

                    const reqs = [];
                    for (let i = 1; i <= count; i++) {
                        const uid = `sim_user_${i}_` + Math.floor(Math.random() * 900 + 100);
                        reqs.push(
                            fetch('/api/v1/buy', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ event_id: 1, user_id: uid })
                            }).then(async res => {
                                const data = await res.json();
                                return { ok: res.ok, status: res.status, data, uid };
                            }).catch(err => ({ ok: false, error: err.message, uid }))
                        );
                    }

                    const results = await Promise.all(reqs);
                    const successCount = results.filter(r => r.ok && r.data?.success).length;
                    const failCount = results.filter(r => !r.ok).length;

                    this.addLog('BATCH_DONE', `Xong ${count} requests: ${successCount} Thành công (200), ${failCount} Thất bại/Hết vé (400)`, successCount > this.status.total_tickets ? 'error' : 'info');

                    await this.fetchStatus();
                    this.loading = false;
                },

                async resetEvent() {
                    this.loading = true;
                    this.loadingText = 'Đang reset kho vé...';
                    try {
                        const res = await fetch('/api/v1/events/reset', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ total_tickets: 20 })
                        });
                        if (res.ok) {
                            this.addLog('RESET', 'Đã xóa toàn bộ đơn hàng và khôi phục kho về 20 vé!', 'info');
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
