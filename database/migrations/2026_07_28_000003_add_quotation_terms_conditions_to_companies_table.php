<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'quotation_terms_conditions')) {
                $table->longText('quotation_terms_conditions')->nullable()->after('invoice_terms_conditions');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'quotation_terms_conditions')) {
                $table->dropColumn('quotation_terms_conditions');
            }
        });
    }
};
