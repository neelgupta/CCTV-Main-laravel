<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Inquiry extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'inquiries';

    protected $fillable = [
        'lead_id',
        'name',
        'phone',
        'email',
        'company_name',
        'state',
        'city',
        'message',
        'status',
        'notes',
        'source',
        'ip_address',
        'user_agent',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * The available status constants.
     */
    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_DEMO_SCHEDULED = 'demo_scheduled';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_REJECTED = 'rejected';

    public static function statusOptions(): array
    {
        return [
            self::STATUS_NEW => 'New',
            self::STATUS_CONTACTED => 'Contacted',
            self::STATUS_DEMO_SCHEDULED => 'Demo Scheduled',
            self::STATUS_CONVERTED => 'Converted',
            self::STATUS_CLOSED => 'Closed',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusOptions()[$this->status] ?? ucfirst(str_replace('_', ' ', (string) $this->status));
    }

    public function getStatusBadgeClassesAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_NEW => 'bg-rose-100 text-rose-700 border border-rose-200',
            self::STATUS_CONTACTED => 'bg-blue-100 text-blue-700 border border-blue-200',
            self::STATUS_DEMO_SCHEDULED => 'bg-purple-100 text-purple-700 border border-purple-200',
            self::STATUS_CONVERTED => 'bg-emerald-100 text-emerald-700 border border-emerald-200',
            self::STATUS_CLOSED => 'bg-slate-100 text-slate-700 border border-slate-200',
            self::STATUS_REJECTED => 'bg-amber-100 text-amber-700 border border-amber-200',
            default => 'bg-slate-100 text-slate-700 border border-slate-200',
        };
    }
}

