<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [
            ['key' => 'tickets.view', 'name' => 'View Tickets'],
            ['key' => 'tickets.create', 'name' => 'Create Tickets'],
            ['key' => 'tickets.reply', 'name' => 'Reply to Tickets'],
            ['key' => 'tickets.manage', 'name' => 'Manage Tickets'],
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

        $adminRoleIds = DB::table('roles')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            foreach (['tickets.view', 'tickets.create', 'tickets.reply'] as $key) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionIds[$key], 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        $employeeRoleIds = DB::table('roles')
            ->whereIn('slug', ['employee'])
            ->pluck('id');

        foreach ($employeeRoleIds as $roleId) {
            foreach (['tickets.view', 'tickets.create', 'tickets.reply'] as $key) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $permissionIds[$key], 'role_id' => $roleId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $keys = ['tickets.view', 'tickets.create', 'tickets.reply', 'tickets.manage'];
        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
};
