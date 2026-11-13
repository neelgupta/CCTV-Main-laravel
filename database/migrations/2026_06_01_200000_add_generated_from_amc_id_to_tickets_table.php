<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'generated_from_amc_id')) {
                $table->foreignId('generated_from_amc_id')
                    ->nullable()
                    ->after('board_stage')
                    ->constrained('amcs')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'generated_from_amc_id')) {
                $table->dropConstrainedForeignId('generated_from_amc_id');
            }
        });
    }
};

