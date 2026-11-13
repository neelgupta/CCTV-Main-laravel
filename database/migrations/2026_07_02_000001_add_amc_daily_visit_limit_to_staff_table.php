<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff', 'amc_daily_visit_limit')) {
                $table->unsignedSmallInteger('amc_daily_visit_limit')->default(5)->after('license_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            if (Schema::hasColumn('staff', 'amc_daily_visit_limit')) {
                $table->dropColumn('amc_daily_visit_limit');
            }
        });
    }
};
