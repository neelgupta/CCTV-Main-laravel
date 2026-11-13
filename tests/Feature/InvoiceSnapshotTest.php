<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Models\TicketDocument;
use App\Services\GlobalSettingService;
use App\Support\SubscriptionInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvoiceSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_invoice_keeps_details_from_purchase_time(): void
    {
        $settings = app(GlobalSettingService::class);
        $settings->putMany([
            'business_name' => 'Original Seller',
            'gst_number' => '24AAAAA0000A1Z5',
            'address' => 'Old Seller Address',
            'subscription_gst_percentage' => '18',
        ]);

        $company = $this->createCompany('Original Buyer', 'Old Buyer Street');
        $plan = SubscriptionPlan::create([
            'name' => 'Original Plan',
            'slug' => 'plan-' . Str::lower(Str::random(6)),
            'price' => 1000,
            'monthly_price' => 1000,
            'yearly_price' => 10000,
            'billing_cycle' => 'monthly',
            'staff_limit' => 5,
            'is_active' => true,
        ]);

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'amount_paid' => 1180,
            'billing_reference' => 'SUB-SNAP001',
        ]);

        $settings->putMany([
            'business_name' => 'Renamed Seller',
            'gst_number' => '27BBBBB1111B1Z5',
            'address' => 'New Seller Address',
            'subscription_gst_percentage' => '28',
        ]);
        $company->update(['name' => 'Renamed Buyer', 'address' => 'New Buyer Street', 'gst_number' => '29CCCCC2222C1Z5']);
        $plan->update(['name' => 'Renamed Plan']);

        $data = SubscriptionInvoice::data($subscription->fresh(['company', 'plan']));

        $this->assertSame('Original Seller', $data['businessName']);
        $this->assertSame('24AAAAA0000A1Z5', $data['businessGst']);
        $this->assertSame('Old Seller Address', $data['businessAddress']);
        $this->assertSame('Original Buyer', $data['partyCompanyName']);
        $this->assertStringContainsString('Old Buyer Street', $data['partyCompanyAddress']);
        $this->assertSame('', $data['partyCompanyGst']);
        $this->assertSame('Original Plan', $data['planName']);
        $this->assertEquals(18, $data['gstRate']);
        $this->assertSame('1,000.00', $data['subtotalFormatted']);
    }

    public function test_billing_document_keeps_seller_and_client_details_until_it_is_edited(): void
    {
        $company = $this->createCompany('Original Workshop', 'Old Workshop Road');
        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Original Client',
            'mobile' => '9999999999',
            'email' => 'client-' . Str::lower(Str::random(8)) . '@example.com',
            'address' => 'Old Client Lane',
        ]);

        $document = TicketDocument::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'document_type' => 'invoice',
            'invoice_or_quote' => 1,
            'document_number' => 'INV-2026-0100',
            'items' => [['name' => 'Dome Camera', 'qty' => 1, 'price' => 2500, 'unit' => 'pcs', 'total' => 2500]],
            'subtotal' => 2500,
            'discount' => 0,
            'gst_amount' => 0,
            'total' => 2500,
            'payment_status' => 0,
        ]);

        $company->update(['name' => 'Renamed Workshop', 'address' => 'New Workshop Road']);
        $client->update(['name' => 'Renamed Client', 'address' => 'New Client Lane']);

        // Names are printed uppercase, so compare case-insensitively.
        $html = strtolower($this->renderDocument($document->fresh()));

        $this->assertStringContainsString('original workshop', $html);
        $this->assertStringContainsString('old workshop road', $html);
        $this->assertStringContainsString('original client', $html);
        $this->assertStringContainsString('old client lane', $html);
        $this->assertStringNotContainsString('renamed workshop', $html);
        $this->assertStringNotContainsString('renamed client', $html);

        // A payment-only update does not re-issue the document.
        $document->update(['payment_status' => 1]);
        $this->assertSame('Original Client', $document->fresh()->billing_snapshot['party']['name']);

        // Editing the invoice re-issues it with the current details.
        $document->update(['items' => [['name' => 'Bullet Camera', 'qty' => 1, 'price' => 3000, 'unit' => 'pcs', 'total' => 3000]], 'total' => 3000]);
        $this->assertSame('Renamed Client', $document->fresh()->billing_snapshot['party']['name']);
        $this->assertSame('Renamed Workshop', $document->fresh()->billing_snapshot['seller']['name']);
    }

    private function renderDocument(TicketDocument $document): string
    {
        return view('tickets.document', [
            'document' => $document,
            'type' => 'invoice',
            'ticket' => null,
            'client' => $document->client,
            'items' => $document->items,
            'subtotal' => $document->subtotal,
            'discount' => $document->discount,
            'gstAmount' => $document->gst_amount,
            'total' => $document->total,
            'paymentStatus' => 'Unpaid',
            'paymentMethod' => null,
            'isPdf' => true,
            'autoPrint' => false,
        ])->render();
    }

    private function createCompany(string $name, string $address): Company
    {
        return Company::create([
            'name' => $name,
            'email' => 'company-' . Str::lower(Str::random(8)) . '@example.com',
            'address' => $address,
            'is_active' => true,
            'is_onboarded' => true,
            'invoice_prefix' => 'INV',
            'invoice_year' => 2026,
            'invoice_sequence' => 1,
        ]);
    }
}
