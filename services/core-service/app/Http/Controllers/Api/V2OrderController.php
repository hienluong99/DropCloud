<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class V2OrderController extends Controller
{
    /**
     * V2: Database Pessimistic Locking (SELECT ... FOR UPDATE)
     * 
     * Nguyên lý hoạt động:
     * 1. Mở một Database Transaction.
     * 2. Khóa dòng bản ghi sự kiện bằng `lockForUpdate()` (tương đương `SELECT ... FOR UPDATE`).
     * 3. Bất kỳ request nào khác muốn đọc/ghi cùng dòng này đều bị ép phải XẾP HÀNG CHỜ cho đến khi Transaction hiện tại COMMIT hoặc ROLLBACK.
     * 4. Kiểm tra tồn kho, trừ vé và tạo Order an toàn tuyệt đối.
     * 
     * Kết quả:
     * - ✅ Triệt tiêu 100% Race Condition / Overselling (Số vé bán ra chính xác tuyệt đối = total_tickets).
     * - ⚠️ Nhược điểm phát sinh (Lock Contention & Latency):
     *   + Hàng đợi xếp hàng làm tăng độ trễ (Latency vọt cao, p95 tăng).
     *   + Giữ kết nối DB lâu dẫn tới nguy cơ cạn kiệt Connection Pool và Lock Timeout khi chịu tải lớn.
     */
    public function buyV2(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|string',
        ]);

        $eventId = $validated['event_id'];
        $userId = $validated['user_id'];
        $startTime = microtime(true);

        try {
            // DB::transaction với 10 lần retry tự động khi gặp Lock Timeout
            $result = DB::transaction(function () use ($eventId, $userId) {
                // 1. SELECT ... FOR UPDATE (Khóa dòng sự kiện này)
                $event = Event::where('id', $eventId)->lockForUpdate()->first();

                if (!$event || $event->available_tickets <= 0) {
                    return [
                        'success' => false,
                        'status_code' => 400,
                        'message' => 'Hết vé! (Sold out)',
                    ];
                }

                // Giữ cửa sổ xử lý (20ms I/O delay)
                usleep(20000); // 20ms I/O delay

                // 2. Trừ vé trong Database
                $event->available_tickets = $event->available_tickets - 1;
                $event->save();

                // 3. Tạo Order
                $order = Order::create([
                    'event_id' => $event->id,
                    'user_id' => $userId,
                    'status' => 'PAID',
                    'version' => 'v2',
                ]);

                return [
                    'success' => true,
                    'status_code' => 200,
                    'order' => $order,
                    'remaining_tickets' => $event->available_tickets,
                ];
            }, 10);

            $durationMs = round((microtime(true) - $startTime) * 1000, 2);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'version' => 'v2-pessimistic-lock',
                    'message' => $result['message'],
                    'latency_ms' => $durationMs,
                ], $result['status_code']);
            }

            return response()->json([
                'success' => true,
                'version' => 'v2-pessimistic-lock',
                'message' => 'Đặt vé thành công với DB Pessimistic Lock!',
                'latency_ms' => $durationMs,
                'data' => [
                    'order_id' => $result['order']->id,
                    'user_id' => $result['order']->user_id,
                    'remaining_tickets' => $result['remaining_tickets'],
                ],
            ], 200);

        } catch (Throwable $e) {
            $durationMs = round((microtime(true) - $startTime) * 1000, 2);
            return response()->json([
                'success' => false,
                'version' => 'v2-pessimistic-lock',
                'error' => 'Database Lock / Connection Timeout Error',
                'details' => $e->getMessage(),
                'latency_ms' => $durationMs,
            ], 500);
        }
    }
}
