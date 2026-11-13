<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            $table->json('reminder_weekdays')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            $table->dropColumn('reminder_weekdays');
        });
    }
};
