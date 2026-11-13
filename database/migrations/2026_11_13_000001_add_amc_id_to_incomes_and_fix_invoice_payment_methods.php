<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            if (! Schema::hasColumn('incomes', 'amc_id')) {
                $table->foreignId('amc_id')->nullable()->after('invoice_id')->constrained('amcs')->nullOnDelete();
                $table->unique(['company_id', 'amc_id']);
            }
        });

        // Invoices only use 0/2 = Cash and 1 = Online. Older auto-created incomes
        // stored method 1 as "Bank" and method 2 as "UPI"; correct them.
        $invoiceIds = fn (int $method) => DB::table('ticket_invoices')->where('payment_method', $method)->select('id');

        DB::table('incomes')
            ->whereIn('invoice_id', $invoiceIds(1))
            ->where('payment_method', 'Bank')
            ->update(['payment_method' => 'Online']);

        DB::table('incomes')
            ->whereIn('invoice_id', $invoiceIds(2))
            ->where('payment_method', 'UPI')
            ->update(['payment_method' => 'Cash']);
    }

    public function down(): void
    {
        Schema::table('incomes', function (Blueprint $table): void {
            if (Schema::hasColumn('incomes', 'amc_id')) {
                $table->dropUnique(['company_id', 'amc_id']);
                $table->dropConstrainedForeignId('amc_id');
            }
        });
    }
};
