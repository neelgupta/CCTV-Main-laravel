<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class CompanyMailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'mailer',
        'host',
        'port',
        'username',
        'password',
        'encryption',
        'from_email',
        'from_name',
        'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function setPasswordAttribute(?string $value): void
    {
        if ($value === null || trim($value) === '') {
            $this->attributes['password'] = null;

            return;
        }

        $this->attributes['password'] = Crypt::encryptString($value);
    }

    public function getPasswordAttribute($value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return null;
        }
    }
}
