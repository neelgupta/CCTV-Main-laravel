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
        Schema::create('communication_whatsapp_contacts', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->uuid('uuid')->unique();
            $blueprint->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $blueprint->foreignId('integration_account_id')->constrained('integration_accounts')->cascadeOnDelete();

            $blueprint->string('phone_number', 30);
            $blueprint->string('profile_name')->nullable();
            $blueprint->timestamps();

            // Ensure uniqueness of phone_number per company (multi-tenancy isolation)
            $blueprint->unique(['company_id', 'phone_number']);
            $blueprint->index('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_whatsapp_contacts');
    }
};
