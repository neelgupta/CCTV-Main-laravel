<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingEmailSenderConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'sender_email',
        'display_name',
        'reply_to_email',
        'cc_email',
        'bcc_email',
        'is_connected',
        'connected_at',
    ];

    protected $casts = [
        'is_connected' => 'boolean',
        'connected_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
