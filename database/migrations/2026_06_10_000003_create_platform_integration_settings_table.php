<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->unique(); // google, microsoft, ...
            $table->boolean('is_enabled')->default(false);
            $table->string('client_id', 500)->nullable();
            $table->text('client_secret')->nullable(); // encrypted
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_integration_settings');
    }
};
