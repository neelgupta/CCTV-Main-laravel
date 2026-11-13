<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffAppActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'staff_id',
        'device_id',
        'device_id_hash',
        'platform',
        'app_version',
        'activity_type',
        'last_active_at',
    ];

    protected $hidden = [
        'device_id',
        'device_id_hash',
    ];

    protected function casts(): array
    {
        return [
            'device_id' => 'encrypted',
            'last_active_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
