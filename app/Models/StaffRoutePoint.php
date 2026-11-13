<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffRoutePoint extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'staff_id',
        'recorded_at',
        'latitude',
        'longitude',
        'accuracy_meters',
        'speed_kph',
        'heading_degrees',
        'source',
        'device_name',
        'metadata',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'accuracy_meters' => 'decimal:2',
        'speed_kph' => 'decimal:2',
        'heading_degrees' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
