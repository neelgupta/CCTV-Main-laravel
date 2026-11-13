<?php

namespace Tests\Feature\Api;

use App\Models\Company;
use App\Models\BoqProduct;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StaffTicketDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_staff_can_fetch_ticket_detail_with_service_fields(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-DETAIL-001',
            'customer_contact_number' => '9999999999',
            'customer_name' => 'Demo Customer',
            'customer_email' => 'demo@example.com',
            'type' => 'support',
            'subject' => 'Need detail view',
            'priority' => 'high',
            'status' => 'reschedule',
            'visit_date' => '2026-06-05',
            'description' => 'Detailed ticket description for the service engineer.',
            'service_notes' => 'Observed wiring issue near DVR unit.',
            'current_condition_photos' => ['company_1/tickets/current-condition/before-one.jpg'],
            'after_repair_photos' => ['company_1/tickets/after-repair/after-one.jpg'],
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $creatorUser->id,
            'message' => 'Please resolve this quickly.',
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets/' . $ticket->getRouteKey());

        $response
            ->assertOk()
            ->assertJsonPath('ticket.ticket_number', 'TKT-DETAIL-001')
            ->assertJsonPath('ticket.service_notes', 'Observed wiring issue near DVR unit.')
            ->assertJsonPath('ticket.status', 'reschedule')
            ->assertJsonPath('ticket.status_label', 'Reschedule')
            ->assertJsonPath('ticket.current_condition_photos.0.path', 'company_1/tickets/current-condition/before-one.jpg')
            ->assertJsonPath('ticket.after_repair_photos.0.path', 'company_1/tickets/after-repair/after-one.jpg')
            ->assertJsonPath('ticket.replies.0.message', 'Please resolve this quickly.')
            ->assertJsonFragment(['value' => 'reschedule', 'label' => 'Reschedule']);
    }

    public function test_logged_in_staff_can_fetch_ticket_detail_with_invoice_items(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        $product = BoqProduct::create([
            'company_id' => $company->id,
            'name' => 'CCTV Camera',
            'unit' => 'pcs',
            'price' => 2500,
            'gst' => 18,
            'description' => 'HD camera unit',
            'is_active' => true,
        ]);

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-INVOICE-001',
            'customer_contact_number' => '9999999999',
            'customer_name' => 'Demo Customer',
            'type' => 'support',
            'subject' => 'Invoice detail view',
            'priority' => 'high',
            'status' => 'open',
            'visit_date' => '2026-07-14',
            'description' => 'Ticket with invoice data.',
        ]);

        $document = TicketDocument::create([
            'ticket_id' => $ticket->id,
            'company_id' => $company->id,
            'created_by' => $staffUser->id,
            'document_type' => 'invoice',
            'invoice_or_quote' => 1,
            'document_number' => 'INV-2026-00099',
            'items' => [[
                'product_id' => $product->id,
                'name' => $product->name,
                'qty' => 2,
                'price' => 2500,
                'base_total' => 5000,
                'unit' => $product->unit,
                'gst' => 18,
                'gst_amount' => 900,
                'total' => 5900,
                'description' => $product->description,
            ]],
            'subtotal' => 5000,
            'discount' => 0,
            'gst_amount' => 900,
            'total' => 5900,
            'payment_status' => 1,
            'payment_method' => 0,
        ]);

        TicketInvoice::create([
            'company_id' => $company->id,
            'ticket_id' => $ticket->id,
            'staff_id' => $staffUser->id,
            'invoice_id' => $document->id,
            'invoice_number' => $document->document_number,
            'invoice_subtotal' => 5000,
            'invoice_discount' => 0,
            'invoice_gst_amount' => 900,
            'invoice_total' => 5900,
            'total_amount' => 5900,
            'payment_status' => 1,
            'payment_method' => 0,
            'created_by' => $staffUser->id,
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/tickets/' . $ticket->getRouteKey());

        $response
            ->assertOk()
            ->assertJsonPath('ticket.invoices.0.invoice.document_number', 'INV-2026-00099')
            ->assertJsonPath('ticket.invoices.0.invoice.items.0.product_id', $product->id)
            ->assertJsonPath('ticket.invoices.0.invoice.items.0.name', 'CCTV Camera')
            ->assertJsonPath('ticket.invoices.0.invoice.summary.total', 5900.0)
            ->assertJsonPath('ticket.invoices.0.payment_status', 'Paid')
            ->assertJsonPath('ticket.invoices.0.payment_method', 'Cash')
            ->assertJsonPath('ticket.invoice_number', 'INV-2026-00099')
            ->assertJsonPath('ticket.invoice_items.0.product_id', $product->id)
            ->assertJsonPath('ticket.invoice_items.0.name', 'CCTV Camera')
            ->assertJsonPath('ticket.invoice_summary.total', 5900.0)
            ->assertJsonPath('ticket.invoice_payment_status', 'Paid')
            ->assertJsonPath('ticket.invoice_payment_method', 'Cash');
    }

    public function test_logged_in_staff_can_update_ticket_service_report_and_upload_photos(): void
    {
        Storage::fake('public');

        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-UPDATE-001',
            'type' => 'support',
            'subject' => 'Need service update',
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => '2026-06-10',
            'description' => 'Ticket for upload testing.',
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept', 'application/json')
            ->post('/api/staff/tickets/' . $ticket->getRouteKey() . '/service-report', [
                'service_notes' => 'Filter changed and camera feed restored.',
                'status' => 'pending',
                'visit_date' => '2026-06-12',
                'current_condition_photos' => [
                    UploadedFile::fake()->image('before-1.jpg'),
                    UploadedFile::fake()->image('before-2.jpg'),
                ],
                'after_repair_photos' => [
                    UploadedFile::fake()->image('after-1.jpg'),
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('message', 'Ticket service report updated successfully.')
            ->assertJsonPath('ticket.service_notes', 'Filter changed and camera feed restored.')
            ->assertJsonPath('ticket.status', 'open')
            ->assertJsonPath('ticket.status_label', 'Pending')
            ->assertJsonCount(2, 'ticket.current_condition_photos')
            ->assertJsonCount(1, 'ticket.after_repair_photos');

        $ticket->refresh();

        $this->assertSame('open', $ticket->status);
        $this->assertSame('Filter changed and camera feed restored.', $ticket->service_notes);
        $this->assertSame('2026-06-12', $ticket->visit_date?->toDateString());
        $this->assertCount(2, $ticket->current_condition_photos ?? []);
        $this->assertCount(1, $ticket->after_repair_photos ?? []);

        foreach ($ticket->current_condition_photos as $path) {
            Storage::disk('public')->assertExists($path);
        }

        foreach ($ticket->after_repair_photos as $path) {
            Storage::disk('public')->assertExists($path);
        }
    }

    public function test_signed_ticket_photo_url_opens_image_successfully(): void
    {
        Storage::fake('public');

        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');
        $creatorUser = $this->createEmployeeAccount($company, 'creator.alpha@example.com', 'EMP-1002', 'Secret@123', 'Creator User');

        $photoPath = 'company_' . $company->id . '/tickets/current-condition/test-photo.jpg';
        Storage::disk('public')->put($photoPath, 'fake-image-content');

        $ticket = Ticket::create([
            'company_id' => $company->id,
            'user_id' => $creatorUser->id,
            'assigned_to' => $staffUser->id,
            'ticket_number' => 'TKT-PHOTO-001',
            'type' => 'support',
            'subject' => 'Need photo preview',
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => '2026-06-10',
            'description' => 'Ticket for photo open testing.',
            'current_condition_photos' => [$photoPath],
        ]);

        $url = URL::signedRoute('ticket-photos.open', [
            'ticket' => $ticket->getRouteKey(),
            'collection' => 'current-condition',
            'index' => 0,
        ], absolute: false);

        $this->get($url)
            ->assertOk()
            ->assertHeader('content-disposition', 'inline; filename=test-photo.jpg');
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
