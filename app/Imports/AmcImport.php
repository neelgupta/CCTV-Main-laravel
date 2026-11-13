<?php

namespace App\Imports;

use App\Models\Amc;
use App\Models\AmcPlan;
use App\Models\AmcVisit;
use App\Models\User;
use App\Services\ClientSyncService;
use App\Services\AmcTechnicianAssignmentService;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AmcImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(private readonly User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        $companyId = (int) $this->user->company_id;
        $existingAmcs = Amc::query()
            ->where('company_id', $companyId)
            ->get();
        $amcPlans = AmcPlan::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->get();

        $prepared = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            $name = $this->requiredValue($data, ['client_name', 'name', 'customer_name']);
            $email = $this->optionalValue($data, ['email']);
            $phone = $this->requiredValue($data, ['phone', 'mobile_number', 'mobile']);
            $address = $this->requiredValue($data, ['address']);
            $projectName = $this->requiredValue($data, ['project_name']);
            $planValue = $this->optionalValue($data, ['plan', 'plan_name', 'amc_plan', 'amc_plan_name']);
            $selectedPlan = $this->resolvePlan($amcPlans, $planValue);
            $amount = $this->numericValue($data, ['amount', 'price', 'amc_amount', 'plan_price']);

            if ($selectedPlan) {
                $amount = round((float) $selectedPlan->price, 2);
            }

            if ($name === null || $phone === null || $address === null || $projectName === null || $amount === null) {
                $errors[] = "Row {$rowNumber}: client_name, phone, address, project_name, and amount are required. If you provide a valid plan, amount can be omitted.";
                continue;
            }

            if ($planValue !== null && ! $selectedPlan) {
                $errors[] = "Row {$rowNumber}: plan '{$planValue}' does not match any active AMC plan for your company.";
                continue;
            }

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: email must be a valid email address.";
                continue;
            }

            $discount = $this->numericValue($data, ['discount']) ?? 0.0;
            $gst = $this->numericValue($data, ['gst', 'tax_percent']) ?? 0.0;
            $amcType = $this->optionalValue($data, ['amc_type']);
            $normalizedAmcType = $this->normalizeAmcType($amcType);

            if ($amount < 0 || $discount < 0 || $gst < 0) {
                $errors[] = "Row {$rowNumber}: amount, discount, and gst must be 0 or greater.";
                continue;
            }

            if ($discount > $amount) {
                $errors[] = "Row {$rowNumber}: discount cannot be greater than amount.";
                continue;
            }

            if ($gst > 100) {
                $errors[] = "Row {$rowNumber}: gst must be between 0 and 100.";
                continue;
            }

            if ($amcType !== null && $normalizedAmcType === null) {
                $errors[] = "Row {$rowNumber}: amc_type must be comprehensive or non_comprehensive.";
                continue;
            }

            $startDate = $this->optionalValue($data, ['start_date']) ?: now()->toDateString();
            try {
                $startDate = Carbon::parse($startDate)->toDateString();
            } catch (\Throwable) {
                $errors[] = "Row {$rowNumber}: start_date is not a valid date.";
                continue;
            }

            $baseAmount = round($amount - $discount, 2);
            $calculatedAmount = round($baseAmount + (($baseAmount * $gst) / 100), 2);
            $totalAmount = round($this->numericValue($data, ['total_amount']) ?? $calculatedAmount, 2);
            $planDurationMonths = $selectedPlan?->duration_months ?? 12;
            $endDate = Amc::calculateEndDate($startDate, (int) $planDurationMonths)->toDateString();
            $includesOnCallSupport = (bool) ($selectedPlan?->includes_on_call_support ?? true);
            $serviceMode = $selectedPlan?->service_mode ?: ($includesOnCallSupport ? 'on_call' : 'scheduled');
            $generatedVisits = $selectedPlan && $selectedPlan->generatesScheduledVisits()
                ? Amc::generateVisitScheduleForPlan(
                    $startDate,
                    (int) $selectedPlan->duration_months,
                    (int) $selectedPlan->visit_count
                )
                : [];

            $existingAmc = $existingAmcs->first(function (Amc $amc) use ($name, $email, $phone, $amount, $projectName, $startDate) {
                $emailMatches = $email === null
                    ? blank($amc->email)
                    : Str::lower(trim((string) $amc->email)) === Str::lower($email);

                return Str::lower(trim((string) $amc->customer_name)) === Str::lower($name)
                    && $emailMatches
                    && trim((string) $amc->mobile_number) === trim($phone)
                    && Str::lower(trim((string) $amc->project_name)) === Str::lower($projectName)
                    && round((float) $amc->amc_amount, 2) === round($amount, 2)
                    && optional($amc->start_date)->format('Y-m-d') === $startDate;
            });

            if ($existingAmc) {
                continue;
            }

            $prepared[] = [
                'client' => [
                    'company_id' => $companyId,
                    'name' => $name,
                    'email' => $email ? Str::lower($email) : null,
                    'mobile' => trim($phone),
                    'phone' => trim($phone),
                    'address' => $address,
                    'is_direct_client' => true,
                ],
                'amc' => [
                    'company_id' => $companyId,
                    'created_by' => $this->user->id,
                    'amc_plan_id' => $selectedPlan?->id,
                    'customer_name' => $name,
                    'project_name' => $projectName,
                    'mobile_number' => trim($phone),
                    'email' => $email ? Str::lower($email) : null,
                    'address' => $address,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amc_type' => $normalizedAmcType ?? 'non_comprehensive',
                    'plan_name' => $selectedPlan?->name ?? 'Imported AMC',
                    'plan_duration_months' => $planDurationMonths,
                    'plan_price' => round((float) ($selectedPlan?->price ?? $amount), 2),
                    'plan_description' => $selectedPlan?->description ?? 'Imported from spreadsheet',
                    'includes_on_call_support' => $includesOnCallSupport,
                    'service_mode' => $serviceMode,
                    'visit_frequency' => $selectedPlan?->generatesScheduledVisits() ? 'plan_based' : 'on_call',
                    'total_visits' => count($generatedVisits),
                    'total_cameras' => 0,
                    'camera_types' => [],
                    'service_scope' => [],
                    'amc_amount' => round($amount, 2),
                    'discount' => round($discount, 2),
                    'tax_percent' => round($gst, 2),
                    'total_amount' => $totalAmount,
                    'payment_mode' => 'bank_transfer',
                    'payment_status' => 'pending',
                    'service_timing' => 'Imported AMC contract.',
                    'parts_replacement_policy' => 'As per imported AMC contract.',
                    'emergency_support_terms' => 'Standard imported AMC support terms.',
                ],
                'visits' => $generatedVisits,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'amc_import_file' => $errors,
            ]);
        }

        DB::transaction(function () use ($prepared, $companyId) {
            $subscriptionLimits = app(SubscriptionLimitService::class);
            $clientSyncService = app(ClientSyncService::class);
            $assignmentService = app(AmcTechnicianAssignmentService::class);

            foreach ($prepared as $item) {
                $subscriptionLimits->ensureWithinLimit($companyId, 'amcs', 1, 'customer_name');

                $client = $clientSyncService->upsert($item['client']);
                $item['amc']['client_id'] = $client->id;
                $item['amc']['contract_no'] = Amc::nextContractNo($companyId);

                $amc = Amc::create($item['amc']);

                foreach ($item['visits'] ?? [] as $visit) {
                    $createdVisit = AmcVisit::create([
                        'amc_id' => $amc->id,
                        'visit_no' => $visit['visit_no'],
                        'visit_date' => $visit['visit_date'],
                        'status' => $visit['status'],
                        'technician' => null,
                    ]);

                    $assignmentService->assignTechnicianIfRequired($createdVisit, now(), 'import');
                }
            }
        });
    }

    private function resolvePlan(Collection $plans, ?string $value): ?AmcPlan
    {
        if ($value === null) {
            return null;
        }

        $normalizedValue = Str::of($value)
            ->trim()
            ->lower()
            ->replace(['-', '_'], ' ')
            ->squish()
            ->toString();

        return $plans->first(function (AmcPlan $plan) use ($value, $normalizedValue) {
            $normalizedPlanName = Str::of($plan->name)
                ->trim()
                ->lower()
                ->replace(['-', '_'], ' ')
                ->squish()
                ->toString();

            return (string) $plan->id === trim($value)
                || $normalizedPlanName === $normalizedValue;
        });
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = Str::of((string) $key)
                ->trim()
                ->lower()
                ->replace(['-', ' '], '_')
                ->toString();

            $normalized[$normalizedKey] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function requiredValue(array $data, array $keys): ?string
    {
        return $this->optionalValue($data, $keys);
    }

    private function optionalValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function numericValue(array $data, array $keys): ?float
    {
        $value = $this->optionalValue($data, $keys);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function normalizeAmcType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = Str::of($value)
            ->trim()
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        return array_key_exists($normalized, Amc::TYPE_OPTIONS) ? $normalized : null;
    }
}
