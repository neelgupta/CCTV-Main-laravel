<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('communication_webhook_logs', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->uuid('uuid')->unique();
            $blueprint->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $blueprint->foreignId('integration_account_id')->constrained('integration_accounts')->cascadeOnDelete();
            
            $blueprint->tinyInteger('provider')->index();
            $blueprint->tinyInteger('event_type')->index();
            
            $blueprint->json('payload');
            $blueprint->json('headers')->nullable();
            
            $blueprint->tinyInteger('processed')->default(0)->index(); // Maps to ProcessedStatusEnum (e.g. 0 => Pending, 1 => Processed, 2 => Failed)
            $blueprint->tinyInteger('attempt_count')->default(1);
            $blueprint->text('processing_error')->nullable();
            
            $blueprint->string('request_ip')->nullable();
            $blueprint->string('user_agent')->nullable();
            
            $blueprint->timestamp('processed_at')->nullable();
            $blueprint->timestamp('created_at')->nullable(); // only created_at needed for audit trails
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_webhook_logs');
    }
};
