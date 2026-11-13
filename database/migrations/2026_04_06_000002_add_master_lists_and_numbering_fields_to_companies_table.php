<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'lead_source_master_list')) {
                $table->json('lead_source_master_list')->nullable()->after('registration_date');
            }

            if (!Schema::hasColumn('companies', 'quotation_prefix')) {
                $table->string('quotation_prefix')->nullable()->after('lead_source_master_list');
            }

            if (!Schema::hasColumn('companies', 'quotation_sequence')) {
                $table->unsignedBigInteger('quotation_sequence')->nullable()->after('quotation_prefix');
            }

            if (!Schema::hasColumn('companies', 'challan_prefix')) {
                $table->string('challan_prefix')->nullable()->after('quotation_sequence');
            }

            if (!Schema::hasColumn('companies', 'challan_sequence')) {
                $table->unsignedBigInteger('challan_sequence')->nullable()->after('challan_prefix');
            }

            if (!Schema::hasColumn('companies', 'project_reference_prefix')) {
                $table->string('project_reference_prefix')->nullable()->after('challan_sequence');
            }

            if (!Schema::hasColumn('companies', 'project_reference_year')) {
                $table->unsignedInteger('project_reference_year')->nullable()->after('project_reference_prefix');
            }

            if (!Schema::hasColumn('companies', 'project_reference_sequence')) {
                $table->unsignedBigInteger('project_reference_sequence')->nullable()->after('project_reference_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'project_reference_sequence',
                'project_reference_year',
                'project_reference_prefix',
                'challan_sequence',
                'challan_prefix',
                'quotation_sequence',
                'quotation_prefix',
                'lead_source_master_list',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
