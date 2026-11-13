<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('type', 50); // google_drive, email_smtp
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted JSON
            $table->string('test_status', 20)->nullable(); // success | failed
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_integrations');
    }
};
