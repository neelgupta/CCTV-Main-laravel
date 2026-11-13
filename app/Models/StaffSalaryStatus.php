<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffSalaryStatus extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'month_key',
        'status',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
