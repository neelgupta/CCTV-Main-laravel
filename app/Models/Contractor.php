<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contractor extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'phone_number',
        'email',
        'referral_name',
        'address',
        'notes',
    ];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
