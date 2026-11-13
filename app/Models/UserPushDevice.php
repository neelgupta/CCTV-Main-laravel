<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserPushDevice extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'fcm_token_hash',
        'fcm_token',
        'browser_name',
        'platform_name',
        'user_agent',
        'last_seen_at',
        'is_enabled',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
        'is_enabled' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function tokenHash(string $token): string
    {
        return hash('sha256', trim($token));
    }
}
