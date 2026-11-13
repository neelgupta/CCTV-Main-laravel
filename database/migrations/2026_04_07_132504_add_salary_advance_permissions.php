<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['key' => 'teams.staff.salary.advance.view', 'name' => 'View Staff Salary Advance'],
            ['key' => 'teams.staff.salary.advance.manage', 'name' => 'Manage Staff Salary Advance'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $permission['key']],
                [
                    'name' => $permission['name'],
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column($permissions, 'key'))
            ->pluck('id', 'key');

        $adminRoles = DB::table('roles')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        foreach ($adminRoles as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionId, 'role_id' => $roleId],
                    ['updated_at' => $now, 'created_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $keys = [
            'teams.staff.salary.advance.view',
            'teams.staff.salary.advance.manage',
        ];

        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
