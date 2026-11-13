<?php

namespace Tests\Feature;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\AmcVisitAssignmentHistory;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use App\Services\AmcTechnicianAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AmcTechnicianAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_auto_assignment_distributes_visits_evenly_across_available_technicians(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 2, 0, 0, 0));

        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@alpha.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $technicians = collect([
            ['name' => 'Rahul', 'email' => 'rahul@example.com'],
            ['name' => 'Amit', 'email' => 'amit@example.com'],
            ['name' => 'Karan', 'email' => 'karan@example.com'],
        ])->map(function (array $data) use ($company, $role): Staff {
            $user = User::factory()->create([
                'company_id' => $company->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => 'employee',
                'role_id' => $role->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            return Staff::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'name' => $data['name'],
                'employee_id' => strtoupper($data['name']) . '-001',
                'contact_number' => '9999999999',
                'join_date' => '2026-01-01',
                'role_id' => $role->id,
                'amc_daily_visit_limit' => 5,
            ]);
        });

        $amc = Amc::create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
            'contract_no' => 'AMC-2026-27-0001',
            'customer_name' => 'Kaki',
            'project_name' => 'Office CCTV',
            'mobile_number' => '9876543210',
            'address' => '123 Main Street',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'manual',
            'total_visits' => 10,
            'scheduled_visits' => 0,
            'remaining_visits' => 10,
            'amc_amount' => 2500,
            'total_amount' => 2500,
            'payment_status' => 'pending',
        ]);

        foreach (range(1, 10) as $visitNo) {
            AmcVisit::create([
                'amc_id' => $amc->id,
                'visit_no' => $visitNo,
                'visit_type' => 'scheduled',
                'visit_date' => '2026-07-04',
                'status' => 'pending',
                'technician' => null,
            ]);
        }

        $summary = app(AmcTechnicianAssignmentService::class)->assignDueVisits(Carbon::create(2026, 7, 2, 0, 0, 0));

        $this->assertSame(10, $summary['assigned']);
        $this->assertSame(0, $summary['skipped']);

        $counts = AmcVisit::query()
            ->where('amc_id', $amc->id)
            ->whereDate('visit_date', '2026-07-04')
            ->whereNotNull('technician')
            ->get()
            ->groupBy('technician')
            ->map->count();

        $this->assertSame(10, $counts->sum());
        $this->assertLessThanOrEqual(1, $counts->max() - $counts->min());
        $this->assertSame(10, AmcVisitAssignmentHistory::query()->count());

        Carbon::setTestNow();
    }

    public function test_manual_override_records_assignment_history(): void
    {
        $company = Company::create([
            'name' => 'Beta Systems',
            'company_code' => 'CC-BETA1',
            'email' => 'beta@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@beta.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $rahulUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Rahul',
            'email' => 'rahul@beta.example.com',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $amitUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Amit',
            'email' => 'amit@beta.example.com',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $rahul = Staff::create([
            'company_id' => $company->id,
            'user_id' => $rahulUser->id,
            'name' => 'Rahul',
            'employee_id' => 'RAHUL-001',
            'contact_number' => '9999999999',
            'join_date' => '2026-01-01',
            'role_id' => $role->id,
            'amc_daily_visit_limit' => 5,
        ]);

        $amit = Staff::create([
            'company_id' => $company->id,
            'user_id' => $amitUser->id,
            'name' => 'Amit',
            'employee_id' => 'AMIT-001',
            'contact_number' => '9999999998',
            'join_date' => '2026-01-01',
            'role_id' => $role->id,
            'amc_daily_visit_limit' => 5,
        ]);

        $amc = Amc::create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
            'contract_no' => 'AMC-2026-27-0002',
            'customer_name' => 'Kaki',
            'project_name' => 'Office CCTV',
            'mobile_number' => '9876543210',
            'address' => '123 Main Street',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'manual',
            'total_visits' => 1,
            'scheduled_visits' => 0,
            'remaining_visits' => 1,
            'amc_amount' => 2500,
            'total_amount' => 2500,
            'payment_status' => 'pending',
        ]);

        $visit = AmcVisit::create([
            'amc_id' => $amc->id,
            'visit_no' => 1,
            'visit_type' => 'scheduled',
            'visit_date' => '2026-07-04',
            'status' => 'pending',
            'technician' => 'Rahul',
        ]);

        app(AmcTechnicianAssignmentService::class)->updateVisitTechnician(
            $visit,
            $amit,
            $admin,
            'Changed by admin',
            'manual'
        );

        $visit->refresh();

        $this->assertSame('Amit', $visit->technician);
        $this->assertSame(1, AmcVisitAssignmentHistory::query()->where('amc_visit_id', $visit->id)->count());

        $history = AmcVisitAssignmentHistory::query()->where('amc_visit_id', $visit->id)->first();
        $this->assertSame('Rahul', $history->old_technician_name);
        $this->assertSame('Amit', $history->new_technician_name);
        $this->assertSame('manual', $history->assignment_source);
        $this->assertSame($admin->id, (int) $history->changed_by);
    }

    public function test_assign_technician_if_required_assigns_due_visit_once_and_skips_when_already_assigned(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 0, 0, 0));

        $company = Company::create([
            'name' => 'Gamma Systems',
            'company_code' => 'CC-GAMMA1',
            'email' => 'gamma@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@gamma.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $role = Role::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'employee'],
            ['name' => 'Employee']
        );

        $techUser = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Rahul',
            'email' => 'rahul@gamma.example.com',
            'role' => 'employee',
            'role_id' => $role->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $technician = Staff::create([
            'company_id' => $company->id,
            'user_id' => $techUser->id,
            'name' => 'Rahul',
            'employee_id' => 'RAHUL-002',
            'contact_number' => '9999999997',
            'join_date' => '2026-01-01',
            'role_id' => $role->id,
            'amc_daily_visit_limit' => 5,
        ]);

        $amc = Amc::create([
            'company_id' => $company->id,
            'created_by' => $admin->id,
            'contract_no' => 'AMC-2026-27-0003',
            'customer_name' => 'Kaki',
            'project_name' => 'Office CCTV',
            'mobile_number' => '9876543210',
            'address' => '123 Main Street',
            'start_date' => '2026-07-01',
            'end_date' => '2026-12-31',
            'amc_type' => 'non_comprehensive',
            'visit_frequency' => 'manual',
            'total_visits' => 1,
            'scheduled_visits' => 0,
            'remaining_visits' => 1,
            'amc_amount' => 2500,
            'total_amount' => 2500,
            'payment_status' => 'pending',
        ]);

        $visit = AmcVisit::create([
            'amc_id' => $amc->id,
            'visit_no' => 1,
            'visit_type' => 'scheduled',
            'visit_date' => '2026-07-03',
            'status' => 'pending',
            'technician' => null,
        ]);

        $service = app(AmcTechnicianAssignmentService::class);

        $this->assertTrue($service->assignTechnicianIfRequired($visit, Carbon::create(2026, 7, 1, 0, 0, 0), 'auto'));

        $visit->refresh();
        $this->assertNotEmpty($visit->technician);
        $this->assertSame(1, AmcVisitAssignmentHistory::query()->where('amc_visit_id', $visit->id)->count());

        $this->assertFalse($service->assignTechnicianIfRequired($visit, Carbon::create(2026, 7, 1, 0, 0, 0), 'auto'));

        Carbon::setTestNow();
    }
}
