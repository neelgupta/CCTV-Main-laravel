<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('send_type', 30)->default('scheduled')->after('type');
            $table->string('banner_image')->nullable()->after('generated_banner_id');
            $table->unsignedInteger('retry_count')->default(0)->after('failed_count');
        });

        Schema::table('email_logs', function (Blueprint $table) {
            $table->string('delivery_status', 30)->nullable()->after('status');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->string('whatsapp_status', 30)->nullable()->after('clicked_at');
            $table->unsignedInteger('retry_attempts')->default(0)->after('error_message');
            $table->timestamp('last_retry_at')->nullable()->after('retry_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('email_logs', function (Blueprint $table) {
            $table->dropColumn(['delivery_status', 'opened_at', 'clicked_at', 'whatsapp_status', 'retry_attempts', 'last_retry_at']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['send_type', 'banner_image', 'retry_count']);
        });
    }
};
