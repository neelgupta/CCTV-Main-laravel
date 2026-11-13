<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'msme_number')) {
                $table->string('msme_number', 50)->nullable()->after('pan_number');
            }

            if (! Schema::hasColumn('companies', 'show_msme_number_in_invoice')) {
                $table->boolean('show_msme_number_in_invoice')->default(false)->after('msme_number');
            }

            if (! Schema::hasColumn('companies', 'show_msme_number_in_quotation')) {
                $table->boolean('show_msme_number_in_quotation')->default(false)->after('show_msme_number_in_invoice');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'show_msme_number_in_quotation')) {
                $table->dropColumn('show_msme_number_in_quotation');
            }

            if (Schema::hasColumn('companies', 'show_msme_number_in_invoice')) {
                $table->dropColumn('show_msme_number_in_invoice');
            }

            if (Schema::hasColumn('companies', 'msme_number')) {
                $table->dropColumn('msme_number');
            }
        });
    }
};
