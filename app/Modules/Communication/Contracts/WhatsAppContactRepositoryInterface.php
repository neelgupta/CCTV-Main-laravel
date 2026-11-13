<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\WhatsAppContact;

interface WhatsAppContactRepositoryInterface
{
    public function findByCompanyAndPhoneNumber(int $companyId, string $phoneNumber): ?WhatsAppContact;

    public function create(array $data): WhatsAppContact;

    public function firstOrCreate(int $companyId, string $phoneNumber, array $additionalData = []): WhatsAppContact;

    public function update(WhatsAppContact $contact, array $data): bool;
}
