<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('staff_advances', 'company_id')) {
            Schema::table('staff_advances', function (Blueprint $table) {
                $table->foreignId('company_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('companies')
                    ->nullOnDelete();
            });
        }

        DB::table('staff_advances')
            ->whereNull('company_id')
            ->orderBy('id')
            ->chunkById(200, function ($advances): void {
                foreach ($advances as $advance) {
                    $companyId = DB::table('staff')
                        ->where('id', $advance->staff_id)
                        ->value('company_id');

                    if ($companyId !== null) {
                        DB::table('staff_advances')
                            ->where('id', $advance->id)
                            ->update(['company_id' => $companyId]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('staff_advances', 'company_id')) {
            Schema::table('staff_advances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('company_id');
            });
        }
    }
};
