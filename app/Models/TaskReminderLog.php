<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskReminderLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'staff_task_id',
        'reminder_type',
        'sent_for_date',
        'sent_at',
        'payload',
    ];

    protected $casts = [
        'sent_for_date' => 'date',
        'sent_at' => 'datetime',
        'payload' => 'array',
    ];

    public function task()
    {
        return $this->belongsTo(StaffTask::class, 'staff_task_id');
    }
}
