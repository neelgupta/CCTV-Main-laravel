<?php

namespace Tests\Feature\Api;

use App\Models\BoqProduct;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Models\TicketQuote;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffTicketQuoteAddTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_creation_links_ticket_and_records_payment_timeline(): void
    {
        [$company, $user, $ticket] = $this->makeStaffTicket();
        $service = $this->makeService($company);
        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/staff/tickets/{$ticket->getRouteKey()}/quote_add", [
                'product_id' => [$service->id],
                'qty' => [2],
                'price' => [1200],
                'invoice_or_quote' => 1,
                'payment_status' => 1,
                'payment_method' => 0,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Invoice created successfully.')
            ->assertJsonPath('document_type', 'invoice')
            ->assertJsonPath('ticket_id', $ticket->id)
            ->assertJsonPath('payment_status', 'Paid')
            ->assertJsonPath('payment_method', 'Cash');

        $invoice = TicketDocument::query()
            ->where('ticket_id', $ticket->id)
            ->where('document_type', 'invoice')
            ->firstOrFail();

        $this->assertDatabaseHas('ticket_invoices', [
            'ticket_id' => $ticket->id,
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->document_number,
            'invoice_subtotal' => '2400.00',
            'invoice_discount' => '0.00',
            'invoice_gst_amount' => '432.00',
            'invoice_total' => '2832.00',
            'payment_status' => 1,
            'payment_method' => 0,
            'created_by' => $user->id,
        ]);

        $messages = TicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->pluck('message')
            ->all();

        $this->assertContains('Invoice Created (' . $invoice->document_number . ')', $messages);
        $this->assertContains('Invoice Marked Paid', $messages);
        $this->assertContains('Payment Received (Cash)', $messages);
    }

    public function test_quotation_creation_links_ticket_and_keeps_payment_fields_empty(): void
    {
        [$company, $user, $ticket] = $this->makeStaffTicket();
        $service = $this->makeService($company);
        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/staff/tickets/{$ticket->getRouteKey()}/quote_add", [
                'product_id' => [$service->id],
                'qty' => [1],
                'price' => [900],
                'invoice_or_quote' => 2,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Quotation created successfully.')
            ->assertJsonPath('document_type', 'quotation')
            ->assertJsonPath('ticket_id', $ticket->id)
            ->assertJsonPath('invoice_or_quote', 2);

        $quotation = TicketDocument::query()
            ->where('ticket_id', $ticket->id)
            ->where('document_type', 'quotation')
            ->firstOrFail();

        $this->assertDatabaseHas('ticket_quotes', [
            'ticket_id' => $ticket->id,
            'quote_id' => $quotation->id,
            'created_by' => $user->id,
        ]);

        $messages = TicketReply::query()
            ->where('ticket_id', $ticket->id)
            ->pluck('message')
            ->all();

        $this->assertContains('Quotation Created (' . $quotation->document_number . ')', $messages);
    }

    public function test_paid_invoice_requires_a_payment_method(): void
    {
        [$company, $user, $ticket] = $this->makeStaffTicket();
        $service = $this->makeService($company);
        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/staff/tickets/{$ticket->getRouteKey()}/quote_add", [
                'product_id' => [$service->id],
                'qty' => [1],
                'price' => [900],
                'invoice_or_quote' => 1,
                'payment_status' => 1,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_method']);
    }

    public function test_invalid_payment_status_returns_validation_error(): void
    {
        [$company, $user, $ticket] = $this->makeStaffTicket();
        $service = $this->makeService($company);
        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/staff/tickets/{$ticket->getRouteKey()}/quote_add", [
                'product_id' => [$service->id],
                'qty' => [1],
                'price' => [900],
                'invoice_or_quote' => 1,
                'payment_status' => 2,
                'payment_method' => 1,
            ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_status']);
    }

    /**
     * @return array{0: Company, 1: User, 2: Ticket}
     */
    private function makeStaffTicket(): array
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $user = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'John Doe');

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'assigned_to' => $user->id,
            'ticket_number' => 'TKT-001',
            'subject' => 'Need service quote',
            'priority' => 'medium',
            'status' => 'open',
            'description' => 'Ticket created for API coverage.',
        ]);

        return [$company, $user, $ticket];
    }

    private function makeService(Company $company): BoqProduct
    {
        return BoqProduct::create([
            'company_id' => $company->id,
            'name' => 'Annual AMC Service',
            'unit' => 'job',
            'price' => 1000,
            'gst' => 18,
            'description' => 'Coverage for quote add tests.',
            'is_active' => true,
        ]);
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
