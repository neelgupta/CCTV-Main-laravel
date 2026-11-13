<?php

namespace App\Support;

use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\LeadQuotation;
use App\Models\LeadSurvey;
use App\Models\Project;
use App\Models\ProjectDrawing;
use App\Models\ProjectMaterial;
use App\Models\ProjectProduction;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyStorageUsage
{
    public function usedBytes(int $companyId): int
    {
        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $companyFolder = 'company_' . $companyId;

        $paths = collect();

        try {
            $paths = $paths->merge(Storage::disk($disk)->allFiles($companyFolder));
        } catch (\Throwable $e) {
            // ignore missing folder or read errors
        }

        $paths = $paths->merge(Staff::where('company_id', $companyId)->pluck('profile_photo')->filter());
        $paths = $paths->merge(StaffDocument::whereHas('staff', fn ($q) => $q->where('company_id', $companyId))->pluck('document_path')->filter());

        $leadIds = Lead::where('company_id', $companyId)->pluck('id');
        $paths = $paths->merge(LeadSurvey::whereIn('lead_id', $leadIds)->pluck('photo_path')->filter());
        $paths = $paths->merge(LeadDocument::whereIn('lead_id', $leadIds)->pluck('file_path')->filter());
        $paths = $paths->merge(LeadQuotation::whereIn('lead_id', $leadIds)->whereNotNull('file_path')->pluck('file_path')->filter());

        $projectIds = Project::where('company_id', $companyId)->pluck('id');
        $paths = $paths->merge(ProjectDrawing::whereIn('project_id', $projectIds)->pluck('file_path')->filter());
        $paths = $paths->merge(ProjectMaterial::whereIn('project_id', $projectIds)->pluck('attachment_path')->filter());
        $paths = $paths->merge(ProjectProduction::whereIn('project_id', $projectIds)->pluck('attachment_path')->filter());

        $normalize = static function ($path): ?string {
            $clean = str_replace('\\', '/', (string) $path);
            $clean = ltrim($clean, '/');
            if (Str::startsWith($clean, 'storage/')) {
                $clean = Str::after($clean, 'storage/');
            }
            if (Str::startsWith($clean, 'public/')) {
                $clean = Str::after($clean, 'public/');
            }
            if (Str::startsWith($clean, ['http://', 'https://', 'data:'])) {
                return null;
            }
            return $clean;
        };

        $usedBytes = 0;
        $seen = [];
        foreach ($paths->filter()->unique()->values() as $path) {
            $normalized = $normalize($path);
            if (! $normalized || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            try {
                if (Storage::disk($disk)->exists($normalized)) {
                    $usedBytes += Storage::disk($disk)->size($normalized);
                }
            } catch (\Throwable $e) {
                // skip unreadable paths
            }
        }

        return $usedBytes;
    }
}
