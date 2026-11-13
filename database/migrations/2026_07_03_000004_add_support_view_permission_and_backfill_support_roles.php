<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'support.view'],
            [
                'name' => 'View Support Tickets',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionIds = DB::table('permissions')
            ->whereIn('key', [
                'support.view',
                'support.create',
                'support.edit',
                'support.delete',
                'tickets.view',
                'tickets.create',
                'tickets.reply',
                'tickets.manage',
            ])
            ->pluck('id', 'key');

        $rolePermissionMap = [
            'tickets.view' => ['support.view'],
            'tickets.create' => ['support.create'],
            'tickets.reply' => ['support.edit'],
            'tickets.manage' => ['support.edit', 'support.delete'],
        ];

        $roleIds = DB::table('roles')
            ->whereNotNull('company_id')
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            $legacyKeys = DB::table('permission_role')
                ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
                ->where('permission_role.role_id', $roleId)
                ->whereIn('permissions.key', array_keys($rolePermissionMap))
                ->pluck('permissions.key')
                ->all();

            foreach ($legacyKeys as $legacyKey) {
                foreach ($rolePermissionMap[$legacyKey] as $canonicalKey) {
                    if (!isset($permissionIds[$canonicalKey])) {
                        continue;
                    }

                    DB::table('permission_role')->updateOrInsert(
                        [
                            'permission_id' => $permissionIds[$canonicalKey],
                            'role_id' => $roleId,
                        ],
                        [
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]
                    );
                }
            }
        }
    }

    public function down(): void
    {
        $permission = DB::table('permissions')->where('key', 'support.view')->first();

        if ($permission) {
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
