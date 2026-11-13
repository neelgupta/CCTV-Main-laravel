<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Communication\Enums\ConnectionStatusEnum;
use App\Modules\Communication\Enums\ProviderEnum;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class IntegrationAccount extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id',
        'uuid',
        'provider',
        'status',
        'display_name',
        'phone_number',
        'phone_number_id',
        'business_account_id',
        'app_id',
        'access_token',
        'verify_token',
        'webhook_secret',
        'connected_at',
        'last_verified_at',
        'metadata',
    ];

    protected $casts = [
        'provider' => ProviderEnum::class,
        'status' => ConnectionStatusEnum::class,
        'access_token' => 'encrypted',
        'verify_token' => 'encrypted',
        'webhook_secret' => 'encrypted',
        'connected_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function webhookLogs()
    {
        return $this->hasMany(CommunicationWebhookLog::class);
    }
}
