<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_connections', function (Blueprint $table) {
            $table->id();
            // company_id null = platform / super admin connection
            $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('owner_type', 20); // company | super_admin

            $table->string('account_email')->nullable();
            $table->string('account_id')->nullable(); // Google account "sub"

            $table->text('access_token')->nullable();  // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->timestamp('token_expires_at')->nullable();

            $table->string('root_folder_id')->nullable();          // we4work-data folder id
            $table->string('root_folder_name')->default('we4work-data');

            $table->string('status', 20)->default('active'); // active | disconnected

            $table->foreignId('connected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_connections');
    }
};
