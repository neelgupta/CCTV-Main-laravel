<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardStaffTaskSummaryService
{
    /**
     * @return Collection<int, array{
     *     staff_id:int,
     *     staff_user_id:int|null,
     *     staff_name:string,
     *     amc_total:int,
     *     amc_remaining:int,
     *     complaint_total:int,
     *     complaint_remaining:int,
     *     support_total:int,
     *     support_remaining:int,
     *     total:int
     * }>
     */
    public function todayForUser(User $user): Collection
    {
        $companyId = (int) $user->company_id;

        if ($companyId <= 0) {
            return collect();
        }

        $today = now()->toDateString();
        $canViewAllStaffWorkload = $user->isCompanyAdmin() || $user->hasPermission('tickets.manage');
        $summaryQuery = DB::table('staff as s')
            ->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->leftJoin('tickets as t', function ($join) use ($companyId, $today, $canViewAllStaffWorkload, $user): void {
                $join->on('t.assigned_to', '=', 'u.id')
                    ->where('t.company_id', '=', $companyId)
                    ->whereDate('t.visit_date', '=', $today);

                if (! $canViewAllStaffWorkload) {
                    $join->where('t.assigned_to', '=', (int) $user->id);
                }
            })
            ->where('s.company_id', '=', $companyId)
            ->when(
                ! $canViewAllStaffWorkload,
                fn ($query) => $query->where('s.user_id', '=', (int) $user->id)
            )
            ->selectRaw(
                "s.id as staff_id,
                u.id as staff_user_id,
                COALESCE(NULLIF(TRIM(s.name), ''), NULLIF(TRIM(u.name), ''), 'Staff') as staff_name,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) THEN 1 ELSE 0 END), 0) as amc_total,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) AND LOWER(COALESCE(t.status, '')) NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END), 0) as amc_remaining,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND LOWER(COALESCE(t.type, '')) = 'complain' AND NOT (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) THEN 1 ELSE 0 END), 0) as complaint_total,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND LOWER(COALESCE(t.type, '')) = 'complain' AND NOT (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) AND LOWER(COALESCE(t.status, '')) NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END), 0) as complaint_remaining,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND LOWER(COALESCE(t.type, '')) = 'support' AND NOT (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) THEN 1 ELSE 0 END), 0) as support_total,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL AND LOWER(COALESCE(t.type, '')) = 'support' AND NOT (t.generated_from_amc_id IS NOT NULL OR LOWER(COALESCE(t.source_type, '')) IN ('amc', 'amc_visit')) AND LOWER(COALESCE(t.status, '')) NOT IN ('closed', 'resolved') THEN 1 ELSE 0 END), 0) as support_remaining,
                COALESCE(SUM(CASE WHEN t.id IS NOT NULL THEN 1 ELSE 0 END), 0) as total"
            )
            ->groupBy('s.id', 'u.id', 's.name', 'u.name')
            ->orderByDesc('total')
            ->orderByRaw("LOWER(COALESCE(NULLIF(TRIM(s.name), ''), NULLIF(TRIM(u.name), ''), 'Staff')) ASC")
            ->orderBy('s.id');

        return $summaryQuery
            ->get()
            ->map(function ($row): array {
                return [
                    'staff_id' => (int) $row->staff_id,
                    'staff_user_id' => isset($row->staff_user_id) ? (int) $row->staff_user_id : null,
                    'staff_name' => (string) $row->staff_name,
                    'amc_total' => (int) ($row->amc_total ?? 0),
                    'amc_remaining' => (int) ($row->amc_remaining ?? 0),
                    'complaint_total' => (int) ($row->complaint_total ?? 0),
                    'complaint_remaining' => (int) ($row->complaint_remaining ?? 0),
                    'support_total' => (int) ($row->support_total ?? 0),
                    'support_remaining' => (int) ($row->support_remaining ?? 0),
                    'total' => (int) ($row->total ?? 0),
                ];
            });
    }
}
