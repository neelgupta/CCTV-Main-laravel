<?php

namespace Tests\Feature\Api;

use App\Models\BoqCategory;
use App\Models\BoqProduct;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffProductListTest extends TestCase
{
    use RefreshDatabase;

    public function test_logged_in_staff_can_list_only_active_products_from_their_company(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
            'enable_boq' => true,
        ]);

        $otherCompany = Company::create([
            'name' => 'Beta Systems',
            'company_code' => 'CC-BETA01',
            'email' => 'beta@example.com',
            'is_active' => true,
            'enable_boq' => true,
        ]);

        $staffUser = $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');

        $cameraCategory = BoqCategory::create([
            'company_id' => $company->id,
            'name' => 'Cameras',
        ]);

        $otherCategory = BoqCategory::create([
            'company_id' => $otherCompany->id,
            'name' => 'Accessories',
        ]);

        $visibleProduct = BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $cameraCategory->id,
            'name' => 'Hikvision Dome Camera',
            'brand' => 'Hikvision',
            'model' => 'DS-2CE1A',
            'unit' => 'Pcs',
            'price' => 25000,
            'gst' => 18,
            'description' => 'Indoor camera',
            'is_active' => true,
        ]);

        BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $cameraCategory->id,
            'name' => 'Inactive Camera',
            'unit' => 'Pcs',
            'price' => 1000,
            'gst' => 18,
            'is_active' => false,
        ]);

        BoqProduct::create([
            'company_id' => $otherCompany->id,
            'category_id' => $otherCategory->id,
            'name' => 'Other Company Product',
            'unit' => 'Pcs',
            'price' => 500,
            'gst' => 18,
            'is_active' => true,
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/products');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.pagination.current_page', 1)
            ->assertJsonPath('meta.pagination.per_page', 20)
            ->assertJsonPath('meta.filters.include_inactive', false)
            ->assertJsonPath('meta.categories.0.name', 'Cameras')
            ->assertJsonCount(1, 'products')
            ->assertJsonPath('products.0.id', $visibleProduct->id)
            ->assertJsonPath('products.0.name', 'Hikvision Dome Camera')
            ->assertJsonPath('products.0.brand', 'Hikvision')
            ->assertJsonPath('products.0.model', 'DS-2CE1A')
            ->assertJsonPath('products.0.unit', 'Pcs')
            ->assertJsonPath('products.0.price', 25000.0)
            ->assertJsonPath('products.0.gst', 18.0)
            ->assertJsonPath('products.0.category.id', $cameraCategory->id)
            ->assertJsonPath('products.0.category.name', 'Cameras');
    }

    public function test_staff_product_list_supports_search_category_and_include_inactive_filters(): void
    {
        $company = Company::create([
            'name' => 'Alpha Systems',
            'company_code' => 'CC-ALPHA1',
            'email' => 'alpha@example.com',
            'is_active' => true,
            'enable_boq' => true,
        ]);

        $this->createEmployeeAccount($company, 'tech.alpha@example.com', 'EMP-1001', 'Secret@123', 'Alpha Tech');

        $cameraCategory = BoqCategory::create([
            'company_id' => $company->id,
            'name' => 'Cameras',
        ]);

        $accessoryCategory = BoqCategory::create([
            'company_id' => $company->id,
            'name' => 'Accessories',
        ]);

        BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $cameraCategory->id,
            'name' => 'Indoor Dome Camera',
            'brand' => 'Hikvision',
            'unit' => 'Pcs',
            'price' => 12000,
            'gst' => 18,
            'is_active' => true,
        ]);

        $inactiveMatchingProduct = BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $cameraCategory->id,
            'name' => 'Legacy Dome Camera',
            'brand' => 'CP Plus',
            'unit' => 'Pcs',
            'price' => 8000,
            'gst' => 18,
            'description' => 'Legacy dome model',
            'is_active' => false,
        ]);

        BoqProduct::create([
            'company_id' => $company->id,
            'category_id' => $accessoryCategory->id,
            'name' => 'RJ45 Connector',
            'unit' => 'Box',
            'price' => 300,
            'gst' => 18,
            'is_active' => true,
        ]);

        $token = $this->loginAndGetToken('EMP-1001', 'CC-ALPHA1', 'Secret@123');

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/staff/products?search=dome&category_id=' . $cameraCategory->id . '&include_inactive=1');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.filters.search', 'dome')
            ->assertJsonPath('meta.filters.category_id', $cameraCategory->id)
            ->assertJsonPath('meta.filters.include_inactive', true)
            ->assertJsonCount(2, 'products')
            ->assertJsonPath('products.0.category.name', 'Cameras')
            ->assertJsonPath('products.1.id', $inactiveMatchingProduct->id)
            ->assertJsonPath('products.1.is_active', false);
    }

    public function test_staff_product_list_requires_authentication(): void
    {
        $this->getJson('/api/staff/products')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthenticated.');
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
