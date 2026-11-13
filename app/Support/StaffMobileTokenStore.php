<?php

namespace App\Support;

use App\Models\StaffApiToken;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class StaffMobileTokenStore
{
    public function issue(User $user, string $deviceName, ?Carbon $expiresAt = null): array
    {
        $plainTextToken = Str::random(80);
        $now = now();
        $role = $user->normalizedRole();

        $token = StaffApiToken::query()->create([
            'user_id' => $user->id,
            'staff_id' => $user->staff?->id,
            'name' => Str::limit(trim($deviceName), 100, ''),
            'token' => hash('sha256', $plainTextToken),
            'last_used_at' => $now,
            'expires_at' => $expiresAt ?? $this->ttl(),
        ]);

        return [
            'plain_text_token' => $plainTextToken,
            'payload' => $this->payloadFromModel($token, $role),
        ];
    }

    public function find(string $plainTextToken): ?array
    {
        if ($plainTextToken === '') {
            return null;
        }

        $token = StaffApiToken::query()
            ->where('token', hash('sha256', $plainTextToken))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $token ? $this->payloadFromModel($token) : null;
    }

    public function touch(string $plainTextToken, array $payload): array
    {
        $token = StaffApiToken::query()
            ->where('token', hash('sha256', $plainTextToken))
            ->first();

        if (! $token) {
            return $payload;
        }

        $token->forceFill([
            'last_used_at' => now(),
        ])->save();

        return $this->payloadFromModel($token->fresh());
    }

    public function forget(string $plainTextToken): void
    {
        if ($plainTextToken === '') {
            return;
        }

        StaffApiToken::query()
            ->where('token', hash('sha256', $plainTextToken))
            ->delete();
    }

    public function forgetNamedTokens(User $user, string $deviceName): void
    {
        $tokenName = Str::limit(trim($deviceName), 100, '');

        if ($tokenName === '') {
            return;
        }

        StaffApiToken::query()
            ->where('user_id', $user->id)
            ->where('name', $tokenName)
            ->delete();
    }

    public function forgetAllForUser(User $user): void
    {
        StaffApiToken::query()
            ->where('user_id', $user->id)
            ->delete();
    }

    private function payloadFromModel(StaffApiToken $token, ?string $role = null): array
    {
        return [
            'id' => $token->id,
            'user_id' => $token->user_id,
            'staff_id' => $token->staff_id,
            'name' => $token->name,
            'role' => $role,
            'issued_at' => $token->created_at?->toISOString(),
            'last_used_at' => $token->last_used_at?->toISOString(),
            'expires_at' => $token->expires_at?->toISOString(),
        ];
    }

    private function ttl(): Carbon
    {
        $days = max((int) env('STAFF_MOBILE_TOKEN_TTL_DAYS', 30), 1);

        return now()->addDays($days);
    }
}
