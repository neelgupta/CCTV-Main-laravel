<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Half Day', 'Leave', 'Holiday'])->default('Absent');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->decimal('overtime_hours', 5, 2)->default(0); // e.g., 1.5 hrs
            $table->timestamps();

            $table->unique(['staff_id','date']); // prevent duplicate entries per staff per day
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance');
    }
};
