<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'site_preparation_status')) {
                $table->string('site_preparation_status')->nullable()->after('completion_percentage');
            }
            if (!Schema::hasColumn('projects', 'fixing_welding_status')) {
                $table->string('fixing_welding_status')->nullable()->after('site_preparation_status');
            }
            if (!Schema::hasColumn('projects', 'testing_commissioning_status')) {
                $table->string('testing_commissioning_status')->nullable()->after('fixing_welding_status');
            }
            if (!Schema::hasColumn('projects', 'final_inspection_status')) {
                $table->string('final_inspection_status')->nullable()->after('testing_commissioning_status');
            }
            if (!Schema::hasColumn('projects', 'client_handover_status')) {
                $table->string('client_handover_status')->nullable()->after('final_inspection_status');
            }
            if (!Schema::hasColumn('projects', 'installation_completed_at')) {
                $table->timestamp('installation_completed_at')->nullable()->after('client_handover_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $columns = [];

            foreach ([
                'site_preparation_status',
                'fixing_welding_status',
                'testing_commissioning_status',
                'final_inspection_status',
                'client_handover_status',
                'installation_completed_at',
            ] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};

