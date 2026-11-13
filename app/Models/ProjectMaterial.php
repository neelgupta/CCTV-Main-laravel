<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectMaterial extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'project_id',
        'product_id',
        'material_name',
        'qty',
        'unit',
        'rate',
        'contractor_price',
        'gst',
        'total',
        'note',
        'attachment_path',
        'qa_done',
        'dispatched_at',
        'source_drawing_id',
        'source_type',
        'source_label',
    ];

    protected $casts = [
        'qty' => 'decimal:2',
        'rate' => 'decimal:2',
        'contractor_price' => 'decimal:2',
        'gst' => 'decimal:2',
        'total' => 'decimal:2',
        'qa_done' => 'boolean',
        'dispatched_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function sourceDrawing()
    {
        return $this->belongsTo(ProjectDrawing::class, 'source_drawing_id');
    }

    public function product()
    {
        return $this->belongsTo(BoqProduct::class, 'product_id');
    }
}
