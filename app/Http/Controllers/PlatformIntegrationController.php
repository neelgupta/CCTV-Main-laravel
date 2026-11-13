<?php

namespace App\Http\Controllers;

use App\Models\DriveConnection;
use App\Models\DriveConnectionLog;
use App\Models\PlatformIntegrationSetting;
use App\Services\GoogleOAuthService;
use Illuminate\Http\Request;

class PlatformIntegrationController extends Controller
{
    public function index(GoogleOAuthService $google)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $googleSetting  = PlatformIntegrationSetting::google();
        $redirectUri    = $google->redirectUri();
        $driveConnection = DriveConnection::activeForSuperAdmin();
        $driveLogs = DriveConnectionLog::query()
            ->where('drive_type', DriveConnection::OWNER_SUPER_ADMIN)
            ->with('performedBy:id,name')
            ->latest()
            ->limit(25)
            ->get();

        return view('super-admin.integrations', compact('googleSetting', 'redirectUri', 'driveConnection', 'driveLogs'));
    }

    public function updateGoogle(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'client_id'     => 'required|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'is_enabled'    => 'sometimes|boolean',
        ]);

        $existing = PlatformIntegrationSetting::google();

        // Keep the stored secret if the field is left blank on update.
        $secret = filled($validated['client_secret'] ?? null)
            ? $validated['client_secret']
            : $existing?->client_secret;

        if (blank($secret)) {
            return back()->withInput()->withErrors([
                'client_secret' => 'Client secret is required for the initial setup.',
            ]);
        }

        PlatformIntegrationSetting::updateOrCreate(
            ['provider' => PlatformIntegrationSetting::PROVIDER_GOOGLE],
            [
                'client_id'     => $validated['client_id'],
                'client_secret' => $secret,
                'is_enabled'    => $request->boolean('is_enabled'),
            ]
        );

        return back()->with('success', 'Google OAuth configuration saved.');
    }
}
