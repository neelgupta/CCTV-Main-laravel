<?php

namespace App\Models;

use App\Models\Concerns\HasObfuscatedRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectProduction extends Model
{
    use HasFactory, HasObfuscatedRouteKey;

    protected $fillable = [
        'project_id',
        'title',
        'note',
        'attachment_path',
        'qa_done',
        'dispatched_at',
    ];

    protected $casts = [
        'qa_done' => 'boolean',
        'dispatched_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
