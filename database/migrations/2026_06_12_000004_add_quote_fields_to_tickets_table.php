<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'quote_items')) {
                $table->json('quote_items')->nullable()->after('complate_task_list');
            }

            if (! Schema::hasColumn('tickets', 'quote_subtotal')) {
                $table->decimal('quote_subtotal', 12, 2)->nullable()->after('quote_items');
            }

            if (! Schema::hasColumn('tickets', 'quote_total')) {
                $table->decimal('quote_total', 12, 2)->nullable()->after('quote_subtotal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'quote_total')) {
                $table->dropColumn('quote_total');
            }

            if (Schema::hasColumn('tickets', 'quote_subtotal')) {
                $table->dropColumn('quote_subtotal');
            }

            if (Schema::hasColumn('tickets', 'quote_items')) {
                $table->dropColumn('quote_items');
            }
        });
    }
};
