<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('companies')->update([
            'is_onboarded' => true,
            'onboarding_step' => 3
        ]);
    }

    public function down(): void
    {
        // No reverse action needed as this is a one-time data correction
    }
};
