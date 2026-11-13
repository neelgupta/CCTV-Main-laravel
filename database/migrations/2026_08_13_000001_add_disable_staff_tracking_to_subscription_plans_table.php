<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('subscription_plans', 'disable_staff_tracking')) {
                $table->boolean('disable_staff_tracking')
                    ->default(false)
                    ->after('has_free_trial');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_plans', 'disable_staff_tracking')) {
                $table->dropColumn('disable_staff_tracking');
            }
        });
    }
};
