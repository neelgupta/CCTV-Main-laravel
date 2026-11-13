<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            //
            $table->string('leave_reason')->nullable()->after('status');
            $table->string('correction_reason')->nullable()->after('overtime_hours');
            $table->boolean('is_corrected')->default(false)->after('correction_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_attendance', function (Blueprint $table) {
            if (Schema::hasColumn('staff_attendance', 'leave_reason')) {
                $table->dropColumn('leave_reason');
            }
            if (Schema::hasColumn('staff_attendance', 'correction_reason')) {
                $table->dropColumn('correction_reason');
            }
            if (Schema::hasColumn('staff_attendance', 'is_corrected')) {
                $table->dropColumn('is_corrected');
            }
        });
    }
};
