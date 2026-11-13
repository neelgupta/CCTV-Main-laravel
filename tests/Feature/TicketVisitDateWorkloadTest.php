<?php

namespace Tests\Feature;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Company;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketVisitDateWorkloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_date_workloads_are_sorted_by_ascending_load_and_include_active_jobs(): void
    {
        $company = Company::create([
            'name' => 'Workload Company',
            'company_code' => 'CC-WORK01',
            'email' => 'workload@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Z Admin',
            'email' => 'admin@workload.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $alphaUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Alpha Staff',
            'email' => 'alpha@workload.example.com',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $betaUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Beta Staff',
            'email' => 'beta@workload.example.com',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'company_id' => $company->id,
            'user_id' => $alphaUser->id,
            'name' => 'Alpha Staff',
            'employee_id' => 'EMP-ALPHA',
            'contact_number' => '9999999999',
        ]);

        Staff::create([
            'company_id' => $company->id,
            'user_id' => $betaUser->id,
            'name' => 'Beta Staff',
            'employee_id' => 'EMP-BETA',
            'contact_number' => '8888888888',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $alphaUser->id,
            'ticket_number' => 'TKT-WORK-001',
            'customer_name' => 'Alpha Customer',
            'customer_contact_number' => '7000000001',
            'type' => 'support',
            'subject' => 'Alpha support visit',
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => '2026-07-03',
            'description' => 'Alpha ticket counted for the selected visit date.',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $betaUser->id,
            'ticket_number' => 'TKT-WORK-002',
            'customer_name' => 'Beta Customer',
            'customer_contact_number' => '7000000002',
            'type' => 'support',
            'subject' => 'Beta support visit',
            'priority' => 'medium',
            'status' => 'in_progress',
            'visit_date' => '2026-07-03',
            'description' => 'Beta ticket counted for the selected visit date.',
        ]);

        $amc = Amc::create([
            'company_id' => $company->id,
            'assigned_staff_id' => $alphaUser->id,
            'contract_no' => 'AMC-2026-WORK-001',
            'customer_name' => 'AMC Customer',
            'project_name' => 'AMC Project',
            'mobile_number' => '7777777777',
            'email' => 'amc@example.com',
            'address' => 'Test Address',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'monthly',
            'total_visits' => 12,
            'total_cameras' => 0,
            'amc_amount' => 0,
            'discount' => 0,
            'tax_percent' => 0,
            'total_amount' => 0,
            'payment_status' => 'pending',
        ]);

        AmcVisit::create([
            'amc_id' => $amc->id,
            'visit_no' => 1,
            'visit_date' => '2026-07-03',
            'status' => 'pending',
            'technician' => 'Alpha Staff',
        ]);

        $response = $this->withoutMiddleware()->actingAs($admin)->getJson('/tickets/visit-date-workloads?visit_date=2026-07-03');

        $response->assertOk();
        $response->assertJsonPath('visit_date', '2026-07-03');

        $assignees = collect($response->json('assignees'));
        $workloads = $assignees->pluck('workload')->all();
        $this->assertSame($workloads, collect($workloads)->sort()->values()->all());
        $this->assertSame(2, (int) $assignees->firstWhere('name', 'Alpha Staff')['workload']);
        $this->assertSame(1, (int) $assignees->firstWhere('name', 'Beta Staff')['workload']);
    }
}
