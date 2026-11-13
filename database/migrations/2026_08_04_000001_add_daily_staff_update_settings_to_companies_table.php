<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'enable_daily_staff_update')) {
                $table->boolean('enable_daily_staff_update')->default(false)->after('enable_print_settings');
            }

            if (! Schema::hasColumn('companies', 'daily_staff_update_time')) {
                $table->time('daily_staff_update_time')->nullable()->after('enable_daily_staff_update');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'daily_staff_update_time')) {
                $table->dropColumn('daily_staff_update_time');
            }

            if (Schema::hasColumn('companies', 'enable_daily_staff_update')) {
                $table->dropColumn('enable_daily_staff_update');
            }
        });
    }
};
