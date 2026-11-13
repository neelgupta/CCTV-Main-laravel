<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeadMaterial extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'product_id',
        'material_name',
        'qty',
        'unit',
        'rate',
        'gst',
        'note',
        'total',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'gst' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function product()
    {
        return $this->belongsTo(BoqProduct::class, 'product_id');
    }
}
