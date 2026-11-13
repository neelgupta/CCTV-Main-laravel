<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'allow_growth_staff_tracking')) {
                $table->boolean('allow_growth_staff_tracking')
                    ->default(false)
                    ->after('storage_addon_mb');
            }
        });

        DB::table('companies')->update([
            'allow_growth_staff_tracking' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'allow_growth_staff_tracking')) {
                $table->dropColumn('allow_growth_staff_tracking');
            }
        });
    }
};
