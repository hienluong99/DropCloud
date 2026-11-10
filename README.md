# CloudDrop — High-Concurrency Flash Sale & Ticketing System

> **Hành trình tiến hóa kiến trúc:** Từ hệ thống Monolith cũ bị lỗi bán lố vé và sập tải $\rightarrow$ Tối ưu hóa từng bước lên kiến trúc Cloud-Native Event-Driven chuẩn AWS SAA (Node.js + Redis Lua + AWS SQS + Laravel).

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
```bash
node test-v1-race-condition.js
```
Kết quả kiểm thử thực tế:
```text
=== BẮT ĐẦU TEST RACE CONDITION (V1 LEGACY QUA GATEWAY) ===
Sự kiện có: 20 vé
Bắn đồng thời: 60 requests mua vé...

Kết quả trả về từ API:
- Thành công (200 OK): 46
- Thất bại / Hết vé (400): 14

=============================================
--- THỰC TRẠNG TRONG DATABASE SAU ĐỢT TẢI ---
- Tổng vé ban đầu: 20
- Số đơn hàng thực tế đã tạo: 46
- Số vé còn lại: 0
- Phát hiện Bán Lố (Oversold): ❌ CÓ - BÁN LỐ 26 VÉ! (230%)
=============================================
```

---

## 4. Các bước tiếp theo (Lộ trình tối ưu):
- **🟡 V2:** Thêm Database Locking (`SELECT ... FOR UPDATE`) để chặn đứng 100% việc bán lố $\rightarrow$ Đo lường điểm nghẽn mới: **Lock Contention & Tăng độ trễ**.
- **🟢 V3:** Tách Node.js Edge + Redis Lua Script + AWS SQS.
- **🔵 V4:** Hoàn thiện kiến trúc AWS SAA (DLQ, Delay Queue thu hồi vé, S3 Presigned URL).
