# 🔵 Phiên Bản V4: Hoàn Thiện Kiến Trúc AWS SAA (Self-Healing & Cloud-Native)

> **Mục tiêu của V4:** Bổ sung đầy đủ các mảnh ghép về **Độ tin cậy (Reliability)**, **Chống găm hàng (Anti-Hoarding)**, **Tách tải xuất vé (S3 Offloading)** và **Hạ tầng IaC (Terraform)** theo chuẩn **AWS Certified Solutions Architect - Associate (SAA)**.

---

## 1. Các Tính Năng & Mẫu Thiết Kế AWS SAA Mới Trong V4

### ① Chống găm vé tự động với AWS SQS Delay Queue (`clouddrop-expiration-queue`)
* **Bài toán thực tế:** Khách giữ được vé (`PENDING`), nhưng không thanh toán mà thoát app. Nếu không thu hồi, số vé đó bị "kẹt" vĩnh viễn.
* **Giải pháp V4:**
  1. Ngay khi giữ vé thành công ở [server.js](file:///d:/Project/DropCloud/services/edge-service/src/server.js), Node.js Edge đẩy đồng thời 1 message hẹn giờ (`DelaySeconds = 300s`, có thể truyền `ttl_seconds` khi test) vào `clouddrop-expiration-queue`.
  2. Sau 5 phút, message xuất hiện để Worker [ConsumeExpirationsQueue.php](file:///d:/Project/DropCloud/services/core-service/app/Console/Commands/ConsumeExpirationsQueue.php) xử lý:
     - Nếu đơn đã `PAID` $\rightarrow$ Bỏ qua an toàn.
     - Nếu đơn vẫn `PENDING` $\rightarrow$ Đổi sang `EXPIRED`, cộng `+1` vé lại vào DB, gọi `Redis::incr` + `Redis::srem` trả vé về kho RAM và bắn `ORDER_EXPIRED` qua Redis Pub/Sub $\rightarrow$ Socket.io.

### ② Dead-Letter Queue (DLQ) & Cơ chế tự phục hồi (`clouddrop:redrive-dlq`)
* **Bài toán thực tế:** Nếu MySQL gặp sự cố tạm thời khiến Laravel Worker xử lý lỗi quá 3 lần (`maxReceiveCount: 3`), message sẽ không bị mất.
* **Giải pháp V4:** AWS SQS tự động chuyển message sang `clouddrop-orders-dlq`. Kỹ sư có thể chạy lệnh [RedriveOrdersDlq.php](file:///d:/Project/DropCloud/services/core-service/app/Console/Commands/RedriveOrdersDlq.php) (`php artisan clouddrop:redrive-dlq`) để đẩy toàn bộ đơn lỗi quay lại Main Queue xử lý tiếp khi DB phục hồi.

### ③ Xuất E-Ticket an toàn qua AWS S3 Private Bucket + Presigned URL
* **Bài toán thực tế:** Cho phép tải vé trực tiếp qua server Laravel/EC2 sẽ làm tốn băng thông server và lộ file vé nếu dùng S3 Public.
* **Giải pháp V4:** Trong [V3OrderController.php](file:///d:/Project/DropCloud/services/core-service/app/Http/Controllers/Api/V3OrderController.php):
  1. Khi thanh toán thành công (`POST /api/v3/orders/{token}/pay`), Laravel tạo file E-Ticket (kèm QR Code) và upload lên **AWS S3 Private Bucket** (`clouddrop-tickets`, `ACL => private`).
  2. Trả về **S3 Presigned URL** có chữ ký bảo mật AWS Signature V4 (hiệu lực **15 phút**). Trình duyệt tải vé trực tiếp từ hạ tầng S3 mà không đi qua server ứng dụng.

### ④ Hạ tầng Infrastructure-as-Code (Terraform AWS SAA)
* Toàn bộ kiến trúc được mô hình hóa trong [main.tf](file:///d:/Project/DropCloud/infra/terraform/main.tf) và [variables.tf](file:///d:/Project/DropCloud/infra/terraform/variables.tf):
  * **VPC:** 2 Public Subnets (ALB) + 2 Private Subnets (ECS Fargate, ElastiCache Redis, RDS MySQL Multi-AZ).
  * **S3 Security:** Bật `aws_s3_bucket_public_access_block` + Server-Side Encryption (`AES256`).
  * **SQS:** Main Queue + DLQ RedrivePolicy + Delay Expiration Queue.

---

## 2. Kiểm Thử V4

```powershell
# Khởi chạy toàn bộ hệ thống V4 (bao gồm Expiration Worker)
.\run-v4.ps1

# Chạy kiểm thử E2E: Auto-Expiration (5s) + S3 Presigned URL Download
node test-v4-saa-features.js
```
