<?php

namespace Tests\Feature;

use App\Models\Amc;
use App\Models\Client;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Modules\Accounting\Services\AccountingDashboardService;
use App\Services\ClientOutstandingAmountService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Regression tests for the Phase 0 accounting fixes (wrong numbers shown today).
 */
class AccountingNumbersTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_is_not_counted_twice_in_customer_balance(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);

        $this->createStandaloneInvoice($company, $client, 1000, paid: true);
        $this->createStandaloneInvoice($company, $client, 1000, paid: false);

        $summary = app(ClientOutstandingAmountService::class)->ledgerSummary($client);

        $this->assertSame(1, Income::query()->where('client_id', $client->id)->count());
        $this->assertEqualsWithDelta(2000.0, $summary['total_amount'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $summary['received_amount'], 0.001);
        $this->assertEqualsWithDelta(1000.0, $summary['pending_amount'], 0.001);
    }

    public function test_manual_income_still_reduces_customer_balance(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $this->createStandaloneInvoice($company, $client, 1000, paid: false);
        $this->createIncome($company, 400, 'UPI', clientId: $client->id);

        $summary = app(ClientOutstandingAmountService::class)->ledgerSummary($client);

        $this->assertEqualsWithDelta(600.0, $summary['pending_amount'], 0.001);
    }

    public function test_invoice_paid_online_is_saved_as_online_income(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);

        $online = $this->createStandaloneInvoice($company, $client, 2360, paid: true, method: 1);
        $legacyCash = $this->createStandaloneInvoice($company, $client, 500, paid: true, method: 2);

        $this->assertSame('Online', Income::query()->where('invoice_id', $online->id)->value('payment_method'));
        $this->assertSame('Cash', Income::query()->where('invoice_id', $legacyCash->id)->value('payment_method'));
    }

    public function test_dashboard_online_tile_counts_every_non_cash_method(): void
    {
        $company = $this->createCompany();
        foreach (['Cash' => 100, 'Online' => 200, 'Bank' => 300, 'UPI' => 400, 'Cheque' => 500, 'Card' => 600] as $method => $amount) {
            $this->createIncome($company, $amount, $method);
        }

        $summary = app(AccountingDashboardService::class)->summaryForCompany($company->id);

        $this->assertEqualsWithDelta(2100.0, $summary['total_income'], 0.001);
        $this->assertEqualsWithDelta(100.0, $summary['cash_income'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $summary['online_income'], 0.001);
        $this->assertEqualsWithDelta($summary['total_income'], $summary['cash_income'] + $summary['online_income'], 0.001);
    }

    public function test_dashboard_ignores_expenses_pending_approval(): void
    {
        $company = $this->createCompany();
        $this->createIncome($company, 1000, 'Cash');
        $this->createExpense($company, 300, Expense::STATUS_APPROVED);
        $this->createExpense($company, 500, Expense::STATUS_PENDING);

        $service = app(AccountingDashboardService::class);
        $summary = $service->summaryForCompany($company->id);
        $charts = $service->chartDataForCompany($company->id);

        $this->assertEqualsWithDelta(300.0, $summary['total_expense'], 0.001);
        $this->assertEqualsWithDelta(300.0, $summary['monthly_expense'], 0.001);
        $this->assertEqualsWithDelta(700.0, $summary['net_profit'], 0.001);
        $this->assertEqualsWithDelta(300.0, array_sum($charts['monthly_comparison']['expense']), 0.001);
        $this->assertEqualsWithDelta(300.0, array_sum($charts['expense_breakdown']['values']), 0.001);
    }

    public function test_paid_amc_contract_creates_income_and_follows_status(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $amc = $this->createAmc($company, $client, 'pending');

        $this->assertSame(0, Income::query()->where('amc_id', $amc->id)->count());

        $amc->update(['payment_status' => 'paid', 'payment_mode' => 'upi']);
        $income = Income::query()->where('amc_id', $amc->id)->firstOrFail();
        $this->assertEqualsWithDelta(11800.0, (float) $income->amount, 0.001);
        $this->assertSame('UPI', $income->payment_method);
        $this->assertSame('AMC Payment', $income->category->name);
        $this->assertSame($client->id, $income->client_id);

        $amc->update(['total_amount' => 12000]);
        $this->assertSame(1, Income::query()->where('amc_id', $amc->id)->count());
        $this->assertEqualsWithDelta(12000.0, (float) $income->fresh()->amount, 0.001);

        $amc->update(['payment_status' => 'pending']);
        $this->assertSame(0, Income::query()->where('amc_id', $amc->id)->count());
    }

    public function test_marking_paid_invoice_unpaid_removes_its_income(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createStandaloneInvoice($company, $client, 2360, paid: true);
        $this->assertSame(1, Income::query()->where('invoice_id', $invoice->id)->count());

        $invoice->update(['payment_status' => 0, 'payment_method' => null]);
        $this->assertSame(0, Income::query()->where('invoice_id', $invoice->id)->count());

        $invoice->update(['payment_status' => 1, 'payment_method' => 0]);
        $this->assertSame(1, Income::query()->where('invoice_id', $invoice->id)->count());
    }

    public function test_editing_paid_invoice_amount_updates_the_same_income(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createStandaloneInvoice($company, $client, 2360, paid: true);

        $invoice->update(['total_amount' => 3000, 'invoice_total' => 3000]);

        $incomes = Income::query()->where('invoice_id', $invoice->id)->get();
        $this->assertCount(1, $incomes);
        $this->assertEqualsWithDelta(3000.0, (float) $incomes->first()->amount, 0.001);
    }

    public function test_deleting_invoice_removes_its_income(): void
    {
        $company = $this->createCompany();
        $client = $this->createClient($company);
        $invoice = $this->createStandaloneInvoice($company, $client, 1000, paid: true);

        $invoice->delete();

        $this->assertSame(0, Income::query()->where('company_id', $company->id)->count());
    }

    public function test_invoice_income_category_follows_invoice_type_and_keeps_manual_choice(): void
    {
        $company = $this->createCompany();
        // Default categories every company starts with.
        foreach (['AMC Payment', 'Support Income', 'Service Income', 'Installation', 'Product Sale', 'Other'] as $name) {
            IncomeCategory::query()->create(['company_id' => $company->id, 'name' => $name, 'status' => 'active']);
        }
        $client = $this->createClient($company);
        $invoice = $this->createStandaloneInvoice($company, $client, 1000, paid: true);

        $income = Income::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('Service Income', $income->category->name);

        $installation = IncomeCategory::query()->where('company_id', $company->id)->where('name', 'Installation')->firstOrFail();
        $income->update(['category_id' => $installation->id]);

        $invoice->update(['total_amount' => 1500, 'invoice_total' => 1500]);

        $this->assertSame($installation->id, $income->fresh()->category_id);
    }

    private function createCompany(): Company
    {
        return Company::create([
            'name' => 'Company ' . Str::random(6),
            'email' => 'company-' . Str::lower(Str::random(8)) . '@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ]);
    }

    private function createClient(Company $company): Client
    {
        return Client::create([
            'company_id' => $company->id,
            'name' => 'Client ' . Str::random(6),
            'mobile' => '9' . random_int(100000000, 999999999),
            'email' => 'client-' . Str::lower(Str::random(8)) . '@example.com',
        ]);
    }

    private function createStandaloneInvoice(Company $company, Client $client, float $total, bool $paid, int $method = 0): TicketInvoice
    {
        $document = TicketDocument::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'document_type' => 'invoice',
            'invoice_or_quote' => 1,
            'document_number' => 'INV-' . Str::upper(Str::random(8)),
            'items' => [['name' => 'Dome Camera', 'qty' => 1, 'price' => $total, 'unit' => 'pcs', 'total' => $total]],
            'subtotal' => $total,
            'discount' => 0,
            'gst_amount' => 0,
            'total' => $total,
            'payment_status' => $paid ? 1 : 0,
            'payment_method' => $paid ? $method : null,
        ]);

        $invoice = new TicketInvoice();
        $invoice->forceFill([
            'ticket_id' => null,
            'client_id' => $client->id,
            'invoice_id' => $document->id,
            'company_id' => $company->id,
            'invoice_number' => $document->document_number,
            'invoice_total' => $total,
            'total_amount' => $total,
            'payment_status' => $paid ? 1 : 0,
            'payment_method' => $paid ? $method : null,
        ])->save();

        return $invoice;
    }

    private function createIncome(Company $company, float $amount, string $method, ?int $clientId = null): Income
    {
        $category = IncomeCategory::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Other'],
            ['status' => 'active']
        );

        return Income::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'client_id' => $clientId,
            'title' => 'Income ' . $method,
            'amount' => $amount,
            'payment_method' => $method,
            'received_date' => Carbon::today()->toDateString(),
        ]);
    }

    private function createExpense(Company $company, float $amount, string $status): Expense
    {
        $category = ExpenseCategory::query()->firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Fuel'],
            ['status' => 'active']
        );

        return Expense::query()->create([
            'company_id' => $company->id,
            'category_id' => $category->id,
            'vendor_name' => 'Shell',
            'title' => 'Fuel',
            'amount' => $amount,
            'payment_method' => 'Cash',
            'expense_date' => Carbon::today()->toDateString(),
            'approval_status' => $status,
        ]);
    }

    private function createAmc(Company $company, Client $client, string $paymentStatus): Amc
    {
        return Amc::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'contract_no' => 'AMC-' . Str::upper(Str::random(8)),
            'customer_name' => $client->name,
            'project_name' => 'Site',
            'start_date' => Carbon::today()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'monthly',
            'payment_mode' => 'cash',
            'payment_status' => $paymentStatus,
            'amc_amount' => 10000,
            'tax_percent' => 18,
            'total_amount' => 11800,
        ]);
    }
}
