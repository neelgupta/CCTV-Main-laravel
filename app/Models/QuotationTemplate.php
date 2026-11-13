<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'created_by',
        'name',
        'description',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getItemsCountAttribute(): int
    {
        return is_array($this->items) ? count($this->items) : 0;
    }

    public function getEstimatedTotalAttribute(): float
    {
        if (!is_array($this->items)) {
            return 0.0;
        }

        return (float) collect($this->items)->sum(function ($item) {
            return (float) ($item['total'] ?? 0);
        });
    }
}
