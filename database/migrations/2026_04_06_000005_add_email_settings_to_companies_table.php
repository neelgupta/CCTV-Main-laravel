<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach ([
                'mail_username' => 'after(challan_sequence)',
                'mail_password' => 'after(mail_username)',
                'mail_encryption' => 'after(mail_password)',
                'from_address' => 'after(mail_encryption)',
                'from_name' => 'after(from_address)',
                'to_address' => 'after(from_name)',
                'to_name' => 'after(to_address)',
            ] as $column => $placement) {
                if (!Schema::hasColumn('companies', $column)) {
                    $table->string($column)->nullable();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (['to_name', 'to_address', 'from_name', 'from_address', 'mail_encryption', 'mail_password', 'mail_username'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
