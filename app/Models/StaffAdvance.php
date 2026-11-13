<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffAdvance extends Model
{
    use HasFactory;

    protected $table = 'staff_advances';

    protected $fillable = [
        'company_id',
        'staff_id',
        'advance_date',
        'amount',
        'notes',
    ];

    protected $casts = [
        'advance_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    public static function syncMonthlyPayrollAdvanceAmount(int $companyId, int $staffId, Carbon|string $advanceDate): void
    {
        $monthDate = $advanceDate instanceof Carbon
            ? $advanceDate->copy()->startOfMonth()
            : Carbon::parse($advanceDate)->startOfMonth();

        $advanceTotal = (float) static::query()
            ->where('company_id', $companyId)
            ->where('staff_id', $staffId)
            ->whereBetween('advance_date', [
                $monthDate->copy()->startOfMonth()->toDateString(),
                $monthDate->copy()->endOfMonth()->toDateString(),
            ])
            ->sum('amount');

        StaffSalaryPayment::query()
            ->where('company_id', $companyId)
            ->where('staff_id', $staffId)
            ->whereDate('salary_month', $monthDate->toDateString())
            ->update(['advance_amount' => $advanceTotal]);
    }
}
