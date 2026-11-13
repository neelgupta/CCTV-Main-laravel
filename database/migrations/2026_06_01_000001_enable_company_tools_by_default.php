<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE companies MODIFY enable_quotation_generator TINYINT(1) NOT NULL DEFAULT 1');
            DB::statement('ALTER TABLE companies MODIFY enable_boq TINYINT(1) NOT NULL DEFAULT 1');
        }

        DB::table('companies')
            ->whereNull('enable_quotation_generator')
            ->update(['enable_quotation_generator' => true]);

        DB::table('companies')
            ->whereNull('enable_boq')
            ->update(['enable_boq' => true]);
    }

    public function down(): void
    {
        if (in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE companies MODIFY enable_quotation_generator TINYINT(1) NOT NULL DEFAULT 0');
            DB::statement('ALTER TABLE companies MODIFY enable_boq TINYINT(1) NOT NULL DEFAULT 0');
        }
    }
};
