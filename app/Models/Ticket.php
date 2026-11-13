<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Services\AmcSupportWorkflowService;
use App\Services\DashboardNotificationService;
use App\Services\TicketAssignmentHistoryService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    public const TASK_STATUS_OPTIONS = [
        'pending' => 'Pending',
        'done' => 'Done',
    ];

    public const STATUS_OPTIONS = [
        'open' => 'Pending',
        'in_progress' => 'In Progress',
        'answered' => 'Answered',
        'resolved' => 'Resolved',
        'hold' => 'Hold',
        'reschedule' => 'Reschedule',
        'closed' => 'Closed',
    ];

    public const TYPE_OPTIONS = [
        'complain' => 'Complain',
        // 'sale' => 'Sale',
        // 'meeting' => 'Meeting',
        'support' => 'Support',
    ];

    protected $fillable = [
        'company_id',
        'user_id',
        'assigned_to',
        'project_id',
        'ticket_number',
        'customer_contact_number',
        'customer_name',
        'customer_email',
        'customer_address',
        'type',
        'referenced_by',
        'subject',
        'priority',
        'status',
        'accept_job',
        'board_stage',
        'visit_date',
        'preferred_visit_time',
        'description',
        'tasks',
        'service_notes',
        'work_summary',
        'complate_task_list',
        'start_time',
        'end_time',
        'quote_items',
        'quote_subtotal',
        'quote_discount',
        'quote_gst_amount',
        'quote_total',
        'quote_id',
        'customer_signature',
        'service_started_at',
        'service_ended_at',
        'current_condition_photos',
        'after_repair_photos',
        'generated_from_amc_id',
        'source_type',
        'source_id',
        'visit_number',
        'visit_type',
        'parent_ticket_id',
        'repeat_count',
        'repeated_after_days',
        'assigned_previous_staff_id',
        'repeat_issue_reason',
        'issue_category',
        'repeat_status',
        'last_repeat_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'visit_date' => 'date',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'accept_job' => 'boolean',
        'complate_task_list' => 'array',
        'quote_items' => 'array',
        'quote_subtotal' => 'decimal:2',
        'quote_discount' => 'decimal:2',
        'quote_gst_amount' => 'decimal:2',
        'quote_total' => 'decimal:2',
        'service_started_at' => 'datetime',
        'service_ended_at' => 'datetime',
        'current_condition_photos' => 'array',
        'after_repair_photos' => 'array',
        'tasks' => 'array',
        'repeat_count' => 'integer',
        'repeated_after_days' => 'integer',
        'repeat_status' => 'boolean',
        'last_repeat_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function getStartTimeAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->service_started_at;
    }

    public function setStartTimeAttribute(mixed $value): void
    {
        $this->attributes['service_started_at'] = $value;
    }

    public function getEndTimeAttribute(): ?\Illuminate\Support\Carbon
    {
        return $this->service_ended_at;
    }

    public function setEndTimeAttribute(mixed $value): void
    {
        $this->attributes['service_ended_at'] = $value;
    }

    protected static function booted(): void
    {
        static::created(function (self $ticket): void {
            if ($ticket->assigned_to && Schema::hasTable('ticket_user_assignments')) {
                try {
                    if ($ticket->assignedUsers()->count() === 0) {
                        $ticket->assignedUsers()->syncWithoutDetaching([(int) $ticket->assigned_to]);
                    }
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }

            if (! $ticket->assigned_to) {
                return;
            }

            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                $ticket,
                (int) $ticket->assigned_to,
                auth()->user(),
                $ticket->repeat_status ? 'Repeat' : 'Initial'
            );

            try {
                app(DashboardNotificationService::class)->notifyTicketAssigned($ticket);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        });

        static::updated(function (self $ticket): void {
            if (! $ticket->isDirty('assigned_to') && ! $ticket->wasChanged('assigned_to')) {
                return;
            }

            if ($ticket->assigned_to && Schema::hasTable('ticket_user_assignments')) {
                try {
                    if ($ticket->assignedUsers()->count() === 0) {
                        $ticket->assignedUsers()->sync([(int) $ticket->assigned_to]);
                    }
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }

            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                $ticket->fresh(),
                $ticket->assigned_to ? (int) $ticket->assigned_to : null,
                auth()->user(),
                'Manual'
            );

            try {
                app(DashboardNotificationService::class)->notifyTicketAssigned($ticket);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        });

        static::updated(function (self $ticket): void {
            if (! $ticket->wasChanged('status')) {
                return;
            }

            app(AmcSupportWorkflowService::class)->advanceSupportWorkflowForTicket(
                $ticket->fresh(['generatedFromAmc', 'amc']),
                auth()->user()
            );
        });
    }

    public static function typeOptions(): array
    {
        return self::TYPE_OPTIONS;
    }

    public function getTypeLabelAttribute(): string
    {
        if (! $this->type) {
            return '-';
        }

        return static::TYPE_OPTIONS[$this->type]
            ?? str($this->type)->replace('_', ' ')->title()->toString();
    }

    public static function statusOptions(): array
    {
        return self::STATUS_OPTIONS;
    }

    public static function taskStatusOptions(): array
    {
        return self::TASK_STATUS_OPTIONS;
    }

    public static function normalizeTaskStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return array_key_exists($normalized, self::TASK_STATUS_OPTIONS)
            ? $normalized
            : 'pending';
    }

    public static function normalizeTaskItem(mixed $task): ?array
    {
        if (is_array($task)) {
            $name = Str::squish((string) ($task['name'] ?? $task['task'] ?? ''));

            if ($name === '') {
                return null;
            }

            return [
                'name' => $name,
                'status' => self::normalizeTaskStatus($task['status'] ?? null),
            ];
        }

        $name = Str::squish((string) $task);

        return $name === '' ? null : [
            'name' => $name,
            'status' => 'pending',
        ];
    }

    public static function normalizeTaskItems(mixed $tasks): array
    {
        return collect(is_array($tasks) ? $tasks : [])
            ->map(fn ($task) => self::normalizeTaskItem($task))
            ->filter()
            ->unique(fn (array $task) => Str::lower($task['name']))
            ->values()
            ->all();
    }

    public static function boardStageOptions(?int $companyId = null): array
    {
        if ($companyId && Schema::hasTable('service_stages')) {
            $options = ServiceStage::optionsForCompany($companyId);
            if ($options !== []) {
                return $options;
            }
        }

        return collect(ServiceStage::DEFAULT_STAGES)
            ->mapWithKeys(fn (array $stage) => [$stage['key'] => $stage['name']])
            ->all();
    }

    public function getStatusLabelAttribute(): string
    {
        if (! $this->status) {
            return '-';
        }

        return static::STATUS_OPTIONS[$this->status]
            ?? str($this->status)->replace('_', ' ')->title()->toString();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'ticket_user_assignments')
            ->withTimestamps();
    }

    public function getAssignedUserNamesAttribute(): string
    {
        if ($this->relationLoaded('assignedUsers') && $this->assignedUsers->isNotEmpty()) {
            return $this->assignedUsers->pluck('name')->join(', ');
        }

        if ($this->relationLoaded('assignedUser') && $this->assignedUser) {
            return (string) $this->assignedUser->name;
        }

        return $this->assignedUser?->name ?? 'Unassigned';
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function referencedUser()
    {
        return $this->belongsTo(User::class, 'referenced_by');
    }

    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    public function amc()
    {
        return $this->hasOne(Amc::class, 'support_ticket_id');
    }

    public function documents()
    {
        return $this->hasMany(TicketDocument::class);
    }

    public function attachments()
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function invoices()
    {
        return $this->hasMany(TicketInvoice::class);
    }

    public function taskParts(): HasMany
    {
        return $this->hasMany(TaskPart::class, 'task_id');
    }

    public function quotes()
    {
        return $this->hasMany(TicketQuote::class);
    }

    public function generatedFromAmc()
    {
        return $this->belongsTo(Amc::class, 'generated_from_amc_id');
    }

    public function parentTicket()
    {
        return $this->belongsTo(self::class, 'parent_ticket_id');
    }

    public function repeatTickets()
    {
        return $this->hasMany(self::class, 'parent_ticket_id');
    }

    public function previousStaff()
    {
        return $this->belongsTo(User::class, 'assigned_previous_staff_id');
    }

    public function repeatHistories()
    {
        return $this->hasMany(TicketRepeatHistory::class)->latest('repeated_at');
    }

    public function assignmentHistories()
    {
        return $this->hasMany(TicketAssignmentHistory::class)->latest('assigned_at');
    }

    public function currentAssignmentHistory()
    {
        return $this->hasOne(TicketAssignmentHistory::class)->where('is_current', true)->latestOfMany('assigned_at');
    }

    public function latestRepeatHistory()
    {
        return $this->hasOne(TicketRepeatHistory::class)->latestOfMany('repeated_at');
    }

    public function getRepeatStatusLabelAttribute(): string
    {
        return $this->repeat_status ? 'Yes' : 'No';
    }

    public function isRepeatComplaint(): bool
    {
        return (bool) $this->repeat_status;
    }

    public static function generateTicketNumber(?int $companyId = null): string
    {
        $prefix = 'TKT-' . now()->format('Ymd') . '-';
        $latest = static::query()
            ->when($companyId, fn ($query) => $query->where('company_id', $companyId))
            ->where('ticket_number', 'like', $prefix . '%')
            ->orderByDesc('ticket_number')
            ->value('ticket_number');

        $sequence = $latest ? ((int) substr((string) $latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function stageForStatus(
        string $status,
        ?string $priority = null,
        ?int $assignedTo = null,
        int $repliesCount = 0,
        ?string $preferredStage = null,
        ?int $companyId = null
    ): string {
        $stageOptions = self::boardStageOptions($companyId);

        if ($preferredStage && array_key_exists($preferredStage, $stageOptions)) {
            return $preferredStage;
        }

        if ($companyId && Schema::hasTable('service_stages')) {
            return ServiceStage::resolveStageKey(
                $companyId,
                $status,
                $priority,
                $assignedTo,
                $repliesCount,
                $preferredStage
            );
        }

        return match ($status) {
            'in_progress' => 'in_progress',
            'hold' => 'hold',
            'answered' => 'testing',
            'reschedule' => 'reopen_reschedule',
            'resolved' => 'resolved',
            'closed' => 'close',
            'open' => self::stageForOpenTicket($priority, $assignedTo, $repliesCount),
            default => 'open',
        };
    }

    public static function statusForStage(string $boardStage, ?int $companyId = null): string
    {
        if ($companyId && Schema::hasTable('service_stages')) {
            return ServiceStage::resolveStatusForStage($companyId, $boardStage);
        }

        return match ($boardStage) {
            'in_progress' => 'in_progress',
            'hold' => 'hold',
            'testing' => 'answered',
            'reopen_reschedule' => 'reschedule',
            'resolved' => 'resolved',
            'close', 'done_close' => 'closed',
            'new', 'open', 'blocker' => 'open',
            default => 'open',
        };
    }

    private static function stageForOpenTicket(?string $priority, ?int $assignedTo, int $repliesCount): string
    {
        if (in_array($priority, ['urgent', 'high'], true) && $assignedTo) {
            return 'blocker';
        }

        if (! $assignedTo && $repliesCount === 0) {
            return 'new';
        }

        return 'open';
    }
}
