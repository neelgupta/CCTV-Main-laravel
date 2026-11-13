<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'installation_fail_reason')) {
                $table->string('installation_fail_reason', 500)->nullable()->after('installation_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'installation_fail_reason')) {
                $table->dropColumn('installation_fail_reason');
            }
        });
    }
};

