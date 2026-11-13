<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('company_subscriptions', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->default('monthly')->after('subscription_plan_id');
            }
        });

        DB::table('company_subscriptions')
            ->orderBy('id')
            ->get(['id', 'subscription_plan_id'])
            ->each(function ($subscription): void {
                $billingCycle = DB::table('subscription_plans')
                    ->where('id', $subscription->subscription_plan_id)
                    ->value('billing_cycle') ?: 'monthly';

                DB::table('company_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'billing_cycle' => $billingCycle,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('company_subscriptions', 'billing_cycle')) {
                $table->dropColumn('billing_cycle');
            }
        });
    }
};
