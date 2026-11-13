<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'project_custom_fields')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('project_custom_fields')->nullable()->after('lead_custom_fields');
            });
        }

        if (! Schema::hasColumn('projects', 'custom_fields')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'custom_fields')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasColumn('companies', 'project_custom_fields')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('project_custom_fields');
            });
        }
    }
};
