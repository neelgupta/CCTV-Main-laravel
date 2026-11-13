<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketAssignmentHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketRepeatComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeat_complaint_reopens_the_same_ticket_and_keeps_history(): void
    {
        [$company, $admin, $assignedStaff] = $this->makeCompanyWithUsers();

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $assignedStaff->id,
            'ticket_number' => 'TKT-20260702-0001',
            'customer_contact_number' => '9999999999',
            'customer_name' => 'Repeat Client',
            'type' => 'support',
            'subject' => 'Camera not working',
            'priority' => 'high',
            'status' => 'closed',
            'description' => 'Original ticket description.',
            'service_notes' => 'Previous resolution notes.',
            'repeat_status' => false,
            'repeat_count' => 1,
            'updated_at' => now()->subDays(4),
            'created_at' => now()->subDays(8),
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $assignedStaff->id,
            'message' => 'Issue resolved once before.',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('tickets.repeat-complaint', $ticket), [
                'note' => 'Client says the same issue is back.',
            ]);

        $response->assertSessionHas('success', 'Ticket reopened as repeat complaint.');

        $ticket->refresh();

        $this->assertSame('open', $ticket->status);
        $this->assertTrue($ticket->repeat_status);
        $this->assertSame(2, $ticket->repeat_count);
        $this->assertSame($assignedStaff->id, (int) $ticket->assigned_to);
        $this->assertSame($assignedStaff->id, (int) $ticket->assigned_previous_staff_id);
        $this->assertSame('Original ticket description.', $ticket->description);
        $this->assertSame('Previous resolution notes.', $ticket->service_notes);
        $this->assertSame(1, $ticket->replies()->count());
        $this->assertSame(1, $ticket->repeatHistories()->count());
        $this->assertSame(1, $ticket->assignmentHistories()->count());

        $history = $ticket->repeatHistories()->first();
        $this->assertSame(2, (int) $history->repeat_count);
        $this->assertSame('closed', $history->previous_status);
        $this->assertSame('open', $history->reopened_status);
        $this->assertSame('Client says the same issue is back.', $history->note);

        $assignmentHistory = $ticket->assignmentHistories()->first();
        $this->assertSame($assignedStaff->id, (int) $assignmentHistory->staff_id);
        $this->assertSame(1, (int) $assignmentHistory->repeat_count_handled);
        $this->assertTrue((bool) $assignmentHistory->is_current);
    }

    public function test_repeat_complaint_requires_manual_assignment_when_previous_staff_is_unavailable(): void
    {
        [$company, $admin, $assignedStaff] = $this->makeCompanyWithUsers();
        $backupStaff = $this->makeStaffUser($company, 'backup@example.com', 'Backup Staff');
        $assignedStaff->update(['is_active' => false]);

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $assignedStaff->id,
            'ticket_number' => 'TKT-20260702-0002',
            'customer_contact_number' => '8888888888',
            'customer_name' => 'Repeat Client 2',
            'type' => 'support',
            'subject' => 'Recorder issue',
            'priority' => 'medium',
            'status' => 'closed',
            'description' => 'Original ticket description.',
            'repeat_count' => 0,
            'repeat_status' => false,
            'updated_at' => now()->subDays(2),
        ]);

        $this->actingAs($admin)
            ->post(route('tickets.repeat-complaint', $ticket), [])
            ->assertSessionHasErrors(['assigned_to']);

        $this->actingAs($admin)
            ->post(route('tickets.repeat-complaint', $ticket), [
                'assigned_to' => $backupStaff->id,
            ])
            ->assertSessionHas('success', 'Ticket reopened as repeat complaint.');

        $ticket->refresh();

        $this->assertSame('open', $ticket->status);
        $this->assertSame($backupStaff->id, (int) $ticket->assigned_to);
        $this->assertTrue($ticket->repeat_status);
        $this->assertSame(1, $ticket->repeat_count);
        $this->assertSame(1, $ticket->repeatHistories()->count());
        $this->assertSame(2, $ticket->assignmentHistories()->count());

        $currentAssignment = $ticket->assignmentHistories()->where('is_current', true)->first();
        $this->assertSame($backupStaff->id, (int) $currentAssignment->staff_id);
        $this->assertSame(1, (int) $currentAssignment->repeat_count_handled);
    }

    public function test_manual_reassignment_creates_a_new_assignment_history_row(): void
    {
        [$company, $admin, $assignedStaff] = $this->makeCompanyWithUsers();
        $backupStaff = $this->makeStaffUser($company, 'backup2@example.com', 'Backup Staff 2');

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $admin->id,
            'assigned_to' => $assignedStaff->id,
            'ticket_number' => 'TKT-20260702-0003',
            'customer_contact_number' => '7777777777',
            'customer_name' => 'Manual Reassign Client',
            'type' => 'support',
            'subject' => 'Manual reassignment test',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Original ticket description.',
            'repeat_count' => 0,
            'repeat_status' => false,
        ]);

        $this->actingAs($admin)
            ->patch(route('tickets.update', $ticket), [
                'customer_contact_number' => '7777777777',
                'customer_name' => 'Manual Reassign Client',
                'customer_email' => null,
                'customer_address' => null,
                'project_id' => null,
                'type' => 'support',
                'subject' => 'Manual reassignment test',
                'priority' => 'medium',
                'visit_date' => now()->addDay()->toDateString(),
                'description' => 'Updated description.',
                'assigned_to' => $backupStaff->id,
                'referenced_by' => null,
                'selected_customer_source_type' => null,
                'selected_customer_source_id' => null,
            ])
            ->assertSessionHas('success', 'Ticket updated successfully.');

        $ticket->refresh();

        $this->assertSame($backupStaff->id, (int) $ticket->assigned_to);
        $this->assertSame(2, $ticket->assignmentHistories()->count());
        $this->assertSame(1, $ticket->assignmentHistories()->where('staff_id', $assignedStaff->id)->count());
        $this->assertSame(1, $ticket->assignmentHistories()->where('staff_id', $backupStaff->id)->count());
    }

    private function makeCompanyWithUsers(): array
    {
        $company = Company::create([
            'name' => 'Repeat Co',
            'company_code' => 'CC-REPEAT',
            'email' => 'repeat@example.com',
            'is_active' => true,
        ]);

        $adminRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'company_admin'],
            ['name' => 'Company Admin']
        );

        $employeeRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin-repeat@example.com',
            'password' => 'Secret@123',
            'role' => 'company_admin',
            'role_id' => $adminRole->id,
            'is_active' => true,
        ]);

        $assignedStaff = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Mitu Pradhan',
            'email' => 'mitu.repeat@example.com',
            'password' => 'Secret@123',
            'role' => 'employee',
            'role_id' => $employeeRole->id,
            'is_active' => true,
        ]);

        return [$company, $admin, $assignedStaff];
    }

    private function makeStaffUser(Company $company, string $email, string $name): User
    {
        $employeeRole = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        return User::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
            'email' => $email,
            'password' => 'Secret@123',
            'role' => 'employee',
            'role_id' => $employeeRole->id,
            'is_active' => true,
        ]);
    }
}
