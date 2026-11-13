<?php

declare(strict_types=1);

namespace App\Modules\Communication\Models;

use App\Modules\Communication\Enums\MessageDirectionEnum;
use App\Modules\Communication\Events\WhatsAppMessageSaved;
use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class WhatsAppMessage extends Model
{
    use BelongsToCompany, HasFactory;

    protected $table = 'communication_whatsapp_messages';

    protected $fillable = [
        'company_id',
        'uuid',
        'integration_account_id',
        'whatsapp_contact_id',
        'message_id',
        'direction',
        'type',
        'body',
        'media_id',
        'media_url',
        'status',
        'raw_payload',
        'sent_at',
    ];

    protected $casts = [
        'direction' => MessageDirectionEnum::class,
        'raw_payload' => 'array',
        'sent_at' => 'datetime',
    ];

    protected $dispatchesEvents = [
        'created' => WhatsAppMessageSaved::class,
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

    public function contact()
    {
        return $this->belongsTo(WhatsAppContact::class, 'whatsapp_contact_id');
    }
}
