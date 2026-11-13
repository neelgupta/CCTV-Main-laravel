<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveConnection extends Model
{
    const OWNER_COMPANY     = 'company';
    const OWNER_SUPER_ADMIN = 'super_admin';

    const STATUS_ACTIVE       = 'active';
    const STATUS_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'company_id',
        'owner_type',
        'account_email',
        'account_id',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'root_folder_id',
        'root_folder_name',
        'status',
        'connected_by',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'access_token'     => 'encrypted',
        'refresh_token'    => 'encrypted',
        'token_expires_at' => 'datetime',
        'connected_at'     => 'datetime',
        'disconnected_at'  => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function connectedBy()
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null
            && $this->token_expires_at->isPast();
    }

    /** The active company-level Drive connection, if any. */
    public static function activeForCompany(int $companyId): ?self
    {
        return static::query()
            ->where('owner_type', self::OWNER_COMPANY)
            ->where('company_id', $companyId)
            ->where('status', self::STATUS_ACTIVE)
            ->latest('connected_at')
            ->first();
    }

    /** The active platform / super-admin Drive connection, if any. */
    public static function activeForSuperAdmin(): ?self
    {
        return static::query()
            ->where('owner_type', self::OWNER_SUPER_ADMIN)
            ->where('status', self::STATUS_ACTIVE)
            ->latest('connected_at')
            ->first();
    }
}
