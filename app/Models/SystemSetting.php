<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'key',
        'value',
        'type',
        'description',
        'is_encrypted',
    ];

    protected function casts(): array
    {
        return [
            'is_encrypted' => 'boolean',
        ];
    }

    public static function getValue(string $key, mixed $default = null, ?int $companyId = null): mixed
    {
        $query = static::query();
        if ($companyId) {
            $query->where('company_id', $companyId);
        }
        $setting = $query->where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer', 'int' => (int) $setting->value,
            'float', 'double' => (float) $setting->value,
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public static function setValue(string $key, mixed $value, ?int $companyId = null, array $options = []): void
    {
        $record = static::query();
        if ($companyId) {
            $record->where('company_id', $companyId);
        }
        $record = $record->where('key', $key)->first();

        $payload = array_merge([
            'key' => $key,
            'value' => is_string($value) ? $value : (is_array($value) ? json_encode($value) : (string) $value),
            'type' => match (true) {
                is_int($value) => 'integer',
                is_float($value) => 'float',
                is_bool($value) => 'boolean',
                is_array($value) => 'json',
                default => 'string',
            },
            'company_id' => $companyId,
        ], $options);

        if ($record) {
            $record->update($payload);
        } else {
            static::create($payload);
        }
    }
}
