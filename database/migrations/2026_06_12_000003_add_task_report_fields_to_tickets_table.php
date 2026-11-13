<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'work_summary')) {
                $table->text('work_summary')->nullable()->after('service_notes');
            }

            if (! Schema::hasColumn('tickets', 'complate_task_list')) {
                $table->json('complate_task_list')->nullable()->after('work_summary');
            }

            if (! Schema::hasColumn('tickets', 'customer_signature')) {
                $table->string('customer_signature')->nullable()->after('complate_task_list');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'customer_signature')) {
                $table->dropColumn('customer_signature');
            }

            if (Schema::hasColumn('tickets', 'complate_task_list')) {
                $table->dropColumn('complate_task_list');
            }

            if (Schema::hasColumn('tickets', 'work_summary')) {
                $table->dropColumn('work_summary');
            }
        });
    }
};
