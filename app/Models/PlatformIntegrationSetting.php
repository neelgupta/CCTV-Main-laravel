<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformIntegrationSetting extends Model
{
    const PROVIDER_GOOGLE = 'google';

    protected $fillable = [
        'provider',
        'is_enabled',
        'client_id',
        'client_secret',
        'metadata',
    ];

    protected $casts = [
        'is_enabled'    => 'boolean',
        'client_secret' => 'encrypted',
        'metadata'      => 'array',
    ];

    public function isConfigured(): bool
    {
        return $this->is_enabled
            && filled($this->client_id)
            && filled($this->client_secret);
    }

    public static function google(): ?self
    {
        return static::query()->where('provider', self::PROVIDER_GOOGLE)->first();
    }
}
