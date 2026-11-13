<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('project_materials', 'source_type')) {
                $table->string('source_type', 30)->nullable()->after('source_drawing_id');
            }

            if (! Schema::hasColumn('project_materials', 'source_label')) {
                $table->string('source_label')->nullable()->after('source_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            $columns = collect(['source_label', 'source_type'])
                ->filter(fn (string $column) => Schema::hasColumn('project_materials', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
