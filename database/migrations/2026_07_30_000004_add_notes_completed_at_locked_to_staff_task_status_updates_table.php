<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_task_status_updates', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_task_status_updates', 'notes')) {
                $table->text('notes')->nullable()->after('comment');
            }

            if (! Schema::hasColumn('staff_task_status_updates', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status_date')->index();
            }

            if (! Schema::hasColumn('staff_task_status_updates', 'locked')) {
                $table->boolean('locked')->default(false)->after('completed_at')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_task_status_updates', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_task_status_updates', 'locked')) {
                $table->dropColumn('locked');
            }

            if (Schema::hasColumn('staff_task_status_updates', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('staff_task_status_updates', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
