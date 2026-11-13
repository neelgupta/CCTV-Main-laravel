<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DashboardNotification extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'notification_key',
        'category',
        'source_type',
        'source_id',
        'title',
        'message',
        'url',
        'notification_date',
        'status',
        'read_at',
        'archived_at',
        'payload',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'read_at' => 'datetime',
        'archived_at' => 'datetime',
        'payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForUser($query, User $user)
    {
        return $query->where('user_id', $user->id);
    }
}
