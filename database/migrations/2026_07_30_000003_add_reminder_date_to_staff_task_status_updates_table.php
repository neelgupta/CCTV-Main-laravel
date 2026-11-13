<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_task_status_updates', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_task_status_updates', 'reminder_date')) {
                $table->date('reminder_date')->nullable()->after('status_date')->index();
            }
        });

        if (Schema::hasTable('staff_task_status_updates')) {
            try {
                Schema::table('staff_task_status_updates', function (Blueprint $table): void {
                    $table->unique(['staff_task_id', 'reminder_date'], 'staff_task_status_updates_task_reminder_unique');
                });
            } catch (\Throwable $e) {
                // Index may already exist on some installs; ignore and keep schema usable.
            }
        }
    }

    public function down(): void
    {
        Schema::table('staff_task_status_updates', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_task_status_updates', 'reminder_date')) {
                $table->dropColumn('reminder_date');
            }
        });
    }
};
