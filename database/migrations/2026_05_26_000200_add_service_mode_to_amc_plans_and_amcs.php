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
            $table->string('service_mode', 20)->default('scheduled')->after('includes_on_call_support');
        });

        Schema::table('amcs', function (Blueprint $table) {
            $table->string('service_mode', 20)->default('scheduled')->after('includes_on_call_support');
        });

        DB::table('amc_plans')
            ->where('includes_on_call_support', true)
            ->update(['service_mode' => 'on_call']);

        DB::table('amcs')
            ->where('visit_frequency', 'on_call')
            ->update(['service_mode' => 'on_call']);

        DB::table('amcs')
            ->where('visit_frequency', '!=', 'on_call')
            ->where('includes_on_call_support', true)
            ->update(['service_mode' => 'hybrid']);
    }

    public function down(): void
    {
        Schema::table('amcs', function (Blueprint $table) {
            $table->dropColumn('service_mode');
        });

        Schema::table('amc_plans', function (Blueprint $table) {
            $table->dropColumn('service_mode');
        });
    }
};
