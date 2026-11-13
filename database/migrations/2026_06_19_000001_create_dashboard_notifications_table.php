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
        Schema::create('dashboard_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('notification_key');
            $table->string('category');
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('title');
            $table->text('message')->nullable();
            $table->text('url')->nullable();
            $table->date('notification_date')->index();
            $table->string('status')->default('unread')->index();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'notification_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dashboard_notifications');
    }
};
