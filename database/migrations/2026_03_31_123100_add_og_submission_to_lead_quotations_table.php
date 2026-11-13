<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lead_quotations', function (Blueprint $table) {
            $table->unsignedBigInteger('og_form_submission_id')->nullable()->after('form_data');
            $table->index('og_form_submission_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_quotations', function (Blueprint $table) {
            $table->dropIndex(['og_form_submission_id']);
            $table->dropColumn('og_form_submission_id');
        });
    }
};

