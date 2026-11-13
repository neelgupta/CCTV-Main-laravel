<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\StaffBankDetails;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use App\Models\StaffAdvance;
use App\Models\StaffRoutePoint;
use App\Models\StaffAppActivity;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\MediaFile;
use Illuminate\Database\Eloquent\SoftDeletes;

class Staff extends Model
{
    //
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'user_id', 'employee_id', 'name', 'role_id',
        'department', 'join_date', 'contact_number',
        'company_id', 'profile_photo',
        'address', 'city', 'state', 'country', 'zipcode',
        'company_subscription_id', 'license_key',
        'amc_daily_visit_limit',
    ];

    use SoftDeletes; // 2. Use the trait

    protected $dates = ['deleted_at'];
    // Relationships
    public function user() {
        return $this->belongsTo(User::class);
    }

    public function companySubscription()
    {
        return $this->belongsTo(CompanySubscription::class);
    }

    public function salaryConfiguration() {
        return $this->hasOne(SalaryConfiguration::class);
    }

    public function bankDetails() {
        return $this->hasOne(StaffBankDetails::class);
    }

    public function documents() {
        return $this->hasMany(StaffDocument::class);
    }

    public function attendance() {
        return $this->hasMany(StaffAttendance::class, 'staff_id');
    }

    public function salaryPayments(): HasMany
    {
        return $this->hasMany(StaffSalaryPayment::class, 'staff_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(StaffActivity::class, 'staff_id');
    }

    public function advances(): HasMany
    {
        return $this->hasMany(StaffAdvance::class, 'staff_id');
    }

    public function routePoints(): HasMany
    {
        return $this->hasMany(StaffRoutePoint::class, 'staff_id');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(StaffApiToken::class);
    }

    public function latestMobileToken()
    {
        return $this->hasOne(StaffApiToken::class)->latestOfMany('created_at');
    }

    public function appActivities(): HasMany
    {
        return $this->hasMany(StaffAppActivity::class, 'staff_id');
    }

    public function latestAppActivity()
    {
        return $this->hasOne(StaffAppActivity::class, 'staff_id')->latestOfMany('last_active_at');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isAmcTechnicianCandidate(): bool
    {
        $role = strtolower(trim((string) ($this->role?->slug ?? '')));
        $roleName = strtolower(trim((string) ($this->role?->name ?? '')));

        if (in_array($role, ['technician', 'employee'], true) || str_contains($role, 'technician')) {
            return true;
        }

        return str_contains($roleName, 'technician');
    }

    public function amcDailyVisitLimit(): int
    {
        $limit = (int) ($this->amc_daily_visit_limit ?? 0);

        return $limit > 0 ? $limit : 5;
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (empty($this->profile_photo)) {
            return null;
        }

        $rawPath = str_replace('\\', '/', (string) $this->profile_photo);
        $path = ltrim($rawPath, '/');

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        $media = MediaFile::query()
            ->where('company_id', $this->company_id)
            ->where('path', $path)
            ->first();

        $disk = (string) ($media?->disk ?: (env('AWS_ACCESS_KEY_ID') ? 's3' : 'public'));

        return route('api.staff.profile-image.public', [
            'company_id' => $this->company_id,
            'disk' => $disk,
            'path' => $path,
            'name' => basename($path),
        ]);
    }

    public static function generateEmployeeId(int $companyId): string
    {
        $prefix = 'EMP-' . $companyId . '-';

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $prefix . now()->format('ymdHis') . '-' . Str::upper(Str::random(4));

            if (!static::query()->where('employee_id', $candidate)->exists()) {
                return $candidate;
            }
        }

        return $prefix . now()->format('ymdHisv') . '-' . Str::upper(Str::random(8));
    }

    public function scopeActiveWithActiveSubscription($query)
    {
        $today = now()->toDateString();
        $activeStatuses = ['active', 'trialing'];

        return $query
            ->whereHas('user', function ($userQuery) {
                $userQuery->where('is_active', true);
            })
            ->whereHas('companySubscription', function ($subQuery) use ($today, $activeStatuses) {
                $subQuery->whereIn('status', $activeStatuses)
                    ->where(function ($q) use ($today) {
                        $q->whereNull('starts_at')
                            ->orWhereDate('starts_at', '<=', $today);
                    })
                    ->where(function ($q) use ($today) {
                        $q->whereNull('ends_at')
                            ->orWhereDate('ends_at', '>=', $today);
                    });
            });
    }
}
