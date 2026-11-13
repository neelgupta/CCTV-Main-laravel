<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (! Schema::hasColumn('ticket_documents', 'terms_conditions')) {
                $table->longText('terms_conditions')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_documents', function (Blueprint $table): void {
            if (Schema::hasColumn('ticket_documents', 'terms_conditions')) {
                $table->dropColumn('terms_conditions');
            }
        });
    }
};
