<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_stages')) {
            return;
        }

        Schema::create('service_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('name');
            $table->string('color', 7)->default('#4f46e5');
            $table->string('description', 255)->nullable();
            $table->string('mapped_status')->default('open');
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_stages');
    }
};
