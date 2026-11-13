<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letterhead_settings', function (Blueprint $table) {
            $table->id();
            $table->string('file_path')->nullable();
            $table->boolean('enabled')->default(false);
            $table->unsignedInteger('top_margin')->default(20);
            $table->unsignedInteger('bottom_margin')->default(20);
            $table->unsignedInteger('left_margin')->default(20);
            $table->unsignedInteger('right_margin')->default(20);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterhead_settings');
    }
};
