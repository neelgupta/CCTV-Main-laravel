<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('subscription_plans', 'disable_staff_tracking')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->boolean('disable_staff_tracking')
                    ->default(false)
                    ->after('has_free_trial');
            });
        }

        DB::table('subscription_plans')
            ->where('price', 199)
            ->orWhere('monthly_price', 199)
            ->orWhere('yearly_price', 199)
            ->update([
                'disable_staff_tracking' => true,
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('subscription_plans', 'disable_staff_tracking')) {
            Schema::table('subscription_plans', function (Blueprint $table): void {
                $table->dropColumn('disable_staff_tracking');
            });
        }
    }
};