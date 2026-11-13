<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'monthly_price')) {
                $table->decimal('monthly_price', 10, 2)
                    ->default(0)
                    ->after('price');
            }

            if (! Schema::hasColumn('subscription_plans', 'yearly_price')) {
                $table->decimal('yearly_price', 10, 2)
                    ->default(0)
                    ->after('monthly_price');
            }

            if (! Schema::hasColumn('subscription_plans', 'staff_limit')) {
                $table->unsignedInteger('staff_limit')
                    ->nullable()
                    ->after('yearly_price');
            }
        });

        DB::table('subscription_plans')
            ->orderBy('id')
            ->chunkById(100, function ($plans) {
                foreach ($plans as $plan) {
                    $limits = [];

                    if (property_exists($plan, 'limits') && is_string($plan->limits) && $plan->limits !== '') {
                        $decodedLimits = json_decode($plan->limits, true);
                        if (is_array($decodedLimits)) {
                            $limits = $decodedLimits;
                        }
                    }

                    DB::table('subscription_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'monthly_price' => $plan->monthly_price ?? $plan->price ?? 0,
                            'yearly_price' => $plan->yearly_price ?? $plan->price ?? 0,
                            'staff_limit' => array_key_exists('staff', $limits) && $limits['staff'] !== null && $limits['staff'] !== ''
                                ? (int) $limits['staff']
                                : null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'staff_limit')) {
                $table->dropColumn('staff_limit');
            }

            if (Schema::hasColumn('subscription_plans', 'yearly_price')) {
                $table->dropColumn('yearly_price');
            }

            if (Schema::hasColumn('subscription_plans', 'monthly_price')) {
                $table->dropColumn('monthly_price');
            }
        });
    }
};
