<?php

namespace App\Services;

use App\Models\City;
use App\Models\Company;
use App\Models\Inquiry;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCategory;
use App\Models\LeadStatus;
use App\Models\Staff;
use App\Models\State;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class InquiryLeadSyncService
{
    public function __construct(
        private readonly ClientSyncService $clientSyncService,
        private readonly ?LeadAssignmentService $leadAssignmentService = null
    ) {
    }

    /**
     * Resolve target company from parameter, company_code, or env/config.
     */
    public function resolveTargetCompany(?string $companyCode = null, ?int $companyId = null): ?Company
    {
        if ($companyId) {
            $company = Company::find($companyId);
            if ($company) {
                return $company;
            }
        }

        if (!empty($companyCode)) {
            $company = Company::where('company_code', trim($companyCode))->first();
            if ($company) {
                return $company;
            }
        }

        // Direct lookup for Sourcecube Technology CCTV Pro One (Company Code: CC-HY5FLC)
        $company = Company::where('company_code', 'CC-HY5FLC')->first();
        if ($company) {
            return $company;
        }

        // Fallbacks: look for Sourcecube by registered email or company name
        return Company::where('email', 'sourcecube@mailinator.com')
            ->orWhere('name', 'like', '%Sourcecube%')
            ->first() ?? Company::first();
    }

    /**
     * Create or sync a Lead from an Inquiry.
     */
    public function createLeadFromInquiry(Inquiry $inquiry, ?Company $company = null): ?Lead
    {
        $company = $company ?? $this->resolveTargetCompany();
        if (!$company) {
            Log::warning("InquiryLeadSyncService: Target company could not be resolved for inquiry #{$inquiry->id}");
            return null;
        }

        // Check if already linked via lead_id column
        if (Schema::hasColumn('inquiries', 'lead_id') && !empty($inquiry->lead_id)) {
            $existing = Lead::where('company_id', $company->id)->find($inquiry->lead_id);
            if ($existing) {
                return $existing;
            }
        }

        // Check if a lead with same mobile or email already exists in this company
        $existingLead = Lead::where('company_id', $company->id)
            ->where(function ($q) use ($inquiry) {
                $q->where('mobile', $inquiry->phone);
                if (!empty($inquiry->email)) {
                    $q->orWhere('email', $inquiry->email);
                }
            })
            ->first();

        if ($existingLead) {
            if (Schema::hasColumn('inquiries', 'lead_id') && empty($inquiry->lead_id)) {
                $inquiry->updateQuietly(['lead_id' => $existingLead->id]);
            }
            return $existingLead;
        }

        return DB::transaction(function () use ($inquiry, $company) {
            // 1. Resolve State & City
            $stateId = null;
            $cityId = null;

            if (!empty($inquiry->state)) {
                $stateObj = State::whereRaw('LOWER(name) = ?', [Str::lower(trim($inquiry->state))])
                    ->orWhere('name', 'like', '%' . trim($inquiry->state) . '%')
                    ->first();
                $stateId = $stateObj?->id;
            }

            if (!empty($inquiry->city)) {
                $cityQuery = City::whereRaw('LOWER(name) = ?', [Str::lower(trim($inquiry->city))]);
                if ($stateId) {
                    $cityQuery->where('state_id', $stateId);
                }
                $cityObj = $cityQuery->first();
                $cityId = $cityObj?->id;
            }

            // 2. Resolve Status (Prefer "New Lead" or default status or lowest order)
            $status = LeadStatus::where('company_id', $company->id)
                ->where(function ($q) {
                    $q->where('name', 'like', '%New%')
                      ->orWhere('is_default', 1);
                })
                ->orderBy('order')
                ->first()
                ?? LeadStatus::where('company_id', $company->id)->orderBy('order')->first();

            // 3. Resolve Category (Prefer "Demo Category", or first category, or create if none exists)
            $category = LeadCategory::where('company_id', $company->id)
                ->where('name', 'like', '%Demo%')
                ->first()
                ?? LeadCategory::where('company_id', $company->id)->first();

            if (!$category) {
                $category = LeadCategory::create([
                    'company_id' => $company->id,
                    'name' => 'Demo Category',
                ]);
            }

            // 4. Resolve Assignee / Creator User in company
            $assignee = $this->resolveLeadAssignee($company);
            $creator = $assignee ?? User::where('company_id', $company->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            $clientName = !empty($inquiry->company_name)
                ? trim($inquiry->company_name)
                : trim($inquiry->name);

            $contactPersonNote = (!empty($inquiry->company_name) && !empty($inquiry->name) && $inquiry->company_name !== $inquiry->name)
                ? $inquiry->name
                : null;

            $inquiryEmail = $inquiry->email ?: null;
            if ($inquiryEmail && $company->isInternalStaffOrAdminEmail($inquiryEmail)) {
                $inquiryEmail = null;
            }

            // 5. Create Lead
            $lead = Lead::create([
                'company_id'           => $company->id,
                'lead_status_id'       => $status?->id,
                'lead_category_id'     => $category?->id,
                'assigned_to'          => $assignee?->id,
                'created_by'           => $creator?->id,
                'client_name'          => $clientName,
                'referral_person_name' => $contactPersonNote,
                'email'                => $inquiryEmail,
                'mobile'               => $inquiry->phone,
                'source'               => !empty($inquiry->source) ? $inquiry->source : 'Landing Page Demo',
                'state_id'             => $stateId,
                'city_id'              => $cityId,
                'address'              => $inquiry->city ? trim(($inquiry->city . ', ' . ($inquiry->state ?? '')), ', ') : ($inquiry->state ?? null),
                'priority'             => 'Medium',
                'estimated_value'      => 0,
                'next_follow_up'       => now()->addDay(),
            ]);

            if ($assignee) {
                $lead->assignedUsers()->sync([$assignee->id]);
            }

            // 6. Upsert into Client table
            try {
                $this->clientSyncService->upsert([
                    'company_id' => $company->id,
                    'name'       => $lead->client_name,
                    'email'      => $lead->email,
                    'mobile'     => $lead->mobile,
                    'phone'      => $inquiry->phone,
                    'state_id'   => $lead->state_id,
                    'city_id'    => $lead->city_id,
                    'address'    => $lead->address,
                ]);
            } catch (\Throwable $e) {
                Log::warning('Client sync skipped during inquiry lead creation: ' . $e->getMessage());
            }

            // 7. Activity Log
            try {
                LeadActivity::create([
                    'lead_id'     => $lead->id,
                    'user_id'     => $assignee?->id,
                    'type'        => 'status_change',
                    'description' => 'Lead created automatically from Inquiry. Contact: ' . $inquiry->name . ' (' . $inquiry->phone . ')' . ($inquiry->message ? ' - Message: ' . $inquiry->message : ''),
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('LeadActivity creation skipped: ' . $e->getMessage());
            }

            // 8. Associate lead_id back to inquiry if column exists
            if (Schema::hasColumn('inquiries', 'lead_id')) {
                $inquiry->updateQuietly(['lead_id' => $lead->id]);
            }

            return $lead;
        });
    }

    /**
     * Sync all existing inquiries into company leads.
     */
    public function syncAllInquiries(?Company $company = null): array
    {
        $company = $company ?? $this->resolveTargetCompany();
        if (!$company) {
            return [
                'total' => 0,
                'created' => 0,
                'skipped' => 0,
                'error' => 'Target company not found.',
            ];
        }

        $inquiries = Inquiry::query()->orderBy('id')->get();
        $total = $inquiries->count();
        $created = 0;
        $skipped = 0;

        foreach ($inquiries as $inquiry) {
            try {
                $beforeCount = Lead::where('company_id', $company->id)->count();
                $lead = $this->createLeadFromInquiry($inquiry, $company);
                $afterCount = Lead::where('company_id', $company->id)->count();

                if ($afterCount > $beforeCount) {
                    $created++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                Log::error("Failed to sync inquiry #{$inquiry->id} to lead: " . $e->getMessage());
                $skipped++;
            }
        }

        return [
            'total'   => $total,
            'created' => $created,
            'skipped' => $skipped,
            'company' => $company->name . ' (' . $company->company_code . ')',
        ];
    }

    /**
     * Resolve eligible staff member to assign lead to:
     * - Employee role (non-admin role, not company admin or super admin)
     * - Must have active subscription plan
     * - Staff with least assigned leads
     * - Random choice if tied
     *
     * @param Company $company
     * @return User|null
     */
    public function resolveLeadAssignee(Company $company): ?User
    {
        return ($this->leadAssignmentService ?? app(LeadAssignmentService::class))->resolveLeadAssignee($company);
    }
}

