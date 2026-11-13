<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadFollowupHistory extends Model
{
    use HasFactory;

    protected $table = 'lead_followup_history';

    protected $fillable = [
        'lead_id',
        'summary',
        'status',
        'followup_date',
        'next_followup_date',
        'created_by',
    ];

    protected $casts = [
        'followup_date' => 'date',
        'next_followup_date' => 'date',
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
