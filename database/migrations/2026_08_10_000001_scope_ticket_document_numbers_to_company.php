<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->dropUnique('ticket_documents_document_number_unique');
            $table->unique(['company_id', 'document_number'], 'ticket_documents_company_document_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->dropUnique('ticket_documents_company_document_number_unique');
            $table->unique('document_number');
        });
    }
};
