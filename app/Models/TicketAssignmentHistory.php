<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'staff_id',
        'assigned_at',
        'released_at',
        'assigned_by',
        'assignment_type',
        'repeat_count_handled',
        'is_current',
        'note',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
        'released_at' => 'datetime',
        'repeat_count_handled' => 'integer',
        'is_current' => 'boolean',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
