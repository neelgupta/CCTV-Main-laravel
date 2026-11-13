<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory, BelongsToCompany;

    public const TYPE_NORMAL = 'normal';
    public const TYPE_FESTIVAL = 'festival';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public const SEND_TYPE_IMMEDIATE = 'immediate';
    public const SEND_TYPE_SCHEDULED = 'scheduled';

    protected $fillable = [
        'company_id',
        'user_id',
        'uuid',
        'type',
        'send_type',
        'name',
        'subject',
        'email_template_id',
        'whatsapp_template_name',
        'festival_campaign_id',
        'generated_banner_id',
        'banner_image',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'batch_size',
        'batch_delay_seconds',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'failed_count',
        'retry_count',
        'settings',
    ];

    public function isImmediate(): bool
    {
        return $this->send_type === self::SEND_TYPE_IMMEDIATE;
    }

    public function isScheduled(): bool
    {
        return $this->send_type === self::SEND_TYPE_SCHEDULED || blank($this->send_type);
    }

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'settings' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign) {
            if (blank($campaign->uuid)) {
                $campaign->uuid = (string) Str::uuid();
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(MarketingEmailTemplate::class, 'email_template_id');
    }

    public function festivalCampaign(): BelongsTo
    {
        return $this->belongsTo(FestivalCampaign::class);
    }

    public function generatedBanner(): BelongsTo
    {
        return $this->belongsTo(GeneratedBanner::class);
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsAppLog::class);
    }
}
