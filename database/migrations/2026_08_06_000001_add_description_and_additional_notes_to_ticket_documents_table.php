<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_documents', 'has_additional_notes')) {
                $table->text('has_additional_notes')->nullable()->after('notes');
            }

           

            if (! Schema::hasColumn('ticket_documents', 'additional_notes')) {
                $table->text('additional_notes')->nullable()->after('has_additional_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_documents', 'additional_notes')) {
                $table->dropColumn('additional_notes');
            }

            if (Schema::hasColumn('ticket_documents', 'has_additional_notes')) {
                $table->dropColumn('has_additional_notes');
            }

          
        });
    }
};
