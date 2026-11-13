<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::updateOrCreate(
            ['company_id' => null, 'slug' => 'super_admin'],
            ['name' => 'Super Admin']
        );

        $allAccessId = Permission::query()->where('key', 'all.access')->value('id');
        if ($allAccessId) {
            $role->permissions()->syncWithoutDetaching([$allAccessId]);
        }

        $this->upsertUser([
            'email' => 'superadmin@cctvworkshop.local',
            'company_id' => null,
            'name' => 'Platform Super Admin',
            'password' => Hash::make('Pass@123'),
            'email_verified_at' => now(),
            'role' => 'super_admin',
            'role_id' => $role->id,
            'is_active' => true,
        ]);
    }

    private function upsertUser(array $attributes): User
    {
        return User::updateOrCreate(['email' => $attributes['email']], $attributes);
    }
}
