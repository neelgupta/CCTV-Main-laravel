<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }

            if (!Schema::hasColumn('projects', 'reference_no')) {
                $table->string('reference_no')->nullable()->unique()->after('uuid');
            }

            if (!Schema::hasColumn('projects', 'client_name')) {
                $table->string('client_name')->nullable()->after('name');
            }

            if (!Schema::hasColumn('projects', 'current_stage')) {
                $table->string('current_stage')->default('drawing')->after('description');
            }

            if (!Schema::hasColumn('projects', 'project_manager_id')) {
                $table->foreignId('project_manager_id')->nullable()->after('current_stage')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('projects', 'team_member_ids')) {
                $table->json('team_member_ids')->nullable()->after('project_manager_id');
            }

            if (!Schema::hasColumn('projects', 'completion_percentage')) {
                $table->unsignedTinyInteger('completion_percentage')->default(0)->after('end_date');
            }
        });

        $projects = DB::table('projects')->orderBy('id')->get();

        foreach ($projects as $index => $project) {
            $lead = $project->lead_id
                ? DB::table('leads')->select('client_name', 'assigned_to')->where('id', $project->lead_id)->first()
                : null;

            $managerId = $lead?->assigned_to;

            DB::table('projects')
                ->where('id', $project->id)
                ->update([
                    'uuid' => $project->uuid ?: (string) Str::uuid(),
                    'reference_no' => $project->reference_no ?: sprintf('PRJ-%s-%04d', now()->format('Y'), $index + 1),
                    'client_name' => $project->client_name ?: $lead?->client_name,
                    'current_stage' => $project->current_stage ?: 'drawing',
                    'project_manager_id' => $project->project_manager_id ?: $managerId,
                    'team_member_ids' => $project->team_member_ids ?: ($managerId ? json_encode([$managerId]) : null),
                    'completion_percentage' => $project->completion_percentage ?? 0,
                    'status' => in_array($project->status, [null, '', 'pending'], true) ? 'on_track' : $project->status,
                ]);
        }

        if (!Schema::hasTable('project_drawings')) {
            Schema::create('project_drawings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->string('file_path');
                $table->string('file_name');
                $table->string('version')->default('1.0');
                $table->string('drawing_status')->default('draft');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_materials')) {
            Schema::create('project_materials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->string('material_name');
                $table->decimal('qty', 12, 2)->default(0);
                $table->string('size')->nullable();
                $table->text('note')->nullable();
                $table->string('attachment_path')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('project_productions')) {
            Schema::create('project_productions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->onDelete('cascade');
                $table->string('title');
                $table->text('note')->nullable();
                $table->string('attachment_path')->nullable();
                $table->boolean('qa_done')->default(false);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_productions');
        Schema::dropIfExists('project_materials');
        Schema::dropIfExists('project_drawings');

        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'project_manager_id')) {
                $table->dropConstrainedForeignId('project_manager_id');
            }

            if (Schema::hasColumn('projects', 'reference_no')) {
                $table->dropUnique(['reference_no']);
            }

            if (Schema::hasColumn('projects', 'uuid')) {
                $table->dropUnique(['uuid']);
            }

            $columns = collect([
                'uuid',
                'reference_no',
                'client_name',
                'current_stage',
                'team_member_ids',
                'completion_percentage',
            ])->filter(fn ($column) => Schema::hasColumn('projects', $column))->values()->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
