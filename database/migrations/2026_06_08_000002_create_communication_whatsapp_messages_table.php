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
        Schema::create('communication_whatsapp_messages', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->uuid('uuid')->unique();
            $blueprint->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $blueprint->foreignId('integration_account_id')->constrained('integration_accounts')->cascadeOnDelete();
            $blueprint->foreignId('whatsapp_contact_id')->constrained('communication_whatsapp_contacts')->cascadeOnDelete();

            $blueprint->string('message_id')->nullable(); // Meta's wamid
            $blueprint->tinyInteger('direction')->index(); // 1: Inbound, 2: Outbound
            $blueprint->string('type', 50)->default('text'); // text, image, document, location, template, etc.
            $blueprint->text('body')->nullable();

            $blueprint->string('media_id')->nullable();
            $blueprint->string('media_url', 2048)->nullable();
            $blueprint->string('status', 50)->default('sent'); // sent, delivered, read, failed

            $blueprint->json('raw_payload')->nullable();
            $blueprint->timestamp('sent_at')->nullable();
            $blueprint->timestamps();

            $blueprint->index('message_id');
            $blueprint->index('sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('communication_whatsapp_messages');
    }
};
