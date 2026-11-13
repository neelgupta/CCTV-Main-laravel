<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boq_products', function (Blueprint $table) {
            if (!Schema::hasColumn('boq_products', 'brand')) {
                $table->string('brand')->nullable()->after('name');
            }

            if (!Schema::hasColumn('boq_products', 'model')) {
                $table->string('model')->nullable()->after('brand');
            }

            if (!Schema::hasColumn('boq_products', 'image_path')) {
                $table->string('image_path')->nullable()->after('model');
            }
        });

        Schema::table('lead_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('lead_materials', 'product_id')) {
                $table->foreignId('product_id')
                    ->nullable()
                    ->after('lead_id')
                    ->constrained('boq_products')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lead_materials', function (Blueprint $table) {
            if (Schema::hasColumn('lead_materials', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }
        });

        Schema::table('boq_products', function (Blueprint $table) {
            if (Schema::hasColumn('boq_products', 'image_path')) {
                $table->dropColumn('image_path');
            }

            if (Schema::hasColumn('boq_products', 'model')) {
                $table->dropColumn('model');
            }

            if (Schema::hasColumn('boq_products', 'brand')) {
                $table->dropColumn('brand');
            }
        });
    }
};
