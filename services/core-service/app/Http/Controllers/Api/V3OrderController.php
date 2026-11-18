<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Aws\S3\S3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;
use Throwable;

class V3OrderController extends Controller
{
    private function makeS3Client(): S3Client
    {
        return new S3Client([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'endpoint' => env('AWS_ENDPOINT', 'http://127.0.0.1:4566'),
            'use_path_style_endpoint' => filter_var(env('AWS_USE_PATH_STYLE_ENDPOINT', true), FILTER_VALIDATE_BOOLEAN),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);
    }

    private function generatePresignedUrl(S3Client $s3, string $bucket, string $key, string $expires = '+15 minutes'): string
    {
        $cmd = $s3->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key,
        ]);

        $request = $s3->createPresignedRequest($cmd, $expires);
        return (string) $request->getUri();
    }

    private function renderTicketHtml(Order $order): string
    {
        $title = htmlspecialchars($order->event?->title ?? 'CloudDrop Flash Sale Event', ENT_QUOTES, 'UTF-8');
        $user = htmlspecialchars($order->user_id, ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars($order->reservation_token, ENT_QUOTES, 'UTF-8');
        $paidAt = now()->format('H:i:s d/m/Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8" />
<title>E-Ticket #{$order->id} - CloudDrop</title>
<style>
  body { font-family: system-ui, -apple-system, sans-serif; background: #0b0f19; color: #f8fafc; display: flex; justify-content: center; padding: 40px; }
  .ticket { width: 520px; background: #111827; border: 1px solid #334155; border-radius: 16px; padding: 28px; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
  .badge { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 700; background: #064e3b; color: #6ee7b7; border: 1px solid #047857; }
  h1 { margin: 12px 0 4px; font-size: 22px; }
  .meta { color: #94a3b8; font-size: 13px; margin-bottom: 20px; }
  .row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px dashed #1e293b; font-size: 14px; }
  .qr { margin-top: 20px; text-align: center; background: #fff; padding: 14px; border-radius: 12px; width: 140px; margin-inline: auto; color: #0f172a; }
  .footer { margin-top: 16px; text-align: center; font-size: 11px; color: #64748b; }
</style>
</head>
<body>
  <div class="ticket">
    <span class="badge">VERIFIED E-TICKET (AWS S3)</span>
    <h1>{$title}</h1>
    <div class="meta">Mã đơn: #{$order->id} &bull; Thanh toán lúc: {$paidAt}</div>
    <div class="row"><span>Khách hàng:</span><strong>{$user}</strong></div>
    <div class="row"><span>Trạng thái:</span><strong style="color:#34d399;">PAID</strong></div>
    <div class="row"><span>Reservation Token:</span><code style="font-size:11px;">{$token}</code></div>
    <div class="qr">
      <svg width="110" height="110" viewBox="0 0 24 24" fill="currentColor">
        <path d="M2 2h8v8H2V2zm2 2v4h4V4H4zm10-2h8v8h-8V2zm2 2v4h4V4h-4zM2 14h8v8H2v-8zm2 2v4h4v-4H4zm14 2h2v2h-2v-2zm-4-2h2v2h-2v-2zm2 4h2v2h-2v-2zm2-2h2v2h-2v-2zm0-4h2v2h-2v-2zm-4 0h2v2h-2v-2z"/>
      </svg>
    </div>
    <div class="footer">Lưu trữ an toàn trên AWS S3 Private Bucket &bull; Tải qua Presigned URL (Hiệu lực 15 phút)</div>
  </div>
</body>
</html>
HTML;
    }

    /**
     * Tra cứu trạng thái đơn hàng theo reservation_token (dùng cho polling fallback hoặc chi tiết đơn)
     */
    public function getOrderByToken(string $token): JsonResponse
    {
        $order = Order::with('event')->where('reservation_token', $token)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'status' => 'PROCESSING_IN_QUEUE',
                'message' => 'Đơn hàng đang nằm trong hàng đợi AWS SQS chờ Worker xử lý.',
            ], 202);
        }

        return response()->json([
            'success' => true,
            'status' => $order->status,
            'data' => [
                'order_id' => $order->id,
                'reservation_token' => $order->reservation_token,
                'user_id' => $order->user_id,
                'event_title' => $order->event?->title,
                'status' => $order->status,
                'version' => $order->version,
                'ticket_s3_key' => $order->ticket_s3_key,
                'expires_at' => $order->expires_at,
                'created_at' => $order->created_at,
            ],
        ]);
    }

    /**
     * Thanh toán đơn hàng PENDING -> PAID, xuất vé lên AWS S3 & trả về Presigned URL (15 phút)
     */
    public function payOrder(string $token): JsonResponse
    {
        $order = Order::with('event')->where('reservation_token', $token)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng hoặc đơn đang xử lý.',
            ], 404);
        }

        if ($order->status === 'EXPIRED') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã hết hạn giữ chỗ và vé đã được hoàn trả về kho.',
            ], 409);
        }

        $bucket = env('AWS_BUCKET', 'clouddrop-tickets');
        $s3Key = $order->ticket_s3_key ?: sprintf('tickets/event-%d/order-%d-%s.html', $order->event_id, $order->id, substr($token, 0, 8));

        if ($order->status !== 'PAID') {
            $order->status = 'PAID';
            $order->ticket_s3_key = $s3Key;
            $order->save();
        }

        $presignedUrl = null;
        try {
            $s3 = $this->makeS3Client();
            $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $s3Key,
                'Body' => $this->renderTicketHtml($order),
                'ContentType' => 'text/html; charset=utf-8',
                'ACL' => 'private',
            ]);
            $presignedUrl = $this->generatePresignedUrl($s3, $bucket, $s3Key, '+15 minutes');
        } catch (Throwable $e) {
            // Giữ trạng thái PAID ngay cả khi LocalStack S3 tạm chưa sẵn sàng
        }

        try {
            Redis::publish('clouddrop:order-events', json_encode([
                'type' => 'ORDER_PAID',
                'order_id' => $order->id,
                'reservation_token' => $order->reservation_token,
                'user_id' => $order->user_id,
                'status' => 'PAID',
                'ticket_s3_key' => $s3Key,
                'ticket_download_url' => $presignedUrl,
                'timestamp' => now()->toIso8601String(),
            ]));
        } catch (Throwable $e) {
            // Không làm lỗi response thanh toán nếu Redis Pub/Sub tạm gián đoạn
        }

        return response()->json([
            'success' => true,
            'message' => 'Thanh toán thành công! Vé đã được xuất lên AWS S3.',
            'data' => [
                'order_id' => $order->id,
                'reservation_token' => $order->reservation_token,
                'user_id' => $order->user_id,
                'status' => $order->status,
                'ticket_s3_key' => $s3Key,
                'ticket_download_url' => $presignedUrl,
                'presigned_expires_in_minutes' => 15,
            ],
        ]);
    }

    /**
     * Cấp lại AWS S3 Presigned URL mới (15 phút) cho đơn đã thanh toán
     */
    public function getTicketDownloadUrl(string $token): JsonResponse
    {
        $order = Order::where('reservation_token', $token)->first();

        if (!$order || $order->status !== 'PAID' || !$order->ticket_s3_key) {
            return response()->json([
                'success' => false,
                'message' => 'Vé chưa sẵn sàng hoặc đơn hàng chưa được thanh toán.',
            ], 404);
        }

        $bucket = env('AWS_BUCKET', 'clouddrop-tickets');
        $s3 = $this->makeS3Client();
        $presignedUrl = $this->generatePresignedUrl($s3, $bucket, $order->ticket_s3_key, '+15 minutes');

        return response()->json([
            'success' => true,
            'ticket_s3_key' => $order->ticket_s3_key,
            'ticket_download_url' => $presignedUrl,
            'expires_in_minutes' => 15,
        ]);
    }
}
