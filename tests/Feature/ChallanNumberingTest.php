<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectChallan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ChallanNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_company_has_its_own_challan_sequence(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();
        $projectA = $this->createProject($companyA);
        $projectB = $this->createProject($companyB);

        $first = $this->createChallan($projectA);
        $this->createChallan($projectB);
        $this->createChallan($projectB);
        $second = $this->createChallan($projectA);

        $this->assertSame('CHL-0001', $first->challan_no);
        $this->assertSame('CHL-0002', $second->challan_no);
        $this->assertSame($companyA->id, $first->company_id);
        $this->assertSame(
            ['CHL-0001', 'CHL-0002'],
            ProjectChallan::query()->where('company_id', $companyB->id)->orderBy('id')->pluck('challan_no')->all()
        );
    }

    public function test_numbers_used_by_another_company_are_still_available(): void
    {
        $companyA = $this->createCompany();
        $companyB = $this->createCompany();
        $this->createChallan($this->createProject($companyB));

        $this->assertFalse($companyA->challanNumberExists('CHL-0001'));
        $this->assertTrue($companyB->challanNumberExists('CHL-0001'));
        $this->assertSame(1, $companyA->nextAvailableChallanSequence(1));
        $this->assertSame(2, $companyB->nextAvailableChallanSequence(1));
    }

    public function test_configured_sequence_skips_only_numbers_used_by_the_same_company(): void
    {
        $company = $this->createCompany(['challan_prefix' => 'DC', 'challan_sequence' => 39]);
        $other = $this->createCompany(['challan_prefix' => 'DC', 'challan_sequence' => 39]);
        $this->createChallan($this->createProject($other));

        $this->assertSame('DC-0039', $this->createChallan($this->createProject($company))->challan_no);
        $this->assertSame('DC-0040', $this->createChallan($this->createProject($company))->challan_no);
        $this->assertSame(41, (int) $company->fresh()->challan_sequence);
    }

    private function createCompany(array $attributes = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Company ' . Str::random(6),
            'email' => 'company-' . Str::lower(Str::random(8)) . '@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ], $attributes));
    }

    private function createProject(Company $company): Project
    {
        return Project::create([
            'company_id' => $company->id,
            'reference_no' => 'PRJ-' . Str::upper(Str::random(8)),
            'name' => 'Project ' . Str::random(6),
            'client_name' => 'Client',
        ]);
    }

    private function createChallan(Project $project): ProjectChallan
    {
        return ProjectChallan::create([
            'project_id' => $project->id,
            'production_ids' => [],
            'status' => 'created',
        ]);
    }
}
