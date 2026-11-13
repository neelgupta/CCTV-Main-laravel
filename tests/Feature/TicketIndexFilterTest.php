<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_support_tickets_index_supports_search_and_filters(): void
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

        $assignedEmployee = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Alpha Tech',
            'email' => 'tech@alpha.example.com',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'company_id' => $company->id,
            'user_id' => $assignedEmployee->id,
            'name' => 'Alpha Tech',
            'employee_id' => 'EMP-1001',
            'email' => 'tech@alpha.example.com',
            'contact_number' => '9999999999',
            'status' => 'active',
        ]);

        $otherEmployee = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Beta Tech',
            'email' => 'beta@alpha.example.com',
            'role' => 'employee',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::create([
            'company_id' => $company->id,
            'user_id' => $otherEmployee->id,
            'name' => 'Beta Tech',
            'employee_id' => 'EMP-1002',
            'email' => 'beta@alpha.example.com',
            'contact_number' => '8888888888',
            'status' => 'active',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $assignedEmployee->id,
            'ticket_number' => 'TKT-FILTER-001',
            'customer_name' => 'Filter Customer',
            'customer_email' => 'filter@example.com',
            'customer_contact_number' => '7777777777',
            'type' => 'support',
            'subject' => 'Primary filtered ticket',
            'priority' => 'high',
            'status' => 'in_progress',
            'visit_date' => '2026-06-15',
            'description' => 'This ticket should match every support module filter.',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $otherEmployee->id,
            'ticket_number' => 'TKT-FILTER-002',
            'customer_name' => 'Other Assignee',
            'customer_email' => 'other@example.com',
            'customer_contact_number' => '6666666666',
            'type' => 'support',
            'subject' => 'Wrong assignee ticket',
            'priority' => 'high',
            'status' => 'in_progress',
            'visit_date' => '2026-06-15',
            'description' => 'This ticket should be removed by assigned employee filter.',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $assignedEmployee->id,
            'ticket_number' => 'TKT-FILTER-003',
            'customer_name' => 'Wrong Date',
            'customer_email' => 'date@example.com',
            'customer_contact_number' => '5555555555',
            'type' => 'support',
            'subject' => 'Outside visit range ticket',
            'priority' => 'high',
            'status' => 'in_progress',
            'visit_date' => '2026-07-01',
            'description' => 'This ticket should be removed by visit date range.',
        ]);

        $response = $this->actingAs($admin)->get('/tickets?' . http_build_query([
            'search' => 'Alpha Tech',
            'priority' => 'high',
            'assigned_to' => $assignedEmployee->id,
            'status' => 'in_progress',
            'start_date' => '2026-06-10',
            'end_date' => '2026-06-20',
        ]));

        $response->assertOk();
        $response->assertSee('Primary filtered ticket');
        $response->assertDontSee('Wrong assignee ticket');
        $response->assertDontSee('Outside visit range ticket');
        $response->assertSee('Search All Fields');
        $response->assertSee('value="Alpha Tech"', false);
        $response->assertSee('value="2026-06-10"', false);
        $response->assertSee('value="2026-06-20"', false);
        $response->assertSee('/tickets/export?search=Alpha+Tech&amp;priority=high&amp;assigned_to=' . $assignedEmployee->id . '&amp;status=in_progress&amp;start_date=2026-06-10&amp;end_date=2026-06-20', false);
    }
}
