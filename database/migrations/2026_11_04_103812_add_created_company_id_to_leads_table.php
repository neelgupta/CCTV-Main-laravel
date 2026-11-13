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
        if (! Schema::hasColumn('leads', 'created_company_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('created_company_id')->nullable()->after('client_name')->constrained('companies')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('leads', 'created_company_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_company_id');
            });
        }
    }
};
