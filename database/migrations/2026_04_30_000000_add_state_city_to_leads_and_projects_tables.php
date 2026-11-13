<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (! Schema::hasColumn('leads', 'state_id')) {
                $table->foreignId('state_id')->nullable()->after('pan_number')->constrained('states')->nullOnDelete();
            }

            if (! Schema::hasColumn('leads', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities')->nullOnDelete();
            }
        });

        Schema::table('projects', function (Blueprint $table) {
            if (! Schema::hasColumn('projects', 'state_id')) {
                $table->foreignId('state_id')->nullable()->after('client_name')->constrained('states')->nullOnDelete();
            }

            if (! Schema::hasColumn('projects', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('state_id')->constrained('cities')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'city_id')) {
                $table->dropConstrainedForeignId('city_id');
            }

            if (Schema::hasColumn('projects', 'state_id')) {
                $table->dropConstrainedForeignId('state_id');
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'city_id')) {
                $table->dropConstrainedForeignId('city_id');
            }

            if (Schema::hasColumn('leads', 'state_id')) {
                $table->dropConstrainedForeignId('state_id');
            }
        });
    }
};
