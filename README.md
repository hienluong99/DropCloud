# CloudDrop — High-Concurrency Flash Sale & Ticketing System

> **Hành trình tiến hóa kiến trúc:** Từ hệ thống Monolith cũ bị lỗi bán lố vé và sập tải $\rightarrow$ Tối ưu hóa từng bước lên kiến trúc Cloud-Native Event-Driven chuẩn AWS SAA (Node.js + Redis Lua + AWS SQS + Laravel).

---

## 🚀 Hướng Dẫn Cài Đặt & Chạy Nhanh
Xem hướng dẫn chi tiết từng bước từ cài đặt môi trường đến khởi chạy hệ thống tại: **[SETUP_GUIDE.md](SETUP_GUIDE.md)**.

* **Khởi động nhanh (Windows):** `.\run-v1.ps1`
* **Dừng toàn bộ server:** `.\stop-v1.ps1`
* **Chạy kiểm thử tải (Race Condition):** `node test-v1-race-condition.js`

---

## 🌟 Trải Nghiệm Hệ Thống Trong Thực Tế

Hệ thống được thiết kế tách bạch rõ ràng giữa **Trải nghiệm Người Dùng Thực Tế** và **Phòng Thử Nghiệm Tải (Stress Test Lab)** cho kỹ sư / người phỏng vấn:

### 1. 🎟️ Trang Khách Hàng Thực Tế: [http://localhost:3000](http://localhost:3000)
- **Giao diện đặt vé Flash Sale hiện đại:** Banner sự kiện đại nhạc hội, giá vé Early-Bird, thanh tiến trình tồn kho cập nhật thời gian thực.
- **Thao tác người dùng mượt mà:** Bấm nút **"SĂN VÉ NGAY"**, nhập thông tin khách hàng, xác nhận đặt vé.
- **Quản lý vé & Check-in QR:** Khi mua thành công, vé được lưu và hiển thị trực tiếp trong mục *"Vé & Đơn Hàng Của Bạn"* kèm mã QR check-in tiện lợi.

---

### 2. 🔬 Phòng Thử Nghiệm Tải (Stress Test Lab): [http://localhost:3000/stress-test](http://localhost:3000/stress-test)
- Dành riêng cho buổi phỏng vấn hoặc kiểm thử nội bộ.
- **Giám sát 3 chỉ số theo thời gian thực:**
  - *Hạn Mức Kho Ban Đầu* (20 vé).
  - *Số Vé Còn Lại Trong DB* (available_tickets).
  - *Số Đơn Hàng Thực Tế Đã Tạo* (orders_created).
- **Bộ điều khiển tải cao:**
  - Bắn 1 request đơn lẻ.
  - Bắn 20 requests đồng thời.
  - **Bắn 60 requests cùng lúc:** Kích hoạt ngay hiện tượng **Race Condition** khiến hệ thống bán lố lên tới `40 - 50 vé` (vượt 200% quy định) và ô cảnh báo **BÁN LỐ** nhấp nháy đỏ!
  - Nút **"Reset kho về 20 vé"** để biểu diễn lại bất cứ lúc nào.

---

## 3. Chạy kiểm thử tự động qua Terminal:

### Kiểm thử V1 (Race Condition & Bán lố vé):
```bash
node test-v1-race-condition.js
```

### Kiểm thử V2 (Pessimistic Locking `SELECT ... FOR UPDATE`):
```bash
node test-v2-pessimistic-lock.js
```

### Chạy so sánh đối đầu toàn diện V1 vs V2:
```bash
node test-compare-v1-v2.js
```

---

## 4. Báo Cáo Kỹ Thuật Chi Tiết (Architecture Specifications)
- 🔴 **V1 Monolith (Legacy):** [V1_ARCHITECTURE.md](V1_ARCHITECTURE.md)
- 🟡 **V2 Pessimistic Locking:** [V2_ARCHITECTURE.md](V2_ARCHITECTURE.md)

---

## 5. Các bước tiếp theo (Lộ trình tối ưu):
- **✅ 🔴 V1:** Monolith không khóa $\rightarrow$ Tái hiện lỗi Race Condition & Bán lố 230%.
- **✅ 🟡 V2:** Database Locking (`SELECT ... FOR UPDATE`) $\rightarrow$ Loại bỏ 100% bán lố, ghi nhận điểm nghẽn **Lock Contention & Tăng độ trễ**.
- **⏳ 🟢 V3:** Tách Node.js Edge + Redis Lua Script + AWS SQS (Giải quyết đồng thời 0% bán lố và độ trễ < 15ms).
- **⏳ 🔵 V4:** Hoàn thiện kiến trúc AWS SAA (DLQ, Delay Queue thu hồi vé sau 5 phút, S3 Presigned URL).
