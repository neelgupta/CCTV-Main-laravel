<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ExpiredStaffSubscriptionAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_user_cannot_log_in_when_assigned_subscription_is_expired(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        $company = $this->createCompany();
        $role = $this->createRole($company, 'Technician', 'employee');
        $plan = $this->createPlan(['staff_limit' => 1]);

        $expiredSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-08',
            'status' => 'expired',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-EXPIRED-001',
            'license_count' => 1,
            'license_keys' => ['LIC-EXPIRED-001'],
        ]);

        $staffUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Expired Staff',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role_id' => $role->id,
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'user_id' => $staffUser->id,
            'company_id' => $company->id,
            'company_subscription_id' => $expiredSubscription->id,
            'employee_id' => 'EMP-EXPIRED-001',
            'name' => $staffUser->name,
            'contact_number' => '9876543210',
            'join_date' => '2026-05-01',
            'role_id' => $role->id,
            'license_key' => 'LIC-EXPIRED-001',
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'staff@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors([
            'email' => 'Your assigned subscription plan (Growth) is expired. Please contact your company admin.',
        ]);
        $this->assertGuest();
    }

    public function test_company_admin_can_log_in_even_when_assigned_subscription_is_expired(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        $company = $this->createCompany();
        $adminRole = $this->createRole($company, 'Company Admin', 'company_admin');
        $plan = $this->createPlan(['staff_limit' => 1]);

        $expiredSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-08',
            'status' => 'expired',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-ADMIN-001',
            'license_count' => 1,
            'license_keys' => ['LIC-ADMIN-001'],
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'user_id' => $admin->id,
            'company_id' => $company->id,
            'company_subscription_id' => $expiredSubscription->id,
            'employee_id' => 'EMP-ADMIN-001',
            'name' => $admin->name,
            'contact_number' => '9876543211',
            'join_date' => '2026-05-01',
            'role_id' => $adminRole->id,
            'license_key' => 'LIC-ADMIN-001',
        ]);

        $response = $this->post('/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_company_admin_can_reactivate_expired_staff_on_active_subscription_with_available_seat(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        $company = $this->createCompany();
        $adminRole = $this->createRole($company, 'Company Admin', 'company_admin');
        $employeeRole = $this->createRole($company, 'Technician', 'employee');
        $plan = $this->createPlan(['staff_limit' => 2]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role_id' => $adminRole->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $expiredSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-06-08',
            'status' => 'expired',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-OLD-001',
            'license_count' => 1,
            'license_keys' => ['LIC-OLD-001'],
        ]);

        $activeSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-06-09',
            'ends_at' => '2026-07-08',
            'status' => 'active',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-NEW-001',
            'license_count' => 2,
            'license_keys' => ['LIC-NEW-001', 'LIC-NEW-002'],
        ]);

        $expiredStaffUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Expired Staff',
            'email' => 'staff@example.com',
            'password' => 'password',
            'role_id' => $employeeRole->id,
            'role' => 'employee',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $staff = Staff::create([
            'user_id' => $expiredStaffUser->id,
            'company_id' => $company->id,
            'company_subscription_id' => $expiredSubscription->id,
            'employee_id' => 'EMP-STAFF-001',
            'name' => $expiredStaffUser->name,
            'contact_number' => '9876543212',
            'join_date' => '2026-05-01',
            'role_id' => $employeeRole->id,
            'license_key' => 'LIC-OLD-001',
        ]);

        $response = $this->actingAs($admin)->patch(route('teams.staff.reactivate-subscription', $staff), [
            'company_subscription_id' => $activeSubscription->id,
            'reactivate_staff_id' => $staff->getRouteKey(),
            'reactivate_staff_name' => $staff->name,
            'reactivate_current_plan' => 'Growth',
        ]);

        $response->assertRedirect(route('teams.staff.index'));
        $response->assertSessionHas('success', 'Staff member activated on the selected subscription plan.');

        $staff->refresh();
        $expiredStaffUser->refresh();

        $this->assertSame($activeSubscription->id, $staff->company_subscription_id);
        $this->assertContains($staff->license_key, ['LIC-NEW-001', 'LIC-NEW-002']);
        $this->assertTrue($expiredStaffUser->is_active);
    }

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Acme CCTV',
            'email' => 'acme@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ]);
    }

    private function createRole(Company $company, string $name, string $slug): Role
    {
        return Role::create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    private function createPlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 0,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 2,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ], $overrides));
    }
}
