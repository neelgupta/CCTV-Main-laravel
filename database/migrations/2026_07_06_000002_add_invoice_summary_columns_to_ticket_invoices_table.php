<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_invoices', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('invoice_id');
            }

            if (! Schema::hasColumn('ticket_invoices', 'invoice_subtotal')) {
                $table->decimal('invoice_subtotal', 12, 2)->nullable()->after('invoice_number');
            }

            if (! Schema::hasColumn('ticket_invoices', 'invoice_discount')) {
                $table->decimal('invoice_discount', 12, 2)->nullable()->after('invoice_subtotal');
            }

            if (! Schema::hasColumn('ticket_invoices', 'invoice_gst_amount')) {
                $table->decimal('invoice_gst_amount', 12, 2)->nullable()->after('invoice_discount');
            }

            if (! Schema::hasColumn('ticket_invoices', 'invoice_total')) {
                $table->decimal('invoice_total', 12, 2)->nullable()->after('invoice_gst_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_invoices', 'invoice_total')) {
                $table->dropColumn('invoice_total');
            }

            if (Schema::hasColumn('ticket_invoices', 'invoice_gst_amount')) {
                $table->dropColumn('invoice_gst_amount');
            }

            if (Schema::hasColumn('ticket_invoices', 'invoice_discount')) {
                $table->dropColumn('invoice_discount');
            }

            if (Schema::hasColumn('ticket_invoices', 'invoice_subtotal')) {
                $table->dropColumn('invoice_subtotal');
            }

            if (Schema::hasColumn('ticket_invoices', 'invoice_number')) {
                $table->dropColumn('invoice_number');
            }
        });
    }
};
