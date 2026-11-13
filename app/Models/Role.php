<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    use HasFactory, HasObfuscatedRouteKey, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'permission_role')->withTimestamps();
    }

    public function isCompanyAdmin(): bool
    {
        return in_array(strtolower(trim((string) $this->slug)), ['company_admin', 'companyadmin'], true);
    }

    public function hasPermission(string $permissionKey): bool
    {
        if ($this->isCompanyAdmin()) {
            return true;
        }

        $key = strtolower(trim($permissionKey));
        $permissionKeys = [$key];

        if (str_starts_with($key, 'support.')) {
            $permissionKeys[] = 'tickets.' . substr($key, strlen('support.'));
        } elseif (str_starts_with($key, 'tickets.')) {
            $permissionKeys[] = 'support.' . substr($key, strlen('tickets.'));
        }

        return $this->permissions()->whereIn('key', array_values(array_unique(array_merge(['all.access'], $permissionKeys))))->exists();
    }
}
