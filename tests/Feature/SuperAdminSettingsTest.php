<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\GlobalSetting;
use App\Models\Role;
use App\Models\Staff;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_global_settings_page(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get(route('super-admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Global Settings');
        $response->assertSee('Secure Configuration');
    }

    public function test_super_admin_can_store_settings_and_encrypt_secret_values(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'google_maps_api_key' => 'maps-test-key',
            'payment_gateway_public_key' => 'pg-public-001',
            'payment_gateway_secret_key' => 'pg-secret-001',
            'payment_gateway_webhook_key' => 'webhook-secret-001',
            'gst_number' => 'GST-12345',
            'business_name' => 'CCTV Workshop Platform',
            'support_email' => 'support@example.com',
            'email_from_name' => 'CCTV Workshop',
            'email_from_address' => 'noreply@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'smtp_username' => 'smtp-user',
            'smtp_password' => 'smtp-secret-001',
            'smtp_encryption' => 'null',
        ]);

        $response->assertRedirect(route('super-admin.settings.index'));
        $response->assertSessionHas('success', 'Settings updated successfully.');

        $this->assertDatabaseHas('global_settings', [
            'key' => 'google_maps_api_key',
            'value' => 'maps-test-key',
            'is_encrypted' => false,
        ]);

        $this->assertDatabaseMissing('global_settings', [
            'key' => 'smtp_encryption',
        ]);

        $secretSetting = GlobalSetting::query()->where('key', 'payment_gateway_secret_key')->firstOrFail();
        $webhookSetting = GlobalSetting::query()->where('key', 'payment_gateway_webhook_key')->firstOrFail();
        $smtpPasswordSetting = GlobalSetting::query()->where('key', 'smtp_password')->firstOrFail();

        $this->assertTrue($secretSetting->is_encrypted);
        $this->assertTrue($webhookSetting->is_encrypted);
        $this->assertTrue($smtpPasswordSetting->is_encrypted);
        $this->assertSame('pg-secret-001', Crypt::decryptString((string) $secretSetting->getRawOriginal('value')));
        $this->assertSame('webhook-secret-001', Crypt::decryptString((string) $webhookSetting->getRawOriginal('value')));
        $this->assertSame('smtp-secret-001', Crypt::decryptString((string) $smtpPasswordSetting->getRawOriginal('value')));
        $this->assertSame('maps-test-key', config('services.google_maps.key'));
        $this->assertSame('pg-public-001', config('services.razorpay.key_id'));
        $this->assertSame('pg-secret-001', config('services.razorpay.key_secret'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('noreply@example.com', config('mail.from.address'));
    }

    public function test_payment_gateway_mode_selects_matching_key_set(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'payment_gateway_mode' => 'test',
            'payment_gateway_public_key' => 'rzp_live_public',
            'payment_gateway_secret_key' => 'rzp-live-secret',
            'payment_gateway_webhook_key' => 'live-webhook-secret',
            'payment_gateway_test_public_key' => 'rzp_test_public',
            'payment_gateway_test_secret_key' => 'rzp-test-secret',
            'payment_gateway_test_webhook_key' => 'test-webhook-secret',
        ])->assertRedirect(route('super-admin.settings.index'));

        $testSecret = GlobalSetting::query()->where('key', 'payment_gateway_test_secret_key')->firstOrFail();
        $this->assertTrue($testSecret->is_encrypted);
        $this->assertSame('test', config('services.razorpay.mode'));
        $this->assertSame('rzp_test_public', config('services.razorpay.key_id'));
        $this->assertSame('rzp-test-secret', config('services.razorpay.key_secret'));
        $this->assertSame('test-webhook-secret', config('services.razorpay.webhook_secret'));

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'payment_gateway_mode' => 'live',
            'payment_gateway_public_key' => 'rzp_live_public',
            'payment_gateway_test_public_key' => 'rzp_test_public',
        ])->assertRedirect(route('super-admin.settings.index'));

        $this->assertSame('live', config('services.razorpay.mode'));
        $this->assertSame('rzp_live_public', config('services.razorpay.key_id'));
        $this->assertSame('rzp-live-secret', config('services.razorpay.key_secret'));
        $this->assertSame('live-webhook-secret', config('services.razorpay.webhook_secret'));
    }

    public function test_blank_webhook_key_keeps_the_stored_secret(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'payment_gateway_webhook_key' => 'webhook-secret-001',
        ]);

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'payment_gateway_webhook_key' => '',
        ]);

        $webhookSetting = GlobalSetting::query()->where('key', 'payment_gateway_webhook_key')->firstOrFail();
        $this->assertTrue($webhookSetting->is_encrypted);
        $this->assertSame('webhook-secret-001', Crypt::decryptString((string) $webhookSetting->getRawOriginal('value')));
    }

    public function test_super_admin_can_store_recurring_billing_settings(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'subscription_recurring_enabled' => '1',
            'subscription_recurring_mode' => 'mandatory',
            'subscription_grace_days' => 5,
            'subscription_grace_access' => 'read_only',
            'subscription_total_count_monthly' => 60,
            'subscription_total_count_yearly' => 5,
            'subscription_trial_requires_mandate' => '0',
            'subscription_renewal_reminder_days' => 2,
        ])->assertRedirect(route('super-admin.settings.index'));

        $this->assertSame('1', setting('subscription_recurring_enabled'));
        $this->assertSame('mandatory', setting('subscription_recurring_mode'));
        $this->assertSame('5', setting('subscription_grace_days'));
        $this->assertSame('read_only', setting('subscription_grace_access'));
        $this->assertSame('60', setting('subscription_total_count_monthly'));
        $this->assertSame('5', setting('subscription_total_count_yearly'));
        $this->assertSame('0', setting('subscription_trial_requires_mandate'));
        $this->assertSame('2', setting('subscription_renewal_reminder_days'));

        $this->actingAs($superAdmin)->get(route('super-admin.settings.index'))
            ->assertOk()
            ->assertSee('Payment Gateway (Razorpay)')
            ->assertSee('Recurring Billing');
    }

    public function test_recurring_billing_settings_are_validated(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'payment_gateway_mode' => 'sandbox',
            'subscription_recurring_mode' => 'sometimes',
            'subscription_grace_days' => 90,
            'subscription_total_count_monthly' => 0,
        ])->assertSessionHasErrorsIn('settingsForm', [
            'payment_gateway_mode',
            'subscription_recurring_mode',
            'subscription_grace_days',
            'subscription_total_count_monthly',
        ]);
    }

    public function test_company_admin_cannot_access_global_settings(): void
    {
        $company = Company::create([
            'name' => 'Restricted Company',
            'company_code' => 'RC-1001',
            'email' => 'restricted@example.com',
            'address' => '10 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        $plan = $this->createPlan();
        $subscription = $this->createActiveSubscription($company, $plan);
        $companyAdminRole = Role::create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
        ]);

        $companyAdmin = User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $companyAdminRole->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        Staff::query()->create([
            'user_id' => $companyAdmin->id,
            'company_id' => $company->id,
            'company_subscription_id' => $subscription->id,
            'name' => 'Admin Staff',
            'employee_id' => 'EMP-ADMIN-01',
            'contact_number' => '9999999999',
            'role_id' => $companyAdminRole->id,
        ]);

        $response = $this->actingAs($companyAdmin)->get(route('super-admin.settings.index'));

        $response->assertForbidden();
    }

    public function test_support_numbers_are_saved_and_shown_in_header_support_menu(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'support_call_number' => '+91 98765 43210',
            'support_whatsapp_number' => '9876501234',
        ])->assertRedirect(route('super-admin.settings.index'));

        $this->assertSame('+91 98765 43210', setting('support_call_number'));
        $this->assertSame('9876501234', setting('support_whatsapp_number'));

        $response = $this->actingAs($superAdmin)->get(route('super-admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Contact support');
        $response->assertSee('href="tel:+919876543210"', false);
        $response->assertSee('href="https://wa.me/919876501234"', false);
    }

    public function test_support_menu_is_hidden_when_no_support_numbers_are_set(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $response = $this->actingAs($superAdmin)->get(route('super-admin.settings.index'));

        $response->assertOk();
        $response->assertDontSee('Contact support');
    }

    public function test_support_numbers_reject_invalid_characters(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)->patch(route('super-admin.settings.update'), [
            'support_call_number' => 'call-me-maybe',
        ])->assertSessionHasErrorsIn('settingsForm', ['support_call_number']);

        $this->assertNull(setting('support_call_number'));
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-settings@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . Str::lower(Str::random(4)),
            'price' => 999,
            'monthly_price' => 999,
            'yearly_price' => 9999,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 10,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);
    }

    private function createActiveSubscription(Company $company, SubscriptionPlan $plan): CompanySubscription
    {
        return CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-SETTINGS-001',
            'license_count' => 1,
            'license_keys' => ['LIC-SETTINGS-001'],
        ]);
    }
}
