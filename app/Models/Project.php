<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToCompany;
use App\Models\Company;
use Illuminate\Support\Str;
use App\Models\State;
use App\Models\City;

class Project extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'uuid',
        'reference_no',
        'lead_id',
        'contractor_id',
        'name',
        'client_name',
        'state_id',
        'city_id',
        'latitude',
        'longitude',
        'description',
        'current_stage',
        'project_manager_id',
        'team_member_ids',
        'commission_type',
        'commission_value',
        'start_date',
        'end_date',
        'completion_percentage',
        'site_preparation_status',
        'fixing_welding_status',
        'testing_commissioning_status',
        'final_inspection_status',
        'client_handover_status',
        'installation_completed_at',
        'installation_fail_reason',
        'status',
        'custom_fields',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'team_member_ids' => 'array',
        'commission_value' => 'decimal:2',
        'completion_percentage' => 'integer',
        'installation_completed_at' => 'datetime',
        'custom_fields' => 'array',
    ];

    protected static function booted()
    {
        static::creating(function (Project $project) {
            if (empty($project->uuid)) {
                $project->uuid = (string) Str::uuid();
            }

            if (empty($project->reference_no)) {
                $project->reference_no = static::nextReferenceNo($project->company_id);
            }

            if (empty($project->status)) {
                $project->status = 'on_track';
            }

            if (empty($project->current_stage)) {
                $project->current_stage = 'new-project';
            }

            if ($project->completion_percentage === null) {
                $project->completion_percentage = 0;
            }
        });

        static::saved(function (Project $project) {
            if (! $project->wasRecentlyCreated && ! $project->wasChanged('project_manager_id')) {
                return;
            }

            try {
                app(\App\Services\DashboardNotificationService::class)->notifyProjectManagerAssigned($project);
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
            ->where($field, $value)
            ->firstOrFail();
    }

    public function scopeAccessibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $accessQuery) use ($user): void {
            $accessQuery->where('project_manager_id', $user->id)
                ->orWhereJsonContains('team_member_ids', (int) $user->id)
                ->orWhereJsonContains('team_member_ids', (string) $user->id)
                ->orWhereHas('lead', function (Builder $leadQuery) use ($user): void {
                    $leadQuery->where('assigned_to', $user->id)
                        ->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($user): void {
                            $assignedUsersQuery->whereKey($user->id);
                        });
                });
        });
    }

    public static function nextReferenceNo(?int $companyId): string
    {
        $company = $companyId ? Company::query()->find($companyId) : null;
        $year = (int) ($company?->project_reference_year ?: now()->year);
        $prefix = trim((string) ($company?->project_reference_prefix ?: 'PRJ'));
        $prefix = $prefix !== '' ? $prefix . '-' . $year . '-' : 'PRJ-' . $year . '-';
        $sequence = (int) ($company?->project_reference_sequence ?: 1);
        // IMPORTANT: ignore company global scope because reference_no is globally unique.
        $maxSequence = (int) static::query()
            ->withoutGlobalScopes()
            ->where('reference_no', 'like', $prefix . '%')
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING_INDEX(reference_no, '-', -1) AS UNSIGNED)), 0) as max_seq")
            ->value('max_seq');

        $sequence = max($sequence, $maxSequence + 1);

        $referenceNo = sprintf('%s%04d', $prefix, $sequence);
        while (static::query()->withoutGlobalScopes()->where('reference_no', $referenceNo)->exists()) {
            $sequence++;
            $referenceNo = sprintf('%s%04d', $prefix, $sequence);
        }

        return $referenceNo;
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function contractor()
    {
        return $this->belongsTo(Contractor::class);
    }

    public function drawings()
    {
        return $this->hasMany(ProjectDrawing::class);
    }

    public function materials()
    {
        return $this->hasMany(ProjectMaterial::class);
    }

    public function tasks()
    {
        return $this->hasMany(StaffTask::class);
    }

    public function productions()
    {
        return $this->hasMany(ProjectProduction::class);
    }

    public function activities()
    {
        return $this->hasMany(ProjectActivity::class);
    }

    public function comments()
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function challans()
    {
        return $this->hasMany(ProjectChallan::class);
    }

    public function amcs()
    {
        return $this->hasMany(Amc::class);
    }

    public function supportTickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function getStageLabelAttribute(): string
    {
        if (in_array($this->current_stage, ['drawing', 'new-project'], true)) {
            return 'New Project';
        }

        if ($this->current_stage === 'in-progress') {
            return 'In Progress';
        }

        return str($this->current_stage)->replace('_', ' ')->title()->toString();
    }

    public function getNormalizedCurrentStageAttribute(): string
    {
        return in_array($this->current_stage, ['drawing', 'new-project'], true)
            ? 'new-project'
            : (string) $this->current_stage;
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
}
