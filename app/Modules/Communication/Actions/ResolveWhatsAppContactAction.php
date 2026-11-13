<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\WhatsAppContactRepositoryInterface;
use App\Modules\Communication\Models\WhatsAppContact;
use Illuminate\Support\Str;

class ResolveWhatsAppContactAction
{
    public function __construct(
        protected WhatsAppContactRepositoryInterface $contactRepository
    ) {}

    /**
     * Resolve an existing contact by phone number or create one if it doesn't exist.
     */
    public function execute(int $companyId, int $integrationAccountId, string $phoneNumber, ?string $profileName = null): WhatsAppContact
    {
        // Clean the phone number (keep only digits)
        $cleanNumber = preg_replace('/\D/', '', $phoneNumber);

        // Query contact within the company scope
        $contact = $this->contactRepository->findByCompanyAndPhoneNumber((int) $companyId, $cleanNumber);

        if ($contact) {
            // Update profile name if it was previously empty or changed
            if ($profileName && $contact->profile_name !== $profileName) {
                $this->contactRepository->update($contact, [
                    'profile_name' => $profileName,
                ]);
            }

            return $contact;
        }

        // Create a new contact if not found
        return $this->contactRepository->create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $companyId,
            'integration_account_id' => $integrationAccountId,
            'phone_number' => $cleanNumber,
            'profile_name' => $profileName,
        ]);
    }
}
