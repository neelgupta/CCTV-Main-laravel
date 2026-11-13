<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmcVisitHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'amc_visit_id',
        'changed_by',
        'previous_visit_date',
        'new_visit_date',
        'reason',
        'notes',
    ];

    protected $casts = [
        'previous_visit_date' => 'date',
        'new_visit_date' => 'date',
    ];

    public function visit()
    {
        return $this->belongsTo(AmcVisit::class, 'amc_visit_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
