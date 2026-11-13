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
        Schema::create('google_drive_backup_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('backup_name');
            $table->string('google_file_id')->nullable()->index();
            $table->text('google_file_url')->nullable();
            $table->string('folder_id', 191);
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('status', 30)->index();
            $table->dateTime('start_time')->nullable()->index();
            $table->dateTime('end_time')->nullable()->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'start_time']);
            $table->index(['folder_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_drive_backup_logs');
    }
};
