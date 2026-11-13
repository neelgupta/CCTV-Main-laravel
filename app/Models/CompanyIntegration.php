<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyIntegration extends Model
{
    // Supported types beyond WhatsApp (which lives in integration_accounts)
    const TYPE_GOOGLE_DRIVE = 'google_drive';
    const TYPE_EMAIL_SMTP   = 'email_smtp';
    const TYPE_WHATSAPP_WATI = 'whatsapp_wati';

    const MODE_MANUAL = 'manual';
    const MODE_AUTO   = 'auto';

    protected $fillable = [
        'company_id',
        'type',
        'connection_mode',
        'is_enabled',
        'credentials',
        'test_status',
        'last_tested_at',
    ];

    protected $casts = [
        'is_enabled'     => 'boolean',
        'credentials'    => 'encrypted:array',
        'last_tested_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function isConnected(): bool
    {
        return $this->test_status === 'success';
    }

    public function isAuto(): bool
    {
        return $this->connection_mode === self::MODE_AUTO;
    }

    public function statusLabel(): string
    {
        if (! $this->is_enabled) {
            return 'Disabled';
        }

        return match ($this->test_status) {
            'success' => 'Connected',
            'failed'  => 'Failed',
            default   => 'Not Tested',
        };
    }

    /**
     * Full Tailwind badge classes (written out so JIT does not purge them).
     */
    public function statusBadgeClasses(): string
    {
        if (! $this->is_enabled) {
            return 'bg-slate-50 text-slate-700 ring-slate-200';
        }

        return match ($this->test_status) {
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'failed'  => 'bg-rose-50 text-rose-700 ring-rose-200',
            default   => 'bg-amber-50 text-amber-700 ring-amber-200',
        };
    }

    public function statusDotClass(): string
    {
        if (! $this->is_enabled) {
            return 'bg-slate-400';
        }

        return match ($this->test_status) {
            'success' => 'bg-emerald-500',
            'failed'  => 'bg-rose-500',
            default   => 'bg-amber-500',
        };
    }
}
