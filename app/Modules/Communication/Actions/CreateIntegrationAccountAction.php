<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\DTOs\IntegrationAccountData;
use App\Modules\Communication\Enums\ConnectionStatusEnum;
use App\Modules\Communication\Models\IntegrationAccount;

class CreateIntegrationAccountAction
{
    public function __construct(
        protected IntegrationAccountRepositoryInterface $repository
    ) {}

    public function execute(IntegrationAccountData $data): IntegrationAccount
    {
        $existing = $this->repository->getAllForCompany($data->companyId)->first();

        if ($existing) {
            $updateData = $data->toArray();

            // If the submitted token is the masked placeholder, do not overwrite the existing token
            if (isset($updateData['access_token']) && str_starts_with($updateData['access_token'], '••')) {
                unset($updateData['access_token']);
            }

            $this->repository->update($existing, $updateData);

            return $existing->fresh();
        }

        $accountData = array_merge($data->toArray(), [
            'status' => ConnectionStatusEnum::DISCONNECTED->value,
        ]);

        return $this->repository->create($accountData)->fresh();
    }
}
