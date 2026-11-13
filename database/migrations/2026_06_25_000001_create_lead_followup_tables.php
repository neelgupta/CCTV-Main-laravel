<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_followup_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->text('summary');
            $table->string('status', 50)->default('pending');
            $table->date('followup_date')->nullable();
            $table->date('next_followup_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'followup_date']);
            $table->index(['lead_id', 'created_at']);
        });

        Schema::create('lead_followup_schedule', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->date('followup_date');
            $table->string('status', 50)->default('pending');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['lead_id', 'followup_date']);
            $table->index(['followup_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_followup_schedule');
        Schema::dropIfExists('lead_followup_history');
    }
};
