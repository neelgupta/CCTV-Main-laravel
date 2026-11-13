<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppContact extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'communication_whatsapp_contacts';

    protected $fillable = [
        'company_id',
        'uuid',
        'integration_account_id',
        'phone_number',
        'profile_name',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function integrationAccount()
    {
        return $this->belongsTo(IntegrationAccount::class);
    }

    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_contact_id');
    }
}
