<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'customer_contact_number')) {
                $table->string('customer_contact_number')->nullable()->after('assigned_to');
            }

            if (! Schema::hasColumn('tickets', 'type')) {
                $table->string('type')->nullable()->after('customer_contact_number');
            }

            if (! Schema::hasColumn('tickets', 'referenced_by')) {
                $table->string('referenced_by')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'referenced_by')) {
                $table->dropColumn('referenced_by');
            }

            if (Schema::hasColumn('tickets', 'type')) {
                $table->dropColumn('type');
            }

            if (Schema::hasColumn('tickets', 'customer_contact_number')) {
                $table->dropColumn('customer_contact_number');
            }
        });
    }
};

