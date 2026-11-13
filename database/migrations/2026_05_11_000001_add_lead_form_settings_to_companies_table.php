<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (! Schema::hasColumn('companies', 'show_lead_gst_field')) {
                $table->boolean('show_lead_gst_field')->default(true)->after('enable_boq');
            }

            if (! Schema::hasColumn('companies', 'show_lead_pan_field')) {
                $table->boolean('show_lead_pan_field')->default(true)->after('show_lead_gst_field');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'show_lead_pan_field')) {
                $table->dropColumn('show_lead_pan_field');
            }

            if (Schema::hasColumn('companies', 'show_lead_gst_field')) {
                $table->dropColumn('show_lead_gst_field');
            }
        });
    }
};
