<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketRepeatHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'reopened_by_user_id',
        'previous_assigned_to',
        'assigned_to',
        'previous_status',
        'reopened_status',
        'repeat_count',
        'repeated_after_days',
        'note',
        'repeated_at',
    ];

    protected $casts = [
        'repeat_count' => 'integer',
        'repeated_after_days' => 'integer',
        'repeated_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function reopenedBy()
    {
        return $this->belongsTo(User::class, 'reopened_by_user_id');
    }

    public function previousAssignee()
    {
        return $this->belongsTo(User::class, 'previous_assigned_to');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
