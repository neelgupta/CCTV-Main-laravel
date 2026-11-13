<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_connection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('drive_type', 20);            // company | super_admin
            $table->string('action', 20);                // connected | reconnected | disconnected
            $table->string('status', 20);                // active | disconnected

            $table->string('account_email')->nullable();
            $table->string('previous_account_email')->nullable();
            $table->string('current_account_email')->nullable();

            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();

            $table->timestamps();

            $table->index(['drive_type', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_connection_logs');
    }
};
