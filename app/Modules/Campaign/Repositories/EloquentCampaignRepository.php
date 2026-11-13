<?php

namespace App\Modules\Campaign\Repositories;

use App\Models\Campaign;
use App\Models\EmailLog;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentCampaignRepository implements CampaignRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = []): LengthAwarePaginator
    {
        return Campaign::withoutGlobalScopes()
            ->with(['creator:id,name', 'emailTemplate:id,name', 'festivalCampaign:id,festival_name,festival_date'])
            ->where('company_id', $companyId)
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(filled($filters['type'] ?? null), fn ($query) => $query->where('type', $filters['type']))
            ->latest()
            ->paginate(12)
            ->withQueryString();
    }

    public function findForCompany(int $companyId, int|string $identifier): ?Campaign
    {
        return Campaign::withoutGlobalScopes()
            ->with(['creator', 'emailTemplate', 'festivalCampaign', 'generatedBanner', 'recipients', 'emailLogs', 'whatsappLogs'])
            ->where('company_id', $companyId)
            ->where(function ($query) use ($identifier) {
                $query->where('id', $identifier)->orWhere('uuid', $identifier);
            })
            ->first();
    }

    public function findByTrackingToken(string $token): ?Campaign
    {
        $emailLog = EmailLog::query()->where('tracking_token', $token)->first();

        return $emailLog?->campaign;
    }

    public function dueScheduled(): Collection
    {
        return Campaign::withoutGlobalScopes()
            ->where('status', Campaign::STATUS_SCHEDULED)
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();
    }

    public function create(array $attributes): Campaign
    {
        return Campaign::create($attributes);
    }

    public function update(Campaign $campaign, array $attributes): bool
    {
        return $campaign->update($attributes);
    }
}
