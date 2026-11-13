<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('ticket_documents')->restrictOnDelete();
            $table->unsignedTinyInteger('payment_status')->default(0);
            $table->unsignedTinyInteger('payment_method')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'invoice_id'], 'ticket_invoices_ticket_invoice_unique');
            $table->index(['ticket_id', 'payment_status'], 'ticket_invoices_ticket_payment_index');
        });

        Schema::create('ticket_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('quote_id')->constrained('ticket_documents')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['ticket_id', 'quote_id'], 'ticket_quotes_ticket_quote_unique');
            $table->index(['ticket_id', 'quote_id'], 'ticket_quotes_ticket_quote_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_quotes');
        Schema::dropIfExists('ticket_invoices');
    }
};
