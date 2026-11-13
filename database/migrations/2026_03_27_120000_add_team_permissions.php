<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissions = [
            ['key' => 'teams.staff.view', 'name' => 'View Team Staff'],
            ['key' => 'teams.staff.create', 'name' => 'Create Team Staff'],
            ['key' => 'teams.staff.edit', 'name' => 'Edit Team Staff'],
            ['key' => 'teams.staff.delete', 'name' => 'Delete Team Staff'],
            ['key' => 'teams.staff.documents.manage', 'name' => 'Manage Staff Documents'],
            ['key' => 'teams.staff.salary.view', 'name' => 'View Staff Salary'],
            ['key' => 'teams.staff.salary.manage', 'name' => 'Manage Staff Salary'],
            ['key' => 'teams.staff.bank.view', 'name' => 'View Staff Bank Details'],
            ['key' => 'teams.staff.bank.manage', 'name' => 'Manage Staff Bank Details'],
            ['key' => 'teams.staff.attendance.view', 'name' => 'View Staff Attendance'],
            ['key' => 'teams.staff.attendance.manage', 'name' => 'Manage Staff Attendance'],
            ['key' => 'teams.staff.attendance.bulk.manage', 'name' => 'Manage Bulk Staff Attendance'],
            ['key' => 'teams.staff.self.manage', 'name' => 'Manage Own Staff Details'],
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
            'teams.staff.view',
            'teams.staff.create',
            'teams.staff.edit',
            'teams.staff.delete',
            'teams.staff.documents.manage',
            'teams.staff.salary.view',
            'teams.staff.salary.manage',
            'teams.staff.bank.view',
            'teams.staff.bank.manage',
            'teams.staff.attendance.view',
            'teams.staff.attendance.manage',
            'teams.staff.attendance.bulk.manage',
            'teams.staff.self.manage',
        ];

        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
