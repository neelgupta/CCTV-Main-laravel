<?php

namespace App\Http\Controllers;

use App\Models\CompanyIntegration;
use App\Models\CompanyNotificationSetting;
use App\Models\DriveConnection;
use App\Models\DriveConnectionLog;
use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\Enums\ConnectionStatusEnum;
use App\Services\FirebasePushService;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

class IntegrationController extends Controller
{
    public function index(GoogleOAuthService $google, FirebasePushService $firebasePush)
    {
        $user      = Auth::user();
        $companyId = (int) $user->company_id;

        $integrations = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('type');

        $googleDrive = $integrations->get(CompanyIntegration::TYPE_GOOGLE_DRIVE);
        $emailSmtp   = $integrations->get(CompanyIntegration::TYPE_EMAIL_SMTP);

        // WhatsApp status from Communication module
        $whatsappAccount = null;
        try {
            $repo            = app(IntegrationAccountRepositoryInterface::class);
            $whatsappAccount = $repo->getAllForCompany($companyId)->first();
        } catch (Throwable) {
        }

        // Company-wide notification matrix (managed by admin, read-only for users).
        $notificationMatrix = CompanyNotificationSetting::matrixForCompany($companyId);
        $isAdmin = $user->isCompanyAdmin() || $user->isSuperAdmin();

        // Which channels are actually deliverable for this company right now?
        $waConnected = $whatsappAccount
            && $whatsappAccount->status === ConnectionStatusEnum::CONNECTED;
        $emailActive = $emailSmtp && $emailSmtp->is_enabled && $emailSmtp->isConnected();

        $channelAvailability = [
            'whatsapp' => $waConnected,
            'email'    => $emailActive,
            'push'     => $firebasePush->isConfigured(),
        ];

        // Is platform-level Google OAuth available for one-click Auto Connect?
        $googleAutoAvailable = $google->isConfigured();

        $driveConnection = DriveConnection::activeForCompany($companyId);
        $driveLogs = DriveConnectionLog::query()
            ->where('drive_type', DriveConnection::OWNER_COMPANY)
            ->where('company_id', $companyId)
            ->with('performedBy:id,name')
            ->latest()
            ->limit(15)
            ->get();

        return view('integrations.index', compact(
            'googleDrive',
            'emailSmtp',
            'whatsappAccount',
            'notificationMatrix',
            'channelAvailability',
            'isAdmin',
            'googleAutoAvailable',
            'driveConnection',
            'driveLogs',
        ));
    }

    /**
     * Save the company-wide notification matrix (admin only). Applies to all users.
     */
    public function updateNotificationMatrix(Request $request)
    {
        $this->authorizeAdmin();

        $companyId = (int) Auth::user()->company_id;
        $modules   = array_keys(CompanyNotificationSetting::MODULES);

        $submitted = (array) $request->input('settings', []);

        DB::transaction(function () use ($modules, $submitted, $companyId) {
            foreach ($modules as $module) {
                $row = (array) ($submitted[$module] ?? []);

                CompanyNotificationSetting::updateOrCreate(
                    ['company_id' => $companyId, 'module' => $module],
                    [
                        'is_enabled'       => (bool) ($row['is_enabled'] ?? false),
                        'whatsapp_enabled' => (bool) ($row['whatsapp'] ?? false),
                        'email_enabled'    => (bool) ($row['email'] ?? false),
                        'push_enabled'     => (bool) ($row['push'] ?? false),
                    ]
                );
            }
        });

        return back()->with('success', 'Notification settings saved. These apply to all users in your company.');
    }

