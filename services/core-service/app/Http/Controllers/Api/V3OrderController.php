<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Redis;

class V3OrderController extends Controller
{
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
                'created_at' => $order->created_at,
            ],
        ]);
    }

    /**
     * Thanh toán đơn hàng PENDING -> PAID
     */
    public function payOrder(string $token): JsonResponse
    {
        $order = Order::where('reservation_token', $token)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy đơn hàng hoặc đơn đang xử lý.',
            ], 404);
        }

        if ($order->status === 'PAID') {
            return response()->json([
                'success' => true,
                'message' => 'Đơn hàng đã được thanh toán trước đó.',
                'data' => $order,
            ]);
        }

        if ($order->status === 'EXPIRED') {
            return response()->json([
                'success' => false,
                'message' => 'Đơn hàng đã hết hạn giữ chỗ.',
            ], 409);
        }

        $order->status = 'PAID';
        $order->save();

        try {
            Redis::publish('clouddrop:order-events', json_encode([
                'type' => 'ORDER_PAID',
                'order_id' => $order->id,
                'reservation_token' => $order->reservation_token,
                'user_id' => $order->user_id,
                'status' => 'PAID',
                'timestamp' => now()->toIso8601String(),
            ]));
        } catch (\Throwable $e) {
            // Không làm lỗi response thanh toán nếu Redis Pub/Sub tạm gián đoạn
        }

        return response()->json([
            'success' => true,
            'message' => 'Thanh toán thành công! Vé đã được xác nhận.',
            'data' => [
                'order_id' => $order->id,
                'reservation_token' => $order->reservation_token,
                'user_id' => $order->user_id,
                'status' => $order->status,
            ],
        ]);
    }
}
