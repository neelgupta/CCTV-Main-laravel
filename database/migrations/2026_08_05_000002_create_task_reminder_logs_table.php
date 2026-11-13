<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->string('reminder_type', 50);
            $table->date('sent_for_date')->index();
            $table->timestamp('sent_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['staff_task_id', 'reminder_type', 'sent_for_date'], 'task_reminder_logs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminder_logs');
    }
};
