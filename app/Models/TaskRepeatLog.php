<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskRepeatLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'source_task_id',
        'repeated_task_id',
        'repeat_type',
        'base_due_date',
        'processed_for_date',
        'processed_at',
        'payload',
    ];

    protected $casts = [
        'base_due_date' => 'date',
        'processed_for_date' => 'date',
        'processed_at' => 'datetime',
        'payload' => 'array',
    ];
}
