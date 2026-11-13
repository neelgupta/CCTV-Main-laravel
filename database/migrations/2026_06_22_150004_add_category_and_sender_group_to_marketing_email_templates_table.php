<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_email_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('marketing_email_templates', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('company_id')->constrained('marketing_email_template_categories')->nullOnDelete();
            }

            if (! Schema::hasColumn('marketing_email_templates', 'sender_group_id')) {
                $table->foreignId('sender_group_id')->nullable()->after('updated_by')->constrained('marketing_email_sender_groups')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('marketing_email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('marketing_email_templates', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }

            if (Schema::hasColumn('marketing_email_templates', 'sender_group_id')) {
                $table->dropConstrainedForeignId('sender_group_id');
            }
        });
    }
};
