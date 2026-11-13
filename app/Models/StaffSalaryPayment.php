<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffSalaryPayment extends Model
{
    use HasFactory;

    protected $table = 'staff_salary_payments';

    protected $fillable = [
        'company_id',
        'staff_id',
        'salary_month',
        'advance_amount',
        'is_paid',
        'paid_at',
    ];

    protected $casts = [
        'salary_month' => 'date',
        'advance_amount' => 'decimal:2',
        'is_paid' => 'boolean',
        'paid_at' => 'datetime',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }
}
