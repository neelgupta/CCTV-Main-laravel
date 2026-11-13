<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->boolean('is_recurring')->default(false)->after('auto_renew');
            $table->string('gateway_mode', 10)->nullable()->after('is_recurring');
            $table->string('razorpay_subscription_id')->nullable()->unique()->after('razorpay_payment_id');
            $table->string('razorpay_plan_id')->nullable()->after('razorpay_subscription_id');
            $table->string('razorpay_customer_id')->nullable()->after('razorpay_plan_id');
            $table->string('razorpay_status', 30)->nullable()->after('razorpay_customer_id');
            $table->timestamp('current_start')->nullable()->after('razorpay_status');
            $table->timestamp('current_end')->nullable()->after('current_start');
            $table->timestamp('next_charge_at')->nullable()->after('current_end');
            $table->unsignedInteger('paid_count')->default(0)->after('next_charge_at');
            $table->unsignedInteger('total_count')->nullable()->after('paid_count');
            $table->boolean('cancel_at_cycle_end')->default(false)->after('total_count');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_at_cycle_end');
            $table->timestamp('payment_failed_at')->nullable()->after('cancelled_at');
            $table->date('grace_ends_at')->nullable()->after('payment_failed_at');

            $table->index(['is_recurring', 'razorpay_status'], 'company_subscriptions_recurring_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('company_subscriptions_recurring_status_idx');
            $table->dropUnique(['razorpay_subscription_id']);
            $table->dropColumn([
                'is_recurring',
                'gateway_mode',
                'razorpay_subscription_id',
                'razorpay_plan_id',
                'razorpay_customer_id',
                'razorpay_status',
                'current_start',
                'current_end',
                'next_charge_at',
                'paid_count',
                'total_count',
                'cancel_at_cycle_end',
                'cancelled_at',
                'payment_failed_at',
                'grace_ends_at',
            ]);
        });
    }
};
