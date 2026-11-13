<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Services\ClientSyncService;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ClientSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_client_for_same_name_with_different_phone_or_email(): void
    {
        $company = $this->createCompanyWithActivePlan();

        Client::create([
            'company_id' => $company->id,
            'name' => 'Acme Industries',
            'email' => 'accounts@acme.test',
            'mobile' => '9999999999',
            'phone' => '9999999999',
            'address' => 'Old address',
        ]);

        $client = app(ClientSyncService::class)->upsert([
            'company_id' => $company->id,
            'name' => 'Acme Industries',
            'email' => 'support@acme.test',
            'mobile' => '8888888888',
            'phone' => '8888888888',
            'address' => 'New address',
        ]);

        $this->assertNotNull($client->id);
        $this->assertSame(2, Client::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('clients', [
            'company_id' => $company->id,
            'name' => 'Acme Industries',
            'email' => 'accounts@acme.test',
            'phone' => '9999999999',
            'address' => 'Old address',
        ]);
        $this->assertDatabaseHas('clients', [
            'company_id' => $company->id,
            'name' => 'Acme Industries',
            'email' => 'support@acme.test',
            'phone' => '8888888888',
            'address' => 'New address',
        ]);
    }

    public function test_it_updates_existing_client_when_email_matches(): void
    {
        $company = $this->createCompanyWithActivePlan();

        $existing = Client::create([
            'company_id' => $company->id,
            'name' => 'Acme Industries',
            'email' => 'accounts@acme.test',
            'mobile' => '9999999999',
            'phone' => '9999999999',
            'address' => 'Old address',
        ]);

        $client = app(ClientSyncService::class)->upsert([
            'company_id' => $company->id,
            'name' => 'Acme Industries Pvt Ltd',
            'email' => 'accounts@acme.test',
            'mobile' => '7777777777',
            'phone' => '7777777777',
            'address' => 'Updated address',
        ]);

        $this->assertSame($existing->id, $client->id);
        $this->assertSame(1, Client::query()->where('company_id', $company->id)->count());
        $this->assertDatabaseHas('clients', [
            'id' => $existing->id,
            'name' => 'Acme Industries Pvt Ltd',
            'email' => 'accounts@acme.test',
            'phone' => '7777777777',
            'address' => 'Updated address',
        ]);
    }

    public function test_it_rejects_conflicting_email_and_phone_that_point_to_different_clients(): void
    {
        $company = $this->createCompanyWithActivePlan();

        $emailClient = Client::create([
            'company_id' => $company->id,
            'name' => 'Acme Accounts',
            'email' => 'accounts@acme.test',
            'mobile' => '9999999999',
            'phone' => '9999999999',
        ]);

        $phoneClient = Client::create([
            'company_id' => $company->id,
            'name' => 'Acme Support',
            'email' => 'support@acme.test',
            'mobile' => '8888888888',
            'phone' => '8888888888',
        ]);

        try {
            app(ClientSyncService::class)->upsert([
                'company_id' => $company->id,
                'name' => 'Acme Industries',
                'email' => $emailClient->email,
                'mobile' => $phoneClient->phone,
                'phone' => $phoneClient->phone,
            ]);

            $this->fail('Expected identity conflict validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('email', $exception->errors());
            $this->assertArrayHasKey('phone', $exception->errors());
        }
    }

    private function createCompanyWithActivePlan(): Company
    {
        $company = Company::create([
            'name' => 'Acme Security',
            'email' => 'company@example.com',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'features' => [],
            'limits' => [
                'clients' => 10,
                'users' => 10,
            ],
            'is_active' => true,
            'is_popular' => false,
        ]);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => '2026-06-01',
            'ends_at' => '2026-06-30',
            'status' => 'active',
            'auto_renew' => true,
            'amount_paid' => 0,
            'usage_baseline' => app(SubscriptionLimitService::class)->snapshotUsageBaseline($company->id),
        ]);

        return $company;
    }
}
