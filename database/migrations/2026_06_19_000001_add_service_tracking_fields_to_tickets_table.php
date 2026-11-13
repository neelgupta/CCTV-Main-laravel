<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'service_started_at')) {
                $table->timestamp('service_started_at')->nullable()->after('service_notes');
            }

            if (! Schema::hasColumn('tickets', 'service_ended_at')) {
                $table->timestamp('service_ended_at')->nullable()->after('service_started_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            foreach ([
                'service_ended_at',
                'service_started_at',
            ] as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
