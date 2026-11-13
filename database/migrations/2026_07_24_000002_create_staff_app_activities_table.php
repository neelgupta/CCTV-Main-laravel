<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_app_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->text('device_id')->nullable();
            $table->string('device_id_hash', 64);
            $table->string('platform', 100)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('activity_type', 50)->default('api_request');
            $table->timestamp('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'staff_id', 'device_id_hash'], 'staff_app_activities_device_unique');
            $table->index('company_id');
            $table->index('staff_id');
            $table->index('last_active_at');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_app_activities');
    }
};
