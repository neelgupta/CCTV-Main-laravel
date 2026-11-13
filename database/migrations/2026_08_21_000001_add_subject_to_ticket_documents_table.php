<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_documents', 'subject')) {
                $table->string('subject')->nullable()->after('document_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_documents', 'subject')) {
                $table->dropColumn('subject');
            }
        });
    }
};
