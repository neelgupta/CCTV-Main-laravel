<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GeneratedBanner extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'campaign_id',
        'festival_campaign_id',
        'created_by',
        'source',
        'prompt_text',
        'file_path',
        'mime_type',
        'width',
        'height',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function festivalCampaign(): BelongsTo
    {
        return $this->belongsTo(FestivalCampaign::class);
    }

    public function getDisplayUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        $rawPath = str_replace('\\', '/', (string) $this->file_path);

        if (Str::startsWith($rawPath, ['http://', 'https://', 'data:'])) {
            return $rawPath;
        }

        return Storage::disk(env('AWS_ACCESS_KEY_ID') ? 's3' : 'public')
            ->url(ltrim($rawPath, '/'));
    }
}
