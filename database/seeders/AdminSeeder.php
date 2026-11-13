<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\ProjectStage;
use App\Models\Role;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::updateOrCreate(
            ['email' => 'admin@cctvworkshop.local'],
            [
                'name' => 'CCTV Workshop',
                'mobile_number' => '9876543210',
                'phone_number' => '9876543210',
                'address' => 'Main Office, CCTV Workshop, India',
                'city' => 'Kolkata',
                'state' => 'West Bengal',
                'business_type' => 'CCTV',
                'enable_quotation_generator' => true,
                'enable_boq' => true,
                'is_onboarded' => true,
                'onboarding_step' => 4,
                'is_active' => true,
            ]
        );

        $companyAdminRole = Role::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'company_admin'],
            ['name' => 'Company Admin']
        );

        Role::updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $companyAdminRole->permissions()->sync(Permission::query()->pluck('id')->all());

        $this->upsertUser([
            'email' => 'admin@cctvworkshop.local',
            'company_id' => $company->id,
            'name' => 'CCTV Workshop Admin',
            'password' => Hash::make('Pass@123'),
            'role' => 'company_admin',
            'role_id' => $companyAdminRole->id,
            'is_active' => true,
        ]);

        $leadStatuses = [
            ['name' => 'New Lead', 'color' => '#3498db', 'order' => 1],
            ['name' => 'Contacted', 'color' => '#f1c40f', 'order' => 2],
            ['name' => 'Site Survey', 'color' => '#e67e22', 'order' => 3],
            ['name' => 'Quote', 'color' => '#9b59b6', 'order' => 4],
            ['name' => 'Proposal Sent', 'color' => '#0ea5e9', 'order' => 5],
            ['name' => 'Won', 'color' => '#2ecc71', 'order' => 6],
            ['name' => 'Lost', 'color' => '#e74c3c', 'order' => 7],
        ];

        foreach ($leadStatuses as $status) {
            LeadStatus::updateOrCreate(
                ['company_id' => $company->id, 'name' => $status['name']],
                [
                    'color' => $status['color'],
                    'order' => $status['order'],
                    'is_default' => in_array(strtolower($status['name']), ['new lead', 'proposal sent', 'won'], true),
                ]
            );
        }

        LeadStatus::ensureWon($company->id);
        LeadStatus::ensureNewLead($company->id);
        LeadStatus::normalizeSystemStages($company->id);
        ProjectStage::ensureDefaults($company->id);
    }

    private function upsertUser(array $attributes): User
    {
        return User::updateOrCreate(['email' => $attributes['email']], $attributes);
    }
}
