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
            if (!Schema::hasColumn('project_challans', 'name_of_person')) {
                $table->string('name_of_person')->nullable()->after('status');
            }
            if (!Schema::hasColumn('project_challans', 'needed_for')) {
                $table->string('needed_for')->nullable()->after('name_of_person');
            }
            if (!Schema::hasColumn('project_challans', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('needed_for');
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
            if (Schema::hasColumn('project_challans', 'name_of_person')) {
                $columns[] = 'name_of_person';
            }
            if (Schema::hasColumn('project_challans', 'needed_for')) {
                $columns[] = 'needed_for';
            }
            if (Schema::hasColumn('project_challans', 'delivery_address')) {
                $columns[] = 'delivery_address';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

