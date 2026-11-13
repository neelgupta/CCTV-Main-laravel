<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $manageId = DB::table('permissions')->where('key', 'teams.staff.salary.manage')->value('id');
        $viewId = DB::table('permissions')->where('key', 'teams.staff.salary.view')->value('id');
        if (!$manageId || !$viewId) {
            return;
        }

        $roles = DB::table('roles')->whereIn('slug', ['employee', 'manager'])->pluck('id');
        foreach ($roles as $roleId) {
            foreach ([$viewId, $manageId] as $pid) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $pid, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $manageId = DB::table('permissions')->where('key', 'teams.staff.salary.manage')->value('id');
        $viewId = DB::table('permissions')->where('key', 'teams.staff.salary.view')->value('id');
        $roles = DB::table('roles')->whereIn('slug', ['employee', 'manager'])->pluck('id');
        if ($manageId && $roles->isNotEmpty()) {
            DB::table('permission_role')->whereIn('role_id', $roles)->where('permission_id', $manageId)->delete();
        }
        if ($viewId && $roles->isNotEmpty()) {
            DB::table('permission_role')->whereIn('role_id', $roles)->where('permission_id', $viewId)->delete();
        }
    }
};
