<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->unique('company_id');
        });

        $companyId = DB::table('companies')->orderBy('id')->value('id');

        if ($companyId) {
            DB::table('letterhead_settings')
                ->whereNull('company_id')
                ->update(['company_id' => $companyId]);
        }
    }

    public function down(): void
    {
        Schema::table('letterhead_settings', function (Blueprint $table) {
            $table->dropUnique(['company_id']);
            $table->dropConstrainedForeignId('company_id');
        });
    }
};
