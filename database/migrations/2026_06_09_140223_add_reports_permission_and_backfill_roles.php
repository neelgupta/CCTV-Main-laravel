<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permission = ['key' => 'reports.view', 'name' => 'View Reports Module'];

        DB::table('permissions')->updateOrInsert(
            ['key' => $permission['key']],
            [
                'name' => $permission['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('key', $permission['key'])
            ->value('id');

        $adminRoleIds = DB::table('roles')
            ->whereIn('slug', ['company_admin', 'companyadmin'])
            ->pluck('id');

        foreach ($adminRoleIds as $roleId) {
            DB::table('permission_role')->updateOrInsert(
                ['permission_id' => $permissionId, 'role_id' => $roleId],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'reports.view')
            ->value('id');

        if (! $permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};
