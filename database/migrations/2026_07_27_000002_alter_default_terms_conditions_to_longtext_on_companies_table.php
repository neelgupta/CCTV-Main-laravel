<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasColumn('companies', 'default_terms_conditions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE companies MODIFY default_terms_conditions LONGTEXT NULL');
        }
    }

    public function down(): void
    {
        if (! DB::getSchemaBuilder()->hasColumn('companies', 'default_terms_conditions')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE companies MODIFY default_terms_conditions TEXT NULL');
        }
    }
};
