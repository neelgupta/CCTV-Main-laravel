<?php

namespace Tests\Unit;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AmcLastVisitStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_and_next_visit_statuses_are_exposed_separately(): void
    {
        $company = Company::withoutEvents(function () {
            return Company::create([
                'name' => 'Test Company',
                'email' => 'test-company@example.com',
            ]);
        });

        User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'role' => 'company_admin',
        ]);

        $amc = Amc::create([
            'company_id' => $company->id,
            'contract_no' => 'AMC-TEST-0001',
            'customer_name' => 'Test Customer',
            'project_name' => 'Test Project',
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
            'visit_date' => Carbon::today()->subDays(3)->toDateString(),
            'status' => 'pending',
        ]);

        AmcVisit::create([
            'amc_id' => $amc->id,
            'visit_no' => 2,
            'visit_date' => Carbon::today()->addMonths(3)->toDateString(),
            'status' => 'completed',
        ]);

        $amc->load('visits');

        $this->assertSame('pending', $amc->lastVisitStatusKey());
        $this->assertSame('Pending', $amc->lastVisitStatusLabel());
        $this->assertSame('completed', $amc->nextVisitStatusKey());
        $this->assertSame('Completed', $amc->nextVisitStatusLabel());
        $this->assertSame(Carbon::today()->addMonths(3)->toDateString(), $amc->nextVisit()?->visit_date?->toDateString());
    }
}
