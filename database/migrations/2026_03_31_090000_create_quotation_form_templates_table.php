<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_form_templates', function (Blueprint $table) {
            $table->id();
            $table->string('template_key')->unique();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->json('schema');
            $table->string('submit_button_text')->default('Create Quotation');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_form_templates');
    }
};

