<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_staff_update_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('daily_staff_update_reports', 'emailed_at')) {
                $table->timestamp('emailed_at')->nullable()->after('generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('daily_staff_update_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('daily_staff_update_reports', 'emailed_at')) {
                $table->dropColumn('emailed_at');
            }
        });
    }
};
