<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionIds = DB::table('permissions')->pluck('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(
                    [
                        'permission_id' => $permissionId,
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

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->pluck('id');
        $roleIds = DB::table('roles')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        if ($permissionIds->isNotEmpty() && $roleIds->isNotEmpty()) {
            DB::table('permission_role')
                ->whereIn('permission_id', $permissionIds)
                ->whereIn('role_id', $roleIds)
                ->delete();
        }
    }
};
