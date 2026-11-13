<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('document_type', 20);
            $table->unsignedTinyInteger('invoice_or_quote')->nullable();
            $table->string('document_number')->unique();
            $table->json('items')->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('discount', 12, 2)->nullable();
            $table->decimal('gst_amount', 12, 2)->nullable();
            $table->decimal('total', 12, 2)->nullable();
            $table->unsignedTinyInteger('payment_status')->nullable();
            $table->unsignedTinyInteger('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_documents');
    }
};
