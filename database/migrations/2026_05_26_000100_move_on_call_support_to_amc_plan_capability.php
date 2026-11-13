<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_plans', function (Blueprint $table) {
            $table->boolean('includes_on_call_support')->default(false)->after('description');
        });

        Schema::table('amcs', function (Blueprint $table) {
            $table->boolean('includes_on_call_support')->default(false)->after('plan_description');
        });

        DB::table('amcs')
            ->where('amc_type', 'on_call')
            ->update([
                'includes_on_call_support' => true,
                'amc_type' => 'non_comprehensive',
            ]);
    }

    public function down(): void
    {
        DB::table('amcs')
            ->where('includes_on_call_support', true)
            ->where('visit_frequency', 'on_call')
            ->update([
                'amc_type' => 'on_call',
            ]);

        Schema::table('amcs', function (Blueprint $table) {
            $table->dropColumn('includes_on_call_support');
        });

        Schema::table('amc_plans', function (Blueprint $table) {
            $table->dropColumn('includes_on_call_support');
        });
    }
};
