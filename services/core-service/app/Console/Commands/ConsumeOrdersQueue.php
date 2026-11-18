<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ConsumeOrdersQueue extends Command
{
    protected $signature = 'clouddrop:consume-orders {--once : Chỉ chạy 1 vòng poll rồi thoát (dùng cho test)}';

    protected $description = 'Laravel SQS Worker: Tiêu thụ message từ AWS SQS, lưu Order Idempotent và bắn sự kiện qua Redis Pub/Sub';

    public function handle(): int
    {
        $queueUrl = env('SQS_ORDERS_QUEUE_URL', 'http://127.0.0.1:4566/000000000000/clouddrop-orders-queue');

        $sqs = new SqsClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'endpoint' => env('AWS_ENDPOINT', 'http://127.0.0.1:4566'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        $this->info("[CloudDrop SQS Worker] Đang lắng nghe hàng đợi: {$queueUrl}");

        do {
            try {
                $result = $sqs->receiveMessage([
                    'QueueUrl' => $queueUrl,
                    'MaxNumberOfMessages' => 10,
                    'WaitTimeSeconds' => 5, // Long polling giảm chi phí & độ trễ
                    'VisibilityTimeout' => 30,
                ]);

                $messages = $result->get('Messages') ?? [];

                foreach ($messages as $message) {
                    $this->processMessage($sqs, $queueUrl, $message);
                }
            } catch (Throwable $e) {
                $this->error('[SQS Poll Error] ' . $e->getMessage());
                sleep(2);
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }

    private function processMessage(SqsClient $sqs, string $queueUrl, array $message): void
    {
        $body = json_decode($message['Body'] ?? '{}', true);
        $receiptHandle = $message['ReceiptHandle'];

        $token = $body['reservation_token'] ?? null;
        $eventId = $body['event_id'] ?? null;
        $userId = $body['user_id'] ?? null;
        $expiresIn = (int) ($body['expires_in_seconds'] ?? 300);
        $version = $body['version'] ?? 'v3';

        if (!$token || !$eventId || !$userId) {
            $this->warn("[SQS Worker] Message không hợp lệ, bỏ qua & xóa khỏi queue.");
            $sqs->deleteMessage(['QueueUrl' => $queueUrl, 'ReceiptHandle' => $receiptHandle]);
            return;
        }

        try {
            $order = DB::transaction(function () use ($token, $eventId, $userId, $expiresIn, $version) {
                // Idempotency Check: Nếu token đã tồn tại thì không trừ vé lại
                $existing = Order::where('reservation_token', $token)->first();
                if ($existing) {
                    return $existing;
                }

                // Trừ vé trong DB (An toàn vì Redis ở Edge đã chặn overselling từ trước)
                Event::where('id', $eventId)
                    ->where('available_tickets', '>', 0)
                    ->decrement('available_tickets');

                return Order::create([
                    'event_id' => $eventId,
                    'user_id' => $userId,
                    'reservation_token' => $token,
                    'status' => 'PENDING',
                    'version' => $version,
                    'expires_at' => now()->addSeconds($expiresIn),
                ]);
            });

            // Xóa message khỏi SQS sau khi DB commit thành công
            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);

            // Bắn sự kiện Realtime qua Redis Pub/Sub -> Node.js Edge -> Socket.io Client
            $eventPayload = json_encode([
                'type' => 'ORDER_READY',
                'order_id' => $order->id,
                'reservation_token' => $token,
                'event_id' => $eventId,
                'user_id' => $userId,
                'status' => $order->status,
                'timestamp' => now()->toIso8601String(),
            ]);

            Redis::publish('clouddrop:order-events', $eventPayload);

            $this->info("[OK] Order #{$order->id} (User: {$userId}, Token: " . substr($token, 0, 8) . "...) -> DB & Pub/Sub");
        } catch (QueryException $e) {
            // Bắt lỗi Unique Constraint (23000) trong trường hợp 2 worker xử lý song song cùng 1 message trùng
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'UNIQUE')) {
                $this->warn("[Idempotency] Duplicate token {$token} detected & safely ignored.");
                $sqs->deleteMessage(['QueueUrl' => $queueUrl, 'ReceiptHandle' => $receiptHandle]);
                return;
            }
            $this->error("[DB Error] {$e->getMessage()} (Message sẽ được SQS retry hoặc đẩy sang DLQ)");
        } catch (Throwable $e) {
            $this->error("[Worker Error] {$e->getMessage()} (Message sẽ được SQS retry hoặc đẩy sang DLQ)");
        }
    }
}
