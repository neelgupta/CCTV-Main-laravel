<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProjectExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly Collection $projects,
        private readonly Collection $clientsByName,
        private readonly Collection $usersById,
        private readonly ?Company $company = null,
    ) {
    }

    public function collection(): Collection
    {
        $customFields = $this->company?->projectCustomFieldDefinitions() ?? [];

        return $this->projects->map(function ($project) use ($customFields) {
            $client = $this->clientsByName->get(mb_strtolower(trim((string) $project->client_name)));
            $lead = $project->lead;

            $row = [
                $project->name,
                $project->client_name,
                $lead?->email ?: $client?->email,
                $lead?->mobile ?: $client?->phone ?: $client?->mobile,
                $lead?->address ?: $client?->address,
                $project->reference_no,
                $lead?->client_name,
                $project->stage_label,
                $project->status,
                $project->manager?->name,
                $project->state?->name ?: $lead?->state?->name ?: $client?->state?->name,
                $project->city?->name ?: $lead?->city?->name ?: $client?->city?->name,
                optional($project->start_date)->format('Y-m-d'),
                optional($project->end_date)->format('Y-m-d'),
                $project->description,
                collect($project->team_member_ids ?? [])
                    ->map(fn ($id) => $this->usersById->get((int) $id)?->name)
                    ->filter()
                    ->join(', '),
            ];

            foreach ($customFields as $field) {
                $fieldKey = (string) ($field['key'] ?? '');
                if ($fieldKey === '') {
                    continue;
                }

                $row[] = $this->formatCustomFieldValue(
                    $field,
                    data_get($project->custom_fields ?? [], $fieldKey)
                );
            }

            $row[] = optional($project->created_at)->format('Y-m-d H:i:s');
            $row[] = optional($project->updated_at)->format('Y-m-d H:i:s');

            return $row;
        });
    }

    public function headings(): array
    {
        $headings = [
            'project_name',
            'client_name',
            'email',
            'phone',
            'address',
            'reference_no',
            'lead_name',
            'current_stage',
            'status',
            'project_manager',
            'state',
            'city',
            'start_date',
            'end_date',
            'description',
            'team_members',
        ];

        foreach ($this->company?->projectCustomFieldDefinitions() ?? [] as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label !== '') {
                $headings[] = $label;
            }
        }

        $headings[] = 'created_at';
        $headings[] = 'updated_at';

        return $headings;
    }

    private function formatCustomFieldValue(array $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (($field['type'] ?? null) === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOL) ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return implode(', ', array_map(static fn ($item) => trim((string) $item), $value));
        }

        return trim((string) $value);
    }
}
