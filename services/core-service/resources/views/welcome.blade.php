<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Săn Vé Đại Nhạc Hội Flash Sale 2026 | CloudDrop</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; }
        [x-cloak] { display: none !important; }
        .gradient-glow {
            background: radial-gradient(circle at 50% -20%, rgba(124, 58, 237, 0.25), transparent 70%);
        }
    </style>
</head>
<body class="bg-[#0b0f19] text-slate-100 min-h-screen flex flex-col selection:bg-purple-500 selection:text-white" x-data="userEventApp()" x-init="init()">
    
    <!-- Top Navigation -->
    <header class="border-b border-slate-800/80 bg-[#0e1322]/80 backdrop-blur-md sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-purple-600 to-indigo-500 flex items-center justify-center shadow-lg shadow-purple-500/30">
                    <span class="text-xl">⚡</span>
                </div>
                <div>
                    <span class="font-extrabold text-lg tracking-tight bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">CloudDrop</span>
                    <span class="text-[10px] text-purple-400 font-semibold px-2 py-0.5 rounded-full bg-purple-950/60 border border-purple-800/60 ml-1.5 uppercase">Flash Ticket</span>
                </div>
            </div>

            <!-- Header Action & Link to Stress Test Lab -->
            <div class="flex items-center gap-4">
                <a href="/stress-test" target="_blank" class="flex items-center gap-1.5 text-xs font-semibold px-3.5 py-2 rounded-lg bg-slate-800/90 hover:bg-slate-700 text-slate-300 hover:text-white border border-slate-700 transition shadow-sm">
                    <span>🔬 Mở Phòng Thử Nghiệm Tải (Stress Test Lab)</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 gradient-glow py-8 px-4">
        <div class="max-w-5xl mx-auto space-y-8">
            
            <!-- Hero Event Banner -->
            <div class="relative rounded-2xl overflow-hidden border border-slate-800 bg-slate-900/60 shadow-2xl">
                <div class="h-44 md:h-56 bg-gradient-to-r from-purple-900/40 via-indigo-900/30 to-slate-900 relative p-6 md:p-8 flex flex-col justify-end">
                    <div class="absolute top-4 right-4 flex items-center gap-2">
                        <span class="animate-ping absolute inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                        <span class="text-xs font-bold text-emerald-400 tracking-wide uppercase">Đang mở bán Flash Sale</span>
                    </div>

                    <div class="max-w-2xl space-y-2">
                        <span class="text-xs font-bold px-2.5 py-1 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30">CONCERT 2026</span>
                        <h1 class="text-2xl md:text-4xl font-extrabold text-white tracking-tight" x-text="event.title">Đại Nhạc Hội Flash Sale 2026</h1>
                        <p class="text-slate-300 text-xs md:text-sm flex items-center gap-4 pt-1">
                            <span>📍 SVĐ Quốc Gia Mỹ Đình, Hà Nội</span>
                            <span>📅 20:00 - Thứ Bảy, 15/12/2026</span>
                        </p>
                    </div>
                </div>

                <!-- Event Details & Ticket Buying Strip -->
                <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-6 bg-[#0f1424]/90 items-center">
                    
                    <!-- Giá vé -->
                    <div>
                        <div class="text-xs text-slate-400 font-medium">Giá vé Early-Bird</div>
                        <div class="text-2xl md:text-3xl font-extrabold text-purple-400 mt-1">
                            500.000 <span class="text-sm font-normal text-slate-400">VND</span>
                        </div>
                        <div class="text-[11px] text-slate-500 mt-0.5">Giảm 50% so với giá vé tại quầy</div>
                    </div>

                    <!-- Tồn kho & Thanh tiến độ -->
                    <div class="space-y-2">
                        <div class="flex justify-between text-xs">
                            <span class="text-slate-400">Số vé còn lại:</span>
                            <span class="font-bold" :class="event.available_tickets > 0 ? 'text-emerald-400' : 'text-red-400'">
                                <span x-text="event.available_tickets">--</span> / <span x-text="event.total_tickets">--</span> vé
                            </span>
                        </div>
                        <div class="w-full h-2.5 bg-slate-800 rounded-full overflow-hidden">
                            <div class="h-full bg-gradient-to-r from-purple-500 to-emerald-400 transition-all duration-500"
                                 :style="`width: ${Math.max(0, Math.min(100, (event.available_tickets / (event.total_tickets || 1)) * 100))}%`"></div>
                        </div>
                        <div class="text-[11px] text-amber-400/90 font-medium" x-show="event.available_tickets <= 5 && event.available_tickets > 0">
                            🔥 Chỉ còn vài vé cuối cùng!
                        </div>
                        <div class="text-[11px] text-red-400 font-medium" x-show="event.available_tickets <= 0">
                            ⛔ Vé đã được bán hết!
                        </div>
                    </div>

                    <!-- Nút Mua Vé -->
                    <div class="flex flex-col gap-2">
                        <button @click="openCheckoutModal()" 
                                :disabled="event.available_tickets <= 0 || loading"
                                class="w-full py-3.5 px-6 rounded-xl font-bold text-sm text-white bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-500 hover:to-indigo-500 disabled:from-slate-800 disabled:to-slate-800 disabled:text-slate-500 disabled:cursor-not-allowed shadow-lg shadow-purple-600/30 transition transform active:scale-[0.98] flex items-center justify-center gap-2">
                            <span x-show="!loading">⚡ SĂN VÉ NGAY</span>
                            <span x-show="loading" class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg>
                                Đang xử lý đơn...
                            </span>
                        </button>
                        <span class="text-[11px] text-center text-slate-500">Mỗi khách hàng đặt 1 vé / lượt</span>
                    </div>

                </div>
            </div>

            <!-- Tab: Danh Sách Vé Của Bạn Đã Đặt -->
            <div class="rounded-2xl border border-slate-800 bg-[#0f1424]/70 p-6 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-2">
                        <span class="text-base font-bold text-white">🎟️ Vé & Đơn Hàng Của Bạn</span>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 font-medium" x-text="myTickets.length + ' vé'"></span>
                    </div>
                    <button @click="fetchStatus()" class="text-xs text-purple-400 hover:text-purple-300 font-medium flex items-center gap-1">
                        🔄 Làm mới
                    </button>
                </div>

                <div x-show="myTickets.length === 0" class="py-12 text-center text-slate-500 text-sm">
                    Bạn chưa đặt vé nào. Hãy bấm <strong>"SĂN VÉ NGAY"</strong> ở phía trên để trải nghiệm!
                </div>

                <!-- Danh sách thẻ vé của User -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4" x-show="myTickets.length > 0">
                    <template x-for="t in myTickets" :key="t.order_id">
                        <div class="rounded-xl border border-slate-700/80 bg-slate-800/40 p-4 flex justify-between items-center relative overflow-hidden">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-purple-400 uppercase tracking-wider" x-text="'MÃ ĐƠN #' + t.order_id"></span>
                                    <span class="text-[10px] px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-800">ĐÃ THANH TOÁN</span>
                                </div>
                                <div class="font-bold text-white text-sm" x-text="event.title"></div>
                                <div class="text-xs text-slate-400">Khách hàng: <span class="font-mono text-slate-300" x-text="t.user_id"></span></div>
                                <div class="text-[11px] text-slate-500">Thời gian đặt: <span x-text="t.time"></span></div>
                            </div>
                            <div class="text-center pl-4 border-l border-slate-700/60">
                                <div class="w-14 h-14 bg-white rounded-lg p-1 mx-auto flex items-center justify-center">
                                    <!-- QR Demo -->
                                    <svg class="w-full h-full text-slate-900" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm10-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14 2h2v2h-2v-2zm-4-2h2v2h-2v-2zm2 4h2v2h-2v-2zm2-2h2v2h-2v-2zm0-4h2v2h-2v-2zm-4 0h2v2h-2v-2z"/>
                                    </svg>
                                </div>
                                <span class="text-[10px] text-slate-400 block mt-1">Check-in QR</span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Footer Storytelling Banner -->
            <div class="rounded-xl border border-purple-900/40 bg-purple-950/20 p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="text-xs font-bold text-purple-300 uppercase tracking-wide">💡 Góc Đánh Giá Kỹ Thuật (Architecture Review):</div>
                    <p class="text-xs text-slate-300 max-w-2xl">
                        Trang này đại diện cho trải nghiệm người dùng thực tế. Ở hậu trường, phiên bản hiện tại là <strong>V1 (Legacy)</strong> đang đọc/ghi trực tiếp vào DB mà không có cơ chế khóa. 
                        Muốn thấy hệ thống sụp đổ và bán lố vé thế nào khi có 50 người cùng bấm nút "SĂN VÉ"?
                    </p>
                </div>
                <a href="/stress-test" target="_blank" class="shrink-0 px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs transition shadow-md">
                    Xem Thử Nghiệm Bán Lố &rarr;
                </a>
            </div>

        </div>
    </main>

    <!-- Modal Mua Vé Người Dùng Thực Tế -->
    <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-cloak>
        <div class="bg-[#121829] border border-slate-700 rounded-2xl max-w-md w-full p-6 space-y-5 shadow-2xl relative" @click.away="showModal = false">
            <div class="flex justify-between items-center pb-3 border-b border-slate-800">
                <h3 class="text-base font-bold text-white">Xác nhận đặt vé Flash Sale</h3>
                <button @click="showModal = false" class="text-slate-400 hover:text-white">&times;</button>
            </div>

            <div class="space-y-3 text-xs">
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Tên khách hàng / Mã định danh</label>
                    <input type="text" x-model="customerName" class="w-full px-3 py-2.5 bg-slate-800/80 border border-slate-700 rounded-lg text-white font-medium focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-slate-400 font-medium mb-1">Email nhận vé</label>
                    <input type="email" x-model="customerEmail" class="w-full px-3 py-2.5 bg-slate-800/80 border border-slate-700 rounded-lg text-white font-medium focus:outline-none focus:border-purple-500">
                </div>
                <div class="p-3 rounded-lg bg-slate-800/40 border border-slate-700/60 space-y-1.5">
                    <div class="flex justify-between text-slate-400">
                        <span>Sự kiện:</span>
                        <span class="text-slate-200 font-medium" x-text="event.title"></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Số lượng:</span>
                        <span class="text-slate-200 font-medium">1 vé</span>
                    </div>
                    <div class="flex justify-between text-slate-400 pt-1 border-t border-slate-700/50">
                        <span>Tổng tiền:</span>
                        <span class="text-purple-400 font-bold text-sm">500.000 VND</span>
                    </div>
                </div>
            </div>

            <div class="flex gap-3 pt-2">
                <button @click="showModal = false" class="flex-1 py-2.5 px-4 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 font-medium text-xs">
                    Hủy bỏ
                </button>
                <button @click="confirmBuy()" class="flex-1 py-2.5 px-4 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-lg shadow-purple-600/30">
                    Xác nhận mua
                </button>
            </div>
        </div>
    </div>

    <!-- Script điều khiển Alpine.js -->
    <script>
        function userEventApp() {
            return {
                event: {
                    id: 1,
                    title: 'Đại Nhạc Hội Flash Sale 2026',
                    total_tickets: 20,
                    available_tickets: 20
                },
                loading: false,
                showModal: false,
                customerName: 'Nguyen Van A',
                customerEmail: 'user' + Math.floor(Math.random() * 900 + 100) + '@gmail.com',
                myTickets: JSON.parse(localStorage.getItem('clouddrop_my_tickets') || '[]'),

                async init() {
                    await this.fetchStatus();
                    // Polling nhẹ mỗi 3 giây để cập nhật vé theo thời gian thực
                    setInterval(() => {
                        this.fetchStatus();
                    }, 3000);
                },

                async fetchStatus() {
                    try {
                        const res = await fetch('/api/v1/events/1/status');
                        if (res.ok) {
                            const data = await res.json();
                            this.event = data.event;
                            this.event.total_tickets = data.total_tickets;
                            this.event.available_tickets = data.available_tickets;
                        }
                    } catch (e) {
                        console.error("Lỗi fetch status:", e);
                    }
                },

                openCheckoutModal() {
                    this.customerName = 'Khách Hàng #' + Math.floor(Math.random() * 9000 + 1000);
                    this.showModal = true;
                },

                async confirmBuy() {
                    this.showModal = false;
                    this.loading = true;
                    try {
                        const res = await fetch('/api/v1/buy', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                event_id: 1,
                                user_id: this.customerName
                            })
                        });

                        const data = await res.json();
                        if (res.ok && data.success) {
                            // Lưu vào danh sách vé của máy này
                            const ticket = {
                                order_id: data.data.order_id,
                                user_id: data.data.user_id,
                                time: new Date().toLocaleTimeString() + ' ' + new Date().toLocaleDateString()
                            };
                            this.myTickets.unshift(ticket);
                            localStorage.setItem('clouddrop_my_tickets', JSON.stringify(this.myTickets));
                            alert('🎉 CHÚC MỪNG: Bạn đã đặt vé thành công! Mã đơn: #' + data.data.order_id);
                        } else {
                            alert('❌ ĐẶT VÉ THẤT BẠI: ' + (data.message || 'Hết vé!'));
                        }
                    } catch (err) {
                        alert('Lỗi kết nối tới server: ' + err.message);
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
