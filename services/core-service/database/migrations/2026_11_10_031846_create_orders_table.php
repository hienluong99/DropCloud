<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('user_id');
            $table->string('reservation_token')->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'EXPIRED'])->default('PAID');
            $table->string('version')->default('v1'); // Để theo dõi order sinh ra từ v1, v2 hay v3
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
