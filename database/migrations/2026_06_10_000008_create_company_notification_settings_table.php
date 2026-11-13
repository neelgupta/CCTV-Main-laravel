<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('module', 50); // lead | ticket | project | payment | staff

            $table->boolean('is_enabled')->default(true);   // master toggle for the module
            $table->boolean('whatsapp_enabled')->default(false);
            $table->boolean('email_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);

            $table->timestamps();

            $table->unique(['company_id', 'module']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_notification_settings');
    }
};
