<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->whereNull('company_id')
            ->whereIn('role', ['super_admin', 'admin'])
            ->update([
                'email_verified_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Keep verification state intact; reverting this would make the
        // super-admin route blocked again by the verified middleware.
    }
};
