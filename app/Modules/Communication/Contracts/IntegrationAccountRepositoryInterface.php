<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\IntegrationAccount;
use Illuminate\Support\Collection;

interface IntegrationAccountRepositoryInterface
{
    public function findByCompanyAndId(int $companyId, int $id): ?IntegrationAccount;

    public function findByCompanyAndUuid(int $companyId, string $uuid): ?IntegrationAccount;

    public function findByCompanyAndPhoneNumberId(int $companyId, string $phoneNumberId): ?IntegrationAccount;

    public function findByPhoneNumberId(string $phoneNumberId): ?IntegrationAccount;

    public function getAllForCompany(int $companyId): Collection;

    public function create(array $data): IntegrationAccount;

    public function update(IntegrationAccount $account, array $data): bool;

    public function delete(IntegrationAccount $account): bool;
}
