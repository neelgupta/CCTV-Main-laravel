<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\Models\IntegrationAccount;
use Illuminate\Support\Collection;

class EloquentIntegrationAccountRepository implements IntegrationAccountRepositoryInterface
{
    public function findByCompanyAndId(int $companyId, int $id): ?IntegrationAccount
    {
        return IntegrationAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();
    }

    public function findByCompanyAndUuid(int $companyId, string $uuid): ?IntegrationAccount
    {
        return IntegrationAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function findByCompanyAndPhoneNumberId(int $companyId, string $phoneNumberId): ?IntegrationAccount
    {
        return IntegrationAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }

    /**
     * Globally locate an integration account by phone number ID.
     * This is required to identify the tenant/company context when a Meta webhook request is received.
     */
    public function findByPhoneNumberId(string $phoneNumberId): ?IntegrationAccount
    {
        return IntegrationAccount::withoutGlobalScopes()
            ->where('phone_number_id', $phoneNumberId)
            ->first();
    }

    public function getAllForCompany(int $companyId): Collection
    {
        return IntegrationAccount::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->get();
    }

    public function create(array $data): IntegrationAccount
    {
        return IntegrationAccount::create($data);
    }

    public function update(IntegrationAccount $account, array $data): bool
    {
        return $account->update($data);
    }

    public function delete(IntegrationAccount $account): bool
    {
        return (bool) $account->delete();
    }
}
