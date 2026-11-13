<?php

namespace Tests\Feature;

use App\Models\Amc;
use App\Models\AmcPlan;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use App\Services\AmcSupportWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AmcSupportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_amc_support_button_generates_a_single_ticket_and_reuses_it_on_second_click(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@alpha.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $employeeRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $technician = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Alpha Tech',
            'email' => 'tech@alpha.example.com',
            'role' => 'employee',
            'role_id' => $employeeRole->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $staff = Staff::create([
            'company_id' => $company->id,
            'user_id' => $technician->id,
            'name' => 'Alpha Tech',
            'employee_id' => 'EMP-1001',
            'contact_number' => '9999999999',
            'join_date' => now()->toDateString(),
            'role_id' => $employeeRole->id,
        ]);

        AmcPlan::create([
            'company_id' => $company->id,
            'name' => 'Quarterly AMC',
            'duration_months' => 12,
            'visit_count' => 4,
            'price' => 12000,
            'description' => 'Quarterly coverage',
            'includes_on_call_support' => true,
            'service_mode' => 'hybrid',
            'is_active' => true,
        ]);

        $amc = Amc::create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
            'contract_no' => 'AMC-2026-1-0001',
            'amc_plan_id' => 1,
            'assigned_staff_id' => $staff->id,
            'customer_name' => 'ABC Company',
            'project_name' => 'Office CCTV',
            'mobile_number' => '9876543210',
            'email' => 'client@example.com',
            'address' => '123 Main Street',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'amc_type' => 'non_comprehensive',
            'service_mode' => 'hybrid',
            'visit_frequency' => 'manual',
            'total_visits' => 4,
            'scheduled_visits' => 0,
            'remaining_visits' => 4,
            'amc_amount' => 12000,
            'total_amount' => 12000,
            'payment_status' => 'pending',
            'plan_name' => 'Quarterly AMC',
            'plan_duration_months' => 12,
        ]);

        $workflowService = app(AmcSupportWorkflowService::class);

        $ticket = $workflowService->generateSupportTicketForAmc($amc, null, $admin);
        $this->assertNotNull($ticket);
        $this->assertTrue($ticket->wasRecentlyCreated);
        $this->assertSame($amc->id, (int) $ticket->generated_from_amc_id);
        $this->assertSame('amc', $ticket->source_type);
        $this->assertSame($amc->id, (int) $ticket->source_id);
        $this->assertSame($staff->user_id, (int) $ticket->assigned_to);
        $this->assertSame('AMC Support Request - Office CCTV', $ticket->subject);

        $secondTicket = $workflowService->generateSupportTicketForAmc($amc, null, $admin);
        $this->assertFalse($secondTicket->wasRecentlyCreated);
        $this->assertSame($ticket->id, $secondTicket->id);

        $this->assertSame(1, Ticket::query()->where('company_id', $company->id)->count());
    }
}
