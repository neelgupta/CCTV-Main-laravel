<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaFile extends Model
{
    use HasFactory, HasObfuscatedRouteKey, SoftDeletes;

    const PROVIDER_LOCAL             = 'local';
    const PROVIDER_COMPANY_DRIVE     = 'company_drive';
    const PROVIDER_SUPER_ADMIN_DRIVE = 'super_admin_drive';

    protected $fillable = [
        'company_id',
        'module',
        'title',
        'gallery_visible',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size',
        'mediable_type',
        'mediable_id',
        'storage_deleted_at',
        'storage_provider',
        'drive_account_email',
        'drive_account_id',
        'drive_folder_id',
        'drive_file_id',
        'storage_path_label',
        'upload_source',
        'drive_connection_id',
        'uploaded_at',
    ];

    protected $casts = [
        'storage_deleted_at' => 'datetime',
        'uploaded_at' => 'datetime',
        'size' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function mediable()
    {
        return $this->morphTo();
    }

    public function getUrlAttribute(): ?string
    {
        if (!$this->path) {
            return null;
        }

        return route('settings.gallery.open', $this);
    }

    public function getHumanSizeAttribute(): string
    {
        $size = (int) ($this->size ?? 0);
        if ($size <= 0) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $power = (int) floor(log($size, 1024));

        return round($size / (1024 ** $power), 2) . ' ' . ($units[$power] ?? 'B');
    }

    public function getIsImageAttribute(): bool
    {
        return Str::startsWith((string) $this->mime_type, 'image/');
    }

    public function getStorageProviderLabelAttribute(): string
    {
        return match ($this->storage_provider) {
            self::PROVIDER_COMPANY_DRIVE     => 'Company Google Drive',
            self::PROVIDER_SUPER_ADMIN_DRIVE => 'Super Admin Google Drive',
            default                          => 'Default Storage',
        };
    }

    public function isOnDrive(): bool
    {
        return in_array($this->storage_provider, [
            self::PROVIDER_COMPANY_DRIVE,
            self::PROVIDER_SUPER_ADMIN_DRIVE,
        ], true);
    }
}
