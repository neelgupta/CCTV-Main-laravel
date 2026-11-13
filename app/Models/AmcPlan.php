<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmcPlan extends Model
{
    use HasFactory, BelongsToCompany;

    public const SERVICE_MODE_OPTIONS = [
        'scheduled' => 'Scheduled Visits Only',
        'on_call' => 'On Call Only',
        'hybrid' => 'Hybrid (Visits + On Call)',
    ];

    protected $fillable = [
        'company_id',
        'name',
        'duration_months',
        'visit_count',
        'price',
        'description',
        'includes_on_call_support',
        'service_mode',
        'is_active',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'visit_count' => 'integer',
        'price' => 'decimal:2',
        'includes_on_call_support' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function amcs()
    {
        return $this->hasMany(Amc::class);
    }

    public function durationLabel(): string
    {
        return Amc::formatDurationMonths((int) $this->duration_months);
    }

    public function supportsOnCall(): bool
    {
        return in_array($this->service_mode, ['on_call', 'hybrid'], true);
    }

    public function generatesScheduledVisits(): bool
    {
        return in_array($this->service_mode, ['scheduled', 'hybrid'], true);
    }
}
