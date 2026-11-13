<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class StaffTask extends Model
{
    use HasFactory, BelongsToCompany, HasObfuscatedRouteKey;

    protected $fillable = [
        'company_id',
        'staff_id',
        'created_by_staff_id',
        'project_id',
        'task_name',
        'status',
        'message',
        'updated_by',
        'due_date',
        'has_reminder',
        'reminder_date',
        'reminder_type',
        'reminder_days_before',
        'is_repeated',
        'repeat_type',
        'reminder_weekdays',
        'last_reminder_sent_at',
        'last_repeat_processed_at',
        'repeat_anchor_due_date',
        'task_description',
        'has_blocker',
        'blocker_level',
        'blocker_description',
    ];

    protected $casts = [
        'due_date' => 'date',
        'reminder_date' => 'date',
        'has_reminder' => 'boolean',
        'reminder_days_before' => 'integer',
        'is_repeated' => 'boolean',
        'reminder_weekdays' => 'array',
        'last_reminder_sent_at' => 'datetime',
        'last_repeat_processed_at' => 'datetime',
        'repeat_anchor_due_date' => 'date',
        'has_blocker' => 'boolean',
    ];

    public const REPEAT_TYPES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    public const REMINDER_WEEKDAYS = [
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
    ];

    protected static function booted()
    {
        static::saved(function (StaffTask $task) {
            if (! $task->wasRecentlyCreated && ! $task->wasChanged('staff_id')) {
                return;
            }

            try {
                app(\App\Services\DashboardNotificationService::class)->notifyStaffTaskAssigned($task);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'reschedule' => 'Reschedule',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function reminderWeekdayOptions(): array
    {
        return self::REMINDER_WEEKDAYS;
    }

    public static function repeatTypeOptions(): array
    {
        return self::REPEAT_TYPES;
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusUpdates(): HasMany
    {
        return $this->hasMany(StaffTaskStatusUpdate::class, 'staff_task_id')->latest();
    }

    public function reminderUpdates(): HasMany
    {
        return $this->hasMany(StaffTaskStatusUpdate::class, 'staff_task_id')->orderBy('reminder_date');
    }

    public function reminderStatusForDate(Carbon $date): ?StaffTaskStatusUpdate
    {
        return $this->statusUpdates
            ->first(fn (StaffTaskStatusUpdate $update) => $update->reminder_date?->isSameDay($date));
    }

    public function reminderNotesForDate(Carbon $date): ?string
    {
        return $this->reminderStatusForDate($date)?->reminderNotes();
    }

    public function nextEditableReminderDate(): ?Carbon
    {
        foreach ($this->reminderDates() as $date) {
            $reminderStatus = $this->reminderStatusForDate($date);

            if (! $reminderStatus || ! $reminderStatus->isFinalizedReminder()) {
                return $date->copy()->startOfDay();
            }
        }

        return null;
    }

    public function reminderScheduleStateForDate(Carbon $date): array
    {
        $date = $date->copy()->startOfDay();
        $today = now()->startOfDay();
        $reminderStatus = $this->reminderStatusForDate($date);
        $nextEditableDate = $this->nextEditableReminderDate();

        if ($reminderStatus?->isFinalizedReminder()) {
            return [
                'state' => 'completed',
                'editable' => false,
                'locked' => true,
                'label' => $reminderStatus->reminderStatusLabel(),
                'status' => $reminderStatus->status,
                'notes' => $reminderStatus->reminderNotes(),
                'completed_by' => $reminderStatus->user?->name,
                'completed_at' => $reminderStatus->completed_at ?? $reminderStatus->created_at,
            ];
        }

        if ($nextEditableDate && $date->isSameDay($nextEditableDate) && $date->lte($today)) {
            return [
                'state' => 'active',
                'editable' => true,
                'locked' => false,
                'label' => 'Pending',
                'status' => $reminderStatus?->status ?? 'pending',
                'notes' => $reminderStatus?->reminderNotes(),
                'completed_by' => null,
                'completed_at' => null,
            ];
        }

        return [
            'state' => 'locked',
            'editable' => false,
            'locked' => true,
            'label' => 'Locked',
            'status' => $reminderStatus?->status ?? 'pending',
            'notes' => $reminderStatus?->reminderNotes(),
            'completed_by' => $reminderStatus?->user?->name,
            'completed_at' => $reminderStatus?->completed_at ?? $reminderStatus?->created_at,
        ];
    }

    public function statusLabel(): string
    {
        return self::statusOptions()[$this->status]
            ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function scheduleStatusLabel(?Carbon $date = null): string
    {
        $date = $date?->copy()->startOfDay() ?? now()->startOfDay();
        $reminderStatus = $this->reminderStatusForDate($date);

        if ($reminderStatus) {
            return match ($reminderStatus->status) {
                'done' => 'Done',
                'in_progress' => 'In Progress',
                'reschedule' => 'Reschedule',
                'cancelled' => 'Cancelled',
                default => 'Pending',
            };
        }

        $dueDate = $this->due_date?->copy()->startOfDay();

        if ($this->status === 'done') {
            return 'Done';
        }

        if ($this->status === 'reschedule') {
            return 'Reschedule';
        }

        if ($dueDate && $date->gt($dueDate)) {
            return 'Delayed';
        }

        return $this->statusLabel();
    }

    public function reminderDates(?Carbon $fromDate = null): Collection
    {
        $dueDate = $this->due_date;
        $weekdays = array_values(array_filter(array_map(
            fn ($weekday) => strtolower(trim((string) $weekday)),
            $this->reminder_weekdays ?? []
        )));

        if (! $dueDate || $weekdays === []) {
            return collect();
        }

        $start = ($fromDate ? $fromDate->copy() : now()->startOfDay())->startOfDay();
        $end = $dueDate->copy()->startOfDay();

        if ($start->gt($end)) {
            return collect();
        }

        $dates = collect();
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            if (in_array(strtolower($cursor->format('l')), $weekdays, true)) {
                $dates->push($cursor->copy());
            }

            $cursor->addDay();
        }

        return $dates;
    }
}
