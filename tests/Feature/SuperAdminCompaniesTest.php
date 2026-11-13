<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_delete_company_via_query_string(): void
    {
        $superAdmin = User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-delete@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $company = Company::create([
            'name' => 'Delete Me Ltd',
            'company_code' => 'CC-DEL001',
            'email' => 'delete-me@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('super-admin.companies', [
            'delete' => $company->id,
        ]));

        $response->assertRedirect(route('super-admin.companies'));
        $response->assertSessionHas('success', 'Company deleted successfully.');
        $this->assertDatabaseMissing('companies', [
            'id' => $company->id,
        ]);
    }

    public function test_company_user_cannot_delete_companies(): void
    {
        $company = Company::create([
            'name' => 'Acme Security',
            'company_code' => 'CC-DEL002',
            'email' => 'acme-delete@example.com',
            'is_active' => true,
        ]);

        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'companyadmin-delete@example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($companyAdmin)
            ->get(route('super-admin.companies', ['delete' => $company->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
        ]);
    }
}
