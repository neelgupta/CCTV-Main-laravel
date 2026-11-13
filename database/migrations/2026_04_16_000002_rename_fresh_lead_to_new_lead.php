<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('lead_statuses')
            ->whereRaw('LOWER(name) = ?', ['fresh lead'])
            ->update([
                'name' => 'New Lead',
                'is_default' => true,
            ]);
    }

    public function down(): void
    {
        DB::table('lead_statuses')
            ->whereRaw('LOWER(name) = ?', ['new lead'])
            ->update([
                'name' => 'Fresh Lead',
            ]);
    }
};