    public function saveGoogleDrive(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'client_id'     => 'required|string|max:500',
            'client_secret' => 'required|string|max:500',
            'refresh_token' => 'nullable|string|max:2000',
            'folder_id'     => 'nullable|string|max:500',
        ]);

        $companyId = (int) Auth::user()->company_id;

        CompanyIntegration::updateOrCreate(
            ['company_id' => $companyId, 'type' => CompanyIntegration::TYPE_GOOGLE_DRIVE],
            [
                'connection_mode' => CompanyIntegration::MODE_MANUAL,
                'credentials'     => $validated,
                'is_enabled'      => true,
                'test_status'     => null,
            ]
        );

        return back()->with('success', 'Google Drive credentials saved.');
    }

    public function saveEmailSmtp(Request $request)
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'host'       => 'required|string|max:255',
            'port'       => 'required|integer|min:1|max:65535',
            'username'   => 'required|string|max:255',
            'password'   => 'required|string|max:500',
            'encryption' => 'required|in:tls,ssl,none',
            'from_name'  => 'required|string|max:255',
            'from_email' => 'required|email|max:255',
        ]);

        $companyId = (int) Auth::user()->company_id;

        CompanyIntegration::updateOrCreate(
            ['company_id' => $companyId, 'type' => CompanyIntegration::TYPE_EMAIL_SMTP],
            [
                'connection_mode' => CompanyIntegration::MODE_MANUAL,
                'credentials'     => $validated,
                'is_enabled'      => true,
                'test_status'     => null,
            ]
        );

        return back()->with('success', 'Email SMTP settings saved.');
    }

    public function toggleIntegration(Request $request, string $type)
    {
        $this->authorizeAdmin();

        $request->validate(['type' => 'sometimes|string']);

        $allowedTypes = [CompanyIntegration::TYPE_GOOGLE_DRIVE, CompanyIntegration::TYPE_EMAIL_SMTP];
        if (! in_array($type, $allowedTypes, true)) {
            abort(422, 'Invalid integration type.');
        }

        $companyId   = (int) Auth::user()->company_id;
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', $type)
            ->firstOrFail();

        $integration->update(['is_enabled' => ! $integration->is_enabled]);

        $status = $integration->is_enabled ? 'enabled' : 'disabled';

        return back()->with('success', ucwords(str_replace('_', ' ', $type)) . " integration {$status}.");
    }

    public function testGoogleDrive()
    {
        $this->authorizeAdmin();

        $companyId   = (int) Auth::user()->company_id;
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_GOOGLE_DRIVE)
            ->first();

        if (! $integration || empty($integration->credentials)) {
            return response()->json(['success' => false, 'message' => 'No credentials configured.'], 422);
        }

        $creds = $integration->credentials;

        // Basic credential presence check (full OAuth flow requires Google API)
        $hasRequired = ! empty($creds['client_id']) && ! empty($creds['client_secret']);

        if ($hasRequired) {
            $integration->update(['test_status' => 'success', 'last_tested_at' => now()]);
            return response()->json(['success' => true, 'message' => 'Credentials look valid. Full verification requires Google OAuth authorization.']);
        }

        $integration->update(['test_status' => 'failed', 'last_tested_at' => now()]);
        return response()->json(['success' => false, 'message' => 'Missing required credentials.'], 422);
    }

    public function testEmailSmtp()
    {
        $this->authorizeAdmin();

        $companyId   = (int) Auth::user()->company_id;
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_EMAIL_SMTP)
            ->first();

        if (! $integration || empty($integration->credentials)) {
            return response()->json(['success' => false, 'message' => 'No SMTP credentials configured.'], 422);
        }

        $creds = $integration->credentials;

        try {
            $transport = new \Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport(
                host: $creds['host'],
                port: (int) $creds['port'],
                tls:  $creds['encryption'] === 'tls',
            );
            $transport->setUsername($creds['username']);
            $transport->setPassword($creds['password']);
            $transport->start();

            $integration->update(['test_status' => 'success', 'last_tested_at' => now()]);
            return response()->json(['success' => true, 'message' => 'SMTP connection successful.']);
        } catch (Throwable $e) {
            $integration->update(['test_status' => 'failed', 'last_tested_at' => now()]);
            return response()->json(['success' => false, 'message' => 'SMTP test failed: ' . $e->getMessage()], 422);
        }
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::user();
        if (! $user || (! $user->isCompanyAdmin() && ! $user->isSuperAdmin())) {
            abort(403, 'Only company admins can manage integrations.');
        }
    }
}
