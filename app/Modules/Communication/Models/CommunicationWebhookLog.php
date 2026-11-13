<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Communication\Enums\ProcessedStatusEnum;
use App\Modules\Communication\Enums\ProviderEnum;
use App\Modules\Communication\Enums\WebhookEventTypeEnum;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CommunicationWebhookLog extends Model
{
    use BelongsToCompany, HasFactory;

    // Disabling standard timestamps since we only track created_at (audit trail)
    public $timestamps = false;

    protected $fillable = [
        'company_id',
        'uuid',
        'integration_account_id',
        'provider',
        'event_type',
        'payload',
        'headers',
        'processed',
        'attempt_count',
        'processing_error',
        'request_ip',
        'user_agent',
        'processed_at',
        'created_at',
    ];

    protected $casts = [
        'provider' => ProviderEnum::class,
        'event_type' => WebhookEventTypeEnum::class,
        'processed' => ProcessedStatusEnum::class,
        'payload' => 'array',
        'headers' => 'array',
        'processed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
            if (empty($model->created_at)) {
                $model->created_at = now();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function integrationAccount()
    {
        return $this->belongsTo(IntegrationAccount::class);
    }
}
