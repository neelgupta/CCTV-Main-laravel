<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailOpen extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_log_id',
        'campaign_id',
        'campaign_recipient_id',
        'user_id',
        'opened_at',
        'ip_address',
        'device_info',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
