<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_products', function (Blueprint $table) {
            $table->decimal('gst', 5, 2)->default(0)->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('boq_products', function (Blueprint $table) {
            $table->dropColumn('gst');
        });
    }
};
