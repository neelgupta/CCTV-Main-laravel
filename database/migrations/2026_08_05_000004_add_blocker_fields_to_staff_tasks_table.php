<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_tasks', 'has_blocker')) {
                $table->boolean('has_blocker')->default(false)->after('task_description');
            }

            if (! Schema::hasColumn('staff_tasks', 'blocker_level')) {
                $table->string('blocker_level')->nullable()->after('has_blocker');
            }

            if (! Schema::hasColumn('staff_tasks', 'blocker_description')) {
                $table->text('blocker_description')->nullable()->after('blocker_level');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_tasks', 'blocker_description')) {
                $table->dropColumn('blocker_description');
            }

            if (Schema::hasColumn('staff_tasks', 'blocker_level')) {
                $table->dropColumn('blocker_level');
            }

            if (Schema::hasColumn('staff_tasks', 'has_blocker')) {
                $table->dropColumn('has_blocker');
            }
        });
    }
};
