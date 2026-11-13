<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingExemptPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_login_on_199_plan_does_not_record_app_activity(): void
    {
        $company = $this->createCompanyWithTrackingExemptSubscription();
        $employee = $this->createEmployee($company, 'tracking-free@example.com', 'EMP-199-01', 'Secret@123');

        $response = $this->postJson(route('api.staff.login'), [
            'email' => $employee->email,
            'company_code' => $company->company_code,
            'password' => 'Secret@123',
            'device_id' => 'device-199-001',
            'platform' => 'Android',
            'app_version' => '1.0.0',
        ]);

        $response->assertOk();
        $this->assertDatabaseMissing('staff_app_activities', [
            'company_id' => $company->id,
            'staff_id' => $employee->staff->id,
            'device_id_hash' => hash('sha256', 'device-199-001'),
        ]);
    }

    public function test_route_tracking_on_199_plan_does_not_persist_points(): void
    {
        $company = $this->createCompanyWithTrackingExemptSubscription();
        $employee = $this->createEmployee($company, 'route-free@example.com', 'EMP-199-02', 'Secret@123');

        $loginResponse = $this->postJson(route('api.staff.login'), [
            'email' => $employee->email,
            'company_code' => $company->company_code,
            'password' => 'Secret@123',
            'device_id' => 'device-199-002',
            'platform' => 'Android',
            'app_version' => '1.0.0',
        ]);

        $token = (string) $loginResponse->json('access_token');

        $response = $this->withToken($token)->postJson(route('api.staff.route-points.store'), [
            'latitude' => 28.6139,
            'longitude' => 77.2090,
            'source' => 'mobile',
            'device_name' => 'android-device',
        ]);

        $response->assertOk();
        $response->assertJsonPath('message', 'Route tracking is disabled for this plan.');

        $this->assertDatabaseMissing('staff_route_points', [
            'company_id' => $company->id,
            'staff_id' => $employee->staff->id,
        ]);
    }

    private function createCompanyWithTrackingExemptSubscription(): Company
    {
        $company = Company::create([
            'name' => 'Tracking Free Company ' . now()->timestamp,
            'company_code' => 'CC-TRK' . strtoupper(substr((string) now()->timestamp, -4)),
            'email' => 'tracking-free-' . now()->timestamp . '@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Tracking Lite',
            'slug' => 'tracking-lite-' . now()->timestamp,
            'price' => 199,
            'monthly_price' => 199,
            'yearly_price' => 199,
            'billing_cycle' => 'monthly',
            'description' => 'Tracking exempt plan',
            'staff_limit' => 5,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
            'disable_staff_tracking' => true,
        ]);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-TRACK-001',
            'license_count' => 1,
            'license_keys' => ['LIC-TRACK-001'],
        ]);

        return $company->refresh();
    }

    private function createEmployee(Company $company, string $email, string $employeeId, string $password): User
    {
        $role = Role::firstOrCreate([
            'company_id' => $company->id,
            'slug' => 'employee',
        ], [
            'name' => 'Employee',
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'password' => $password,
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'company_subscription_id' => $company->latestSubscription->id,
            'employee_id' => $employeeId,
            'name' => $user->name,
            'contact_number' => '9876543210',
            'join_date' => now()->toDateString(),
            'role_id' => $role->id,
            'license_key' => 'LIC-TRACK-001',
        ]);

        return $user->refresh()->load('staff');
    }
}
