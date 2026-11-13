<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyStaffUpdateReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'report_date',
        'generated_by_id',
        'generated_at',
        'generation_type',
        'status',
        'report_text',
        'report_payload',
        'error_message',
    ];

    protected $casts = [
        'report_date' => 'date',
        'generated_at' => 'datetime',
        'emailed_at' => 'datetime',
        'report_payload' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by_id');
    }
}
