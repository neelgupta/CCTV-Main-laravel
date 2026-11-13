<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('amc_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('amc_id')->constrained('amcs')->cascadeOnDelete();
            $table->unsignedSmallInteger('visit_no');
            $table->date('visit_date')->nullable();
            $table->string('status', 50)->default('pending');
            $table->string('technician')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('amc_visits');
    }
};
