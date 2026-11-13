<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminLiveAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_live_analytics_dashboard(): void
    {
        $superAdmin = User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)->get(route('superadmin.live-analytics'));

        $response->assertOk();
        $response->assertSee('Live Analytics Dashboard');
        $response->assertSee('User Activity Stream');
        $response->assertSee('Auto refresh (20s)');
    }

    public function test_super_admin_can_fetch_live_analytics_json(): void
    {
        $superAdmin = User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin2@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Company::create([
            'name' => 'Acme Security',
            'company_code' => 'AC-1001',
            'email' => 'acme@example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($superAdmin)->getJson(route('superadmin.live-analytics.data', ['period' => 'weekly']));

        $response->assertOk();
        $response->assertJsonStructure([
            'generated_at',
            'users' => ['total', 'active', 'inactive', 'online', 'roles'],
            'companies' => ['total', 'active', 'inactive', 'top_activity'],
            'leads' => ['total', 'by_status', 'by_source'],
            'projects' => ['total', 'by_status', 'by_company'],
            'activity' => ['timeline', 'feed', 'module_usage', 'recent_users'],
            'charts' => ['activity_line', 'role_pie', 'lead_status_pie', 'lead_source_bar', 'company_projects_bar'],
        ]);
    }

    public function test_company_user_cannot_access_live_analytics(): void
    {
        $company = Company::create([
            'name' => 'Acme Security',
            'company_code' => 'AC-1002',
            'email' => 'acme2@example.com',
            'is_active' => true,
        ]);

        $admin = User::factory()->create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'email' => 'admin@acme2.example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('superadmin.live-analytics'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('superadmin.live-analytics.data'))->assertForbidden();
    }
}
