<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('company_code')->nullable()->unique()->after('name');
        });

        DB::table('companies')
            ->select('id')
            ->whereNull('company_code')
            ->orderBy('id')
            ->chunkById(100, function ($companies): void {
                foreach ($companies as $company) {
                    do {
                        $code = 'CC-' . Str::upper(Str::random(6));
                    } while (DB::table('companies')->where('company_code', $code)->exists());

                    DB::table('companies')
                        ->where('id', $company->id)
                        ->update(['company_code' => $code]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropUnique(['company_code']);
            $table->dropColumn('company_code');
        });
    }
};
