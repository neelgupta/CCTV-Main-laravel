<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $permissionKey = 'settings.quotation_form.manage';

        $permissionId = DB::table('permissions')->where('key', $permissionKey)->value('id');
        if (!$permissionId) {
            $permissionId = DB::table('permissions')->insertGetId([
                'key' => $permissionKey,
                'name' => 'Manage Quotation Form',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $roles = DB::table('roles')
            ->whereIn('slug', ['company_admin'])
            ->select(['id'])
            ->get();

        foreach ($roles as $role) {
            $exists = DB::table('permission_role')
                ->where('permission_id', $permissionId)
                ->where('role_id', $role->id)
                ->exists();

            if (!$exists) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permissionId,
                    'role_id' => $role->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionKey = 'settings.quotation_form.manage';

        $permissionId = DB::table('permissions')->where('key', $permissionKey)->value('id');
        if (!$permissionId) {
            return;
        }

        DB::table('permission_role')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();
    }
};

