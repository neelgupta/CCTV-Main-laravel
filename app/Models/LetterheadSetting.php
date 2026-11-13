<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LetterheadSetting extends Model
{
    use HasFactory;

    public const DEFAULT_TOP_MARGIN = 20;
    public const DEFAULT_BOTTOM_MARGIN = 20;
    public const DEFAULT_LEFT_MARGIN = 20;
    public const DEFAULT_RIGHT_MARGIN = 20;

    protected $fillable = [
        'company_id',
        'file_path',
        'enabled',
        'top_margin',
        'bottom_margin',
        'left_margin',
        'right_margin',
        'source_filename',
        'page_count',
        'page_width_mm',
        'page_height_mm',
        'page_orientation',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'top_margin' => 'integer',
        'bottom_margin' => 'integer',
        'left_margin' => 'integer',
        'right_margin' => 'integer',
        'page_count' => 'integer',
        'page_width_mm' => 'decimal:2',
        'page_height_mm' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaultMargins(): array
    {
        return [
            'top_margin' => self::DEFAULT_TOP_MARGIN,
            'bottom_margin' => self::DEFAULT_BOTTOM_MARGIN,
            'left_margin' => self::DEFAULT_LEFT_MARGIN,
            'right_margin' => self::DEFAULT_RIGHT_MARGIN,
        ];
    }
}
