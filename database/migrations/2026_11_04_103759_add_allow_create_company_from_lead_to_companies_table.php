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
        if (! Schema::hasColumn('companies', 'allow_create_company_from_lead')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->boolean('allow_create_company_from_lead')->default(false)->after('allow_appointment_module');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'allow_create_company_from_lead')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropColumn('allow_create_company_from_lead');
            });
        }
    }
};
