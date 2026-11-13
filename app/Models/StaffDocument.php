<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StaffDocument extends Model
{
    //
    use HasFactory;

    protected $fillable = ['staff_id','document_type','document_path'];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }

    public function getDisplayUrlAttribute(): ?string
    {
        if (empty($this->document_path)) {
            return null;
        }

        $rawPath = str_replace('\\', '/', (string) $this->document_path);
        $path = ltrim($rawPath, '/');

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        if ($disk === 's3') {
            return Storage::disk($disk)->url($path);
        }

        if (Str::startsWith($path, 'public/')) {
            $path = Str::after($path, 'public/');
        }

        if (Str::startsWith($path, 'storage/')) {
            return url($path);
        }

        return url('storage/' . $path);
    }
}
