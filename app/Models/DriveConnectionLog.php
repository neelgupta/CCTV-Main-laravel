<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveConnectionLog extends Model
{
    const ACTION_CONNECTED    = 'connected';
    const ACTION_RECONNECTED  = 'reconnected';
    const ACTION_DISCONNECTED = 'disconnected';

    protected $fillable = [
        'company_id',
        'drive_type',
        'action',
        'status',
        'account_email',
        'previous_account_email',
        'current_account_email',
        'performed_by',
        'connected_at',
        'disconnected_at',
    ];

    protected $casts = [
        'connected_at'    => 'datetime',
        'disconnected_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
