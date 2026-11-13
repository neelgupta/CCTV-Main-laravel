<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTicketListTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_staff_can_list_only_their_company_tickets(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $otherCompany = Company::create([
            'name' => 'Beta Systems',
            'company_code' => 'CC-BETA01',
            'email' => 'beta@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');
        $otherCompanyUser = $this->createEmployeeAccount($otherCompany, 'tech.beta@example.com', 'EMP-2001', 'Secret@123', 'Beta Tech');

        $assignedTicket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-ASSIGNED-001',
            'customer_contact_number' => '9999999999',
            'customer_name' => 'Assigned Customer',
            'customer_email' => 'assigned@example.com',
            'type' => 'support',
            'subject' => 'Assigned support ticket',
            'priority' => 'high',
            'status' => 'open',
            'visit_date' => now()->addDays(2)->toDateString(),
            'description' => 'This ticket is assigned to the logged in staff member.',
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        TicketReply::create([
            'ticket_id' => $assignedTicket->id,
            'user_id' => $creatorUser->id,
            'message' => 'Initial reply',
        ]);

        $createdTicket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'assigned_to' => $creatorUser->id,
            'ticket_number' => 'TKT-CREATED-001',
            'customer_contact_number' => '8888888888',
            'customer_name' => 'Created Customer',
            'customer_email' => 'created@example.com',
            'type' => 'complain',
            'subject' => 'Created by staff ticket',
            'priority' => 'medium',
            'status' => 'in_progress',
            'visit_date' => now()->addDays(4)->toDateString(),
            'description' => 'This ticket was created by the logged in staff member.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => null,
            'ticket_number' => 'TKT-HIDDEN-001',
            'type' => 'support',
            'subject' => 'Hidden ticket',
            'priority' => 'low',
            'status' => 'open',
            'visit_date' => now()->addDays(6)->toDateString(),
            'description' => 'This ticket should not appear for the logged in staff member.',
        ]);

        Ticket::create([
            'company_id' => $otherCompany->id,
            'user_id' => $otherCompanyUser->id,
            'assigned_to' => $otherCompanyUser->id,
            'ticket_number' => 'TKT-OTHER-001',
            'type' => 'support',
            'subject' => 'Other company ticket',
            'priority' => 'urgent',
            'status' => 'open',
            'visit_date' => now()->addDays(8)->toDateString(),
            'description' => 'This ticket belongs to another company and must stay hidden.',
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 10)
            ->assertJsonPath('meta.pagination.from', 1)
            ->assertJsonPath('meta.pagination.to', 2)
            ->assertJsonCount(2, 'tickets');

        $ticketNumbers = collect($response->json('tickets'))->pluck('ticket_number')->all();

        $this->assertContains($createdTicket->ticket_number, $ticketNumbers);
        $this->assertContains($assignedTicket->ticket_number, $ticketNumbers);
        $this->assertNotContains('TKT-HIDDEN-001', $ticketNumbers);
        $this->assertNotContains('TKT-OTHER-001', $ticketNumbers);
        $response
            ->assertJsonPath('tickets.0.ticket_number', $createdTicket->ticket_number)
            ->assertJsonPath('tickets.1.ticket_number', $assignedTicket->ticket_number)
            ->assertJsonPath('tickets.1.reply_count', 1)
            ->assertJsonPath('tickets.1.assigned_to.id', $staffUser->id)
            ->assertJsonPath('tickets.1.created_by.id', $creatorUser->id)
            ->assertJsonPath('tickets.0.visit_date', now()->addDays(4)->toDateString());
    }

    public function test_staff_ticket_list_supports_period_status_priority_and_other_filters(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $staffUser->id,
            'assigned_to' => $creatorUser->id,
            'ticket_number' => 'TKT-TODAY-001',
            'customer_name' => 'Today Customer',
            'customer_email' => 'today@example.com',
            'type' => 'support',
            'subject' => 'Today urgent ticket',
            'priority' => 'urgent',
            'status' => 'open',
            'visit_date' => now()->addDays(1)->toDateString(),
            'description' => 'Ticket created today by the logged in staff member.',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $weekTicket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-WEEK-001',
            'customer_name' => 'Week Customer',
            'customer_email' => 'week@example.com',
            'type' => 'meeting',
            'subject' => 'Weekly meeting ticket',
            'priority' => 'high',
            'status' => 'in_progress',
            'visit_date' => now()->addDays(3)->toDateString(),
            'description' => 'Ticket assigned this week to the logged in staff member.',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-MONTH-001',
            'customer_name' => 'Month Customer',
            'customer_email' => 'month@example.com',
            'type' => 'complain',
            'subject' => 'Monthly complaint ticket',
            'priority' => 'low',
            'status' => 'resolved',
            'visit_date' => now()->addDays(5)->toDateString(),
            'description' => 'Ticket assigned earlier this month to the logged in staff member.',
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-OLD-001',
            'customer_name' => 'Old Customer',
            'customer_email' => 'old@example.com',
            'type' => 'sale',
            'subject' => 'Old sales ticket',
            'priority' => 'medium',
            'status' => 'closed',
            'visit_date' => now()->addDays(10)->toDateString(),
            'description' => 'Ticket from a previous month assigned to the logged in staff member.',
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $todayResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?period=today');

        $todayResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 0)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-TODAY-001')
            ->assertJsonPath('meta.filters.period', 'today');

        $weekResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?period=week&status=in_progress&priority=high&type=meeting&assigned_to_me=1&search=weekly');

        $weekResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 1)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-WEEK-001')
            ->assertJsonPath('meta.filters.status.0', 'in_progress')
            ->assertJsonPath('meta.filters.priority.0', 'high')
            ->assertJsonPath('meta.filters.type.0', 'meeting')
            ->assertJsonPath('meta.filters.assigned_to_me', true)
            ->assertJsonPath('meta.filters.search', 'weekly');

        $monthResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?period=month&status=open,resolved&sort=oldest');

        $monthResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 1)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-MONTH-001')
            ->assertJsonPath('tickets.1.ticket_number', 'TKT-TODAY-001')
            ->assertJsonPath('meta.filters.sort', 'oldest');

        $createdByMeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?created_by_me=1');

        $createdByMeResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 0)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-TODAY-001');

        $dateRangeResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?start_date=' . now()->addDays(2)->toDateString() . '&end_date=' . now()->addDays(5)->toDateString() . '&sort=oldest');

        $dateRangeResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_counts.pending', 0)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-WEEK-001')
            ->assertJsonPath('tickets.1.ticket_number', 'TKT-MONTH-001')
            ->assertJsonPath('meta.filters.start_date', now()->addDays(2)->toDateString())
            ->assertJsonPath('meta.filters.end_date', now()->addDays(5)->toDateString());

        $startOnlyResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?start_date=' . now()->addDays(5)->toDateString() . '&sort=oldest');

        $startOnlyResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_counts.pending', 0)
            ->assertJsonPath('meta.status_counts.in_progress', 0)
            ->assertJsonPath('meta.status_counts.closed', 1)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-MONTH-001')
            ->assertJsonPath('tickets.1.ticket_number', 'TKT-OLD-001');

        $endOnlyResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?end_date=' . now()->addDays(3)->toDateString() . '&sort=oldest');

        $endOnlyResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-TODAY-001')
            ->assertJsonPath('tickets.1.ticket_number', 'TKT-WEEK-001');

        $visitDateResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?visit_date=' . $weekTicket->visit_date->toDateString());

        $visitDateResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.pending', 0)
            ->assertJsonPath('meta.status_counts.in_progress', 1)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-WEEK-001')
            ->assertJsonPath('tickets.0.visit_date', $weekTicket->visit_date->toDateString())
            ->assertJsonPath('meta.filters.visit_date', $weekTicket->visit_date->toDateString());
    }

    public function test_staff_ticket_list_date_range_filters_use_visit_date_instead_of_created_at(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-VISIT-IN-001',
            'customer_name' => 'Visit Range Match',
            'customer_email' => 'visit-in@example.com',
            'type' => 'support',
            'subject' => 'Ticket should match by visit date',
            'priority' => 'high',
            'status' => 'open',
            'visit_date' => '2026-06-15',
            'description' => 'This ticket should be returned because visit_date is in range.',
            'created_at' => '2026-01-05 10:00:00',
            'updated_at' => '2026-01-05 10:00:00',
        ]);

        Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-VISIT-OUT-001',
            'customer_name' => 'Created Range Match Only',
            'customer_email' => 'visit-out@example.com',
            'type' => 'support',
            'subject' => 'Ticket should not match by visit date',
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => '2026-07-05',
            'description' => 'This ticket should stay hidden because visit_date is outside range.',
            'created_at' => '2026-06-16 10:00:00',
            'updated_at' => '2026-06-16 10:00:00',
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?start_date=2026-06-10&end_date=2026-06-20');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts.pending', 1)
            ->assertJsonPath('meta.status_counts.in_progress', 0)
            ->assertJsonPath('meta.status_counts.closed', 0)
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-VISIT-IN-001')
            ->assertJsonPath('meta.filters.start_date', '2026-06-10')
            ->assertJsonPath('meta.filters.end_date', '2026-06-20');

        $ticketNumbers = collect($response->json('tickets'))->pluck('ticket_number')->all();

        $this->assertContains('TKT-VISIT-IN-001', $ticketNumbers);
        $this->assertNotContains('TKT-VISIT-OUT-001', $ticketNumbers);
    }

    public function test_staff_ticket_list_requires_authentication(): void
    {
        $this->getJson('/api/staff/tickets')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_staff_ticket_list_supports_pagination(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');

        foreach (range(1, 3) as $index) {
            Ticket::create([
                'company_id' => $company->id,
                'user_id' => $staffUser->id,
                'assigned_to' => $staffUser->id,
                'ticket_number' => 'TKT-PAGE-00' . $index,
                'type' => 'support',
                'subject' => 'Paginated ticket ' . $index,
                'priority' => 'medium',
                'status' => $index === 3 ? 'closed' : 'open',
                'visit_date' => now()->addDays($index)->toDateString(),
                'description' => 'Pagination test ticket.',
                'created_at' => now()->subMinutes(4 - $index),
                'updated_at' => now()->subMinutes(4 - $index),
            ]);
        }

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets?per_page=2&page=2');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.status_counts.pending', 2)
            ->assertJsonPath('meta.status_counts.in_progress', 0)
            ->assertJsonPath('meta.status_counts.closed', 1)
            ->assertJsonPath('meta.pagination.current_page', 2)
            ->assertJsonPath('meta.pagination.last_page', 2)
            ->assertJsonPath('meta.pagination.per_page', 2)
            ->assertJsonPath('meta.pagination.from', 3)
            ->assertJsonPath('meta.pagination.to', 3)
            ->assertJsonPath('meta.pagination.has_more_pages', false)
            ->assertJsonPath('meta.filters.page', 2)
            ->assertJsonPath('meta.filters.per_page', 2)
            ->assertJsonCount(1, 'tickets')
            ->assertJsonPath('tickets.0.ticket_number', 'TKT-PAGE-001');
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
