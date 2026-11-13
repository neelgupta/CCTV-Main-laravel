<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationFormTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_key',
        'company_id',
        'schema',
        'submit_button_text',
    ];

    protected $casts = [
        'schema' => 'array',
        'company_id' => 'integer',
    ];

    public static function defaultKey(): string
    {
        return 'quotation:default';
    }

    public static function companyKey(int $companyId): string
    {
        return 'quotation:company:' . $companyId;
    }

    public static function default(): ?self
    {
        return self::query()->where('template_key', self::defaultKey())->first();
    }

    public static function forCompany(int $companyId): ?self
    {
        return self::query()->where('template_key', self::companyKey($companyId))->first();
    }

    public static function effectiveForCompany(?int $companyId): ?self
    {
        if ($companyId) {
            return self::forCompany($companyId) ?: self::default();
        }

        return self::default();
    }
}

