<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('companies', 'lead_custom_fields')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->json('lead_custom_fields')->nullable()->after('lead_source_master_list');
            });
        }

        if (! Schema::hasColumn('leads', 'custom_fields')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('referral_person_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'custom_fields')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasColumn('companies', 'lead_custom_fields')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('lead_custom_fields');
            });
        }
    }
};
