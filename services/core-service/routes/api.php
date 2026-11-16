<?php

use App\Http\Controllers\Api\LegacyOrderController;
use App\Http\Controllers\Api\V2OrderController;
use App\Http\Controllers\Api\V3OrderController;
use Illuminate\Support\Facades\Route;

// V1 Legacy Routes (Không tối ưu, race condition)
Route::prefix('v1')->group(function () {
    Route::post('/buy', [LegacyOrderController::class, 'buyV1']);
    Route::get('/events/{id}/status', [LegacyOrderController::class, 'getEventStatus']);
    Route::post('/events/reset', [LegacyOrderController::class, 'resetEvent']);
});

// V2 Pessimistic Locking Routes (SELECT ... FOR UPDATE)
Route::prefix('v2')->group(function () {
    Route::post('/buy', [V2OrderController::class, 'buyV2']);
    Route::get('/events/{id}/status', [LegacyOrderController::class, 'getEventStatus']);
    Route::post('/events/reset', [LegacyOrderController::class, 'resetEvent']);
});

// V3 Core Service Routes (SQS Worker hỗ trợ tra cứu & thanh toán đơn theo reservation_token)
// Lưu ý: /api/v3/reserve, /api/v3/events/* được Gateway định tuyến thẳng tới Node.js Edge (3001)
Route::prefix('v3/orders')->group(function () {
    Route::get('/{token}', [V3OrderController::class, 'getOrderByToken']);
    Route::post('/{token}/pay', [V3OrderController::class, 'payOrder']);
});
