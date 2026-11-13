<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffAppActivity;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminCompanyAppUsageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_company_app_usage_index_and_details(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $company = $this->createCompanyWithAppActivity();

        $indexResponse = $this->actingAs($superAdmin)->get(route('super-admin.company-app-usage.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('Company App Usage Report');
        $indexResponse->assertSee($company->name);

        $showResponse = $this->actingAs($superAdmin)->get(route('super-admin.company-app-usage.show', $company));
        $showResponse->assertOk();
        $showResponse->assertSee($company->name);
        $showResponse->assertSee('Staff Usage');
        $showResponse->assertSee('Android');
        $showResponse->assertSee('4.5.6');
        $showResponse->assertSee('Using App');
    }

    public function test_staff_mobile_login_records_app_usage_activity(): void
    {
        $company = $this->createCompanyWithSubscription();
        $employeeRole = Role::firstOrCreate([
            'company_id' => $company->id,
            'slug' => 'employee',
        ], [
            'name' => 'Employee',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $employeeRole->id,
            'role' => 'employee',
            'name' => 'Mobile Staff',
            'email' => 'mobile.staff@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staff = Staff::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'company_subscription_id' => $company->latestSubscription->id,
            'name' => 'Mobile Staff',
            'employee_id' => 'EMP-MOBILE-01',
            'contact_number' => '9998887777',
            'role_id' => $employeeRole->id,
        ]);

        $response = $this->postJson(route('api.staff.login'), [
            'company_code' => $company->company_code,
            'email' => $user->email,
            'password' => 'secret123',
            'device_id' => 'device-12345',
            'platform' => 'Android',
            'app_version' => '4.5.6',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Staff login successful.');

        $this->assertDatabaseHas('staff_app_activities', [
            'company_id' => $company->id,
            'staff_id' => $staff->id,
            'device_id_hash' => hash('sha256', 'device-12345'),
            'platform' => 'Android',
            'app_version' => '4.5.6',
            'activity_type' => 'login',
        ]);
    }

    public function test_company_admin_cannot_view_company_app_usage_pages(): void
    {
        $company = $this->createCompanyWithSubscription();
        $companyAdminRole = Role::firstOrCreate([
            'company_id' => $company->id,
            'slug' => 'company_admin',
        ], [
            'name' => 'Company Admin',
        ]);

        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $companyAdminRole->id,
            'role' => 'company_admin',
            'name' => 'Company Admin',
            'email' => 'company.admin@example.com',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($companyAdmin)->get(route('super-admin.company-app-usage.index'))->assertForbidden();
        $this->actingAs($companyAdmin)->get(route('super-admin.company-app-usage.show', $company))->assertForbidden();
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-usage@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createCompanyWithAppActivity(): Company
    {
        $company = $this->createCompanyWithSubscription();
        $employeeRole = Role::firstOrCreate([
            'company_id' => $company->id,
            'slug' => 'employee',
        ], [
            'name' => 'Employee',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $employeeRole->id,
            'role' => 'employee',
            'name' => 'Active Staff',
            'email' => 'active.staff@example.com',
            'password' => 'secret123',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staff = Staff::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'company_subscription_id' => $company->latestSubscription->id,
            'name' => 'Active Staff',
            'employee_id' => 'EMP-ACTIVE-01',
            'contact_number' => '9876543210',
            'role_id' => $employeeRole->id,
        ]);

        StaffAppActivity::query()->create([
            'company_id' => $company->id,
            'staff_id' => $staff->id,
            'device_id' => 'device-android-001',
            'device_id_hash' => hash('sha256', 'device-android-001'),
            'platform' => 'Android',
            'app_version' => '4.5.6',
            'activity_type' => 'login',
            'last_active_at' => now(),
        ]);

        return $company;
    }

    private function createCompanyWithSubscription(): Company
    {
        $company = Company::create([
            'name' => 'Usage Test Company ' . Str::upper(Str::random(4)),
            'company_code' => 'UC-' . Str::upper(Str::random(6)),
            'email' => 'usage-' . Str::lower(Str::random(6)) . '@example.com',
            'address' => '10 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . Str::lower(Str::random(4)),
            'price' => 999,
            'monthly_price' => 999,
            'yearly_price' => 9999,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 10,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-USAGE-001',
            'license_count' => 1,
            'license_keys' => ['LIC-USAGE-001'],
        ]);

        return $company->refresh();
    }
}
