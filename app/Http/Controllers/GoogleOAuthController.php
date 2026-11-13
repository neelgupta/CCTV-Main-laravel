<?php

namespace App\Http\Controllers;

use App\Models\CompanyIntegration;
use App\Models\DriveConnection;
use App\Services\DriveConnectionManager;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class GoogleOAuthController extends Controller
{
    public function __construct(private GoogleOAuthService $google)
    {
    }

    /**
     * Kick off the Google consent flow for a given service (drive | gmail).
     */
    public function redirect(Request $request, string $service)
    {
        $this->authorizeAdmin();

        if (! $this->google->isValidService($service)) {
            abort(404);
        }

        if (! $this->google->isConfigured()) {
            return redirect()->route('integrations.index')
                ->with('error', 'Auto Connect is unavailable — the platform administrator has not configured Google OAuth yet.');
        }

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);
        $request->session()->put('google_oauth_service', $service);

        return redirect()->away($this->google->buildAuthUrl($service, $state));
    }

    /**
     * Handle the Google OAuth callback and persist the connection.
     */
    public function callback(Request $request)
    {
        $this->authorizeAdmin();

        $service = $request->session()->pull('google_oauth_service');
        $expectedState = $request->session()->pull('google_oauth_state');

        if ($request->filled('error')) {
            return redirect()->route('integrations.index')
                ->with('error', 'Google authorization was cancelled.');
        }

        if (! $service || ! $expectedState || $request->input('state') !== $expectedState) {
            return redirect()->route('integrations.index')
                ->with('error', 'Authorization failed: invalid or expired session state. Please try again.');
        }

        $code = $request->input('code');
        if (blank($code)) {
            return redirect()->route('integrations.index')
                ->with('error', 'Authorization failed: no authorization code returned.');
        }

        $email = null;

        try {
            $tokens  = $this->google->exchangeCode($code);
            $account = $this->google->fetchAccountInfo($tokens['access_token'] ?? '');
            $email   = $account['email'] ?? null;

            $this->persistConnection($service, $tokens, $email, $account['sub'] ?? null);
        } catch (Throwable $e) {
            return redirect()->route('integrations.index')
                ->with('error', 'Auto Connect failed: '.$e->getMessage());
        }

        $label = $service === GoogleOAuthService::SERVICE_DRIVE ? 'Google Drive' : 'Gmail';
        $target = Auth::user()->isSuperAdmin() ? route('super-admin.integrations') : route('integrations.index');

        return redirect()->to($target)
            ->with('success', "{$label} connected successfully" . ($email ? " as {$email}." : '.'));
    }

    /**
     * Route the OAuth result to the right store: Drive connections go through the
     * DriveConnectionManager (with audit logging); Gmail stays on company_integrations.
     */
    private function persistConnection(string $service, array $tokens, ?string $email, ?string $accountId): void
    {
        if ($service === GoogleOAuthService::SERVICE_DRIVE) {
            $this->persistDriveConnection($tokens, $email, $accountId);
            return;
        }

        $this->persistGmailConnection($tokens, $email);
    }

    private function persistDriveConnection(array $tokens, ?string $email, ?string $accountId): void
    {
        $user      = Auth::user();
        $isSuper   = $user->isSuperAdmin();
        $ownerType = $isSuper ? DriveConnection::OWNER_SUPER_ADMIN : DriveConnection::OWNER_COMPANY;
        $companyId = $isSuper ? null : (int) $user->company_id;

        // Google only returns a refresh_token on first consent; preserve any prior one.
        $previous = $isSuper
            ? DriveConnection::activeForSuperAdmin()
            : DriveConnection::activeForCompany((int) $companyId);
        $refreshToken = $tokens['refresh_token'] ?? $previous?->refresh_token;

        $connection = app(DriveConnectionManager::class)->connect(
            ownerType: $ownerType,
            companyId: $companyId,
            data: [
                'access_token'   => $tokens['access_token'] ?? null,
                'refresh_token'  => $refreshToken,
                'expires_at'     => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in']) : null,
                'account_email'  => $email,
                'account_id'     => $accountId,
                'root_folder_id' => null,
            ],
            userId: $user->id,
        );

        // Keep the company integrations card in sync (super admin has no company row).
        if (! $isSuper) {
            CompanyIntegration::updateOrCreate(
                ['company_id' => $companyId, 'type' => CompanyIntegration::TYPE_GOOGLE_DRIVE],
                [
                    'connection_mode' => CompanyIntegration::MODE_AUTO,
                    'credentials'     => [
                        'connection_mode'     => 'auto',
                        'provider'            => 'google',
                        'account_email'       => $email,
                        'drive_connection_id' => $connection->id,
                    ],
                    'is_enabled'     => true,
                    'test_status'    => 'success',
                    'last_tested_at' => now(),
                ]
            );
        }
    }

    private function persistGmailConnection(array $tokens, ?string $email): void
    {
        $companyId = (int) Auth::user()->company_id;

        $existing = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_EMAIL_SMTP)
            ->first();

        CompanyIntegration::updateOrCreate(
            ['company_id' => $companyId, 'type' => CompanyIntegration::TYPE_EMAIL_SMTP],
            [
                'connection_mode' => CompanyIntegration::MODE_AUTO,
                'credentials'     => [
                    'connection_mode' => CompanyIntegration::MODE_AUTO,
                    'provider'        => 'gmail',
                    'access_token'    => $tokens['access_token'] ?? null,
                    'refresh_token'   => $tokens['refresh_token'] ?? ($existing->credentials['refresh_token'] ?? null),
                    'expires_at'      => isset($tokens['expires_in']) ? now()->addSeconds((int) $tokens['expires_in'])->toIso8601String() : null,
                    'scope'           => $tokens['scope'] ?? null,
                    'account_email'   => $email,
                    'from_email'      => $email,
                    'from_name'       => Auth::user()->name,
                ],
                'is_enabled'     => true,
                'test_status'    => 'success',
                'last_tested_at' => now(),
            ]
        );
    }

    /**
     * Disconnect the active Drive account for the current owner (company or super admin).
     */
    public function disconnectDrive(Request $request)
    {
        $this->authorizeAdmin();

        $user      = Auth::user();
        $isSuper   = $user->isSuperAdmin();
        $ownerType = $isSuper ? DriveConnection::OWNER_SUPER_ADMIN : DriveConnection::OWNER_COMPANY;
        $companyId = $isSuper ? null : (int) $user->company_id;

        app(DriveConnectionManager::class)->disconnect($ownerType, $companyId, $user->id);

        if (! $isSuper) {
            CompanyIntegration::query()
                ->where('company_id', $companyId)
                ->where('type', CompanyIntegration::TYPE_GOOGLE_DRIVE)
                ->update(['is_enabled' => false, 'test_status' => null]);
        }

        $target = $isSuper ? route('super-admin.integrations') : route('integrations.index');

        return redirect()->to($target)->with('success', 'Google Drive disconnected. Existing files remain accessible at their original location.');
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isCompanyAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Only company admins can manage integrations.');
        }
    }
}
