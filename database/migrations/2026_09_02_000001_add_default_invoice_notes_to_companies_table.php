<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('companies', 'has_default_invoice_notes')) {
                $table->boolean('has_default_invoice_notes')->default(false)->after('invoice_sequence');
            }

            if (! Schema::hasColumn('companies', 'default_invoice_notes')) {
                $table->text('default_invoice_notes')->nullable()->after('has_default_invoice_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            if (Schema::hasColumn('companies', 'default_invoice_notes')) {
                $table->dropColumn('default_invoice_notes');
            }

            if (Schema::hasColumn('companies', 'has_default_invoice_notes')) {
                $table->dropColumn('has_default_invoice_notes');
            }
        });
    }
};
