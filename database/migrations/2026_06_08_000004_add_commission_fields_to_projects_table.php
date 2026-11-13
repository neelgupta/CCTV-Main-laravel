<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'commission_type')) {
                $table->enum('commission_type', ['fixed', 'percentage'])
                    ->nullable()
                    ->after('contractor_id');
            }

            if (! Schema::hasColumn('projects', 'commission_value')) {
                $table->decimal('commission_value', 12, 2)
                    ->nullable()
                    ->after('commission_type');
            }
        });

        $projects = DB::table('projects')
            ->join('contractors', 'projects.contractor_id', '=', 'contractors.id')
            ->whereNull('projects.commission_type')
            ->whereNotNull('projects.contractor_id')
            ->get([
                'projects.id',
                'contractors.commission_type as contractor_commission_type',
                'contractors.commission_value as contractor_commission_value',
            ]);

        foreach ($projects as $project) {
            DB::table('projects')
                ->where('id', $project->id)
                ->update([
                    'commission_type' => $project->contractor_commission_type,
                    'commission_value' => $project->contractor_commission_value,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'commission_value')) {
                $table->dropColumn('commission_value');
            }

            if (Schema::hasColumn('projects', 'commission_type')) {
                $table->dropColumn('commission_type');
            }
        });
    }
};
