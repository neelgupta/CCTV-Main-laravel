<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoqProduct extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'company_id',
        'category_id',
        'name',
        'brand',
        'model',
        'image_path',
        'unit',
        'price',
        'gst',
        'description',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'gst' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function category()
    {
        return $this->belongsTo(BoqCategory::class, 'category_id');
    }
}
