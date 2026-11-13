<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amcs', function (Blueprint $table): void {
            if (! Schema::hasColumn('amcs', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('amc_amount');
            }

            if (! Schema::hasColumn('amcs', 'tax_amount')) {
                $table->decimal('tax_amount', 12, 2)->default(0)->after('tax_percent');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amcs', function (Blueprint $table): void {
            if (Schema::hasColumn('amcs', 'tax_amount')) {
                $table->dropColumn('tax_amount');
            }

            if (Schema::hasColumn('amcs', 'subtotal')) {
                $table->dropColumn('subtotal');
            }
        });
    }
};
