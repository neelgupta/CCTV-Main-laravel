<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'repeat_status')) {
                $table->boolean('repeat_status')->default(false)->after('issue_category');
            }

            if (! Schema::hasColumn('tickets', 'last_repeat_at')) {
                $table->timestamp('last_repeat_at')->nullable()->after('repeat_status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'last_repeat_at')) {
                $table->dropColumn('last_repeat_at');
            }

            if (Schema::hasColumn('tickets', 'repeat_status')) {
                $table->dropColumn('repeat_status');
            }
        });
    }
};
