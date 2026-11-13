<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowupSchedule extends Model
{
    use HasFactory;

    protected $table = 'lead_followup_schedule';

    protected $fillable = [
        'lead_id',
        'followup_date',
        'status',
        'completed_at',
        'created_by',
    ];

    protected $casts = [
        'followup_date' => 'date',
        'completed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
