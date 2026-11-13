<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('project_challans')) {
            return;
        }

        Schema::table('project_challans', function (Blueprint $table) {
            if (!Schema::hasColumn('project_challans', 'status')) {
                $table->string('status')->default('created')->after('production_ids');
            }
            if (!Schema::hasColumn('project_challans', 'signature_path')) {
                $table->string('signature_path')->nullable()->after('remarks');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('project_challans')) {
            return;
        }

        Schema::table('project_challans', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('project_challans', 'status')) {
                $columns[] = 'status';
            }
            if (Schema::hasColumn('project_challans', 'signature_path')) {
                $columns[] = 'signature_path';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

