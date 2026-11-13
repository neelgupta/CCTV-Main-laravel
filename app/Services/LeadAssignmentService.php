<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LeadAssignmentService
{
    /**
     * Get eligible staff members (active employee with active subscription) for a company.
     *
     * @param Company|int $company
     * @return \Illuminate\Support\Collection
     */
    public function getEligibleStaffUsers(Company|int $company): \Illuminate\Support\Collection
    {
        $companyId = $company instanceof Company ? (int) $company->id : (int) $company;
        $adminRoles = ['company_admin', 'companyadmin', 'admin', 'super_admin'];

        return Staff::query()
            ->where('company_id', $companyId)
            ->activeWithActiveSubscription()
            ->with(['user.roleRecord'])
            ->get()
            ->filter(function (Staff $staff) use ($adminRoles) {
                $user = $staff->user;
                if (! $user || ! $user->is_active) {
                    return false;
                }

                if ($user->isCompanyAdmin() || $user->isSuperAdmin()) {
                    return false;
                }

                $normalizedRole = strtolower(trim((string) ($user->roleRecord?->slug ?? $user->role ?? '')));
                if (in_array($normalizedRole, $adminRoles, true)) {
                    return false;
                }

                return true;
            })
            ->map(fn (Staff $staff) => $staff->user)
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Resolve eligible staff member to assign lead to:
     * - If default_lead_assignee_id is set and is an active eligible employee, use that staff member.
     * - Otherwise fallback to least assigned leads flow:
     *   - Employee role (non-admin role, not company admin or super admin)
     *   - Must have active subscription plan
     *   - Staff with least assigned leads
     *   - Random choice if tied
     *
     * @param Company|int $company
     * @return User|null
     */
    public function resolveLeadAssignee(Company|int $company): ?User
    {
        $companyModel = $company instanceof Company ? $company : Company::query()->find($company);
        if (! $companyModel) {
            return null;
        }

        $companyId = (int) $companyModel->id;
        $adminRoles = ['company_admin', 'companyadmin', 'admin', 'super_admin'];

        // Get staff belonging to the company with active subscription and active user
        $eligibleStaff = Staff::query()
            ->where('company_id', $companyId)
            ->activeWithActiveSubscription()
            ->with(['user.roleRecord'])
            ->get()
            ->filter(function (Staff $staff) use ($adminRoles) {
                $user = $staff->user;
                if (! $user || ! $user->is_active) {
                    return false;
                }

                if ($user->isCompanyAdmin() || $user->isSuperAdmin()) {
                    return false;
                }

                $normalizedRole = strtolower(trim((string) ($user->roleRecord?->slug ?? $user->role ?? '')));
                if (in_array($normalizedRole, $adminRoles, true)) {
                    return false;
                }

                return true;
            })
            ->values();

        if ($eligibleStaff->isEmpty()) {
            return null;
        }

        // If company has configured a default lead assignee, check if they are active and eligible
        if (! empty($companyModel->default_lead_assignee_id)) {
            $configuredAssignee = $eligibleStaff->first(function (Staff $staff) use ($companyModel) {
                return (int) $staff->user_id === (int) $companyModel->default_lead_assignee_id;
            });

            if ($configuredAssignee && $configuredAssignee->user) {
                return $configuredAssignee->user;
            }
        }

        // Fetch lead counts for each eligible staff member (checking assigned_to or lead_user_assignments)
        $staffUserIds = $eligibleStaff->pluck('user_id')->filter()->unique()->values()->all();

        // Count directly assigned leads
        $directCounts = Lead::query()
            ->where('company_id', $companyId)
            ->whereIn('assigned_to', $staffUserIds)
            ->selectRaw('assigned_to, count(*) as total')
            ->groupBy('assigned_to')
            ->pluck('total', 'assigned_to')
            ->all();

        // Count pivot assigned leads (lead_user_assignments)
        $pivotCounts = DB::table('lead_user_assignments')
            ->join('leads', 'leads.id', '=', 'lead_user_assignments.lead_id')
            ->where('leads.company_id', $companyId)
            ->whereIn('lead_user_assignments.user_id', $staffUserIds)
            ->selectRaw('lead_user_assignments.user_id, count(distinct leads.id) as total')
            ->groupBy('lead_user_assignments.user_id')
            ->pluck('total', 'user_id')
            ->all();

        // Merge and calculate distinct load per user
        $staffWithLoads = $eligibleStaff->map(function (Staff $staff) use ($directCounts, $pivotCounts) {
            $userId = (int) $staff->user_id;
            $count = max((int) ($directCounts[$userId] ?? 0), (int) ($pivotCounts[$userId] ?? 0));
            return [
                'staff' => $staff,
                'user'  => $staff->user,
                'count' => $count,
            ];
        });

        // Find the minimum lead count among candidates
        $minCount = $staffWithLoads->min('count');

        // Filter all candidates with the minimum count
        $tiedCandidates = $staffWithLoads->where('count', $minCount)->values();

        // If multiple candidates have the same lowest count, pick randomly
        $selected = $tiedCandidates->random();

        return $selected['user'] ?? null;
    }
}

