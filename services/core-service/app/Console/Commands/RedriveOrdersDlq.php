<?php

namespace App\Console\Commands;

use Aws\Sqs\SqsClient;
use Illuminate\Console\Command;
use Throwable;

class RedriveOrdersDlq extends Command
{
    protected $signature = 'clouddrop:redrive-dlq {--max=50 : Số lượng message tối đa cần redrive}';

    protected $description = 'V4 AWS SAA Fault Tolerance: Đẩy lại các message lỗi từ Dead-Letter Queue (DLQ) về Main Queue để xử lý lại';

    public function handle(): int
    {
        $dlqUrl = env('SQS_ORDERS_DLQ_URL', 'http://127.0.0.1:4566/000000000000/clouddrop-orders-dlq');
        $mainQueueUrl = env('SQS_ORDERS_QUEUE_URL', 'http://127.0.0.1:4566/000000000000/clouddrop-orders-queue');
        $maxMessages = (int) $this->option('max');

        $sqs = new SqsClient([
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'version' => 'latest',
            'endpoint' => env('AWS_ENDPOINT', 'http://127.0.0.1:4566'),
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID', 'test'),
                'secret' => env('AWS_SECRET_ACCESS_KEY', 'test'),
            ],
        ]);

        $this->info("[CloudDrop DLQ Redrive] Đang kiểm tra DLQ: {$dlqUrl}");
        $redriven = 0;

        try {
            while ($redriven < $maxMessages) {
                $result = $sqs->receiveMessage([
                    'QueueUrl' => $dlqUrl,
                    'MaxNumberOfMessages' => 10,
                    'WaitTimeSeconds' => 1,
                ]);

                $messages = $result->get('Messages') ?? [];
                if (empty($messages)) {
                    break;
                }

                foreach ($messages as $message) {
                    $sqs->sendMessage([
                        'QueueUrl' => $mainQueueUrl,
                        'MessageBody' => $message['Body'],
                    ]);

                    $sqs->deleteMessage([
                        'QueueUrl' => $dlqUrl,
                        'ReceiptHandle' => $message['ReceiptHandle'],
                    ]);

                    $redriven++;
                    $this->line(" -> Redriven message #{$redriven} back to main queue.");
                }
            }

            $this->info("[DONE] Đã redrive tổng cộng {$redriven} message từ DLQ về Main Queue.");
            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('[DLQ Redrive Error] ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
