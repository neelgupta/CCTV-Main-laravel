<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCompanyMailSettingRequest;
use App\Http\Requests\UpdateCompanyMailSettingRequest;
use App\Mail\CompanyMailSettingsTestMail;
use App\Models\Company;
use App\Models\CompanyMailSetting;
use App\Services\CompanyMailService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CompanyMailSettingController extends Controller
{
    private function resolveCompanyForCurrentUser(): ?Company
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->company_id) {
            return Company::query()->find($user->company_id);
        }

        return null;
    }

    public function store(StoreCompanyMailSettingRequest $request)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);
        $company->loadMissing('mailSetting');
        $isCreate = ! $company->mailSetting;

        DB::transaction(function () use ($company, $request, $isCreate): void {
            CompanyMailSetting::query()->updateOrCreate(
                ['company_id' => $company->id],
                $this->payloadFromRequest($request->validated(), $isCreate)
            );
        });

        return redirect()
            ->route('settings.company.index', ['tab' => 'crm'])
            ->with('success', 'Company mail settings saved successfully.');
    }

    public function update(UpdateCompanyMailSettingRequest $request)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);

        DB::transaction(function () use ($company, $request): void {
            $mailSetting = CompanyMailSetting::query()->firstOrNew(['company_id' => $company->id]);
            $mailSetting->fill($this->payloadFromRequest($request->validated(), false));
            $mailSetting->save();
        });

        return redirect()
            ->route('settings.company.index', ['tab' => 'crm'])
            ->with('success', 'Company mail settings updated successfully.');
    }

    public function test(UpdateCompanyMailSettingRequest $request, CompanyMailService $companyMailService)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);

        $user = auth()->user();
        abort_unless($user?->email, 422, 'Your user account does not have an email address.');

        $data = $request->validated();
        $company->loadMissing('mailSetting');
        $mailSetting = $company->mailSetting ? $company->mailSetting->replicate() : new CompanyMailSetting();
        $mailSetting->fill($this->payloadFromRequest($data, false));

        if (blank($data['password'] ?? null) && blank($company->mailSetting?->password)) {
            return back()
                ->withErrors(['password' => 'Please enter a password before testing SMTP.'])
                ->withInput();
        }

        if (blank($mailSetting->password) && ! blank($company->mailSetting?->password)) {
            $mailSetting->password = $company->mailSetting->password;
        }

        try {
            $companyMailService->configureSetting($mailSetting);
            Mail::to($user->email)->send(new CompanyMailSettingsTestMail($company));
        } catch (\Throwable $exception) {
            return back()->with('error', 'Test email failed: ' . $exception->getMessage())->withInput();
        }

        return back()->with('success', 'Test email sent successfully to your email address.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function payloadFromRequest(array $data, bool $isCreate): array
    {
        $payload = [
            'mailer' => $data['mailer'] ?? 'smtp',
            'host' => $data['host'],
            'port' => (int) $data['port'],
            'username' => $data['username'],
            'encryption' => $data['encryption'] ?? null,
            'from_email' => $data['from_email'],
            'from_name' => $data['from_name'],
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
        } elseif (! $isCreate) {
            unset($payload['password']);
        } else {
            $payload['password'] = '';
        }

        return $payload;
    }
}
