<?php

namespace Tests\Feature\Api;

use App\Models\Amc;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_profile_returns_profile_and_dashboard_statistics(): void
    {
        Storage::fake('public');

        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.alpha@example.com',
            'EMP-1001',
            'Secret@123',
            'John Doe'
        );

        $staff = $user->staff;
        $staff->forceFill([
            'contact_number' => '9876543210',
            'address' => '12 Tech Park',
            'city' => 'Indore',
            'state' => 'Madhya Pradesh',
            'country' => 'India',
            'zipcode' => '452001',
            'profile_photo' => 'company_' . $company->id . '/staff_profiles/avatar.jpg',
        ])->save();

        Storage::disk('public')->put($staff->profile_photo, 'avatar');

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
            'ticket_number' => 'TKT-001',
            'type' => 'support',
            'subject' => 'Today open ticket',
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => now()->toDateString(),
            'repeat_status' => 1,
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
            'ticket_number' => 'TKT-002',
            'type' => 'support',
            'subject' => 'In progress ticket',
            'priority' => 'medium',
            'status' => 'in_progress',
            'visit_date' => now()->addDay()->toDateString(),
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
            'ticket_number' => 'TKT-003',
            'type' => 'support',
            'subject' => 'Closed ticket',
            'priority' => 'medium',
            'status' => 'closed',
            'visit_date' => now()->subDay()->toDateString(),
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
            'ticket_number' => 'TKT-004',
            'type' => 'support',
            'subject' => 'Resolved ticket',
            'priority' => 'medium',
            'status' => 'resolved',
            'visit_date' => now()->subDays(2)->toDateString(),
        ]);

        Amc::create([
            'company_id' => $company->id,
            'assigned_staff_id' => $staff->id,
            'contract_no' => 'AMC-001',
            'customer_name' => 'Active AMC',
            'project_name' => 'Active Project',
            'start_date' => now()->subDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'payment_status' => 'pending',
            'total_visits' => 6,
            'scheduled_visits' => 3,
            'remaining_visits' => 3,
        ]);

        Amc::create([
            'company_id' => $company->id,
            'assigned_staff_id' => $staff->id,
            'contract_no' => 'AMC-002',
            'customer_name' => 'Completed AMC',
            'project_name' => 'Completed Project',
            'start_date' => now()->subDays(30)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'payment_status' => 'paid',
            'total_visits' => 4,
            'scheduled_visits' => 4,
            'remaining_visits' => 0,
        ]);

        Amc::create([
            'company_id' => $company->id,
            'assigned_staff_id' => $staff->id,
            'contract_no' => 'AMC-003',
            'customer_name' => 'Upcoming AMC',
            'project_name' => 'Upcoming Project',
            'start_date' => now()->addDays(3)->toDateString(),
            'end_date' => now()->addDays(30)->toDateString(),
            'payment_status' => 'pending',
            'total_visits' => 2,
            'scheduled_visits' => 0,
            'remaining_visits' => 2,
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/profile');

        $response
            ->assertOk()
            ->assertJsonPath('profile.id', $staff->id)
            ->assertJsonPath('profile.employee_code', 'EMP-1001')
            ->assertJsonPath('profile.first_name', 'John')
            ->assertJsonPath('profile.last_name', 'Doe')
            ->assertJsonPath('profile.full_name', 'John Doe')
            ->assertJsonPath('profile.email', 'tech.alpha@example.com')
            ->assertJsonPath('profile.phone', '9876543210')
            ->assertJsonPath('profile.designation', 'Employee')
            ->assertJsonPath('profile.department', null)
            ->assertJsonPath('profile.role', 'employee')
            ->assertJsonPath('profile.company.id', $company->id)
            ->assertJsonPath('profile.company.name', 'Alpha Systems')
            ->assertJsonPath('profile.address.city', 'Indore')
            ->assertJsonPath('profile.status', 'active')
            ->assertJsonPath('dashboard_statistics.today_tickets', 1)
            ->assertJsonPath('dashboard_statistics.open_tickets', 1)
            ->assertJsonPath('dashboard_statistics.in_progress_tickets', 1)
            ->assertJsonPath('dashboard_statistics.closed_tickets', 2)
            ->assertJsonPath('dashboard_statistics.repeat_tickets', 1)
            ->assertJsonPath('dashboard_statistics.pending_amc', 1)
            ->assertJsonPath('dashboard_statistics.completed_amc', 1)
            ->assertJsonPath('dashboard_statistics.upcoming_amc', 1)
            ->assertJsonPath('dashboard_statistics.total_assigned_tickets', 4)
            ->assertJsonPath('dashboard_statistics.total_assigned_amc', 3);

        $this->assertNotNull($response->json('profile.profile_image'));
    }

    public function test_staff_can_update_own_profile_and_replace_profile_image(): void
    {
        Storage::fake('public');

        $company = Company::create([
            'name' => 'Beta Systems',
            'company_code' => 'CC-BETA01',
            'email' => 'beta@example.com',
            'is_active' => true,
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.beta@example.com',
            'EMP-2001',
            'Secret@123',
            'Sarah Stone'
        );

        $staff = $user->staff;
        $staff->forceFill([
            'contact_number' => '9123456789',
            'address' => 'Old Address',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'country' => 'India',
            'zipcode' => '411001',
            'profile_photo' => 'company_' . $company->id . '/staff_profiles/old-avatar.jpg',
        ])->save();

        Storage::disk('public')->put($staff->profile_photo, 'old-avatar');

        $token = $this->loginAndGetToken('EMP-2001', 'CC-BETA01', 'Secret@123');
        $newImage = UploadedFile::fake()->image('new-avatar.jpg', 400, 400);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->post('/api/staff/profile', [
                'first_name' => 'Sarah',
                'last_name' => 'Stone',
                'phone' => '9988776655',
                'address' => 'New Address',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'zipcode' => '400001',
                'profile_image' => $newImage,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Profile updated successfully.')
            ->assertJsonPath('profile.full_name', 'Sarah Stone')
            ->assertJsonPath('profile.phone', '9988776655')
            ->assertJsonPath('profile.address.address', 'New Address')
            ->assertJsonPath('profile.address.city', 'Mumbai');

        $staff->refresh();

        $this->assertSame('Sarah Stone', $staff->name);
        $this->assertSame('9988776655', $staff->contact_number);
        $this->assertSame('New Address', $staff->address);
        $this->assertNotNull($staff->profile_photo);
        $this->assertTrue(Storage::disk('public')->exists($staff->profile_photo));
        $this->assertFalse(Storage::disk('public')->exists('company_' . $company->id . '/staff_profiles/old-avatar.jpg'));
    }

    public function test_staff_can_change_password_and_revoke_existing_tokens(): void
    {
        $company = Company::create([
            'name' => 'Gamma Systems',
            'company_code' => 'CC-GAMMA1',
            'email' => 'gamma@example.com',
            'is_active' => true,
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.gamma@example.com',
            'EMP-3001',
            'Secret@123',
            'Mike Ross'
        );

        $token = $this->loginAndGetToken('EMP-3001', 'CC-GAMMA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/staff/change-password', [
                'current_password' => 'Secret@123',
                'new_password' => 'NewSecret@123',
                'confirm_password' => 'NewSecret@123',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Password changed successfully.');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecret@123', $user->password));

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/profile')
            ->assertStatus(401);
    }

    public function test_staff_profile_requires_authentication(): void
    {
        $this->getJson('/api/staff/profile')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_staff_profile_rejects_invalid_token(): void
    {
        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->getJson('/api/staff/profile')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_staff_profile_update_returns_validation_errors(): void
    {
        $company = Company::create([
            'name' => 'Delta Systems',
            'company_code' => 'CC-DELTA1',
            'email' => 'delta@example.com',
            'is_active' => true,
        ]);

        $this->createEmployeeAccount(
            $company,
            'tech.delta@example.com',
            'EMP-4001',
            'Secret@123',
            'Alex Morgan'
        );

        $token = $this->loginAndGetToken('EMP-4001', 'CC-DELTA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/staff/profile', [
                'name' => '',
                
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.name.0', 'Name is required.');
    }

    public function test_staff_change_password_rejects_wrong_current_password(): void
    {
        $company = Company::create([
            'name' => 'Epsilon Systems',
            'company_code' => 'CC-EPS01',
            'email' => 'epsilon@example.com',
            'is_active' => true,
        ]);

        $this->createEmployeeAccount(
            $company,
            'tech.epsilon@example.com',
            'EMP-5001',
            'Secret@123',
            'Priya Shah'
        );

        $token = $this->loginAndGetToken('EMP-5001', 'CC-EPS01', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/staff/change-password', [
                'current_password' => 'Wrong@123',
                'new_password' => 'NewSecret@123',
                'confirm_password' => 'NewSecret@123',
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'incorrect_current_password')
            ->assertJsonPath('errors.current_password.0', 'Current password is incorrect.');
    }

    private function loginAndGetToken(string $employeeId, string $companyCode, string $password): string
    {
        return (string) $this->postJson('/api/staff/login', [
            'employee_id' => $employeeId,
            'company_code' => $companyCode,
            'password' => $password,
            'device_name' => 'android-app',
        ])->assertOk()->json('access_token');
    }

    private function createEmployeeAccount(
        Company $company,
        string $email,
        string $employeeId,
        string $password,
        string $name
    ): User {
        $employeeRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => 'employee',
            'role_id' => $employeeRole->id,
            'is_active' => true,
        ]);

        Staff::create([
            'user_id' => $user->id,
            'employee_id' => $employeeId,
            'name' => $user->name,
            'role_id' => $employeeRole->id,
            'company_id' => $company->id,
            'join_date' => now()->toDateString(),
        ]);

        return $user;
    }
}
