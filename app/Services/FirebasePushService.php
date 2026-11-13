<?php

namespace App\Services;

use App\Models\CompanyNotificationSetting;
use App\Models\DashboardNotification;
use App\Models\User;
use App\Models\UserPushDevice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class FirebasePushService
{
    public function isConfigured(): bool
    {
        $config = config('services.firebase');

        return filled($config['project_id'] ?? null)
            && filled($config['client_email'] ?? null)
            && filled($config['private_key'] ?? null)
            && filled($config['messaging_sender_id'] ?? null)
            && filled($config['api_key'] ?? null)
            && filled($config['app_id'] ?? null)
            && filled($config['vapid_key'] ?? null);
    }

    public function sendDashboardNotification(DashboardNotification $notification, ?string $module = null): void
    {
        $user = $notification->user;

        if (! $user || ! $user->company_id || ! $this->isConfigured()) {
            return;
        }

        if (! $this->shouldSendForModule($user, $module)) {
            return;
        }

        $title = (string) $notification->title;
        $body = filled($notification->message) ? (string) $notification->message : $title;

        $data = array_filter([
            'notification_id' => (string) $notification->id,
            'notification_key' => (string) $notification->notification_key,
            'category' => (string) $notification->category,
            'url' => (string) ($notification->url ?: route('dashboard')),
            'title' => $title,
            'body' => $body,
        ], static fn ($value) => filled($value));

        $this->sendToUser(
            user: $user,
            title: $title,
            body: $body,
            data: $data,
            url: $notification->url ?: route('dashboard'),
            tag: 'notification-' . $notification->notification_key
        );
    }

    public function sendToUser(User $user, string $title, string $body, array $data = [], ?string $url = null, ?string $tag = null): void
    {
        if (! $user->company_id || ! $this->isConfigured() || ! $user->prefersPushNotifications()) {
            return;
        }

        $devices = UserPushDevice::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('is_enabled', true)
            ->whereNotNull('fcm_token')
            ->get();

        if ($devices->isEmpty()) {
            return;
        }

        foreach ($devices as $device) {
            $this->sendToToken(
                token: (string) $device->fcm_token,
                title: $title,
                body: $body,
                data: $data,
                url: $url,
                tag: $tag
            );

            $device->forceFill(['last_seen_at' => now()])->save();
        }
    }

    public function sendToToken(string $token, string $title, string $body, array $data = [], ?string $url = null, ?string $tag = null): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $projectId = (string) config('services.firebase.project_id');
        $accessToken = $this->accessToken();

        $message = [
            'token' => $token,
            'notification' => [
                'title' => $title,
                'body' => $body,
            ],
            'data' => $this->stringifyData($data, $url, $tag),
            'webpush' => [
                'notification' => array_filter([
                    'title' => $title,
                    'body' => $body,
                    'icon' => asset('pwa/icon-192.png'),
                    'badge' => asset('pwa/icon-192.png'),
                    'tag' => $tag,
                    'renotify' => true,
                    'requireInteraction' => true,
                ], static fn ($value) => filled($value)),
                'fcm_options' => array_filter([
                    'link' => $url ?: route('dashboard'),
                ], static fn ($value) => filled($value)),
            ],
        ];

        $response = Http::withToken($accessToken)
            ->acceptJson()
            ->asJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => $message,
            ]);

        if ($response->successful()) {
            return;
        }

        $error = strtolower((string) $response->json('error.status'));
        $messageText = (string) ($response->json('error.message') ?? 'Unknown Firebase error');

        if (str_contains($error, 'not_found') || str_contains($messageText, 'registration token') || str_contains($messageText, 'not registered')) {
            $this->forgetToken($token);
            return;
        }

        Log::warning('Firebase push notification failed.', [
            'status' => $response->status(),
            'error' => $messageText,
        ]);
    }

    public function forgetToken(string $token): void
    {
        UserPushDevice::query()
            ->where('fcm_token_hash', UserPushDevice::tokenHash($token))
            ->delete();
    }

    private function shouldSendForModule(User $user, ?string $module): bool
    {
        if (! $module) {
            return false;
        }

        $normalizedModule = $this->normalizeModuleKey($module);

        if (! $normalizedModule) {
            return false;
        }

        $matrix = CompanyNotificationSetting::matrixForCompany((int) $user->company_id);
        $setting = $matrix->get($normalizedModule);

        if (! $setting || ! $setting->allows('push')) {
            return false;
        }

        return (bool) ($user->notificationPreference?->push_enabled ?? true);
    }

    private function normalizeModuleKey(string $module): ?string
    {
        $module = Str::lower(trim($module));

        return match ($module) {
            'lead', 'leads' => 'lead',
            'project', 'projects' => 'project',
            'ticket', 'tickets', 'service', 'services' => 'ticket',
            'amc', 'amcs', 'payment', 'payments', 'subscription' => 'payment',
            'staff', 'teams' => 'staff',
            default => $module !== '' ? $module : null,
        };
    }

    private function accessToken(): string
    {
        return Cache::remember('firebase.push.access_token', now()->addMinutes(50), function (): string {
            $projectId = (string) config('services.firebase.project_id');
            $clientEmail = (string) config('services.firebase.client_email');
            $privateKey = $this->normalizedPrivateKey((string) config('services.firebase.private_key'));

            if ($projectId === '' || $clientEmail === '' || $privateKey === '') {
                throw new RuntimeException('Firebase credentials are not configured.');
            }

            $now = CarbonImmutable::now()->timestamp;
            $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
            $payload = $this->base64UrlEncode(json_encode([
                'iss' => $clientEmail,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ], JSON_THROW_ON_ERROR));
            $unsignedJwt = $header . '.' . $payload;

            $signature = '';
            $privateKeyResource = openssl_pkey_get_private($privateKey);
            if (! $privateKeyResource) {
                throw new RuntimeException('Unable to load Firebase private key.');
            }

            $signed = openssl_sign($unsignedJwt, $signature, $privateKeyResource, OPENSSL_ALGO_SHA256);
            openssl_pkey_free($privateKeyResource);

            if (! $signed) {
                throw new RuntimeException('Unable to sign Firebase JWT.');
            }

            $jwt = $unsignedJwt . '.' . $this->base64UrlEncode($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (! $response->successful()) {
                throw new RuntimeException((string) ($response->json('error_description') ?? 'Failed to obtain Firebase access token.'));
            }

            return (string) $response->json('access_token');
        });
    }

    private function normalizedPrivateKey(string $privateKey): string
    {
        $privateKey = trim($privateKey);

        return str_replace(['\\n', "\r"], ["\n", ''], $privateKey);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function stringifyData(array $data, ?string $url, ?string $tag): array
    {
        $payload = array_merge($data, [
            'url' => $url ?: route('dashboard'),
            'tag' => $tag ?: 'dashboard-notification',
        ]);

        $result = [];
        foreach ($payload as $key => $value) {
            if ($value === null) {
                continue;
            }

            $result[(string) $key] = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        }

        return $result;
    }
}
