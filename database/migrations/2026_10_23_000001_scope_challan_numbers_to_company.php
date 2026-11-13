<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_challans', function (Blueprint $table) {
            if (!Schema::hasColumn('project_challans', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('project_id')->constrained('companies')->cascadeOnDelete();
            }
        });

        DB::statement('UPDATE project_challans SET company_id = (SELECT projects.company_id FROM projects WHERE projects.id = project_challans.project_id) WHERE company_id IS NULL');

        Schema::table('project_challans', function (Blueprint $table) {
            $table->dropUnique(['challan_no']);
            $table->unique(['company_id', 'challan_no']);
        });

        // Companies that never set a sequence continue after their own highest challan instead of restarting at 1.
        DB::table('companies')
            ->whereNull('challan_sequence')
            ->orderBy('id')
            ->pluck('id')
            ->each(function (int $companyId) {
                $highest = DB::table('project_challans')
                    ->where('company_id', $companyId)
                    ->pluck('challan_no')
                    ->map(fn (string $challanNo) => preg_match('/(\d+)$/', $challanNo, $matches) ? (int) $matches[1] : 0)
                    ->max();

                if ($highest) {
                    DB::table('companies')->where('id', $companyId)->update(['challan_sequence' => $highest + 1]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_challans', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'challan_no']);
            $table->unique('challan_no');
        });

        Schema::table('project_challans', function (Blueprint $table) {
            if (Schema::hasColumn('project_challans', 'company_id')) {
                $table->dropConstrainedForeignId('company_id');
            }
        });
    }
};
