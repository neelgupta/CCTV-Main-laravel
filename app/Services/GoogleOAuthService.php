<?php

namespace App\Services;

use App\Models\PlatformIntegrationSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleOAuthService
{
    public const SERVICE_DRIVE = 'drive';
    public const SERVICE_GMAIL = 'gmail';

    private const AUTH_ENDPOINT  = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';
    private const USERINFO       = 'https://www.googleapis.com/oauth2/v3/userinfo';

    /**
     * Scopes requested per service. userinfo.email is always included so we can
     * record which Google account was connected.
     */
    private const SCOPES = [
        self::SERVICE_DRIVE => [
            'https://www.googleapis.com/auth/drive.file',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
        self::SERVICE_GMAIL => [
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
    ];

    public function config(): ?PlatformIntegrationSetting
    {
        return PlatformIntegrationSetting::google();
    }

    public function isConfigured(): bool
    {
        return (bool) $this->config()?->isConfigured();
    }

    public function isValidService(string $service): bool
    {
        return array_key_exists($service, self::SCOPES);
    }

    public function redirectUri(): string
    {
        return route('integrations.oauth.google.callback');
    }

    /**
     * Build the Google consent screen URL for the given service.
     */
    public function buildAuthUrl(string $service, string $state): string
    {
        $config = $this->requireConfig();

        $params = http_build_query([
            'client_id'     => $config->client_id,
            'redirect_uri'  => $this->redirectUri(),
            'response_type' => 'code',
            'scope'         => implode(' ', self::SCOPES[$service]),
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'include_granted_scopes' => 'true',
            'state'         => $state,
        ]);

        return self::AUTH_ENDPOINT.'?'.$params;
    }

    /**
     * Exchange an authorization code for access/refresh tokens.
     *
     * @return array{access_token:string,refresh_token:?string,expires_in:int,scope:?string}
     */
    public function exchangeCode(string $code): array
    {
        $config = $this->requireConfig();

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'code'          => $code,
            'client_id'     => $config->client_id,
            'client_secret' => $config->client_secret,
            'redirect_uri'  => $this->redirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Token exchange failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * Resolve the email address of the connected Google account.
     */
    public function fetchAccountEmail(string $accessToken): ?string
    {
        return $this->fetchAccountInfo($accessToken)['email'] ?? null;
    }

    /**
     * Resolve identifying details of the connected Google account.
     *
     * @return array{email:?string,sub:?string}
     */
    public function fetchAccountInfo(string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get(self::USERINFO);

        if ($response->failed()) {
            return ['email' => null, 'sub' => null];
        }

        return [
            'email' => $response->json('email'),
            'sub'   => $response->json('sub'),
        ];
    }

    private function requireConfig(): PlatformIntegrationSetting
    {
        $config = $this->config();

        if (! $config || ! $config->isConfigured()) {
            throw new RuntimeException('Google integration is not configured by the platform administrator.');
        }

        return $config;
    }
}
