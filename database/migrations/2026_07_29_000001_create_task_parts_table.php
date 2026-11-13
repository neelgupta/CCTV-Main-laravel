<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_parts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tickets')->cascadeOnDelete();
            $table->string('part_name');
            $table->decimal('qty', 12, 2);
            $table->decimal('gst', 12, 2);
            $table->decimal('price', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_parts');
    }
};
