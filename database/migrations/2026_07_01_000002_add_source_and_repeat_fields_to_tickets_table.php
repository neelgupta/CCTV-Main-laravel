<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'source_type')) {
                $table->string('source_type', 50)->nullable()->after('generated_from_amc_id');
            }

            if (! Schema::hasColumn('tickets', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }

            if (! Schema::hasColumn('tickets', 'visit_number')) {
                $table->unsignedSmallInteger('visit_number')->nullable()->after('source_id');
            }

            if (! Schema::hasColumn('tickets', 'visit_type')) {
                $table->string('visit_type', 20)->nullable()->after('visit_number');
            }

            if (! Schema::hasColumn('tickets', 'parent_ticket_id')) {
                $table->foreignId('parent_ticket_id')->nullable()->after('visit_number')->constrained('tickets')->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'repeat_count')) {
                $table->unsignedSmallInteger('repeat_count')->default(0)->after('parent_ticket_id');
            }

            if (! Schema::hasColumn('tickets', 'repeated_after_days')) {
                $table->unsignedInteger('repeated_after_days')->nullable()->after('repeat_count');
            }

            if (! Schema::hasColumn('tickets', 'assigned_previous_staff_id')) {
                $table->foreignId('assigned_previous_staff_id')->nullable()->after('repeated_after_days')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'repeat_issue_reason')) {
                $table->text('repeat_issue_reason')->nullable()->after('assigned_previous_staff_id');
            }

            if (! Schema::hasColumn('tickets', 'issue_category')) {
                $table->string('issue_category', 120)->nullable()->after('repeat_issue_reason');
            }

            $table->unique(['source_type', 'source_id'], 'tickets_source_type_source_id_unique');
            $table->index(['company_id', 'parent_ticket_id'], 'tickets_company_parent_ticket_index');
            $table->index(['company_id', 'issue_category'], 'tickets_company_issue_category_index');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropUnique('tickets_source_type_source_id_unique');
            $table->dropIndex('tickets_company_parent_ticket_index');
            $table->dropIndex('tickets_company_issue_category_index');

            if (Schema::hasColumn('tickets', 'issue_category')) {
                $table->dropColumn('issue_category');
            }

            if (Schema::hasColumn('tickets', 'repeat_issue_reason')) {
                $table->dropColumn('repeat_issue_reason');
            }

            if (Schema::hasColumn('tickets', 'assigned_previous_staff_id')) {
                $table->dropConstrainedForeignId('assigned_previous_staff_id');
            }

            if (Schema::hasColumn('tickets', 'repeated_after_days')) {
                $table->dropColumn('repeated_after_days');
            }

            if (Schema::hasColumn('tickets', 'repeat_count')) {
                $table->dropColumn('repeat_count');
            }

            if (Schema::hasColumn('tickets', 'parent_ticket_id')) {
                $table->dropConstrainedForeignId('parent_ticket_id');
            }

            if (Schema::hasColumn('tickets', 'visit_number')) {
                $table->dropColumn('visit_number');
            }

            if (Schema::hasColumn('tickets', 'visit_type')) {
                $table->dropColumn('visit_type');
            }

            if (Schema::hasColumn('tickets', 'source_id')) {
                $table->dropColumn('source_id');
            }

            if (Schema::hasColumn('tickets', 'source_type')) {
                $table->dropColumn('source_type');
            }
        });
    }
};
