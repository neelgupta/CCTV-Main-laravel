<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailBounce extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_log_id',
        'campaign_id',
        'campaign_recipient_id',
        'bounce_type',
        'reason',
        'bounced_at',
        'provider_payload',
    ];

    protected function casts(): array
    {
        return [
            'bounced_at' => 'datetime',
            'provider_payload' => 'array',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
