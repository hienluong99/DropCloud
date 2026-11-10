# Hướng Dẫn Cài Đặt & Chạy CloudDrop Trên Local

Tài liệu này hướng dẫn chi tiết cách cài đặt và khởi chạy dự án **CloudDrop** trên môi trường máy cá nhân (Localhost).

---

## 1. Yêu Cầu Môi Trường (Prerequisites)

Trước khi bắt đầu, hãy đảm bảo máy tính của bạn đã cài đặt các công cụ sau:

* **Node.js**: Phiên bản `>= 18.x` ([Tải tại đây](https://nodejs.org/))
* **PHP**: Phiên bản `>= 8.2` ([Tải cho Windows](https://windows.php.net/download/))
  * Các extension PHP cần bật trong file `php.ini`:
    * `extension=pdo_sqlite`
    * `extension=sqlite3`
    * `extension=fileinfo`
    * `extension=openssl`
    * `extension=mbstring`
    * `extension=curl`
* **Composer**: Trình quản lý package cho PHP ([Tải tại đây](https://getcomposer.org/))
* **Git**: Quản lý mã nguồn

---

## 2. Các Bước Cài Đặt Lần Đầu (First-Time Setup)

Mở terminal (PowerShell trên Windows hoặc Bash/Zsh trên Linux/macOS) và thực hiện:

### Bước 2.1: Cấu hình Backend (Laravel Core Service)
```bash
# 1. Di chuyển vào thư mục dịch vụ Core
cd services/core-service

# 2. Tạo file cấu hình môi trường .env (nếu chưa có)
cp .env.example .env

# 3. Cài đặt các thư viện PHP
composer install

# 4. Tạo App Key cho Laravel
php artisan key:generate

# 5. Khởi tạo file database SQLite
# Trên Windows PowerShell:
New-Item -ItemType File database/database.sqlite -Force
# Trên Linux/macOS hoặc Git Bash:
# touch database/database.sqlite

# 6. Chạy Migration và nạp dữ liệu mẫu ban đầu (Sự kiện có 20 vé)
php artisan migrate:fresh --seed

# 7. Quay lại thư mục gốc dự án
cd ../..
```

---

## 3. Khởi Chạy Hệ Thống

Kiến trúc **V1 Legacy** mô phỏng một **cụm 4 PHP-FPM Workers** chạy song song đứng sau một **Node.js Gateway (Reverse Proxy / Load Balancer)**:

### Cách 1: Chạy tự động bằng script (Khuyên dùng trên Windows)
Tại thư mục gốc dự án, mở **PowerShell** và chạy:
```powershell
.\run-v1.ps1
```
Script sẽ tự động khởi động:
- 4 PHP Workers trên các cổng `8001`, `8002`, `8003`, `8004`.
- 1 Node.js Gateway trên cổng `3000`.

### Cách 2: Chạy thủ công từng tiến trình
Mở riêng các cửa sổ Terminal tại thư mục gốc:

* **Terminal 1:** `php -S 127.0.0.1:8001 -t services/core-service/public`
* **Terminal 2:** `php -S 127.0.0.1:8002 -t services/core-service/public`
* **Terminal 3:** `php -S 127.0.0.1:8003 -t services/core-service/public`
* **Terminal 4:** `php -S 127.0.0.1:8004 -t services/core-service/public`
* **Terminal 5:** `node gateway.js`

---

## 4. Truy Cập Ứng Dụng

Sau khi các server đã khởi động, bạn có thể mở trình duyệt:

* 🎟️ **Trang Khách Hàng Săn Vé:** [http://localhost:3000](http://localhost:3000)
  * Trải nghiệm giao diện đặt vé Flash Sale trực quan, đặt mua vé và nhận mã QR Check-in.
* 🔬 **Phòng Thử Nghiệm Tải (Stress Test Lab):** [http://localhost:3000/stress-test](http://localhost:3000/stress-test)
  * Theo dõi trực tiếp kho vé, số đơn đã tạo.
  * Bấm nút **"Bắn 60 requests cùng lúc"** để quan sát hiện tượng bán lố vé (Race Condition).
  * Bấm nút **"Reset kho về 20 vé"** để chạy lại thử nghiệm.

---

## 5. Chạy Kiểm Thử Race Condition Bằng Terminal

Để chạy kịch bản mô phỏng 60 khách hàng bấm mua đồng thời bằng code:
```bash
node test-v1-race-condition.js
```

**Kết quả mong đợi:**
* Kho chỉ có 20 vé ban đầu.
* Kết quả API trả về thành công tới ~45 - 50 đơn.
* Database bị bán lố (Oversold) hơn 200% do thiếu cơ chế khóa (Locking).

---

## 6. Cách Tắt / Dừng Server

Khi muốn dừng các server đang chạy:

* **Trên Windows PowerShell:**
  ```powershell
  .\stop-v1.ps1
  ```
* **Hoặc bằng lệnh tắt cổng:**
  ```powershell
  Stop-Process -Id (Get-NetTCPConnection -LocalPort 3000,8001,8002,8003,8004).OwningProcess -Force
  ```
