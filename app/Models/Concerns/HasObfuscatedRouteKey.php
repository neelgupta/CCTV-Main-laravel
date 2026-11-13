<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Routing\UrlRoutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Crypt;

trait HasObfuscatedRouteKey
{
    public function getRouteKey(): string
    {
        return static::encodeRouteKeyValue($this->getRouteKeyName() === $this->getKeyName()
            ? $this->getKey()
            : $this->getAttribute($this->getRouteKeyName()));
    }

    public function resolveRouteBinding($value, $field = null): ?UrlRoutable
    {
        $routeField = $field ?? $this->getRouteKeyName();
        $decodedValue = static::decodeRouteKeyValue($value);

        $model = $this->resolveRouteBindingQuery($this, $decodedValue, $routeField)->first();

        if ($model) {
            return $model;
        }

        throw (new ModelNotFoundException())->setModel(static::class, [$value]);
    }

    public static function encodeRouteKeyValue($value): string
    {
        $encrypted = Crypt::encryptString((string) $value);

        return rtrim(strtr(base64_encode($encrypted), '+/', '-_'), '=');
    }

    public static function decodeRouteKeyValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }

        if (is_numeric($value)) {
            return (string) $value === (string) (int) $value ? (int) $value : $value;
        }

        try {
            $decoded = base64_decode(strtr((string) $value, '-_', '+/') . str_repeat('=', (4 - strlen((string) $value) % 4) % 4), true);

            if ($decoded === false) {
                return $value;
            }

            $decrypted = Crypt::decryptString($decoded);

            return ctype_digit($decrypted) ? (int) $decrypted : $decrypted;
        } catch (\Throwable) {
            return $value;
        }
    }
}
