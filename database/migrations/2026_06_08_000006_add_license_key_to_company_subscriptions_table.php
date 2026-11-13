<?php

use App\Models\CompanySubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->string('license_key', 100)->nullable()->unique()->after('billing_reference');
        });

        DB::table('company_subscriptions')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function ($subscription): void {
                DB::table('company_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'license_key' => CompanySubscription::generateLicenseKey(),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            $table->dropUnique('company_subscriptions_license_key_unique');
            $table->dropColumn('license_key');
        });
    }
};
