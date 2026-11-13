<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingEmailSenderGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'name',
        'sender_name',
        'sender_email',
        'cc_email',
        'bcc_email',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(MarketingEmailTemplate::class, 'sender_group_id');
    }
}
