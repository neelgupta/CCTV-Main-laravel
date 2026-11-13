<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('ticket_id')->constrained('clients')->nullOnDelete();
        });

        Schema::table('ticket_invoices', function (Blueprint $table): void {
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('ticket_id')->constrained('clients')->nullOnDelete();
        });

        Schema::table('ticket_quotes', function (Blueprint $table): void {
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_quotes', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable()->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->after('ticket_id')->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ticket_quotes', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_quotes', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });

        Schema::table('ticket_invoices', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_invoices', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });

        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
            $table->dropForeign(['ticket_id']);
        });
        Schema::table('ticket_documents', function (Blueprint $table): void {
            $table->foreignId('ticket_id')->nullable(false)->change();
            $table->foreign('ticket_id')->references('id')->on('tickets')->cascadeOnDelete();
        });
    }
};
