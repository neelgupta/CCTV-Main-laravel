<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskPart extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id',
        'part_name',
        'qty',
        'gst',
        'price',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'gst' => 'decimal:2',
        'price' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'task_id');
    }
}
