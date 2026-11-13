<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectDrawing extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'project_id',
        'file_path',
        'file_name',
        'version',
        'drawing_status',
        'accepted_at',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function material()
    {
        return $this->hasOne(ProjectMaterial::class, 'source_drawing_id');
    }
}
