<?php

namespace App\Services;

use App\Models\GlobalSetting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GlobalSettingService
{
    private const CACHE_KEY = 'global-settings.values';

    public function get(string $key, mixed $default = null): mixed
    {
        $key = $this->normalizeKey($key);
        $values = $this->all();

        return $values[$key] ?? $default;
    }

    public function all(): array
    {
        return Cache::remember($this->cacheKey(), now()->addMinutes(10), function (): array {
            return GlobalSetting::query()
                ->orderBy('key')
                ->get()
                ->mapWithKeys(function (GlobalSetting $setting): array {
                    $value = $setting->value;

                    if ($setting->is_encrypted && filled($value)) {
                        try {
                            $value = Crypt::decryptString((string) $value);
                        } catch (DecryptException $e) {
                            Log::warning('Unable to decrypt global setting value.', [
                                'key' => $setting->key,
                                'setting_id' => $setting->id,
                            ]);

                            $value = null;
                        }
                    }

                    return [$setting->key => $value];
                })
                ->all();
        });
    }

    public function put(string $key, mixed $value, bool $encrypt = false): void
    {
        $key = $this->normalizeKey($key);
        $stringValue = is_string($value) ? trim($value) : (string) $value;

        if ($stringValue === '' && ! $encrypt) {
            GlobalSetting::query()->where('key', $key)->delete();
            $this->refreshRuntimeConfig($key, '');

            return;
        }

        if ($encrypt && $stringValue === '') {
            $this->refreshRuntimeConfig($key, '');

            return;
        }

        GlobalSetting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $encrypt ? Crypt::encryptString($stringValue) : $stringValue,
                'is_encrypted' => $encrypt,
            ]
        );

        $this->refreshRuntimeConfig($key, $stringValue);
    }

    /**
     * Point services.razorpay.* at the live or test key set, based on payment_gateway_mode.
     * The original payment_gateway_* keys are the live set; test keys use payment_gateway_test_*.
     */
    public function applyPaymentGatewayConfig(): void
    {
        $mode = $this->get('payment_gateway_mode') === 'test' ? 'test' : 'live';
        $prefix = $mode === 'test' ? 'payment_gateway_test_' : 'payment_gateway_';

        Config::set('services.razorpay.mode', $mode);
        Config::set('services.razorpay.key_id', $this->get($prefix . 'public_key'));
        Config::set('services.razorpay.key_secret', $this->get($prefix . 'secret_key'));
        Config::set('services.razorpay.webhook_secret', $this->get($prefix . 'webhook_key'));
    }

    public function putMany(array $values, array $encryptedKeys = []): void
    {
        foreach ($values as $key => $value) {
            $this->put((string) $key, $value, in_array((string) $key, $encryptedKeys, true));
        }
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey());
    }

    private function normalizeKey(string $key): string
    {
        return Str::of($key)->trim()->lower()->toString();
    }

    private function cacheKey(): string
    {
        return self::CACHE_KEY;
    }

    private function refreshRuntimeConfig(string $key, string $value): void
    {
        $this->syncRuntimeConfig($key, $value);
        $this->forgetCache();

        if (str_starts_with($key, 'payment_gateway_')) {
            $this->applyPaymentGatewayConfig();
        }
    }

    private function syncRuntimeConfig(string $key, string $value): void
    {
        $normalizedKey = $this->normalizeKey($key);
        $runtimeValue = $value !== '' ? $value : null;

        match ($normalizedKey) {
            'google_maps_api_key' => Config::set('services.google_maps.key', $runtimeValue),
            'email_from_address' => $runtimeValue !== null ? Config::set('mail.from.address', $runtimeValue) : null,
            'email_from_name' => $runtimeValue !== null ? Config::set('mail.from.name', $runtimeValue) : null,
            'smtp_host' => $runtimeValue !== null ? Config::set('mail.mailers.smtp.host', $runtimeValue) : null,
            'smtp_port' => $runtimeValue !== null ? Config::set('mail.mailers.smtp.port', (int) $runtimeValue) : null,
            'smtp_username' => $runtimeValue !== null ? Config::set('mail.mailers.smtp.username', $runtimeValue) : null,
            'smtp_password' => $runtimeValue !== null ? Config::set('mail.mailers.smtp.password', $runtimeValue) : null,
            'smtp_encryption' => $runtimeValue !== null ? Config::set('mail.mailers.smtp.encryption', $runtimeValue) : null,
            default => null,
        };
    }
}
