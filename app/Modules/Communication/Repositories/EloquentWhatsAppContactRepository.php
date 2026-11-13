<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use App\Modules\Communication\Contracts\WhatsAppContactRepositoryInterface;
use App\Modules\Communication\Models\WhatsAppContact;

class EloquentWhatsAppContactRepository implements WhatsAppContactRepositoryInterface
{
    public function findByCompanyAndPhoneNumber(int $companyId, string $phoneNumber): ?WhatsAppContact
    {
        return WhatsAppContact::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('phone_number', $phoneNumber)
            ->first();
    }

    public function create(array $data): WhatsAppContact
    {
        return WhatsAppContact::create($data);
    }

    public function firstOrCreate(int $companyId, string $phoneNumber, array $additionalData = []): WhatsAppContact
    {
        return WhatsAppContact::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'phone_number' => $phoneNumber],
            $additionalData
        );
    }

    public function update(WhatsAppContact $contact, array $data): bool
    {
        return $contact->update($data);
    }
}
