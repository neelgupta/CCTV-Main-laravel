<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\Permission;
use App\Models\LeadCategory;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    /**
     * Seed demo client, lead category, and staff for every company.
     */
    public function run(): void
    {
        Company::query()
            ->orderBy('id')
            ->get()
            ->each(fn (Company $company) => $this->seedCompany($company));
    }

    /**
     * Seed demo records for a single company.
     */
    public function seedCompany(Company $company): void
    {
        $employeeRole = Role::query()->updateOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        if ($employeeRole->permissions()->count() === 0) {
            $employeeRole->permissions()->sync(
                Permission::query()->whereIn('key', $this->employeePermissionKeys())->pluck('id')->all()
            );
        }

        LeadCategory::updateOrCreate(
            [
                'company_id' => $company->id,
                'name' => 'Demo Category',
            ],
            []
        );

        Client::updateOrCreate(
            [
                'company_id' => $company->id,
                'email' => 'demo.client@cctvworkshop.local',
            ],
            [
                'name' => 'Demo Client',
                'mobile' => '9876500000',
                'phone' => '9876500000',
                'address' => 'Demo Client Address, Kolkata, West Bengal',
                'status' => 'active',
            ]
        );

        foreach (['Day', 'Inchs', 'Mtr', 'Nos', 'Pcs', 'Sets'] as $unitName) {
            CompanyUnit::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $unitName,
                ],
                []
            );
        }

        foreach ([0, 5, 9, 12, 18, 28] as $gstRate) {
            CompanyGstRate::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'rate' => $gstRate,
                ],
                []
            );
        }

        foreach ($this->expenseCategories() as $category) {
            \App\Models\ExpenseCategory::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'name' => $category['name'],
                ],
                [
                    'description' => $category['description'],
                    'status' => 'active',
                ]
            );
        }

        $staffUser = User::updateOrCreate(
            ['email' => $this->demoStaffEmail($company)],
            [
                'company_id' => $company->id,
                'name' => 'Demo Staff',
                'password' => 'Demo@123',
                'role' => 'employee',
                'role_id' => $employeeRole->id,
                'is_active' => true,
            ]
        );

        Staff::updateOrCreate(
            ['employee_id' => $this->demoEmployeeId($company)],
            [
                'user_id' => $staffUser->id,
                'name' => 'Demo Staff',
                'role_id' => $employeeRole->id,
                'department' => 'Operations',
                'join_date' => now()->toDateString(),
                'contact_number' => '9876500001',
                'company_id' => $company->id,
                'profile_photo' => null,
            ]
        );
    }

    private function demoStaffEmail(Company $company): string
    {
        if ((int) $company->id === 1) {
            return 'demo.staff@cctvworkshop.local';
        }

        return sprintf('demo.staff+%d@cctvworkshop.local', $company->id);
    }

    private function demoEmployeeId(Company $company): string
    {
        if ((int) $company->id === 1) {
            return 'DEMO-EMP-001';
        }

        return sprintf('DEMO-EMP-%d', $company->id);
    }

    /**
     * Keep starter employee permissions aligned with registration flow.
     *
     * @return array<int, string>
     */
    private function employeePermissionKeys(): array
    {
        return [
            'dashboard.view',
            'leads.view', 'leads.create', 'leads.edit', 'leads.status.update', 'leads.comment',
            'leads.quotation.manage', 'leads.survey.upload', 'leads.documents.upload', 'leads.summary.download',
            'projects.view', 'projects.create', 'projects.edit', 'projects.stage.update', 'projects.assets.manage', 'projects.comment',
            'support.view', 'support.create', 'support.edit',
            'service.view',
            'subscription.view', 'integrations.view', 'clients.view', 'amc.view', 'gallery.view',
        ];
    }

    private function expenseCategories(): array
    {
        return [
            ['name' => 'Salary', 'description' => 'Employee salary and payroll expenses.'],
            ['name' => 'Fuel', 'description' => 'Vehicle and travel fuel expenses.'],
            ['name' => 'Office Rent', 'description' => 'Office rental payments.'],
            ['name' => 'Electricity', 'description' => 'Utility and power expenses.'],
            ['name' => 'Travel', 'description' => 'Travel and local conveyance.'],
            ['name' => 'Internet', 'description' => 'Connectivity and internet bills.'],
            ['name' => 'Marketing', 'description' => 'Promotional and marketing expenses.'],
            ['name' => 'Office Expense', 'description' => 'General office running costs.'],
            ['name' => 'Repair', 'description' => 'Repair and maintenance expenses.'],
            ['name' => 'Miscellaneous', 'description' => 'Miscellaneous expenses.'],
        ];
    }
}
