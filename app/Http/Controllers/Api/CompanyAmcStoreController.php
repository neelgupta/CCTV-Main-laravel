<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\AmcPlan;
use App\Models\CompanyGstRate;
use App\Models\Client;
use App\Models\Project;
use App\Services\AmcSupportWorkflowService;
use App\Services\AmcTechnicianAssignmentService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CompanyAmcStoreController extends Controller
{
    private const SUPPORT_TICKET_SUBJECT_PREFIX = 'AMC Service Setup - ';

    public function update(Request $request, Amc $amc): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can update AMCs.');
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        if ($amc->isServiceClosed()) {
            throw ValidationException::withMessages([
                'amc' => "This AMC service is closed. You can't change anything in this AMC.",
            ]);
        }

        $companyId = (int) $user->company_id;

        $allowedFields = ['discount'];

        $unexpectedFields = array_values(array_diff(array_keys($request->all()), $allowedFields));

        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages([
                'unexpected_fields' => 'The request contains unsupported fields: ' . implode(', ', $unexpectedFields) . '.',
            ]);
        }

        $validated = $this->validateJson($request, [
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ], [
            'discount.numeric' => 'Discount must be a number.',
        ]);

        $client = Client::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $amc->client_id);

        $project = Project::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $amc->project_id);

        $selectedPlan = AmcPlan::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $amc->amc_plan_id);

        $startDate = $amc->start_date?->copy()->startOfDay() ?? Carbon::parse($amc->start_date)->startOfDay();
        $amcAmount = round((float) $selectedPlan->price, 2);
        $discount = round((float) ($validated['discount'] ?? $amc->discount ?? 0), 2);

        if ($discount > $amcAmount) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot be greater than AMC amount.',
            ]);
        }
        // dd($discount);
        $taxPercent = $this->resolveAmcTaxPercent($companyId);
        $subtotal = round(max(0, $amcAmount - $discount), 2);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);
        $endDate = Amc::calculateEndDate($startDate, (int) $selectedPlan->duration_months);
        $includesOnCallSupport = (bool) $selectedPlan->includes_on_call_support;
        $serviceMode = $selectedPlan->service_mode ?: ($includesOnCallSupport ? 'on_call' : 'scheduled');

        DB::transaction(function () use (
            $amc,
            $client,
            $project,
            $selectedPlan,
            $includesOnCallSupport,
            $serviceMode,
            $endDate,
            $amcAmount,
            $discount,
            $taxPercent,
            $subtotal,
            $taxAmount,
            $totalAmount,
        ): void {
            $amc->update([
                'customer_name' => Str::limit((string) $client->name, 191, ''),
                'project_name' => Str::limit((string) $project->name, 191, ''),
                'mobile_number' => preg_replace('/\D+/', '', (string) ($client->mobile ?: $client->phone)) ?: null,
                'email' => filled($client->email) ? Str::lower(trim((string) $client->email)) : null,
                'address' => $client->address,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
                'gst_number' => filled($client->gst_number) ? strtoupper(trim((string) $client->gst_number)) : null,
                'plan_name' => $selectedPlan->name,
                'plan_duration_months' => $selectedPlan->duration_months,
                'plan_price' => $selectedPlan->price,
                'plan_description' => $selectedPlan->description,
                'includes_on_call_support' => $includesOnCallSupport,
                'service_mode' => $serviceMode,
                'end_date' => $endDate->toDateString(),
                'amc_amount' => $amcAmount,
                // 'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_percent' => $taxPercent,
                // 'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
            ]);
        });

        return response()->json([
            'message' => 'AMC updated successfully.',
            'data' => [
                'amc' => $amc->fresh([
                    'client',
                    'project',
                    'amcPlan',
                    'assignedStaff.user',
                    'supportTicket',
                    'visits',
                ]),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can create AMCs.');

        $companyId = (int) $user->company_id;

        $allowedFields = [
            'client_id',
            'project_id',
            'start_date',
            'amc_plan_id',
            'amc_type',
            'discount',
        ];

        $unexpectedFields = array_values(array_diff(array_keys($request->all()), $allowedFields));

        if ($unexpectedFields !== []) {
            throw ValidationException::withMessages([
                'unexpected_fields' => 'The request contains unsupported fields: ' . implode(', ', $unexpectedFields) . '.',
            ]);
        }

        $validated = $this->validateJson($request, [
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'project_id' => [
                'required',
                'integer',
                Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'start_date' => ['required', 'date'],
            'amc_plan_id' => [
                'required',
                'integer',
                Rule::exists('amc_plans', 'id')->where(fn ($query) => $query->where('company_id', $companyId)->where('is_active', true)),
            ],
            'amc_type' => ['required', Rule::in(array_keys(Amc::TYPE_OPTIONS))],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999.99'],
        ], [
            'client_id.required' => 'Client is required.',
            'client_id.exists' => 'Selected client is not available for this company.',
            'project_id.required' => 'Project is required.',
            'project_id.exists' => 'Selected project is not available for this company.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Please enter a valid start date.',
            'amc_plan_id.required' => 'AMC plan is required.',
            'amc_plan_id.exists' => 'Selected AMC plan is not active for this company.',
            'amc_type.required' => 'AMC type is required.',
            'amc_type.in' => 'AMC type must be comprehensive or non_comprehensive.',
            'discount.numeric' => 'Discount must be a number.',
        ]);

        $client = Client::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['client_id']);

        $project = Project::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['project_id']);
        $selectedPlan = AmcPlan::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->findOrFail((int) $validated['amc_plan_id']);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $amcAmount = round((float) $selectedPlan->price, 2);
        $discount = round((float) ($validated['discount'] ?? 0), 2);

        if ($discount > $amcAmount) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot be greater than AMC amount.',
            ]);
        }

        $taxPercent = $this->resolveAmcTaxPercent($companyId);
        $subtotal = round(max(0, $amcAmount - $discount), 2);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);
        $endDate = Amc::calculateEndDate($startDate, (int) $selectedPlan->duration_months);
        $includesOnCallSupport = (bool) $selectedPlan->includes_on_call_support;
        $serviceMode = $selectedPlan->service_mode ?: ($includesOnCallSupport ? 'on_call' : 'scheduled');
        $amcType = (string) $validated['amc_type'];

        app(SubscriptionLimitService::class)->ensureWithinLimit($companyId, 'amcs', 1, 'client_id');

        $amc = null;

        DB::transaction(function () use (
            $user,
            $companyId,
            $client,
            $project,
            $selectedPlan,
            $startDate,
            $endDate,
            $includesOnCallSupport,
            $serviceMode,
            $amcType,
            $amcAmount,
            $discount,
            $taxPercent,
            $subtotal,
            $taxAmount,
            $totalAmount,
            &$amc
        ): void {
            $workflowService = app(AmcSupportWorkflowService::class);

            $amc = Amc::create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'client_id' => $client->id,
                'amc_plan_id' => $selectedPlan->id,
                'assigned_staff_id' => null,
                'created_by' => $user->id,
                'contract_no' => Amc::nextContractNo($companyId),
                'customer_name' => Str::limit((string) $client->name, 191, ''),
                'project_name' => Str::limit((string) $project->name, 191, ''),
                'mobile_number' => preg_replace('/\D+/', '', (string) ($client->mobile ?: $client->phone)) ?: null,
                'email' => filled($client->email) ? Str::lower(trim((string) $client->email)) : null,
                'address' => $client->address,
                'latitude' => $client->latitude,
                'longitude' => $client->longitude,
                'gst_number' => filled($client->gst_number) ? strtoupper(trim((string) $client->gst_number)) : null,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'amc_type' => $amcType,
                'plan_name' => $selectedPlan->name,
                'plan_duration_months' => $selectedPlan->duration_months,
                'plan_price' => $selectedPlan->price,
                'plan_description' => $selectedPlan->description,
                'includes_on_call_support' => $includesOnCallSupport,
                'service_mode' => $serviceMode,
                'visit_frequency' => $selectedPlan->generatesScheduledVisits() ? 'manual' : 'on_call',
                'total_visits' => (int) $selectedPlan->visit_count,
                'scheduled_visits' => 0,
                'remaining_visits' => (int) $selectedPlan->visit_count,
                'total_cameras' => 0,
                'camera_types' => [],
                'dvr_nvr_model' => null,
                'hard_disk_size' => null,
                'installation_location' => null,
                'service_scope' => [],
                'amc_amount' => $amcAmount,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'payment_mode' => 'cash',
                'payment_status' => 'pending',
                'service_timing' => null,
                'parts_replacement_policy' => null,
                'emergency_support_terms' => null,
                'technician_name' => null,
                'technician_contact_number' => null,
            ]);

            $workflowService->seedVisitSlots($amc);

            $amc->load('visits');
            foreach ($amc->visits as $scheduledVisit) {
                app(AmcTechnicianAssignmentService::class)->assignTechnicianIfRequired(
                    $scheduledVisit,
                    now(),
                    'amc_create'
                );
            }

            $supportTicket = $workflowService->ensureCurrentSupportTicketForAmc($amc, $user);
            $amc->forceFill(['support_ticket_id' => $supportTicket->id])->saveQuietly();
        });

        return response()->json([
            'message' => 'AMC created successfully.',
            'data' => [
                'amc' => $amc->fresh([
                    'client',
                    'project',
                    'amcPlan',
                    'assignedStaff.user',
                    'supportTicket',
                    'visits',
                ]),
            ],
        ], 201);
    }

    private function resolveAmcTaxPercent(int $companyId): float
    {
        $rates = CompanyGstRate::query()
            ->where('company_id', $companyId)
            ->orderBy('rate')
            ->pluck('rate');

        return $rates->count() === 1 ? round((float) $rates->first(), 2) : 0.0;
    }

    private function buildSupportTicketSubject(string $projectName): string
    {
        return self::SUPPORT_TICKET_SUBJECT_PREFIX . trim($projectName);
    }

    private function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => collect($validator->errors())->flatten()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
