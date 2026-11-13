<?php

namespace Tests\Feature;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AmcVisitRangeFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_amc_visit_range_cards_filter_rows_by_next_visit_date(): void
    {
        $company = Company::withoutEvents(function () {
            return Company::create([
                'name' => 'Test Company',
                'email' => 'test-company@example.com',
            ]);
        });

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@example.com',
            'role' => 'company_admin',
        ]);

        $today = Carbon::today();

        $todayAmc = $this->createAmcWithVisit($company->id, 'AMC-TODAY-001', $today->copy(), 'pending');
        $nextMonthAmc = $this->createAmcWithVisit($company->id, 'AMC-NEXT-001', $today->copy()->addMonthNoOverflow()->addDays(3), 'completed');

        $response = $this->actingAs($user)->get(route('amcs.index', ['visit_range' => 'today']));

        $response->assertOk();
        $response->assertSee('Today AMC', false);
        $response->assertSee($todayAmc->contract_no, false);
        $response->assertDontSee($nextMonthAmc->contract_no, false);

        $nextMonthResponse = $this->actingAs($user)->get(route('amcs.index', ['visit_range' => 'next_month']));

        $nextMonthResponse->assertOk();
        $nextMonthResponse->assertSee('Next Month AMC', false);
        $nextMonthResponse->assertSee($nextMonthAmc->contract_no, false);
        $nextMonthResponse->assertDontSee($todayAmc->contract_no, false);
    }

    public function test_amc_visit_status_cards_filter_rows_by_last_and_next_visit_status(): void
    {
        $company = Company::withoutEvents(function () {
            return Company::create([
                'name' => 'Test Company',
                'email' => 'test-company@example.com',
            ]);
        });

        $user = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@example.com',
            'role' => 'company_admin',
        ]);

        $today = Carbon::today();

        $lastPendingAmc = $this->createAmcWithVisit($company->id, 'AMC-LAST-PENDING', $today->copy()->subDays(2), 'pending');
        $lastCompletedAmc = $this->createAmcWithVisit($company->id, 'AMC-LAST-COMPLETED', $today->copy()->subDays(4), 'completed');
        $nextScheduledAmc = $this->createAmcWithVisit($company->id, 'AMC-NEXT-SCHEDULED', $today->copy()->addDays(2), 'scheduled');

        $response = $this->actingAs($user)->get(route('amcs.index', ['last_visit_status' => 'pending']));

        $response->assertOk();
        $response->assertSee($lastPendingAmc->contract_no, false);
        $response->assertDontSee($lastCompletedAmc->contract_no, false);
        $response->assertDontSee($nextScheduledAmc->contract_no, false);

        $nextStatusResponse = $this->actingAs($user)->get(route('amcs.index', ['next_visit_status' => 'scheduled']));

        $nextStatusResponse->assertOk();
        $nextStatusResponse->assertSee($nextScheduledAmc->contract_no, false);
        $nextStatusResponse->assertDontSee($lastPendingAmc->contract_no, false);
        $nextStatusResponse->assertDontSee($lastCompletedAmc->contract_no, false);
    }

    private function createAmcWithVisit(int $companyId, string $contractNo, Carbon $visitDate, string $status): Amc
    {
        $amc = Amc::create([
            'company_id' => $companyId,
            'contract_no' => $contractNo,
            'customer_name' => $contractNo . ' Customer',
            'project_name' => $contractNo . ' Project',
            'start_date' => Carbon::today()->subMonth()->toDateString(),
            'end_date' => Carbon::today()->addYear()->toDateString(),
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'monthly',
            'payment_status' => 'paid',
            'amc_amount' => 10000,
            'total_amount' => 10000,
        ]);

        AmcVisit::create([
            'amc_id' => $amc->id,
            'visit_no' => 1,
            'visit_date' => $visitDate->toDateString(),
            'status' => $status,
        ]);

        return $amc;
    }
}
