<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (! Schema::hasColumn('staff', 'company_subscription_id')) {
                $table->foreignId('company_subscription_id')
                    ->nullable()
                    ->after('company_id')
                    ->constrained('company_subscriptions')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('staff', 'license_key')) {
                $table->string('license_key', 100)
                    ->nullable()
                    ->unique()
                    ->after('company_subscription_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table) {
            if (Schema::hasColumn('staff', 'license_key')) {
                $table->dropUnique('staff_license_key_unique');
                $table->dropColumn('license_key');
            }

            if (Schema::hasColumn('staff', 'company_subscription_id')) {
                $table->dropConstrainedForeignId('company_subscription_id');
            }
        });
    }
};
