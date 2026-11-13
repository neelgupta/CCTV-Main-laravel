<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Amc extends Model
{
    use HasFactory, BelongsToCompany, HasObfuscatedRouteKey;

    public const TYPE_OPTIONS = [
        'comprehensive' => 'Comprehensive (Includes Parts + Service)',
        'non_comprehensive' => 'Non-Comprehensive (Service Only)',
    ];

    public const VISIT_FREQUENCY_OPTIONS = [
        'monthly' => 12,
        'quarterly' => 4,
        'half_yearly' => 2,
    ];

    public const CAMERA_TYPE_OPTIONS = [
        'dome' => 'Dome',
        'bullet' => 'Bullet',
        'ptz' => 'PTZ',
    ];

    public const SERVICE_SCOPE_OPTIONS = [
        'camera_cleaning' => 'Camera Cleaning',
        'wiring_check' => 'Wiring Check',
        'dvr_nvr_check' => 'DVR/NVR Check',
        'recording_check' => 'Recording Check',
        'remote_viewing_setup' => 'Remote Viewing Setup',
        'software_update' => 'Software Update',
    ];

    public const PAYMENT_MODE_OPTIONS = [
        'cash' => 'Cash',
        'upi' => 'UPI',
        'bank_transfer' => 'Bank Transfer',
    ];

    public const PAYMENT_STATUS_OPTIONS = [
        'paid' => 'Paid',
        'pending' => 'Pending',
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'client_id',
        'amc_plan_id',
        'assigned_staff_id',
        'support_ticket_id',
        'created_by',
        'contract_no',
        'customer_name',
        'project_name',
        'mobile_number',
        'email',
        'address',
        'latitude',
        'longitude',
        'gst_number',
        'start_date',
        'end_date',
        'amc_type',
        'plan_name',
        'plan_duration_months',
        'plan_price',
        'plan_description',
        'includes_on_call_support',
        'service_mode',
        'visit_frequency',
        'total_visits',
        'scheduled_visits',
        'remaining_visits',
        'total_cameras',
        'camera_types',
        'dvr_nvr_model',
        'hard_disk_size',
        'installation_location',
        'service_scope',
        'amc_amount',
        'subtotal',
        'discount',
        'tax_percent',
        'tax_amount',
        'total_amount',
        'payment_mode',
        'payment_status',
        'service_timing',
        'tasks',
        'parts_replacement_policy',
        'emergency_support_terms',
        'technician_name',
        'technician_contact_number',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'plan_duration_months' => 'integer',
        'plan_price' => 'decimal:2',
        'includes_on_call_support' => 'boolean',
        'scheduled_visits' => 'integer',
        'remaining_visits' => 'integer',
        'camera_types' => 'array',
        'service_scope' => 'array',
        'tasks' => 'array',
        'amc_amount' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected $hidden = [
        'subtotal',
        'tax_amount',
    ];

    public function getRouteKeyName()
    {
        return 'id';
    }

    public function getSecureReferenceAttribute(): string
    {
        $routeKey = preg_replace('/[^A-Za-z0-9]/', '', $this->getRouteKey());

        return 'AMC-SEC-' . Str::upper(Str::substr((string) $routeKey, 0, 10));
    }

    public static function nextContractNo(?int $companyId): string
    {
        $year   = now()->format('Y');
        $prefix = "AMC-{$year}-{$companyId}-";

        $latest = static::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('contract_no', 'like', $prefix . '%')
            ->orderByDesc('contract_no')
            ->value('contract_no');

        $sequence = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function amcPlan()
    {
        return $this->belongsTo(AmcPlan::class);
    }

    public function assignedStaff()
    {
        return $this->belongsTo(Staff::class, 'assigned_staff_id');
    }

    public function supportTicket()
    {
        return $this->belongsTo(Ticket::class, 'support_ticket_id');
    }

    public function visits()
    {
        return $this->hasMany(AmcVisit::class)->orderBy('visit_no');
    }

    public function visitHistories()
    {
        return $this->hasManyThrough(AmcVisitHistory::class, AmcVisit::class);
    }

    public function lastVisit(): ?AmcVisit
    {
        $today = now()->startOfDay();

        if ($this->relationLoaded('visits')) {
            return $this->visits
                ->filter(fn (AmcVisit $visit) => $visit->visit_date && $visit->visit_date->lt($today))
                ->sortByDesc(fn (AmcVisit $visit) => $visit->visit_date?->timestamp ?? 0)
                ->first();
        }

        return $this->visits()
            ->whereDate('visit_date', '<', $today->toDateString())
            ->orderByDesc('visit_date')
            ->orderByDesc('visit_no')
            ->first();
    }

    public function nextVisit(): ?AmcVisit
    {
        $today = now()->startOfDay();

        if ($this->relationLoaded('visits')) {
            return $this->visits
                ->filter(fn (AmcVisit $visit) => $visit->visit_date && $visit->visit_date->gte($today))
                ->sortBy(fn (AmcVisit $visit) => $visit->visit_date?->timestamp ?? PHP_INT_MAX)
                ->first();
        }

        return $this->visits()
            ->whereDate('visit_date', '>=', $today->toDateString())
            ->orderBy('visit_date')
            ->orderBy('visit_no')
            ->first();
    }

    public function visitStatusLabelFor(?AmcVisit $visit): string
    {
        $statusKey = strtolower((string) $visit?->status);

        return $statusKey !== ''
            ? str($statusKey)->headline()->toString()
            : 'N/A';
    }

    public function visitStatusKeyFor(?AmcVisit $visit): string
    {
        return strtolower((string) $visit?->status);
    }

    public function lastVisitStatusKey(): string
    {
        return $this->visitStatusKeyFor($this->lastVisit());
    }

    public function lastVisitStatusLabel(): string
    {
        return $this->visitStatusLabelFor($this->lastVisit());
    }

    public function nextVisitStatusKey(): string
    {
        return $this->visitStatusKeyFor($this->nextVisit());
    }

    public function nextVisitStatusLabel(): string
    {
        return $this->visitStatusLabelFor($this->nextVisit());
    }

    public function isOnCall(): bool
    {
        return $this->service_mode === 'on_call' || $this->visit_frequency === 'on_call';
    }

    public function hasOnCallSupport(): bool
    {
        return in_array($this->service_mode, ['on_call', 'hybrid'], true)
            || $this->isOnCall()
            || (bool) $this->includes_on_call_support;
    }

    public function isHybrid(): bool
    {
        return $this->service_mode === 'hybrid';
    }

    public function generatesScheduledVisits(): bool
    {
        return in_array($this->service_mode, ['scheduled', 'hybrid'], true)
            || ($this->service_mode === null && $this->visit_frequency !== 'on_call');
    }

    public function serviceModeLabel(): string
    {
        return AmcPlan::SERVICE_MODE_OPTIONS[$this->service_mode]
            ?? ($this->isOnCall() ? AmcPlan::SERVICE_MODE_OPTIONS['on_call'] : AmcPlan::SERVICE_MODE_OPTIONS['scheduled']);
    }

    public function visitIntervalMonths(): int
    {
        return match ($this->visit_frequency) {
            'quarterly' => 3,
            'half_yearly' => 6,
            default => 1,
        };
    }

    public static function generateVisitSchedule(string|\DateTimeInterface $startDate, string $visitFrequency, ?string $technician = null): array
    {
        if ($visitFrequency === 'every_call') {
            return [];
        }

        $baseDate = static::normalizeVisitDate(
            Carbon::parse($startDate)->startOfDay()->addDays(2)
        );
        $totalVisits = static::VISIT_FREQUENCY_OPTIONS[$visitFrequency] ?? 0;
        $intervalMonths = match ($visitFrequency) {
            'quarterly' => 3,
            'half_yearly' => 6,
            default => 1,
        };

        $schedule = [];

        for ($index = 0; $index < $totalVisits; $index++) {
            $visitDate = static::normalizeVisitDate(
                $baseDate->copy()->addMonthsNoOverflow($index * $intervalMonths)
            );

            $schedule[] = [
                'visit_no' => $index + 1,
                'visit_date' => $visitDate->toDateString(),
                'status' => 'pending',
                'technician' => $technician,
            ];
        }

        return $schedule;
    }

    public static function calculateEndDate(string|\DateTimeInterface $startDate, int $durationMonths): Carbon
    {
        return Carbon::parse($startDate)
            ->startOfDay()
            ->addMonthsNoOverflow($durationMonths)
            ->subDay();
    }

    public function visitTimeline(?Carbon $today = null): array
    {
        $today = ($today ?: now())->copy()->startOfDay();
        $visits = $this->relationLoaded('visits')
            ? $this->visits
            : $this->visits()->get(['id', 'amc_id', 'visit_no', 'visit_date', 'status', 'technician']);

        $lastVisit = $visits
            ->filter(fn (AmcVisit $visit) => $visit->visit_date && $visit->visit_date->lt($today))
            ->sortByDesc(fn (AmcVisit $visit) => $visit->visit_date?->timestamp ?? 0)
            ->first();

        $nextVisit = $visits
            ->filter(fn (AmcVisit $visit) => $visit->visit_date && $visit->visit_date->gte($today))
            ->sortBy(fn (AmcVisit $visit) => $visit->visit_date?->timestamp ?? 0)
            ->first();

        $editableVisit = $nextVisit ?: $visits
            ->first(fn (AmcVisit $visit) => blank($visit->visit_date));

        return [
            'today' => $today,
            'visits' => $visits,
            'lastVisit' => $lastVisit,
            'nextVisit' => $nextVisit,
            'editableVisit' => $editableVisit,
        ];
    }

    public function latestVisitActivity(): array
    {
        $visits = $this->relationLoaded('visits')
            ? $this->visits
            : $this->visits()->get(['id', 'amc_id', 'visit_no', 'visit_date', 'status', 'technician', 'visit_type', 'support_ticket_id']);

        $candidates = $visits
            ->filter(fn (AmcVisit $visit) => $visit->visit_date)
            ->map(function (AmcVisit $visit): array {
                return [
                    'source' => 'visit',
                    'date' => $visit->visit_date,
                    'status' => (string) $visit->status,
                    'record' => $visit,
                ];
            });

        $supportTicket = $this->relationLoaded('supportTicket')
            ? $this->supportTicket
            : $this->supportTicket()->first(['id', 'visit_date', 'status']);

        if ($supportTicket?->visit_date) {
            $candidates->push([
                'source' => 'support_ticket',
                'date' => $supportTicket->visit_date,
                'status' => (string) $supportTicket->status,
                'record' => $supportTicket,
            ]);
        }

        if ($candidates->isEmpty()) {
            return [
                'source' => null,
                'date' => null,
                'status' => null,
                'record' => null,
            ];
        }

        return $candidates
            ->sortByDesc(fn (array $candidate) => $candidate['date']?->timestamp ?? 0)
            ->first();
    }

    public function isServiceClosed(): bool
    {
        $visits = $this->relationLoaded('visits')
            ? $this->visits
            : $this->visits()->get(['id', 'amc_id', 'status']);

        if ($visits->isEmpty()) {
            return false;
        }

        return $visits->every(function (AmcVisit $visit): bool {
            return strtolower(trim((string) $visit->status)) === 'completed';
        });
    }

    public function serviceClosureMessage(): string
    {
        return "This AMC service is closed. You can't change anything in this AMC.";
    }

    public function visitInvoiceCount(): int
    {
        $visits = $this->relationLoaded('visits')
            ? $this->visits->loadMissing('ticketInvoice')
            : $this->visits()->with('ticketInvoice:id,total_amount,invoice_total')->get(['id', 'amc_id', 'ticket_invoice_id']);

        return $visits
            ->filter(fn (AmcVisit $visit) => $visit->ticketInvoice !== null)
            ->count();
    }

    public function visitInvoiceTotalAmount(): float
    {
        $visits = $this->relationLoaded('visits')
            ? $this->visits->loadMissing('ticketInvoice')
            : $this->visits()->with('ticketInvoice:id,total_amount,invoice_total')->get(['id', 'amc_id', 'ticket_invoice_id']);

        return (float) $visits->sum(function (AmcVisit $visit): float {
            return (float) ($visit->ticketInvoice?->total_amount ?? $visit->ticketInvoice?->invoice_total ?? 0);
        });
    }

    public function remainingVisitInvoiceAmount(): float
    {
        return max(0, (float) $this->total_amount - $this->visitInvoiceTotalAmount());
    }

    public static function generateVisitScheduleForPlan(
        string|\DateTimeInterface $startDate,
        int $durationMonths,
        int $visitCount,
        ?string $technician = null
    ): array {
        if ($visitCount <= 0) {
            return [];
        }

        $firstVisitDate = static::normalizeVisitDate(
            Carbon::parse($startDate)->startOfDay()->addDays(2)
        );
        $schedule = [];

        if ($visitCount === 1) {
            return [[
                'visit_no' => 1,
                'visit_date' => $firstVisitDate->toDateString(),
                'status' => 'pending',
                'technician' => $technician,
            ]];
        }

        for ($index = 0; $index < $visitCount; $index++) {
            $monthOffset = (int) floor(($index * $durationMonths) / $visitCount);
            $visitDate = static::normalizeVisitDate(
                $firstVisitDate->copy()->addMonthsNoOverflow($monthOffset)
            );

            $schedule[] = [
                'visit_no' => $index + 1,
                'visit_date' => $visitDate->toDateString(),
                'status' => 'pending',
                'technician' => $technician,
            ];
        }

        return $schedule;
    }

    private static function normalizeVisitDate(Carbon $date): Carbon
    {
        if ($date->isSunday()) {
            return $date->addDay();
        }

        return $date;
    }

    public static function formatDurationMonths(?int $durationMonths): string
    {
        $durationMonths = (int) $durationMonths;

        if ($durationMonths <= 0) {
            return '-';
        }

        if ($durationMonths < 12) {
            return $durationMonths . ' Month' . ($durationMonths === 1 ? '' : 's');
        }

        if ($durationMonths % 12 === 0) {
            $years = (int) ($durationMonths / 12);

            return $years . ' Year' . ($years === 1 ? '' : 's');
        }

        $years = (int) floor($durationMonths / 12);
        $months = $durationMonths % 12;
        $parts = [];

        if ($years > 0) {
            $parts[] = $years . ' Year' . ($years === 1 ? '' : 's');
        }

        if ($months > 0) {
            $parts[] = $months . ' Month' . ($months === 1 ? '' : 's');
        }

        return implode(' ', $parts);
    }

    public static function visitFrequencyLabel(?string $visitFrequency): string
    {
        if ($visitFrequency === 'every_call') {
            return 'Every Call';
        }

        if (array_key_exists($visitFrequency, static::VISIT_FREQUENCY_OPTIONS)) {
            return str($visitFrequency)->replace('_', ' ')->title()->toString();
        }

        return str($visitFrequency ?? '')->replace('_', ' ')->title()->toString();
    }
}
