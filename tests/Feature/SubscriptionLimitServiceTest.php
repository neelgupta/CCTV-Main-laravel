<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\LeadStatus;
use App\Models\Project;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SubscriptionLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_plan_gives_fresh_module_allowance_on_top_of_existing_usage(): void
    {
        Carbon::setTestNow('2026-04-28 10:00:00');

        $company = Company::create([
            'name' => 'Acme Security',
            'email' => 'acme@example.com',
        ]);

        $status = LeadStatus::create([
            'company_id' => $company->id,
            'name' => 'New Lead',
            'is_default' => true,
            'order' => 1,
        ]);

        $category = LeadCategory::create([
            'company_id' => $company->id,
            'name' => 'Sales',
        ]);

        Lead::create([
            'company_id' => $company->id,
            'lead_status_id' => $status->id,
            'lead_category_id' => $category->id,
            'client_name' => 'Existing Lead',
            'estimated_value' => 1000,
            'priority' => 'medium',
        ]);

        Project::create([
            'company_id' => $company->id,
            'name' => 'Existing Project',
            'client_name' => 'Existing Client',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'features' => [],
            'limits' => [
                'leads' => 1,
                'projects' => 1,
                'storage_mb' => 100,
            ],
            'is_active' => true,
            'is_popular' => false,
        ]);

        $service = app(SubscriptionLimitService::class);
        $baseline = $service->snapshotUsageBaseline($company->id);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => '2026-04-28',
            'ends_at' => '2026-05-27',
            'status' => 'active',
            'auto_renew' => true,
            'amount_paid' => 0,
            'usage_baseline' => $baseline,
        ]);

        $this->assertSame(0, $service->usageCount($company->id, 'leads'));
        $this->assertSame(0, $service->usageCount($company->id, 'projects'));

        $service->ensureWithinLimit($company->id, 'leads', 1, 'client_name');
        $service->ensureWithinLimit($company->id, 'projects', 1, 'name');

        Lead::create([
            'company_id' => $company->id,
            'lead_status_id' => $status->id,
            'lead_category_id' => $category->id,
            'client_name' => 'New Plan Lead',
            'estimated_value' => 2500,
            'priority' => 'high',
        ]);

        Project::create([
            'company_id' => $company->id,
            'name' => 'New Plan Project',
            'client_name' => 'New Plan Client',
        ]);

        $this->assertSame(1, $service->usageCount($company->id, 'leads'));
        $this->assertSame(1, $service->usageCount($company->id, 'projects'));

        try {
            $service->ensureWithinLimit($company->id, 'leads', 1, 'client_name');
            $this->fail('Expected the lead quota from the new plan to be exhausted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('client_name', $exception->errors());
        }

        try {
            $service->ensureWithinLimit($company->id, 'projects', 1, 'name');
            $this->fail('Expected the project quota from the new plan to be exhausted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }
    }

    public function test_each_module_uses_its_own_fresh_limit_after_subscription_change(): void
    {
        Carbon::setTestNow('2026-04-28 10:00:00');

        $company = Company::create([
            'name' => 'Beta Surveillance',
            'email' => 'beta@example.com',
        ]);

        $status = LeadStatus::create([
            'company_id' => $company->id,
            'name' => 'New Lead',
            'is_default' => true,
            'order' => 1,
        ]);

        $category = LeadCategory::create([
            'company_id' => $company->id,
            'name' => 'Construction',
        ]);

        Lead::create([
            'company_id' => $company->id,
            'lead_status_id' => $status->id,
            'lead_category_id' => $category->id,
            'client_name' => 'Old Lead',
            'estimated_value' => 1500,
            'priority' => 'medium',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Silver',
            'slug' => 'silver',
            'price' => 0,
            'billing_cycle' => 'monthly',
            'features' => [],
            'limits' => [
                'leads' => 1,
                'projects' => 1,
                'storage_mb' => 100,
            ],
            'is_active' => true,
            'is_popular' => false,
        ]);

        $service = app(SubscriptionLimitService::class);

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'starts_at' => '2026-04-28',
            'ends_at' => '2026-05-27',
            'status' => 'active',
            'auto_renew' => true,
            'amount_paid' => 0,
            'usage_baseline' => $service->snapshotUsageBaseline($company->id),
        ]);

        Lead::create([
            'company_id' => $company->id,
            'lead_status_id' => $status->id,
            'lead_category_id' => $category->id,
            'client_name' => 'Fresh Lead',
            'estimated_value' => 2200,
            'priority' => 'high',
        ]);

        $this->assertSame(1, $service->usageCount($company->id, 'leads'));
        $this->assertSame(0, $service->usageCount($company->id, 'projects'));

        $service->ensureWithinLimit($company->id, 'projects', 1, 'name');

        Project::create([
            'company_id' => $company->id,
            'name' => 'Fresh Project',
            'client_name' => 'Fresh Client',
        ]);

        $this->assertSame(1, $service->usageCount($company->id, 'projects'));

        try {
            $service->ensureWithinLimit($company->id, 'leads', 1, 'client_name');
            $this->fail('Expected the lead limit to be exhausted independently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('client_name', $exception->errors());
        }

        try {
            $service->ensureWithinLimit($company->id, 'projects', 1, 'name');
            $this->fail('Expected the project limit to be exhausted independently.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }
    }
}
