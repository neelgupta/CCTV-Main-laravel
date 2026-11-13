<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToCompany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use App\Models\LeadStatus;
use App\Models\LeadCategory;
use App\Models\User;
use App\Models\LeadActivity;
use App\Models\LeadSurvey;
use App\Models\LeadQuotation;
use App\Models\LeadComment;
use App\Models\LeadDocument;
use App\Models\LeadMaterial;
use App\Models\LeadFollowupHistory;
use App\Models\LeadFollowupSchedule;
use App\Models\Project;
use App\Models\State;
use App\Models\City;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class Lead extends Model
{
    use HasFactory, BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'lead_status_id', 'lead_category_id', 'assigned_to', 'created_by',
        'client_name', 'created_company_id', 'email', 'mobile', 'gst_number', 'pan_number', 'address', 'latitude','longitude', 'source',
        'referral_person_name',
        'custom_fields',
        'state_id', 'city_id',
        'estimated_value', 'priority', 'next_follow_up', 'stage_indicator',
        'phone',
        'loss_reason',
        'uuid',
    ];

    public static function defaultLossReasons(): array
    {
        return [
            'Budget constraint / Price too high',
            'Competitor chosen',
            'Client postponed / Delayed indefinitely',
            'Requirements cancelled / Not interested',
            'Unresponsive / Not reachable',
            'Scope / Specifications mismatch',
            'Out of service area / Location issue',
        ];
    }
    protected $casts = [
        'next_follow_up' => 'date',
        'custom_fields' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (Lead $lead) {
            if (empty($lead->uuid)) {
                $lead->uuid = (string) Str::uuid();
            }

            if (empty($lead->created_by) && auth()->check()) {
                $lead->created_by = auth()->id();
            }

            if (empty($lead->assigned_to) && auth()->check()) {
                $lead->assigned_to = auth()->id();
            }
        });

        static::saved(function (Lead $lead) {
            if (! $lead->next_follow_up || (! $lead->wasRecentlyCreated && ! $lead->wasChanged('next_follow_up'))) {
                return;
            }

            $lead->followupSchedules()->firstOrCreate(
                ['followup_date' => $lead->next_follow_up->toDateString()],
                [
                    'status' => 'pending',
                    'completed_at' => null,
                    'created_by' => auth()->id() ?: $lead->created_by,
                ]
            );
        });

        static::saved(function (Lead $lead) {
            if (! $lead->wasRecentlyCreated && ! $lead->wasChanged('assigned_to')) {
                return;
            }

            try {
                app(\App\Services\DashboardNotificationService::class)->notifyLeadAssigned($lead);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        });
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field = $field ?? $this->getRouteKeyName();

        return static::query()
            ->accessibleTo(auth()->user())
            ->where(function (Builder $query) use ($field, $value) {
                if ($field === 'uuid') {
                    $query->where('uuid', $value);
                    if (is_numeric($value)) {
                        $query->orWhere('id', (int) $value);
                    }
                } else {
                    $query->where($field, $value);
                }
            })
            ->firstOrFail();
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        // All users of the company can see all leads of their company by default
        $companyId = $user->company_id ?: $user->roleRecord?->company_id;
        if ($companyId) {
            return $query->where('company_id', $companyId);
        }

        return $query;
    }

    public function status()
    {
        return $this->belongsTo(LeadStatus::class, 'lead_status_id');
    }

    public function category()
    {
        return $this->belongsTo(LeadCategory::class, 'lead_category_id');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'lead_user_assignments')
            ->withTimestamps();
    }

    public function activities()
    {
        return $this->hasMany(LeadActivity::class);
    }

    public function surveys()
    {
        return $this->hasMany(LeadSurvey::class);
    }

    public function quotations()
    {
        return $this->hasMany(LeadQuotation::class);
    }

    public function comments()
    {
        return $this->hasMany(LeadComment::class);
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class);
    }

    public function materials()
    {
        return $this->hasMany(LeadMaterial::class);
    }

    public function followupHistories()
    {
        return $this->hasMany(LeadFollowupHistory::class);
    }

    public function followupSchedules()
    {
        return $this->hasMany(LeadFollowupSchedule::class);
    }

    public function createdCompany()
    {
        return $this->belongsTo(Company::class, 'created_company_id');
    }

    public function appointments()
    {
        return $this->hasMany(LeadAppointment::class);
    }

    public function latestFollowupHistory()
    {
        return $this->hasOne(LeadFollowupHistory::class)->latestOfMany();
    }

    public function latestFollowupSchedule()
    {
        return $this->hasOne(LeadFollowupSchedule::class)->latestOfMany();
    }

    public function project()
    {
        return $this->hasOne(Project::class);
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function formatCustomFieldValue(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        if (($field['type'] ?? null) === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Yes' : 'No';
        }

        if (($field['type'] ?? null) === 'date') {
            $date = $this->parseCustomFieldDateValue($value);
            if ($date) {
                return $date->format('d/m/Y');
            }
        }

        if (is_array($value)) {
            $value = implode(', ', array_map(static fn ($item) => trim((string) $item), $value));
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : 'N/A';
    }

    private function parseCustomFieldDateValue(mixed $value): ?Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                // Fall through to string parsing below.
            }
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach ([
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'd/m/Y',
            'M j, Y',
            'F j, Y',
            'j M Y',
            'd-m-Y',
            'm-d-Y',
        ] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Try the next format.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
