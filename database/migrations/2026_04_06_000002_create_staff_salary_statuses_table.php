<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_salary_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->string('month_key', 7); // format: Y-m
            $table->string('status')->default('Pending'); // Pending | Done
            $table->timestamps();
            $table->unique(['staff_id', 'month_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_salary_statuses');
    }
};
