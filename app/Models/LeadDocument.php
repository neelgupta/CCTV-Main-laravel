<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\MediaFile;

class LeadDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'file_path',
        'name',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function getDisplayUrlAttribute(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        $rawPath = str_replace('\\', '/', (string) $this->file_path);
        $path = ltrim($rawPath, '/');

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $media = MediaFile::query()
            ->where('company_id', $this->lead?->company_id)
            ->where('path', $path)
            ->first();

        if ($media) {
            return route('settings.gallery.open', $media);
        }

        return Storage::disk(env('AWS_ACCESS_KEY_ID') ? 's3' : 'public')->url($path);
    }
}
