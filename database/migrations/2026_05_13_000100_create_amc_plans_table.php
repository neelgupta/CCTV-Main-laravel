<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amc_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedSmallInteger('visit_count');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });

        Schema::table('amcs', function (Blueprint $table) {
            $table->foreignId('amc_plan_id')->nullable()->after('client_id')->constrained('amc_plans')->nullOnDelete();
            $table->string('plan_name')->nullable()->after('amc_type');
            $table->unsignedSmallInteger('plan_duration_months')->nullable()->after('plan_name');
        });
    }

    public function down(): void
    {
        Schema::table('amcs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('amc_plan_id');
            $table->dropColumn(['plan_name', 'plan_duration_months']);
        });

        Schema::dropIfExists('amc_plans');
    }
};
