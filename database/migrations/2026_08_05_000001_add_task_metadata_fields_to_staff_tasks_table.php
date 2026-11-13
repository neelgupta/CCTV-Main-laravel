<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            $table->boolean('has_reminder')->default(false)->after('due_date');
            $table->date('reminder_date')->nullable()->after('has_reminder');
            $table->string('reminder_type', 20)->nullable()->after('reminder_date');
            $table->unsignedTinyInteger('reminder_days_before')->nullable()->after('reminder_type');
            $table->boolean('is_repeated')->default(false)->after('reminder_date');
            $table->string('repeat_type', 20)->nullable()->after('is_repeated');
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            $table->dropColumn(['has_reminder', 'reminder_date', 'reminder_type', 'reminder_days_before', 'is_repeated', 'repeat_type']);
        });
    }
};
