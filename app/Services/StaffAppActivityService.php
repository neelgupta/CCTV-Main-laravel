<?php

namespace App\Services;

use App\Models\StaffAppActivity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StaffAppActivityService
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService
    ) {
    }

    public function syncFromRequest(User $user, Request $request, string $activityType = 'api_request'): void
    {
        if (! $user->company_id || ! $user->staff?->id) {
            return;
        }

        if ($this->subscriptionLimitService->trackingIsDisabledForStaff($user->staff)) {
            return;
        }

        $deviceId = $this->resolveDeviceId($request, $user);
        $deviceHash = hash('sha256', $deviceId);
        $platform = $this->resolvePlatform($request);
        $appVersion = $this->resolveAppVersion($request);

        StaffAppActivity::query()->updateOrCreate(
            [
                'company_id' => (int) $user->company_id,
                'staff_id' => (int) $user->staff->id,
                'device_id_hash' => $deviceHash,
            ],
            [
                'device_id' => $deviceId,
                'platform' => $platform,
                'app_version' => $appVersion,
                'activity_type' => $activityType,
                'last_active_at' => now(),
            ]
        );

        app(CompanyAppUsageService::class)->forgetCache();
    }

    private function resolveDeviceId(Request $request, User $user): string
    {
        $deviceId = trim((string) $request->input('device_id', ''));

        if ($deviceId === '') {
            $deviceId = trim((string) $request->header('X-Device-Id', ''));
        }

        if ($deviceId === '') {
            $tokenPayload = $request->attributes->get('staff_api_token');

            if (is_array($tokenPayload)) {
                $deviceId = trim((string) ($tokenPayload['name'] ?? ''));
            }
        }

        if ($deviceId === '') {
            $deviceId = trim((string) $request->input('device_name', ''));
        }

        if ($deviceId === '') {
            $deviceId = trim((string) $request->header('User-Agent', ''));
        }

        if ($deviceId === '') {
            $deviceId = 'staff-' . $user->staff->id;
        }

        return Str::limit($deviceId, 191, '');
    }

    private function resolvePlatform(Request $request): ?string
    {
        $platform = trim((string) $request->input('platform', ''));

        if ($platform === '') {
            $platform = trim((string) $request->header('X-Platform', ''));
        }

        if ($platform === '') {
            $platform = trim((string) $request->header('X-Device-Platform', ''));
        }

        return $platform !== '' ? Str::limit($platform, 100, '') : null;
    }

    private function resolveAppVersion(Request $request): ?string
    {
        $version = trim((string) $request->input('app_version', ''));

        if ($version === '') {
            $version = trim((string) $request->header('X-App-Version', ''));
        }

        return $version !== '' ? Str::limit($version, 50, '') : null;
    }
}
