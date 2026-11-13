<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_repeat_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('reopened_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('previous_assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status', 50)->nullable();
            $table->string('reopened_status', 50)->default('open');
            $table->unsignedInteger('repeat_count')->default(1);
            $table->unsignedInteger('repeated_after_days')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('repeated_at')->nullable()->index();
            $table->timestamps();

            $table->index(['ticket_id', 'repeated_at']);
            $table->index(['assigned_to', 'repeated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_repeat_histories');
    }
};
