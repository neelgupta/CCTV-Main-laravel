<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('staff_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('staff')->onDelete('cascade');

            // Type of activity: login, attendance, salary_update, document_upload, etc.
            $table->string('type');

            // Description/details of the activity
            $table->text('description')->nullable();

            // When activity occurred
            $table->timestamp('occurred_at')->useCurrent();

            // Optional: IP address, browser, or device
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_activities');
    }
};
