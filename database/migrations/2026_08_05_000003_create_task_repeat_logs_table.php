<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_repeat_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('repeated_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->string('repeat_type', 50);
            $table->date('base_due_date')->index();
            $table->date('processed_for_date')->index();
            $table->timestamp('processed_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['source_task_id', 'repeat_type', 'processed_for_date'], 'task_repeat_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_repeat_logs');
    }
};
