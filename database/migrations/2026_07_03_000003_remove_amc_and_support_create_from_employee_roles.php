<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['amc.create', 'support.create'])
            ->pluck('id');

        $employeeRoleIds = DB::table('roles')
            ->whereIn('slug', ['employee'])
            ->pluck('id');

        if ($permissionIds->isEmpty() || $employeeRoleIds->isEmpty()) {
            return;
        }

        DB::table('permission_role')
            ->whereIn('permission_id', $permissionIds)
            ->whereIn('role_id', $employeeRoleIds)
            ->delete();
    }

    public function down(): void
    {
        $now = now();

        $permissionIds = DB::table('permissions')
            ->whereIn('key', ['amc.create', 'support.create'])
            ->pluck('id', 'key');

        $employeeRoleIds = DB::table('roles')
            ->whereIn('slug', ['employee'])
            ->pluck('id');

        foreach ($employeeRoleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
};
