<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_task_status_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_task_id')->constrained('staff_tasks')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->text('comment')->nullable();
            $table->date('status_date')->index();
            $table->date('reminder_date')->nullable()->index();
            $table->timestamps();

            $table->index(['staff_task_id', 'status_date']);
            $table->unique(['staff_task_id', 'reminder_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_task_status_updates');
    }
};
