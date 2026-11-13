<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->morphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (! Schema::hasColumn('activity_logs', 'activity_type')) {
                    $table->string('activity_type', 80)->nullable()->after('action');
                }

                if (! Schema::hasColumn('activity_logs', 'browser')) {
                    $table->string('browser')->nullable()->after('ip_address');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('activity_logs')) {
            Schema::table('activity_logs', function (Blueprint $table) {
                if (Schema::hasColumn('activity_logs', 'activity_type')) {
                    $table->dropColumn('activity_type');
                }

                if (Schema::hasColumn('activity_logs', 'browser')) {
                    $table->dropColumn('browser');
                }
            });
        }

        Schema::dropIfExists('notifications');
    }
};
