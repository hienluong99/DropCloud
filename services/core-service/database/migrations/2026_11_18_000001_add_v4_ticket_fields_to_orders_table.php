<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bổ sung trường cho V4 (AWS SAA):
     * - ticket_s3_key: Đường dẫn object vé lưu trên AWS S3 Private Bucket
     * - expires_at: Thời hạn giữ chỗ (mặc định 5 phút trước khi SQS Delay Queue thu hồi)
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('ticket_s3_key')->nullable()->after('version');
            $table->timestamp('expires_at')->nullable()->after('ticket_s3_key');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['ticket_s3_key', 'expires_at']);
        });
    }
};
