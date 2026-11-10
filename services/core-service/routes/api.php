<?php

use App\Http\Controllers\Api\LegacyOrderController;
use App\Http\Controllers\Api\V2OrderController;
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
