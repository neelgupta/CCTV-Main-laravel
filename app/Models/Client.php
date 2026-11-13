<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\City;
use App\Models\State;

class Client extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'user_id',
        'name',
        'email',
        'mobile',
        'phone',
        'gst_number',
        'pan_number',
        'state_id',
        'city_id',
        'address',
        'latitude',
        'longitude',
        'status',
        'uuid',
        'qr_token',
        'is_direct_client',
    ];

    protected $casts = [
        'is_direct_client' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function (Client $client) {
            if (empty($client->uuid)) {
                $client->uuid = (string) Str::uuid();
            }

            if (empty($client->qr_token)) {
                $client->qr_token = static::generateQrToken();
            }

            if (empty($client->status)) {
                $client->status = 'active';
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function amcs()
    {
        return $this->hasMany(Amc::class);
    }

    public function projects()
    {
        return $this->hasMany(Project::class, 'client_name', 'name');
    }

    public function state()
    {
        return $this->belongsTo(State::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function getRouteKeyName()
    {
        return 'uuid';
    }

    public static function generateQrToken(?int $ignoreClientId = null): string
    {
        do {
            $token = Str::random(48);
            $exists = static::query()
                ->withoutGlobalScopes()
                ->when($ignoreClientId, fn ($query) => $query->whereKeyNot($ignoreClientId))
                ->where('qr_token', $token)
                ->exists();
        } while ($exists);

        return $token;
    }

    public function ensureQrToken(): string
    {
        if (filled($this->qr_token)) {
            return (string) $this->qr_token;
        }

        $this->forceFill([
            'qr_token' => static::generateQrToken((int) $this->id),
        ])->saveQuietly();

        return (string) $this->qr_token;
    }

    public function regenerateQrToken(): string
    {
        $this->forceFill([
            'qr_token' => static::generateQrToken((int) $this->id),
        ])->saveQuietly();

        return (string) $this->qr_token;
    }
}
