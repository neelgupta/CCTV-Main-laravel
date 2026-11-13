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
            if (! Schema::hasColumn('company_subscriptions', 'license_count')) {
                $table->unsignedInteger('license_count')->nullable()->after('license_key');
            }

            if (! Schema::hasColumn('company_subscriptions', 'license_keys')) {
                $table->json('license_keys')->nullable()->after('license_count');
            }
        });

        DB::table('company_subscriptions')
            ->orderBy('id')
            ->get(['id', 'license_key'])
            ->each(function ($subscription): void {
                $licenseKey = $subscription->license_key ?: null;
                $licenseKeys = $licenseKey ? json_encode([$licenseKey]) : null;

                DB::table('company_subscriptions')
                    ->where('id', $subscription->id)
                    ->update([
                        'license_count' => $licenseKey ? 1 : null,
                        'license_keys' => $licenseKeys,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('company_subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('company_subscriptions', 'license_keys')) {
                $table->dropColumn('license_keys');
            }

            if (Schema::hasColumn('company_subscriptions', 'license_count')) {
                $table->dropColumn('license_count');
            }
        });
    }
};
