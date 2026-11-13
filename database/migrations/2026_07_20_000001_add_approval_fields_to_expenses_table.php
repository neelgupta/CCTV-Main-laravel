<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (! Schema::hasColumn('expenses', 'approval_status')) {
                $table->string('approval_status', 50)->default('approved')->after('attachment');
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable()->after('approved_by');
                $table->index(['company_id', 'approval_status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            if (Schema::hasColumn('expenses', 'approval_status')) {
                $table->dropIndex(['company_id', 'approval_status']);
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn(['approval_status', 'approved_at']);
            }
        });
    }
};
