<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Company;

class ProjectChallan extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'project_id',
        'company_id',
        'challan_no',
        'production_ids',
        'status',
        'name_of_person',
        'needed_for',
        'delivery_address',
        'transport_name',
        'vehicle_no',
        'driver_name',
        'lr_no',
        'dispatch_date',
        'remarks',
        'signature_path',
        'created_by',
    ];

    protected $casts = [
        'production_ids' => 'array',
        'dispatch_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function (ProjectChallan $challan) {
            $companyId = $challan->company_id ?: $challan->project?->company_id;
            $challan->company_id = $companyId;

            if (!empty($challan->challan_no)) {
                return;
            }

            $company = $companyId ? Company::query()->find($companyId) : null;

            if ($company) {
                $challan->challan_no = $company->reserveNextChallanNumber();

                return;
            }

            $fallback = new Company();
            $sequence = $fallback->nextAvailableChallanSequence(static::query()->count() + 1);
            $challan->challan_no = $fallback->formatChallanNumber($sequence);
        });
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
