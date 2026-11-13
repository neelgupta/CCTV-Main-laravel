<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class CompanyNotificationSetting extends Model
{
    /**
     * Canonical notification modules: key => [label, description].
     */
    public const MODULES = [
        'lead'    => ['Leads', 'New leads & status changes'],
        'ticket'  => ['Support Tickets', 'Ticket replies & updates'],
        'project' => ['Projects', 'Project stage updates'],
        'payment' => ['Payments', 'Payment & subscription alerts'],
        'staff'   => ['Staff', 'Attendance & staff updates'],
    ];

    public const CHANNELS = ['whatsapp', 'email', 'push'];

    protected $fillable = [
        'company_id',
        'module',
        'is_enabled',
        'whatsapp_enabled',
        'email_enabled',
        'push_enabled',
    ];

    protected $casts = [
        'is_enabled'       => 'boolean',
        'whatsapp_enabled' => 'boolean',
        'email_enabled'    => 'boolean',
        'push_enabled'     => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Return one settings row per canonical module for a company, filling any
     * missing module with sensible defaults (not yet persisted).
     *
     * @return Collection<string,CompanyNotificationSetting>
     */
    public static function matrixForCompany(int $companyId): Collection
    {
        $stored = static::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy('module');

        return collect(array_keys(self::MODULES))->mapWithKeys(function (string $module) use ($stored, $companyId) {
            $row = $stored->get($module) ?? new self([
                'company_id'       => $companyId,
                'module'           => $module,
                'is_enabled'       => true,
                'whatsapp_enabled' => false,
                'email_enabled'    => true,
                'push_enabled'     => false,
            ]);

            return [$module => $row];
        });
    }

    /**
     * Is the given channel currently allowed for a module in this company?
     * (Module must be enabled AND that channel ticked.)
     */
    public function allows(string $channel): bool
    {
        if (! $this->is_enabled) {
            return false;
        }

        return (bool) ($this->{$channel.'_enabled'} ?? false);
    }
}
