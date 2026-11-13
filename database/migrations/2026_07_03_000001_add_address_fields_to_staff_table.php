<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff', 'address')) {
                $table->text('address')->nullable()->after('profile_photo');
            }

            if (! Schema::hasColumn('staff', 'city')) {
                $table->string('city', 191)->nullable()->after('address');
            }

            if (! Schema::hasColumn('staff', 'state')) {
                $table->string('state', 191)->nullable()->after('city');
            }

            if (! Schema::hasColumn('staff', 'country')) {
                $table->string('country', 191)->nullable()->after('state');
            }

            if (! Schema::hasColumn('staff', 'zipcode')) {
                $table->string('zipcode', 20)->nullable()->after('country');
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff', function (Blueprint $table): void {
            if (Schema::hasColumn('staff', 'zipcode')) {
                $table->dropColumn('zipcode');
            }

            if (Schema::hasColumn('staff', 'country')) {
                $table->dropColumn('country');
            }

            if (Schema::hasColumn('staff', 'state')) {
                $table->dropColumn('state');
            }

            if (Schema::hasColumn('staff', 'city')) {
                $table->dropColumn('city');
            }

            if (Schema::hasColumn('staff', 'address')) {
                $table->dropColumn('address');
            }
        });
    }
};
