<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('project_materials', 'qa_done')) {
                $table->boolean('qa_done')->default(false)->after('attachment_path');
            }

            if (!Schema::hasColumn('project_materials', 'dispatched_at')) {
                $table->timestamp('dispatched_at')->nullable()->after('qa_done');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (Schema::hasColumn('project_materials', 'dispatched_at')) {
                $table->dropColumn('dispatched_at');
            }

            if (Schema::hasColumn('project_materials', 'qa_done')) {
                $table->dropColumn('qa_done');
            }
        });
    }
};
