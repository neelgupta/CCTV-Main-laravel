<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->boolean('has_free_trial')->default(false)->after('is_popular');
        });

        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->boolean('is_trial')->default(false)->after('status');
            $table->string('subscriber_email')->nullable()->after('billing_reference');
            $table->index(['subscription_plan_id', 'company_id', 'is_trial'], 'company_subscriptions_plan_company_trial_idx');
            $table->index(['subscription_plan_id', 'subscriber_email', 'is_trial'], 'company_subscriptions_plan_email_trial_idx');
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropIndex('company_subscriptions_plan_company_trial_idx');
            $table->dropIndex('company_subscriptions_plan_email_trial_idx');
            $table->dropColumn(['is_trial', 'subscriber_email']);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn('has_free_trial');
        });
    }
};
