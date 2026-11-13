<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (!Schema::hasColumn('project_materials', 'product_id')) {
                $table->foreignId('product_id')->nullable()->after('project_id')->constrained('boq_products')->nullOnDelete();
            }

            if (!Schema::hasColumn('project_materials', 'unit')) {
                $table->string('unit', 50)->nullable()->after('qty');
            }

            if (!Schema::hasColumn('project_materials', 'rate')) {
                $table->decimal('rate', 12, 2)->default(0)->after('unit');
            }

            if (!Schema::hasColumn('project_materials', 'gst')) {
                $table->decimal('gst', 5, 2)->default(0)->after('rate');
            }

            if (!Schema::hasColumn('project_materials', 'total')) {
                $table->decimal('total', 12, 2)->default(0)->after('gst');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (Schema::hasColumn('project_materials', 'product_id')) {
                $table->dropConstrainedForeignId('product_id');
            }

            $columns = collect(['unit', 'rate', 'gst', 'total'])
                ->filter(fn (string $column) => Schema::hasColumn('project_materials', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
