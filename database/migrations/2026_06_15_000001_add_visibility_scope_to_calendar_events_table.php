<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (! Schema::hasColumn('calendar_events', 'visibility_scope')) {
                $table->string('visibility_scope', 20)->default('company')->after('status');
            }
        });

        if (Schema::hasColumn('calendar_events', 'visibility_scope')) {
            DB::table('calendar_events')
                ->whereNotNull('user_id')
                ->leftJoin('users', 'calendar_events.user_id', '=', 'users.id')
                ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                ->whereRaw(
                    'LOWER(COALESCE(roles.slug, users.role, "")) NOT IN (?, ?, ?, ?)',
                    ['company_admin', 'companyadmin', 'super_admin', 'admin']
                )
                ->update(['visibility_scope' => 'private']);
        }
    }

    public function down(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            if (Schema::hasColumn('calendar_events', 'visibility_scope')) {
                $table->dropColumn('visibility_scope');
            }
        });
    }
};
