<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_plans', function (Blueprint $table) {
            $table->decimal('price', 12, 2)->default(0)->after('visit_count');
            $table->text('description')->nullable()->after('price');
        });

        Schema::table('amcs', function (Blueprint $table) {
            $table->decimal('plan_price', 12, 2)->default(0)->after('plan_duration_months');
            $table->text('plan_description')->nullable()->after('plan_price');
        });
    }

    public function down(): void
    {
        Schema::table('amcs', function (Blueprint $table) {
            $table->dropColumn(['plan_price', 'plan_description']);
        });

        Schema::table('amc_plans', function (Blueprint $table) {
            $table->dropColumn(['price', 'description']);
        });
    }
};
