<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class CalendarEvent extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'title',
        'location',
        'description',
        'event_date',
        'starts_at',
        'event_type',
        'meeting_type',
        'meeting_link',
        'status',
        'visibility_scope',
    ];

    protected $casts = [
        'event_date' => 'date',
        'starts_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
