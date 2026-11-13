<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'preferred_visit_time')) {
                $table->string('preferred_visit_time', 20)
                    ->nullable()
                    ->after('visit_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'preferred_visit_time')) {
                $table->dropColumn('preferred_visit_time');
            }
        });
    }
};
