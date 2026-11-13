<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailClick extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_log_id',
        'campaign_id',
        'campaign_recipient_id',
        'clicked_url',
        'clicked_at',
        'ip_address',
        'device_info',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }

    public function emailLog(): BelongsTo
    {
        return $this->belongsTo(EmailLog::class);
    }
}
