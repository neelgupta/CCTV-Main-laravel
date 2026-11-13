<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('staff_tasks', 'status')) {
                $table->string('status', 30)->default('pending')->after('task_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('staff_tasks', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
