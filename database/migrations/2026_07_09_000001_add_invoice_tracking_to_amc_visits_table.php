<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amc_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('amc_visits', 'ticket_invoice_id')) {
                $table->foreignId('ticket_invoice_id')
                    ->nullable()
                    ->after('support_ticket_id')
                    ->constrained('ticket_invoices')
                    ->nullOnDelete();
            }

            $table->unique(['ticket_invoice_id'], 'amc_visits_ticket_invoice_unique');
        });
    }

    public function down(): void
    {
        Schema::table('amc_visits', function (Blueprint $table): void {
            if (Schema::hasColumn('amc_visits', 'ticket_invoice_id')) {
                $table->dropUnique('amc_visits_ticket_invoice_unique');
                $table->dropConstrainedForeignId('ticket_invoice_id');
            }
        });
    }
};
