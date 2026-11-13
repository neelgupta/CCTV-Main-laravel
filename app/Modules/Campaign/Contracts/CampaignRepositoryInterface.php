<?php

namespace App\Modules\Campaign\Contracts;

use App\Models\Campaign;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CampaignRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator;

    public function findForCompany(int $companyId, int|string $identifier): ?Campaign;

    public function findByTrackingToken(string $token): ?Campaign;

    public function dueScheduled(): Collection;

    public function create(array $attributes): Campaign;

    public function update(Campaign $campaign, array $attributes): bool;
}
