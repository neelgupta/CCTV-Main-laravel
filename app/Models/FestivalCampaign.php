<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FestivalCampaign extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'campaign_id',
        'selected_by_user_id',
        'festival_name',
        'festival_date',
        'description',
        'greetings_text',
        'brand_name',
        'brand_logo_path',
        'ai_provider',
        'prompt_payload',
    ];

    protected function casts(): array
    {
        return [
            'festival_date' => 'date',
            'prompt_payload' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function selectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'selected_by_user_id');
    }

    public function banners(): HasMany
    {
        return $this->hasMany(GeneratedBanner::class);
    }
}
