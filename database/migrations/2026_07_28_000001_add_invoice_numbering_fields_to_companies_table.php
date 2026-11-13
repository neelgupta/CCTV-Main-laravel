<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'invoice_prefix')) {
                $table->string('invoice_prefix')->nullable()->after('quotation_sequence');
            }

            if (! Schema::hasColumn('companies', 'invoice_year')) {
                $table->unsignedInteger('invoice_year')->nullable()->after('invoice_prefix');
            }

            if (! Schema::hasColumn('companies', 'invoice_sequence')) {
                $table->unsignedBigInteger('invoice_sequence')->nullable()->after('invoice_year');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'invoice_sequence',
                'invoice_year',
                'invoice_prefix',
            ] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
