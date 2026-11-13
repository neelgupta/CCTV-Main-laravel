<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('staff_route_points')) {
            return;
        }

        Schema::create('staff_route_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->timestamp('recorded_at')->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('accuracy_meters', 8, 2)->nullable();
            $table->decimal('speed_kph', 8, 2)->nullable();
            $table->decimal('heading_degrees', 8, 2)->nullable();
            $table->string('source', 100)->nullable();
            $table->string('device_name', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'staff_id', 'recorded_at'], 'staff_route_points_company_staff_recorded_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_route_points');
    }
};
