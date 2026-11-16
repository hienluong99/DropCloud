# 🟢 Phiên Bản V3: Node.js Edge + Redis Lua Script + AWS SQS + Laravel Worker

> **Mục tiêu nâng cấp từ V2 lên V3:** Giữ nguyên cam kết **0% Bán lố (Zero Overselling)** của V2, đồng thời xóa bỏ hoàn toàn nút thắt cổ chai **Lock Contention** ở Database để đưa độ trễ (p95) từ **hàng giây** xuống còn **< 15ms**.

---

## 1. Kiến Trúc Luồng Dữ Liệu V3

```text
[Client] ──(POST /api/v3/reserve)──► [ALB Gateway :3000]
                                           │
                                           ▼
                              [Node.js Edge (Fastify :3001)]
                                 │                    │
                   (1) Atomic Lua Script              │ (2) Push SQS
                                 ▼                    ▼
                          [Redis (RAM)]         [AWS SQS Queue]
                                                      │
                                      (3) Long Poll   │
                                                      ▼
[Client] ◄──(Socket.io)── [Node.js Edge] ◄──(Pub/Sub)── [Laravel Worker] ──► [MySQL/SQLite]
```

---

## 2. Tại Sao Phải Dùng Những Công Nghệ Này? (Interview Talking Points)

1. **Tại sao dùng Redis Lua Script thay vì `GET` rồi `DECR` thông thường?**
   - Nếu gọi 2 lệnh `GET` và `DECR` tách rời từ Node.js sang Redis, giữa 2 lệnh vẫn có khoảng hở mạng gây Race Condition.
   - **Lua Script** (`services/edge-service/src/lua/reserve_ticket.lua`) thực thi trên kiến trúc **Single-threaded** của Redis: Toàn bộ logic *"Kiểm tra trùng user (`SISMEMBER`) + Kiểm tra tồn kho (`GET`) + Trừ vé (`DECR`) + Ghi nhận user (`SADD`)"* diễn ra **Nguyên tử (Atomic 100%)** trên RAM trong `< 1ms`.

2. **Tại sao đặt Node.js (Fastify) ở tuyến đầu trước Laravel?**
   - PHP-FPM khởi tạo worker nặng và bị giới hạn số kết nối đồng thời.
   - **Node.js Event Loop** cực kỳ nhẹ cho tác vụ I/O-bound: Hứng hàng nghìn kết nối HTTP đồng thời gọi Redis/SQS và giữ kết nối WebSocket (**Socket.io**) mà tốn rất ít RAM.

3. **Tại sao dùng AWS SQS (Queue-Based Load Leveling)?**
   - Trong AWS SAA, **SQS** giúp **Decouple (tách rời)** tầng tiếp nhận tải cao (Edge) và tầng lưu trữ bền vững (Database).
   - Biến đỉnh tải 5.000 req/giây thành dòng chảy ổn định để Laravel Worker ghi vào Database tuần tự mà không bao giờ làm sập DB.

---

## 3. 3 Khó Khăn Khi Nâng Cấp Lên V3 & Cách Đã Giải Quyết Trong Code

| Khó khăn phát sinh | Nguyên nhân | Cách giải quyết trong CloudDrop V3 |
| :--- | :--- | :--- |
| **1. Đứt đoạn trải nghiệm (Async UX)** | API `/api/v3/reserve` trả về `202 Accepted` ngay khi đẩy vào SQS, lúc này đơn hàng **chưa có trong DB**. | Dùng **Redis Pub/Sub** (`clouddrop:order-events`) + **Socket.io**. Ngay khi Laravel Worker lưu DB xong, nó bắn event để Node.js báo trực tiếp về trình duyệt. |
| **2. SQS gửi trùng message (At-Least-Once Delivery)** | AWS SQS Standard có thể gửi cùng 1 message tới Worker 2 lần $\rightarrow$ Nguy cơ tạo 2 đơn cho 1 vé. | **Idempotency:** Node.js sinh UUID `reservation_token`. Bảng `orders` đánh `UNIQUE INDEX` cho `reservation_token`. Laravel bắt lỗi trùng và bỏ qua an toàn. |
| **3. Mất vé oan khi đẩy SQS lỗi (Phantom Stock Loss)** | Redis đã trừ `-1` vé, nhưng kết nối tới AWS SQS bị đứt giữa chừng. | **Compensating Rollback:** Trong `server.js`, nếu `SendMessageCommand` lỗi, Node.js tự động hoàn lại `INCR stock` và `SREM buyers` trên Redis ngay lập tức. |
