<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffTaskStatusUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'staff_task_id',
        'user_id',
        'status',
        'comment',
        'notes',
        'status_date',
        'reminder_date',
        'completed_at',
        'locked',
    ];

    protected $casts = [
        'status_date' => 'date',
        'reminder_date' => 'date',
        'completed_at' => 'datetime',
        'locked' => 'boolean',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(StaffTask::class, 'staff_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFinalizedReminder(): bool
    {
        return (bool) $this->locked || in_array($this->status, ['done', 'cancelled'], true);
    }

    public function reminderNotes(): ?string
    {
        $notes = trim((string) ($this->notes ?? ''));

        if ($notes !== '') {
            return $notes;
        }

        $comment = trim((string) ($this->comment ?? ''));

        return $comment !== '' ? $comment : null;
    }

    public function reminderStatusLabel(): string
    {
        return match ($this->status) {
            'done' => 'Done',
            'reschedule' => 'Reschedule',
            'cancelled' => 'Blocker',
            'in_progress' => 'In Progress',
            default => 'Pending',
        };
    }
}
