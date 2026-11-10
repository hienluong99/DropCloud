<?php

use Illuminate\Support\Facades\Route;

// Trang khách hàng thực tế (Bán vé, Xem sự kiện, Đặt vé, Nhận QR)
Route::get('/', function () {
    return view('welcome');
});

// Trang Stress Test Lab dành riêng cho kỹ sư / phỏng vấn viên test tải
Route::get('/stress-test', function () {
    return view('stress-test');
});
