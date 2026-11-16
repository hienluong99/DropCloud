<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thêm UNIQUE INDEX cho reservation_token để đảm bảo Idempotency.
     * Khi AWS SQS gửi trùng message (At-Least-Once Delivery), DB sẽ từ chối tạo đơn thứ 2.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unique('reservation_token', 'orders_reservation_token_unique');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_reservation_token_unique');
        });
    }
};
