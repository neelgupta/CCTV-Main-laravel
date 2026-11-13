<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'quote_discount')) {
                $table->decimal('quote_discount', 12, 2)->nullable()->after('quote_subtotal');
            }

            if (! Schema::hasColumn('tickets', 'quote_gst_amount')) {
                $table->decimal('quote_gst_amount', 12, 2)->nullable()->after('quote_discount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'quote_gst_amount')) {
                $table->dropColumn('quote_gst_amount');
            }

            if (Schema::hasColumn('tickets', 'quote_discount')) {
                $table->dropColumn('quote_discount');
            }
        });
    }
};
