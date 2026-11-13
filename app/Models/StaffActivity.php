<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffActivity extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'staff_id', 'type', 'description', 'occurred_at', 'ip_address', 'device'
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }
}
