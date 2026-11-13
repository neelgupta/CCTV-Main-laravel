<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quotations', function (Blueprint $table) {
            $table->string('source')->default('upload')->after('file_path'); // upload|form
            $table->json('form_schema')->nullable()->after('notes');
            $table->json('form_data')->nullable()->after('form_schema');
        });
    }

    public function down(): void
    {
        Schema::table('lead_quotations', function (Blueprint $table) {
            $table->dropColumn(['source', 'form_schema', 'form_data']);
        });
    }
};

