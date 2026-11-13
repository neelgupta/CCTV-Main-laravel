<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->foreignId('created_by_staff_id')
                ->nullable()
                ->after('staff_id')
                ->constrained('staff')
                ->nullOnDelete();

            $table->index(['company_id', 'created_by_staff_id']);
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'created_by_staff_id']);
            $table->dropConstrainedForeignId('created_by_staff_id');
        });
    }
};
