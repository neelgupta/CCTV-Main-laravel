<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Only Growth and Enterprise should be purchasable going forward. Starter is
     * deactivated (not deleted) so existing company_subscriptions rows that still
     * reference it keep working untouched.
     */
    public function up(): void
    {
        DB::table('subscription_plans')
            ->whereRaw('LOWER(slug) = ?', ['starter'])
            ->orWhereRaw('LOWER(name) = ?', ['starter'])
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('subscription_plans')
            ->whereRaw('LOWER(slug) = ?', ['starter'])
            ->orWhereRaw('LOWER(name) = ?', ['starter'])
            ->update(['is_active' => true, 'updated_at' => now()]);
    }
};
