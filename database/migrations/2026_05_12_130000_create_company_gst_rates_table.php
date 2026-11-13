<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_gst_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate', 5, 2);
            $table->timestamps();

            $table->unique(['company_id', 'rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_gst_rates');
    }
};
