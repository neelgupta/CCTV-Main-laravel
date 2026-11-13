<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'gst_number')) {
                $table->string('gst_number', 20)->nullable()->after('phone');
            }

            if (! Schema::hasColumn('clients', 'pan_number')) {
                $table->string('pan_number', 20)->nullable()->after('gst_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'pan_number')) {
                $table->dropColumn('pan_number');
            }

            if (Schema::hasColumn('clients', 'gst_number')) {
                $table->dropColumn('gst_number');
            }
        });
    }
};
