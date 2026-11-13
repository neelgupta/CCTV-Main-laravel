<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingEmailTemplate extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';

    protected $fillable = [
        'company_id',
        'category_id',
        'created_by',
        'updated_by',
        'sender_group_id',
        'name',
        'category',
        'subject',
        'preheader',
        'status',
        'blocks',
        'html_content',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'category_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'sender_group_id' => 'integer',
        'blocks' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function categoryRecord(): BelongsTo
    {
        return $this->belongsTo(MarketingEmailTemplateCategory::class, 'category_id');
    }

    public function senderGroup(): BelongsTo
    {
        return $this->belongsTo(MarketingEmailSenderGroup::class, 'sender_group_id');
    }
}
