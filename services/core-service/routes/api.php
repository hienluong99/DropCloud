<?php

use App\Http\Controllers\Api\LegacyOrderController;
use Illuminate\Support\Facades\Route;

// V1 Legacy Routes (Không tối ưu, race condition)
Route::prefix('v1')->group(function () {
    Route::post('/buy', [LegacyOrderController::class, 'buyV1']);
    Route::get('/events/{id}/status', [LegacyOrderController::class, 'getEventStatus']);
    Route::post('/events/reset', [LegacyOrderController::class, 'resetEvent']);
});
