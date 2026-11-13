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
        Schema::table('project_drawings', function (Blueprint $table) {
            if (!Schema::hasColumn('project_drawings', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('drawing_status');
            }
        });

        Schema::table('project_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('project_materials', 'source_drawing_id')) {
                $table->foreignId('source_drawing_id')->nullable()->after('attachment_path')->constrained('project_drawings')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (Schema::hasColumn('project_materials', 'source_drawing_id')) {
                $table->dropConstrainedForeignId('source_drawing_id');
            }
        });

        Schema::table('project_drawings', function (Blueprint $table) {
            if (Schema::hasColumn('project_drawings', 'accepted_at')) {
                $table->dropColumn('accepted_at');
            }
        });
    }
};
