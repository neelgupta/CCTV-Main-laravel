<?php

namespace Tests\Feature\Auth;

use App\Models\Company;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'company_name' => 'Test Company',
            'email' => 'test@example.com',
            'phone_number' => '9876543210',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'terms' => '1',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));

        $company = Company::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($company);
        $this->assertSame(4, $company->onboarding_step);
        $this->assertFalse((bool) $company->is_onboarded);

        $user = User::query()->where('email', 'test@example.com')->first();

        $this->assertNotNull($user->terms_accepted_at);
        $this->assertSame(config('legal.terms_version'), $user->terms_version);
        $this->assertSame('127.0.0.1', $user->terms_accepted_ip);
    }

    public function test_registration_requires_accepting_terms(): void
    {
        $response = $this->from(route('register.company'))
            ->post(route('register.company.store'), [
                'company_name' => 'Test Company',
                'email' => 'test@example.com',
                'phone_number' => '9876543210',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
            ]);

        $response->assertRedirect(route('register.company'));
        $response->assertSessionHasErrors([
            'terms' => 'Please accept the Terms & Conditions to create your account.',
        ]);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_screen_links_to_terms(): void
    {
        $this->get(route('register.company'))
            ->assertOk()
            ->assertSee('href="https://www.cctvproone.com/terms-conditions.html"', false);
    }

    public function test_register_company_rejects_duplicate_email_with_clear_message(): void
    {
        Company::create([
            'name' => 'Existing Company',
            'email' => 'duplicate@example.com',
            'mobile_number' => '9876543210',
            'phone_number' => '9876543210',
            'is_active' => true,
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);

        $response = $this->from(route('register.company'))
            ->post(route('register.company.store'), [
                'company_name' => 'Another Company',
                'email' => 'duplicate@example.com',
                'phone_number' => '9876543210',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'terms' => '1',
            ]);

        $response->assertRedirect(route('register.company'));
        $response->assertSessionHasErrors([
            'email' => 'This email is already registered with an existing company or user account.',
        ]);
    }

    public function test_register_company_rejects_duplicate_mobile_number_with_clear_message(): void
    {
        Company::create([
            'name' => 'Existing Company',
            'email' => 'existing-mobile@example.com',
            'mobile_number' => '9123456780',
            'phone_number' => '9123456780',
            'is_active' => true,
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);

        $response = $this->from(route('register.company'))
            ->post(route('register.company.store'), [
                'company_name' => 'Another Company',
                'email' => 'another@example.com',
                'phone_number' => '9123456780',
                'password' => 'Password@123',
                'password_confirmation' => 'Password@123',
                'terms' => '1',
            ]);

        $response->assertRedirect(route('register.company'));
        $response->assertSessionHasErrors([
            'phone_number' => 'This mobile number is already registered with an existing company account.',
        ]);
    }

    public function test_onboarding_plan_step_displays_yearly_discount_label_for_discounted_plans(): void
    {
        $company = Company::create([
            'name' => 'Discount Test Company',
            'email' => 'discount@example.com',
            'mobile_number' => '9876543210',
            'phone_number' => '9876543210',
            'is_active' => true,
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'owner@example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 0,
            'monthly_price' => 199,
            'yearly_price' => 1999,
            'billing_cycle' => 'monthly',
            'description' => 'Starter plan',
            'staff_limit' => 1,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);

        $response = $this->actingAs($user)->get(route('onboarding.step4'));

        $response->assertOk();
        $response->assertSee('"discountLabel":"16.3% discount"', false);
    }
}
