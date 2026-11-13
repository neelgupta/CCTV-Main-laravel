<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'gst_number')) {
                $table->string('gst_number')->nullable()->after('address');
            }

            if (!Schema::hasColumn('companies', 'pan_number')) {
                $table->string('pan_number')->nullable()->after('gst_number');
            }

            if (!Schema::hasColumn('companies', 'registration_number')) {
                $table->string('registration_number')->nullable()->after('pan_number');
            }

            if (!Schema::hasColumn('companies', 'registration_date')) {
                $table->date('registration_date')->nullable()->after('registration_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (Schema::hasColumn('companies', 'registration_date')) {
                $table->dropColumn('registration_date');
            }

            if (Schema::hasColumn('companies', 'registration_number')) {
                $table->dropColumn('registration_number');
            }

            if (Schema::hasColumn('companies', 'pan_number')) {
                $table->dropColumn('pan_number');
            }

            if (Schema::hasColumn('companies', 'gst_number')) {
                $table->dropColumn('gst_number');
            }
        });
    }
};
