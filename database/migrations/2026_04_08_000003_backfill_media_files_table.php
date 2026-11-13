<?php

use App\Models\LeadDocument;
use App\Models\LeadQuotation;
use App\Models\LeadSurvey;
use App\Models\MediaFile;
use App\Models\ProjectDrawing;
use App\Models\ProjectMaterial;
use App\Models\ProjectProduction;
use App\Models\Staff;
use App\Models\StaffDocument;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';

        $insertIfMissing = function (array $attributes) {
            if (empty($attributes['path']) || empty($attributes['company_id'])) {
                return;
            }

            $exists = MediaFile::query()
                ->where('company_id', $attributes['company_id'])
                ->where('disk', $attributes['disk'])
                ->where('path', $attributes['path'])
                ->exists();

            if (!$exists) {
                MediaFile::create($attributes);
            }
        };

        Staff::query()
            ->withoutGlobalScopes()
            ->select(['id', 'company_id', 'profile_photo'])
            ->chunkById(200, function ($staffs) use ($insertIfMissing, $disk) {
                foreach ($staffs as $staff) {
                    if (!empty($staff->profile_photo)) {
                        $insertIfMissing([
                            'company_id' => $staff->company_id,
                            'module' => 'staff',
                            'title' => 'Profile Photo',
                            'disk' => $disk,
                            'path' => $staff->profile_photo,
                            'original_name' => basename($staff->profile_photo),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            }
        );

        StaffDocument::query()
            ->select(['id', 'staff_id', 'document_path'])
            ->with(['staff' => function ($query) {
                $query->withTrashed()->select(['id', 'company_id']);
            }])
            ->chunkById(200, function ($documents) use ($insertIfMissing, $disk) {
                foreach ($documents as $document) {
                    if ($document->staff && !empty($document->document_path)) {
                        $insertIfMissing([
                            'company_id' => $document->staff->company_id,
                            'module' => 'staff',
                            'title' => 'Staff Document',
                            'disk' => $disk,
                            'path' => $document->document_path,
                            'original_name' => basename($document->document_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        LeadSurvey::query()
            ->select(['id', 'lead_id', 'photo_path'])
            ->with(['lead' => function ($query) {
                $query->withTrashed()->select(['id', 'company_id']);
            }])
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->lead && !empty($row->photo_path)) {
                        $insertIfMissing([
                            'company_id' => $row->lead->company_id,
                            'module' => 'leads',
                            'title' => 'Lead Survey',
                            'disk' => $disk,
                            'path' => $row->photo_path,
                            'original_name' => basename($row->photo_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        LeadDocument::query()
            ->select(['id', 'lead_id', 'file_path', 'name'])
            ->with(['lead' => function ($query) {
                $query->withTrashed()->select(['id', 'company_id']);
            }])
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->lead && !empty($row->file_path)) {
                        $insertIfMissing([
                            'company_id' => $row->lead->company_id,
                            'module' => 'leads',
                            'title' => $row->name ?: 'Lead Document',
                            'disk' => $disk,
                            'path' => $row->file_path,
                            'original_name' => basename($row->file_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        LeadQuotation::query()
            ->select(['id', 'lead_id', 'file_path', 'source'])
            ->with(['lead' => function ($query) {
                $query->withTrashed()->select(['id', 'company_id']);
            }])
            ->whereNotNull('file_path')
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->lead && strtolower((string) $row->source) === 'upload') {
                        $insertIfMissing([
                            'company_id' => $row->lead->company_id,
                            'module' => 'leads',
                            'title' => 'Lead Quotation',
                            'disk' => $disk,
                            'path' => $row->file_path,
                            'original_name' => basename($row->file_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        ProjectDrawing::query()
            ->select(['id', 'project_id', 'file_path'])
            ->with('project:id,company_id')
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->project && !empty($row->file_path)) {
                        $insertIfMissing([
                            'company_id' => $row->project->company_id,
                            'module' => 'projects',
                            'title' => 'Project Drawing',
                            'disk' => $disk,
                            'path' => $row->file_path,
                            'original_name' => basename($row->file_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        ProjectMaterial::query()
            ->select(['id', 'project_id', 'attachment_path'])
            ->with('project:id,company_id')
            ->whereNotNull('attachment_path')
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->project && !empty($row->attachment_path)) {
                        $insertIfMissing([
                            'company_id' => $row->project->company_id,
                            'module' => 'projects',
                            'title' => 'Project Material',
                            'disk' => $disk,
                            'path' => $row->attachment_path,
                            'original_name' => basename($row->attachment_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });

        ProjectProduction::query()
            ->select(['id', 'project_id', 'attachment_path'])
            ->with('project:id,company_id')
            ->whereNotNull('attachment_path')
            ->chunkById(200, function ($rows) use ($insertIfMissing, $disk) {
                foreach ($rows as $row) {
                    if ($row->project && !empty($row->attachment_path)) {
                        $insertIfMissing([
                            'company_id' => $row->project->company_id,
                            'module' => 'projects',
                            'title' => 'Project Production',
                            'disk' => $disk,
                            'path' => $row->attachment_path,
                            'original_name' => basename($row->attachment_path),
                            'mime_type' => null,
                            'size' => null,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('media_files')->whereIn('module', ['staff', 'leads', 'projects'])->delete();
    }
};
