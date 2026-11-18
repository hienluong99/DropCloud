<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\Order;
use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class ConsumeExpirationsQueue extends Command
{
    protected $signature = 'clouddrop:consume-expirations {--once : Chỉ chạy 1 vòng poll rồi thoát}';

    protected $description = 'V4 SQS Delay Queue Worker: Thu hồi vé từ các đơn PENDING quá hạn chưa thanh toán và trả lại vào Redis + DB';

    public function handle(): int
    {
        $queueUrl = env('SQS_EXPIRATION_QUEUE_URL', 'http://127.0.0.1:4566/000000000000/clouddrop-expiration-queue');

        $sqs = new SqsClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'endpoint' => env('AWS_ENDPOINT', 'http://127.0.0.1:4566'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        $this->info("[CloudDrop Expiration Worker] Đang lắng nghe Delay Queue: {$queueUrl}");

        do {
            try {
                $result = $sqs->receiveMessage([
                    'QueueUrl' => $queueUrl,
                    'MaxNumberOfMessages' => 10,
                    'WaitTimeSeconds' => 5,
                    'VisibilityTimeout' => 30,
                ]);

                $messages = $result->get('Messages') ?? [];

                foreach ($messages as $message) {
                    $this->handleExpiration($sqs, $queueUrl, $message);
                }
            } catch (Throwable $e) {
                $this->error('[Expiration Poll Error] ' . $e->getMessage());
                sleep(2);
            }
        } while (!$this->option('once'));

        return self::SUCCESS;
    }

    private function handleExpiration(SqsClient $sqs, string $queueUrl, array $message): void
    {
        $body = json_decode($message['Body'] ?? '{}', true);
        $receiptHandle = $message['ReceiptHandle'];
        $token = $body['reservation_token'] ?? null;

        if (!$token) {
            $sqs->deleteMessage(['QueueUrl' => $queueUrl, 'ReceiptHandle' => $receiptHandle]);
            return;
        }

        try {
            $releasedOrder = DB::transaction(function () use ($token) {
                $order = Order::where('reservation_token', $token)->lockForUpdate()->first();

                // Nếu đơn chưa được worker chính tạo hoặc đã PAID/EXPIRED thì bỏ qua an toàn
                if (!$order || $order->status !== 'PENDING') {
                    return null;
                }

                $order->status = 'EXPIRED';
                $order->save();

                // Hoàn trả +1 vé vào DB
                Event::where('id', $order->event_id)->increment('available_tickets');

                return $order;
            });

            if ($releasedOrder) {
                // Hoàn trả +1 vé vào Redis RAM & xóa user khỏi tập buyers để người khác mua được
                $stockKey = "event:{$releasedOrder->event_id}:stock";
                $buyersKey = "event:{$releasedOrder->event_id}:buyers";

                $newStock = Redis::incr($stockKey);
                Redis::srem($buyersKey, $releasedOrder->user_id);

                Redis::publish('clouddrop:order-events', json_encode([
                    'type' => 'ORDER_EXPIRED',
                    'order_id' => $releasedOrder->id,
                    'reservation_token' => $token,
                    'event_id' => $releasedOrder->event_id,
                    'user_id' => $releasedOrder->user_id,
                    'status' => 'EXPIRED',
                    'redis_stock' => (int) $newStock,
                    'timestamp' => now()->toIso8601String(),
                ]));

                $this->warn("[EXPIRED & RELEASED] Order #{$releasedOrder->id} (User: {$releasedOrder->user_id}) -> Hoàn +1 vé về Redis (Stock: {$newStock})");
            }

            $sqs->deleteMessage([
                'QueueUrl' => $queueUrl,
                'ReceiptHandle' => $receiptHandle,
            ]);
        } catch (Throwable $e) {
            $this->error("[Expiration Error] {$e->getMessage()}");
        }
    }
}
