<?php

namespace App\Models;

use Ogwebsolutions\FormBuilder\Models\OgForm as BaseOgForm;

class OgForm extends BaseOgForm
{
    protected $fillable = [
        'title',
        'fields',
        'status',
        'company_id',
        'form_key',
    ];

    protected $casts = [
        'fields' => 'array',
    ];

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_form');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function scopeForCompany($query, $companyId)
    {
        return $query->whereHas('companies', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })->orWhere('company_id', $companyId);
    }
}
