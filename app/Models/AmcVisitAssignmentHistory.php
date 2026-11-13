<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmcVisitAssignmentHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'amc_visit_id',
        'old_technician_name',
        'new_technician_name',
        'changed_by',
        'assignment_source',
        'reason',
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
