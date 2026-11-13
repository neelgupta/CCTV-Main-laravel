<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\Project;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadProjectConversionService
{
    public function convertLeadToProject(Lead $lead, array $projectData = []): ?Project
    {
        $lead->loadMissing('project', 'assignedUsers:id');

        if ($lead->project) {
            return $lead->project;
        }

        app(SubscriptionLimitService::class)->ensureWithinLimit((int) $lead->company_id, 'projects', 1, 'project_name');

        $projectStatus = $this->normalizeProjectStatus($projectData['status'] ?? null);
        $startDate = $projectData['start_date'] ?? null;
        $endDate = $projectData['end_date'] ?? null;
        $projectName = $this->normalizeProjectName($projectData['name'] ?? null, $lead->client_name);
        $projectDescription = $this->normalizeProjectDescription($projectData['description'] ?? null, $lead->client_name);

        $assignedUserIds = $lead->assignedUsers->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        app(ClientSyncService::class)->upsert([
            'company_id' => (int) $lead->company_id,
            'name' => $lead->client_name,
            'email' => $lead->email,
            'mobile' => $lead->mobile,
            'phone' => $lead->mobile ?: $lead->phone,
            'gst_number' => $lead->gst_number,
            'pan_number' => $lead->pan_number,
            'state_id' => $lead->state_id,
            'city_id' => $lead->city_id,
            'address' => $lead->address,
        ]);

        $payload = [
            'company_id' => $lead->company_id,
            'lead_id' => $lead->id,
            'name' => $projectName,
            'client_name' => $lead->client_name,
            'description' => $projectDescription,
            'current_stage' => 'new-project',
            'project_manager_id' => $assignedUserIds[0] ?? $lead->assigned_to,
            'team_member_ids' => ! empty($assignedUserIds) ? $assignedUserIds : ($lead->assigned_to ? [$lead->assigned_to] : null),
            'start_date' => $startDate ?: now()->toDateString(),
            'end_date' => $endDate ?: $lead->next_follow_up,
            'completion_percentage' => $projectStatus === 'completed' ? 100 : 0,
            'status' => $projectStatus,
        ];

        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                $payload['reference_no'] = Project::nextReferenceNo($lead->company_id);

                return Project::create($payload);
            } catch (UniqueConstraintViolationException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'projects_reference_no_unique') || $attempt === 4) {
                    throw $e;
                }
            }
        }

        throw ValidationException::withMessages([
            'project' => 'Unable to generate a unique project reference number. Please try again.',
        ]);
    }

    public function backfillWonLeadProjects(int $companyId): int
    {
        $wonStatusId = LeadStatus::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(name) = ?', ['won'])
            ->value('id');

        if (! $wonStatusId) {
            return 0;
        }

        $converted = 0;

        Lead::query()
            ->where('company_id', $companyId)
            ->where('lead_status_id', $wonStatusId)
            ->whereDoesntHave('project')
            ->with('assignedUsers:id')
            ->orderBy('id')
            ->chunkById(50, function ($leads) use (&$converted): void {
                foreach ($leads as $lead) {
                    $this->convertLeadToProject($lead);
                    $converted++;
                }
            });

        return $converted;
    }

    private function normalizeProjectName(?string $name, string $clientName): string
    {
        $fallback = 'Project: ' . trim($clientName);
        $value = Str::squish(trim((string) $name));

        if ($value === '') {
            $value = $fallback;
        }

        return Str::limit($value, 191, '');
    }

    private function normalizeProjectDescription(?string $description, string $clientName): string
    {
        $fallback = 'Converted from lead: ' . trim($clientName);
        $value = Str::squish(trim((string) $description));

        if ($value === '') {
            $value = $fallback;
        }

        return Str::limit($value, 1000, '');
    }

    private function normalizeProjectStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return match ($normalized) {
            'completed' => 'completed',
            'at_risk' => 'at_risk',
            'delayed', 'cancelled' => 'delayed',
            'on_track', 'pending', 'in_progress' => 'on_track',
            default => 'on_track',
        };
    }
}
