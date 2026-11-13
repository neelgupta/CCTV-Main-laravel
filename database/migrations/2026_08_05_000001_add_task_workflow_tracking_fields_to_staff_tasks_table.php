<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_tasks', 'last_reminder_sent_at')) {
                $table->timestamp('last_reminder_sent_at')->nullable()->after('reminder_type');
            }

            if (! Schema::hasColumn('staff_tasks', 'last_repeat_processed_at')) {
                $table->timestamp('last_repeat_processed_at')->nullable()->after('last_reminder_sent_at');
            }

            if (! Schema::hasColumn('staff_tasks', 'repeat_anchor_due_date')) {
                $table->date('repeat_anchor_due_date')->nullable()->after('last_repeat_processed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_tasks', 'repeat_anchor_due_date')) {
                $table->dropColumn('repeat_anchor_due_date');
            }

            if (Schema::hasColumn('staff_tasks', 'last_repeat_processed_at')) {
                $table->dropColumn('last_repeat_processed_at');
            }

            if (Schema::hasColumn('staff_tasks', 'last_reminder_sent_at')) {
                $table->dropColumn('last_reminder_sent_at');
            }
        });
    }
};
