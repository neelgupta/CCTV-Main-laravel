<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('tickets', 'customer_name')) {
                $table->string('customer_name')->nullable()->after('customer_contact_number');
            }

            if (! Schema::hasColumn('tickets', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('customer_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            if (Schema::hasColumn('tickets', 'customer_email')) {
                $table->dropColumn('customer_email');
            }

            if (Schema::hasColumn('tickets', 'customer_name')) {
                $table->dropColumn('customer_name');
            }
        });
    }
};

