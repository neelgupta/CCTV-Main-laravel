<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_admin_can_view_leads_report_page(): void
    {
        $company = Company::create([
            'name' => 'Northstar Systems',
            'company_code' => 'NS-1001',
            'email' => 'northstar@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Northstar Admin',
            'email' => 'admin@northstar.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.leads'));

        $response->assertOk();
        $response->assertSee('Leads Report');
        $response->assertSee('Lead Status & Conversion');
        $response->assertSee('Source Performance');
        $response->assertSee('Monthly Lead Trends');
        $response->assertSee('Export');
        $response->assertSee('New Leads');
        $response->assertSee('Qualified Leads');
        $response->assertDontSee('>On Track</option>', false);
        $response->assertDontSee('>Paid</option>', false);
    }

    public function test_project_report_shows_only_project_status_filters(): void
    {
        $company = Company::create([
            'name' => 'Northstar Systems',
            'company_code' => 'NS-1004',
            'email' => 'northstar4@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Northstar Admin',
            'email' => 'admin4@northstar.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.projects'));

        $response->assertOk();
        $response->assertSee('On Track');
        $response->assertSee('At Risk');
        $response->assertDontSee('>New Leads</option>', false);
        $response->assertDontSee('>Paid</option>', false);
    }

    public function test_company_admin_can_export_leads_report(): void
    {
        $company = Company::create([
            'name' => 'Northstar Systems',
            'company_code' => 'NS-1003',
            'email' => 'northstar3@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Northstar Admin',
            'email' => 'admin3@northstar.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.leads.export'));

        $response->assertOk();
        $response->assertDownload();
    }

    public function test_reports_index_redirects_to_leads_report(): void
    {
        $company = Company::create([
            'name' => 'Northstar Systems',
            'company_code' => 'NS-1002',
            'email' => 'northstar2@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Northstar Admin',
            'email' => 'admin2@northstar.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.index'));

        $response->assertRedirect(route('reports.leads'));
    }

    public function test_company_admin_can_view_users_report_page(): void
    {
        $company = Company::create([
            'name' => 'Orbit Secure',
            'company_code' => 'OS-2002',
            'email' => 'orbit2@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orbit Admin',
            'email' => 'admin@orbit2.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.users'));

        $response->assertOk();
        $response->assertSee('Users Report');
        $response->assertSee('Company Users');
        $response->assertSee('Active Users');
        $response->assertSee('Deactivated Users');
        $response->assertDontSee('id="report-status"', false);
    }

    public function test_users_report_status_toggle_filters_active_users(): void
    {
        $company = Company::create([
            'name' => 'Orbit Secure',
            'company_code' => 'OS-2003',
            'email' => 'orbit3@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orbit Admin',
            'email' => 'admin@orbit3.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Inactive User',
            'email' => 'inactive@orbit3.example.com',
            'role' => 'employee',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('reports.users', ['status' => 'deactivated']));

        $response->assertOk();
        $response->assertSee('Inactive User');
        $response->assertDontSee($admin->email);
    }

    public function test_employee_without_permission_cannot_view_reports_page(): void
    {
        $company = Company::create([
            'name' => 'Orbit Secure',
            'company_code' => 'OS-2001',
            'email' => 'orbit@example.com',
            'is_active' => true,
        ]);

        $employee = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Orbit Employee',
            'email' => 'employee@orbit.example.com',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($employee)->get(route('reports.leads'));

        $response->assertForbidden();
    }
};
