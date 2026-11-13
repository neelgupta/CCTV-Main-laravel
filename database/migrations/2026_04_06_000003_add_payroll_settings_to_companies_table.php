<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'working_days')) {
                $table->json('working_days')->nullable()->after('project_reference_sequence');
            }

            if (!Schema::hasColumn('companies', 'pf_enabled')) {
                $table->boolean('pf_enabled')->default(false)->after('working_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'pf_enabled')) {
                $table->dropColumn('pf_enabled');
            }
            if (Schema::hasColumn('companies', 'working_days')) {
                $table->dropColumn('working_days');
            }
        });
    }
};
