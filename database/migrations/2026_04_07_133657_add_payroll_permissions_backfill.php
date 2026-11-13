<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $keys = [
            ['key' => 'teams.staff.salary.view', 'name' => 'View Staff Salary'],
            ['key' => 'teams.staff.salary.manage', 'name' => 'Manage Staff Salary'],
        ];

        foreach ($keys as $perm) {
            DB::table('permissions')->updateOrInsert(
                ['key' => $perm['key']],
                ['name' => $perm['name'], 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('key', array_column($keys, 'key'))
            ->pluck('id', 'key');

        $roles = DB::table('roles')
            ->select('id', 'slug')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'slug' => strtolower($r->slug)]);

        foreach ($roles as $role) {
            $assign = [];
            if (in_array($role['slug'], ['company_admin', 'companyadmin'], true)) {
                $assign = [
                    $permissionIds['teams.staff.salary.view'] ?? null,
                    $permissionIds['teams.staff.salary.manage'] ?? null,
                ];
            } elseif (in_array($role['slug'], ['employee', 'manager'], true)) {
                $assign = [
                    $permissionIds['teams.staff.salary.view'] ?? null,
                    $permissionIds['teams.staff.salary.manage'] ?? null,
                ];
            }

            foreach (array_filter($assign) as $pid) {
                DB::table('permission_role')->updateOrInsert(
                    ['permission_id' => $pid, 'role_id' => $role['id']],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $keys = ['teams.staff.salary.view', 'teams.staff.salary.manage'];
        $permissionIds = DB::table('permissions')->whereIn('key', $keys)->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('permission_role')->whereIn('permission_id', $permissionIds)->delete();
        }
    }
};
