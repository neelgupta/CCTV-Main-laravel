<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_log_in_with_employee_id_and_company_code(): void
    {
        $firstCompany = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
        ]);

        $secondCompany = Company::create([
            'name' => 'Beta Systems',
            'company_code' => 'CC-BETA01',
            'email' => 'beta@example.com',
        ]);

        $firstUser = $this->createEmployeeAccount($firstCompany, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123');
        $this->createEmployeeAccount($secondCompany, 'tech.beta@example.com', 'EMP-1001', 'Secret@123');

        $response = $this->postJson('/api/staff/login', [
            'employee_id' => 'EMP-1001',
            'company_code' => 'CC-ALPHA1',
            'password' => 'Secret@123',
            'device_name' => 'android-app',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $firstUser->id)
            ->assertJsonPath('user.company.id', $firstCompany->id)
            ->assertJsonPath('user.company.company_code', 'CC-ALPHA1')
            ->assertJsonPath('user.staff.employee_id', 'EMP-1001');
    }

    public function test_staff_login_with_employee_id_requires_matching_company_code(): void
    {
        $company = Company::create([
            'name' => 'Gamma Systems',
            'company_code' => 'CC-GAMMA1',
            'email' => 'gamma@example.com',
        ]);

        $this->createEmployeeAccount($company, 'tech.gamma@example.com', 'EMP-2001', 'Secret@123');

        $response = $this->postJson('/api/staff/login', [
            'employee_id' => 'EMP-2001',
            'company_code' => 'CC-WRONG01',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid user ID, employee ID, email, mobile number, company code, or password.')
            ->assertJsonPath('error_code', 'invalid_credentials')
            ->assertJsonPath('errors.employee_id.0', 'Invalid user ID, employee ID, email, mobile number, company code, or password.');
    }

    public function test_staff_can_log_in_with_mobile_number_and_company_code(): void
    {
        $company = Company::create([
            'name' => 'Delta Systems',
            'company_code' => 'CC-DELTA1',
            'email' => 'delta@example.com',
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.delta@example.com',
            'EMP-3001',
            'Secret@123',
            '9876543210'
        );

        $response = $this->postJson('/api/staff/login', [
            'mobile_number' => '9876543210',
            'company_code' => 'CC-DELTA1',
            'password' => 'Secret@123',
            'device_name' => 'android-app',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.staff.contact_number', '9876543210');
    }

    public function test_staff_can_log_in_with_mobile_number_having_leading_zero(): void
    {
        $company = Company::create([
            'name' => 'Hotel Systems',
            'company_code' => 'CC-HOTEL1',
            'email' => 'hotel@example.com',
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.hotel@example.com',
            'EMP-6001',
            'Secret@123',
            '9574880999'
        );

        $response = $this->postJson('/api/staff/login', [
            'mobile_number' => '09574880999',
            'company_code' => 'CC-HOTEL1',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_staff_can_log_in_with_mobile_alias_field(): void
    {
        $company = Company::create([
            'name' => 'Echo Systems',
            'company_code' => 'CC-ECHO01',
            'email' => 'echo@example.com',
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.echo@example.com',
            'EMP-4001',
            'Secret@123',
            '9123456789'
        );

        $response = $this->postJson('/api/staff/login', [
            'mobile' => '+91 9123456789',
            'company_code' => 'CC-ECHO01',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_staff_can_log_in_with_user_id_as_email(): void
    {
        $company = Company::create([
            'name' => 'India Systems',
            'company_code' => 'CC-INDIA1',
            'email' => 'india@example.com',
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'staff.india@example.com',
            'EMP-7001',
            'Secret@123',
            '9111222333'
        );

        $response = $this->postJson('/api/staff/login', [
            'user_id' => 'staff.india@example.com',
            'company_code' => 'CC-INDIA1',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', 'staff.india@example.com');
    }

    public function test_staff_can_log_in_with_user_id_as_mobile_number(): void
    {
        $company = Company::create([
            'name' => 'Juliet Systems',
            'company_code' => 'CC-JUL01',
            'email' => 'juliet@example.com',
        ]);

        $user = $this->createEmployeeAccount(
            $company,
            'tech.juliet@example.com',
            'EMP-8001',
            'Secret@123',
            '9988776655'
        );

        $response = $this->postJson('/api/staff/login', [
            'user_id' => '9988776655',
            'company_code' => 'CC-JUL01',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('user.id', $user->id);
    }

    public function test_staff_login_with_mobile_number_requires_matching_company_code(): void
    {
        $company = Company::create([
            'name' => 'Foxtrot Systems',
            'company_code' => 'CC-FOXT01',
            'email' => 'foxtrot@example.com',
        ]);

        $this->createEmployeeAccount(
            $company,
            'tech.foxtrot@example.com',
            'EMP-5001',
            'Secret@123',
            '9988776655'
        );

        $response = $this->postJson('/api/staff/login', [
            'mobile_number' => '9988776655',
            'company_code' => 'CC-WRONG01',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('error_code', 'invalid_credentials')
            ->assertJsonPath('errors.mobile_number.0', 'Invalid user ID, employee ID, email, mobile number, company code, or password.');
    }

    public function test_staff_login_requires_company_code(): void
    {
        $response = $this->postJson('/api/staff/login', [
            'mobile_number' => '9876543210',
            'password' => 'Secret@123',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Login validation failed.')
            ->assertJsonPath('error_code', 'validation_failed')
            ->assertJsonPath('errors.company_code.0', 'The company code field is required.');
    }

    public function test_staff_login_returns_proper_response_for_wrong_password(): void
    {
        $company = Company::create([
            'name' => 'Gamma Systems',
            'company_code' => 'CC-GAMMA1',
            'email' => 'gamma@example.com',
        ]);

        $this->createEmployeeAccount($company, 'tech.gamma@example.com', 'EMP-2001', 'Secret@123');

        $response = $this->postJson('/api/staff/login', [
            'employee_id' => 'EMP-2001',
            'company_code' => 'CC-GAMMA1',
            'password' => 'WrongPassword',
        ]);

        $response
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid user ID, employee ID, email, mobile number, company code, or password.')
            ->assertJsonPath('error_code', 'invalid_credentials')
            ->assertJsonPath('errors.employee_id.0', 'Invalid user ID, employee ID, email, mobile number, company code, or password.');
    }

    private function createEmployeeAccount(
        Company $company,
        string $email,
        string $employeeId,
        string $password,
        ?string $contactNumber = null
    ): User {
        $employeeRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $user = User::factory()->create([
            'company_id' => $company->id,
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
            'contact_number' => $contactNumber,
            'join_date' => now()->toDateString(),
        ]);

        return $user;
    }
}
