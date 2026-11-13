<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffAttendance extends Model
{
    //
    use HasFactory;

    protected $table = 'staff_attendance';

    protected $fillable = [
        'staff_id','date','status','check_in_time','check_out_time','overtime_hours','leave_reason','correction_reason','is_corrected',
    ];

    protected $casts = [
        'date' => 'date',
        'overtime_hours' => 'decimal:2',
        'is_corrected' => 'boolean',
    ];

    public function staff() {
        return $this->belongsTo(Staff::class);
    }
}
