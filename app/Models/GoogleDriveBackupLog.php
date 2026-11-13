<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Tracks every Google Drive backup attempt for operational visibility.
 */
class GoogleDriveBackupLog extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'backup_name',
        'google_file_id',
        'google_file_url',
        'folder_id',
        'file_size_bytes',
        'status',
        'start_time',
        'end_time',
        'duration_seconds',
        'error_message',
    ];

    protected $casts = [
        'file_size_bytes' => 'integer',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration_seconds' => 'integer',
    ];

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function durationHuman(): string
    {
        if ($this->duration_seconds === null) {
            return 'Pending';
        }

        if ($this->duration_seconds < 60) {
            return $this->duration_seconds.' sec';
        }

        $minutes = intdiv($this->duration_seconds, 60);
        $seconds = $this->duration_seconds % 60;

        return $minutes.' min'.($seconds > 0 ? ' '.$seconds.' sec' : '');
    }

    public function fileSizeHuman(): string
    {
        $bytes = (int) ($this->file_size_bytes ?? 0);

        if ($bytes <= 0) {
            return '0 B';
        }

        return Number::fileSize($bytes);
    }

    public function createdAtLabel(): string
    {
        $time = $this->start_time ?? $this->created_at;

        if (! $time instanceof CarbonInterface) {
            return 'N/A';
        }

        return $time->format('d M Y, h:i A');
    }
}
