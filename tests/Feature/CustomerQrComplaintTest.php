<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerQrComplaintTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_form_opens_for_user_logged_in_to_another_company(): void
    {
        $client = $this->createClient($this->createCompany('Owner Company', '9000000001'));
        $otherUser = $this->createCompanyAdmin($this->createCompany('Other Company', '9000000002'));

        $this->actingAs($otherUser)
            ->get(route('customer.support.qr.show', ['token' => $client->qr_token]))
            ->assertOk()
            ->assertSee('Ganesh Enterprise')
            ->assertDontSee('Invalid QR Code');
    }

    public function test_qr_complaint_from_another_company_user_is_filed_under_client_company(): void
    {
        $ownerCompany = $this->createCompany('Owner Company', '9000000001');
        $client = $this->createClient($ownerCompany);
        $otherUser = $this->createCompanyAdmin($this->createCompany('Other Company', '9000000002'));

        $this->actingAs($otherUser)
            ->post(route('customer.support.qr.store', ['token' => $client->qr_token]), $this->complaintPayload())
            ->assertOk();

        $ticket = Ticket::query()->where('source_type', 'qr_complaint')->sole();

        $this->assertSame($ownerCompany->id, (int) $ticket->company_id);
        $this->assertSame('Camera offline', $ticket->subject);
    }

    public function test_stale_form_token_reloads_qr_form_instead_of_logging_out(): void
    {
        $this->enableCsrfProtection();

        $client = $this->createClient($this->createCompany('Owner Company', '9000000001'));
        $user = $this->createCompanyAdmin($this->createCompany('Other Company', '9000000002'));

        $response = $this->actingAs($user)
            ->withSession(['_token' => 'current-token'])
            ->post(route('customer.support.qr.store', ['token' => $client->qr_token]), [
                '_token' => 'stale-token',
                ...$this->complaintPayload(),
            ]);

        $response->assertRedirect(route('customer.support.qr.show', ['token' => $client->qr_token]));
        $response->assertSessionHas('qr_form_error');
        $response->assertSessionHasInput('subject', 'Camera offline');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, Ticket::query()->count());
    }

    public function test_stale_form_token_elsewhere_still_redirects_to_login(): void
    {
        $this->enableCsrfProtection();

        $user = $this->createCompanyAdmin($this->createCompany('Owner Company', '9000000001'));

        $this->actingAs($user)
            ->withSession(['_token' => 'current-token'])
            ->post(route('logout'), ['_token' => 'stale-token'])
            ->assertRedirect(route('login'));
    }

    private function enableCsrfProtection(): void
    {
        // CSRF checks are skipped while running tests; force them on for these cases.
        $this->app->instance(ValidateCsrfToken::class, new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
    }

    private function complaintPayload(): array
    {
        return [
            'subject' => 'Camera offline',
            'description' => 'Gate camera shows no signal since morning.',
            'priority' => 'normal',
            'category' => 'support',
        ];
    }

    private function createCompany(string $name, string $mobile): Company
    {
        return Company::create([
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'@example.com',
            'mobile_number' => $mobile,
            'phone_number' => $mobile,
            'is_active' => true,
            'is_onboarded' => true,
            'onboarding_step' => 5,
        ]);
    }

    private function createCompanyAdmin(Company $company): User
    {
        return User::factory()->create([
            'company_id' => $company->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createClient(Company $company): Client
    {
        return Client::withoutGlobalScope('company')->create([
            'company_id' => $company->id,
            'name' => 'Ganesh Enterprise',
            'email' => 'ganesh@example.com',
            'mobile' => '8982548147',
            'status' => 'active',
        ]);
    }
}
