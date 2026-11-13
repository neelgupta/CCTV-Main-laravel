<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

            // Delivery channels
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('email_enabled')->default(true);

            // Notification events
            $table->boolean('notify_lead')->default(true);
            $table->boolean('notify_ticket')->default(true);
            $table->boolean('notify_project')->default(true);
            $table->boolean('notify_payment')->default(false);
            $table->boolean('notify_staff')->default(false);

            $table->timestamps();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
    }
};
