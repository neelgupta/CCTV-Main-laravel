<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_plans', 'razorpay_plan_ids')) {
                // {"live": {"monthly": {"id": "plan_x", "amount": 118000}, "yearly": {...}}, "test": {...}}
                $table->json('razorpay_plan_ids')->nullable()->after('yearly_price');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_plans', 'razorpay_plan_ids')) {
                $table->dropColumn('razorpay_plan_ids');
            }
        });
    }
};
