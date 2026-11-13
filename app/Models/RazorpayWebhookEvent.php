<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RazorpayWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event',
        'entity_id',
        'payload',
        'attempts',
        'processed_at',
        'error',
    ];

    protected $casts = [
        'payload' => 'array',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];
}
