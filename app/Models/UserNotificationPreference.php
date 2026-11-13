<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'whatsapp_enabled',
        'email_enabled',
        'push_enabled',
        'notify_lead',
        'notify_ticket',
        'notify_project',
        'notify_payment',
        'notify_staff',
    ];

    protected $casts = [
        'whatsapp_enabled' => 'boolean',
        'email_enabled'    => 'boolean',
        'push_enabled'     => 'boolean',
        'notify_lead'      => 'boolean',
        'notify_ticket'    => 'boolean',
        'notify_project'   => 'boolean',
        'notify_payment'   => 'boolean',
        'notify_staff'     => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function defaults(int $userId, int $companyId): array
    {
        return [
            'user_id'          => $userId,
            'company_id'       => $companyId,
            'whatsapp_enabled' => false,
            'email_enabled'    => true,
            'push_enabled'     => true,
            'notify_lead'      => true,
            'notify_ticket'    => true,
            'notify_project'   => true,
            'notify_payment'   => false,
            'notify_staff'     => false,
        ];
    }
}
