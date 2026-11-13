<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amcs', function (Blueprint $table) {
            if (! Schema::hasColumn('amcs', 'tasks')) {
                $table->json('tasks')->nullable()->after('service_timing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('amcs', function (Blueprint $table) {
            if (Schema::hasColumn('amcs', 'tasks')) {
                $table->dropColumn('tasks');
            }
        });
    }
};
