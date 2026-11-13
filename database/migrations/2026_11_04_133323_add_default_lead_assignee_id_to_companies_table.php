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
        if (! Schema::hasColumn('companies', 'default_lead_assignee_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('default_lead_assignee_id')
                    ->nullable()
                    ->after('allow_create_company_from_lead')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('companies', 'default_lead_assignee_id')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropForeign(['default_lead_assignee_id']);
                $table->dropColumn('default_lead_assignee_id');
            });
        }
    }
};
