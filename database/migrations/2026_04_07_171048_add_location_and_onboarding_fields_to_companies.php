<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'city')) {
                $table->string('city')->nullable()->after('address');
            }
            if (!Schema::hasColumn('companies', 'state')) {
                $table->string('state')->nullable()->after('city');
            }
            if (!Schema::hasColumn('companies', 'onboarding_step')) {
                $table->integer('onboarding_step')->default(1)->after('state');
            }
            if (!Schema::hasColumn('companies', 'is_onboarded')) {
                $table->boolean('is_onboarded')->default(false)->after('onboarding_step');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['city', 'state', 'onboarding_step', 'is_onboarded']);
        });
    }
};
