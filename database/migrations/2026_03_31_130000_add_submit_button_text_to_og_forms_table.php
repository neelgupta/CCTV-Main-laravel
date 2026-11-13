<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('og_forms', function (Blueprint $table) {
            $table->string('submit_button_text')->default('Create Quotation')->after('form_key');
        });
    }

    public function down(): void
    {
        Schema::table('og_forms', function (Blueprint $table) {
            $table->dropColumn('submit_button_text');
        });
    }
};
