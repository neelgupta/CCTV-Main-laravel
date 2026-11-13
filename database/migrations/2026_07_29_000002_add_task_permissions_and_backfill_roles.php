<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['key' => 'tasks.view', 'name' => 'View Task Module'],
            ['key' => 'tasks.manage', 'name' => 'Manage Tasks'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [
                    'name' => $permission['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column($permissions, 'key'))
            ->pluck('id', 'key');

        $companyAdminRoleIds = DB::table('roles')
            ->whereNotNull('company_id')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        foreach ($companyAdminRoleIds as $roleId) {
            foreach (['tasks.view', 'tasks.manage'] as $key) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionIds[$key], 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $employeeRoleIds = DB::table('roles')
            ->whereNotNull('company_id')
            ->where('slug', 'employee')
            ->pluck('id');

        foreach ($employeeRoleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionIds['tasks.view'], 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $keys = ['tasks.view', 'tasks.manage'];

        $permissionIds = DB::table('permissions')
            ->whereIn('key', $keys)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
