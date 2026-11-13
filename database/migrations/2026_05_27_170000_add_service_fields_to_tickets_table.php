<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'service_notes')) {
                $table->text('service_notes')->nullable()->after('description');
            }

            if (! Schema::hasColumn('tickets', 'current_condition_photos')) {
                $table->json('current_condition_photos')->nullable()->after('service_notes');
            }

            if (! Schema::hasColumn('tickets', 'after_repair_photos')) {
                $table->json('after_repair_photos')->nullable()->after('current_condition_photos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'after_repair_photos')) {
                $table->dropColumn('after_repair_photos');
            }

            if (Schema::hasColumn('tickets', 'current_condition_photos')) {
                $table->dropColumn('current_condition_photos');
            }

            if (Schema::hasColumn('tickets', 'service_notes')) {
                $table->dropColumn('service_notes');
            }
        });
    }
};
