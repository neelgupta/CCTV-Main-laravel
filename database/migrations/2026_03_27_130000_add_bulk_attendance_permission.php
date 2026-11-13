<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['key' => 'teams.staff.attendance.bulk.manage'],
            [
                'name' => 'Manage Bulk Staff Attendance',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $permissionId = DB::table('permissions')
            ->where('key', 'teams.staff.attendance.bulk.manage')
            ->value('id');

        if ($permissionId) {
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
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')
            ->where('key', 'teams.staff.attendance.bulk.manage')
            ->value('id');

        if ($permissionId) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->where('id', $permissionId)->delete();
        }
    }
};
