<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (! Schema::hasColumn('project_materials', 'contractor_price')) {
                $table->decimal('contractor_price', 12, 2)->nullable()->after('rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_materials', function (Blueprint $table) {
            if (Schema::hasColumn('project_materials', 'contractor_price')) {
                $table->dropColumn('contractor_price');
            }
        });
    }
};
