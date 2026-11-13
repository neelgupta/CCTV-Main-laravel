<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Remove phone from leads
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'phone')) {
                $table->dropColumn('phone');
            }
        });

        // Clear category data as requested
      DB::table('lead_categories')->delete();

        // Remove company_id from lead_categories
        Schema::table('lead_categories', function (Blueprint $table) {
            if (Schema::hasColumn('lead_categories', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('phone')->nullable();
        });

        Schema::table('lead_categories', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade');
        });
    }
};
