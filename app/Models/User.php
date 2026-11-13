<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasObfuscatedRouteKey, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'email_verified_at',
        'password',
        'role',
        'role_id',
        'is_active',
        'razorpay_key_id',
        'razorpay_secret',
        'razorpay_currency',
        'razorpay_public_key',
        'razorpay_verify_ssl',
        'profile_photo',
        'terms_accepted_at',
        'terms_version',
        'terms_accepted_ip',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'terms_accepted_at' => 'datetime',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function staff()
    {
        return $this->hasOne(Staff::class);
    }

    public function assignedTickets()
    {
        return $this->hasMany(Ticket::class, 'assigned_to');
    }

    public function ticketAssignments()
    {
        return $this->belongsToMany(Ticket::class, 'ticket_user_assignments')
            ->withTimestamps();
    }

    public function createdTickets()
    {
        return $this->hasMany(Ticket::class, 'user_id');
    }

    public function roleRecord()
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function staffApiTokens(): HasMany
    {
        return $this->hasMany(StaffApiToken::class);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(UserNotificationPreference::class);
    }

    public function pushDevices(): HasMany
    {
        return $this->hasMany(UserPushDevice::class);
    }

    public function prefersPushNotifications(): bool
    {
        return (bool) ($this->notificationPreference?->push_enabled ?? true);
    }

    public function getProfilePhotoUrlAttribute(): ?string
    {
        if (empty($this->profile_photo)) {
            return null;
        }

        $path = ltrim(str_replace('\\', '/', (string) $this->profile_photo), '/');

        if (Str::startsWith($path, ['http://', 'https://', 'data:'])) {
            return $path;
        }

        return route('profile.photo', ['v' => $this->updated_at?->timestamp ?? time()]);
    }

    public function hasVerifiedEmail(): bool
    {
        return true;
    }

    public function isSuperAdmin()
    {
        return $this->hasRole('super_admin', 'admin');
    }

    public function isCompanyAdmin()
    {
        return $this->hasRole('company_admin', 'companyadmin');
    }

    public function isClient(): bool
    {
        return $this->hasRole('client');
    }

    public function isStaffOrAdmin(): bool
    {
        return $this->isSuperAdmin() || $this->isCompanyAdmin() || $this->staff !== null || ! $this->isClient();
    }

    public function canManageCompanySettings(): bool
    {
        return $this->isCompanyAdmin();
    }

    public function normalizedRole(): string
    {
        $roleSlug = $this->roleRecord?->slug;
        if (!empty($roleSlug)) {
            return strtolower(trim((string) $roleSlug));
        }

        return strtolower(trim((string) $this->role));
    }

    public function hasRole(string ...$roles): bool
    {
        $current = $this->normalizedRole();
        $targets = array_map(fn ($r) => strtolower(trim((string) $r)), $roles);

        foreach ($targets as $target) {
            if ($current === $target) {
                return true;
            }

            if ($target === 'super_admin' && $current === 'admin') {
                return true;
            }

            if ($target === 'company_admin' && $current === 'companyadmin') {
                return true;
            }

            if ($target === 'company_user' && $this->company_id !== null && !$this->isSuperAdmin()) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isSuperAdmin() || $this->isCompanyAdmin()) {
            return true;
        }

        $key = Str::lower(trim($permissionKey));
        $permissionKeys = [$key];

        if (Str::startsWith($key, 'support.')) {
            $permissionKeys[] = 'tickets.' . Str::after($key, 'support.');
        } elseif (Str::startsWith($key, 'tickets.')) {
            $permissionKeys[] = 'support.' . Str::after($key, 'tickets.');
        }

        $role = $this->roleRecord;
        if (!$role) {
            return false;
        }

        return $role->permissions()->whereIn('key', array_values(array_unique(array_merge(['all.access'], $permissionKeys))))->exists();
    }

    public function hasAnyPermission(string ...$permissionKeys): bool
    {
        foreach ($permissionKeys as $permissionKey) {
            if ($this->hasPermission($permissionKey)) {
                return true;
            }
        }

        return false;
    }

    public function scopeActiveAssigneeOrAdmin($query)
    {
        $today = now()->toDateString();
        $activeStatuses = ['active', 'trialing'];

        return $query
            ->where('is_active', true)
            ->where(function ($builder) use ($today, $activeStatuses) {
                $builder->where(function ($staffBuilder) use ($today, $activeStatuses) {
                    $staffBuilder->whereHas('staff', function ($staffQuery) use ($today, $activeStatuses) {
                        $staffQuery->whereHas('companySubscription', function ($subQuery) use ($today, $activeStatuses) {
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
                    });
                })->orWhere(function ($companyAdminQuery) {
                    $companyAdminQuery
                        ->whereHas('roleRecord', function ($roleQuery) {
                            $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                        })
                        ->orWhereIn('role', ['company_admin', 'companyadmin']);
                });
            });
    }
}
