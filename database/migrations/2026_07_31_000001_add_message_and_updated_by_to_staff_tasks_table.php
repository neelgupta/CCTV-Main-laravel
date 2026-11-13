<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (! Schema::hasColumn('staff_tasks', 'message')) {
                $table->text('message')->nullable()->after('status');
            }

            if (! Schema::hasColumn('staff_tasks', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('message')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('staff_tasks', function (Blueprint $table): void {
            if (Schema::hasColumn('staff_tasks', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('staff_tasks', 'message')) {
                $table->dropColumn('message');
            }
        });
    }
};
