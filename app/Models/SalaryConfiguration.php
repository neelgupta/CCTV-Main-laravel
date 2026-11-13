<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalaryConfiguration extends Model
{
    //
    use HasFactory;

    protected $fillable = [
        'staff_id', 'basic_salary', 'hra', 'allowances', 'is_active'
    ];

    protected $casts = [
        'allowances' => 'array',
        'basic_salary' => 'decimal:2',
        'hra' => 'decimal:2',
    ];

    public function getTotalSalaryAttribute()
    {
        // If allowances is an array of numbers, sum it; otherwise, treat as 0
       $totalAllowances = is_array($this->allowances)
        ? array_sum($this->allowances)
        : (float) ($this->allowances ?? 0);

        return $this->basic_salary + $this->hra + $totalAllowances;
    }

    public function staff() {
        return $this->belongsTo(Staff::class);
    }
}
