<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LegacyOrderController extends Controller
{
    /**
     * V1: Naive / Legacy Implementation (NO LOCKING)
     * Thảm họa khi có 1000 requests cùng lúc:
     * - Nhiều requests cùng đọc thấy available_tickets > 0 tại cùng một thời điểm.
     * - Race Condition xảy ra, dẫn đến Overselling nghiêm trọng (bán lố vé).
     */
    public function buyV1(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|string',
        ]);

        $eventId = $validated['event_id'];
        $userId = $validated['user_id'];

        // 1. Kiểm tra số lượng vé khả dụng (Đọc tự do từ DB, không lock)
        $event = Event::find($eventId);

        if (!$event || $event->available_tickets <= 0) {
            return response()->json([
                'success' => false,
                'version' => 'v1-legacy',
                'message' => 'Hết vé! (Sold out)',
            ], 400);
        }

        // Mô phỏng độ trễ xử lý I/O thực tế (như kiểm tra thanh toán, render vé PDF hoặc tải mạng)
        // Đây chính là cửa sổ thời gian (window) khiến các request khác ồ ạt đọc được số vé cũ!
        usleep(50000); // 50ms delay

        // 2. Trừ vé trong Database
        $event->available_tickets = $event->available_tickets - 1;
        $event->save();

        // 3. Tạo Order
        $order = Order::create([
            'event_id' => $event->id,
            'user_id' => $userId,
            'status' => 'PAID',
            'version' => 'v1',
        ]);

        return response()->json([
            'success' => true,
            'version' => 'v1-legacy',
            'message' => 'Đặt vé thành công!',
            'data' => [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'remaining_tickets' => $event->available_tickets,
            ],
        ], 200);
    }

    /**
     * Lấy trạng thái sự kiện và thống kê đơn hàng
     */
    public function getEventStatus(int $id): JsonResponse
    {
        $event = Event::withCount('orders')->find($id);

        if (!$event) {
            return response()->json(['message' => 'Event not found'], 404);
        }

        return response()->json([
            'event' => $event,
            'total_tickets' => $event->total_tickets,
            'available_tickets' => $event->available_tickets,
            'orders_created' => $event->orders_count,
            'is_oversold' => $event->orders_count > $event->total_tickets,
            'oversold_count' => max(0, $event->orders_count - $event->total_tickets),
        ]);
    }

    /**
     * Reset lại sự kiện để test đi test lại dễ dàng
     */
    public function resetEvent(Request $request): JsonResponse
    {
        $totalTickets = $request->input('total_tickets', 20);

        // Xóa hoặc reset event id = 1
        $event = Event::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Đại Nhạc Hội Flash Sale 2026',
                'total_tickets' => $totalTickets,
                'available_tickets' => $totalTickets,
                'price' => 500000,
                'status' => 'ACTIVE',
            ]
        );

        Order::where('event_id', $event->id)->delete();

        return response()->json([
            'message' => 'Đã reset sự kiện thành công!',
            'event' => $event,
        ]);
    }
}
