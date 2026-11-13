<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->decimal('amount_paid', 12, 2)->default(0)->after('auto_renew');
            $table->string('payment_method', 50)->nullable()->after('amount_paid');
            $table->string('billing_reference')->nullable()->after('payment_method');
            $table->text('notes')->nullable()->after('billing_reference');
            $table->date('trial_ends_at')->nullable()->after('notes');
            $table->string('razorpay_order_id')->nullable()->after('trial_ends_at');
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'amount_paid',
                'payment_method',
                'billing_reference',
                'notes',
                'trial_ends_at',
                'razorpay_order_id',
                'razorpay_payment_id',
            ]);
        });
    }
};
