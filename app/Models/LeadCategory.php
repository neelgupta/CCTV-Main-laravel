<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadCategory extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'name',
        'company_id'
    ];

    public function leads()
    {
        return $this->hasMany(Lead::class);
    }
}
