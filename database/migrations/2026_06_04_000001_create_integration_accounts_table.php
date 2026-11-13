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
        Schema::create('integration_accounts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->uuid('uuid')->unique();
            $blueprint->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            
            $blueprint->tinyInteger('provider')->index();
            $blueprint->tinyInteger('status')->default(1)->index(); // Maps to ConnectionStatusEnum
            
            $blueprint->string('display_name');
            $blueprint->string('phone_number');
            $blueprint->string('phone_number_id')->index();
            
            $blueprint->string('business_account_id');
            $blueprint->string('app_id')->nullable();
            
            $blueprint->text('access_token');
            $blueprint->text('verify_token');
            $blueprint->text('webhook_secret')->nullable();
            
            $blueprint->timestamp('connected_at')->nullable();
            $blueprint->timestamp('last_verified_at')->nullable();
            
            $blueprint->json('metadata')->nullable();
            $blueprint->timestamps();

            // Ensure uniqueness of phone_number_id per company
            $blueprint->unique(['company_id', 'phone_number_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('integration_accounts');
    }
};
