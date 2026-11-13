<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyHoliday extends Model
{
    protected $fillable = [
        'company_id',
        'holiday_date',
        'title',
        'description',
    ];

    protected $casts = [
        'holiday_date' => 'date',
    ];
}
