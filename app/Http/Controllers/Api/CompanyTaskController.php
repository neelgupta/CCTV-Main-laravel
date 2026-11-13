<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateTaskStatusRequest;
use App\Models\Project;
use App\Models\Staff;
use App\Models\StaffTask;
use App\Services\TaskRepeatService;
use App\Services\TaskStatusService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyTaskController extends Controller
{
    public function updateBlocker(Request $request, StaffTask $task): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this task blocker.',
            ], 403);
        }

        // Task must belong to the user's company
        if ((int) $task->company_id !== (int) $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this task blocker.',
            ], 403);
        }

        // Company admin can update blocker on any task.
        // Other users can update blocker only on their assigned task.
        if (! $user->isCompanyAdmin()) {
            $staffId = (int) ($user->staff?->id ?? 0);

            if ($staffId <= 0 || (int) $task->staff_id !== $staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this task blocker.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'blocker_level' => [
                'required',
                'string',
                Rule::in(['low', 'medium', 'high']),
            ],
            'blocker_description' => [
                'required',
                'string',
                'max:5000',
            ],
        ]);

        $task->update([
            'has_blocker' => true,
            'blocker_level' => $validated['blocker_level'],
            'blocker_description' => trim($validated['blocker_description']),
        ]);

        $task->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Task blocker updated successfully.',
            'blocker' => [
                'has_blocker' => (bool) $task->has_blocker,
                'blocker_level' => $task->blocker_level,
                'blocker_description' => $task->blocker_description,
            ],
        ]);
    }

    public function clearBlocker(Request $request, StaffTask $task): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to resolve this task blocker.',
            ], 403);
        }

        // Task must belong to the user's company
        if ((int) $task->company_id !== (int) $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to resolve this task blocker.',
            ], 403);
        }

        // Company admin can resolve blocker on any task.
        // Other users can resolve blocker only on their assigned task.
        if (! $user->isCompanyAdmin()) {
            $staffId = (int) ($user->staff?->id ?? 0);

            if ($staffId <= 0 || (int) $task->staff_id !== $staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to resolve this task blocker.',
                ], 403);
            }
        }

        $task->update([
            'has_blocker' => false,
            'blocker_level' => null,
            'blocker_description' => null,
        ]);

        $task->refresh();

        return response()->json([
            'success' => true,
            'message' => 'Task blocker resolved successfully.',
            'blocker' => [
                'has_blocker' => (bool) $task->has_blocker,
                'blocker_level' => $task->blocker_level,
                'blocker_description' => $task->blocker_description,
            ],
        ]);
    }

    public function updateStatus(UpdateTaskStatusRequest $request, StaffTask $task, TaskStatusService $taskStatusService): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this task.',
            ], 403);
        }

        if ((int) $task->company_id !== (int) $user->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to update this task.',
            ], 403);
        }

        if (! $user->isCompanyAdmin()) {
            $staffId = (int) ($user->staff?->id ?? 0);

            if ($staffId <= 0 || (int) $task->staff_id !== $staffId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorized to update this task.',
                ], 403);
            }
        }

        $task = $taskStatusService->updateTaskStatus($task, $user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Task status updated successfully.',
            'data' => [
                'id' => $task->id,
                'status' => $task->status,
                'message' => $task->message,
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'Unauthenticated.');

        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));
        $search = trim((string) $request->query('search', ''));
        $taskId = $request->integer('task_id');
        $isCompanyAdmin = (bool) $user->isCompanyAdmin();
        $staffId = $user->staff?->id;

        $query = StaffTask::query()
            ->where('company_id', (int) $user->company_id)
            ->with(['staff:id,name', 'project:id,name,reference_no', 'updatedBy:id,name', 'statusUpdates.user:id,name'])
            ->latest();

        if (! $isCompanyAdmin) {
            abort_unless($staffId, 403, 'No linked staff profile found.');
            $query->where('staff_id', $staffId);
        }

        if ($taskId > 0) {
            $task = (clone $query)->findOrFail($taskId);

            return response()->json([
                'message' => 'Task details retrieved successfully.',
                'data' => $this->formatTask($task),
            ]);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like) {
                $builder->where('task_name', 'like', $like)
                    ->orWhere('task_description', 'like', $like);
            });
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('staff_id')) {
            $query->where('staff_id', $request->integer('staff_id'));
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->integer('project_id'));
        }

        if ($request->filled('due_date_from')) {
            $query->whereDate('due_date', '>=', $request->query('due_date_from'));
        }

        if ($request->filled('due_date_to')) {
            $query->whereDate('due_date', '<=', $request->query('due_date_to'));
        }

        $tasks = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'message' => $isCompanyAdmin
                ? 'Task records retrieved successfully.'
                : 'Task reminders retrieved successfully.',
            'data' => $isCompanyAdmin
                ? collect($tasks->items())->map(fn (StaffTask $task) => $this->formatTask($task))->values()
                : collect($tasks->items())->flatMap(fn (StaffTask $task) => $this->formatStaffReminderTasks($task))->values(),
            'meta' => [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'Unauthenticated.');

        $this->normalizeTaskRequest($request);

        $staffId = $user->staff?->id;
        $isCompanyAdmin = (bool) $user->isCompanyAdmin();
        abort_unless($isCompanyAdmin || $staffId, 403, 'No linked staff profile found.');

        if ($isCompanyAdmin) {
            $validated = $this->validateJson($request, [
                'staff_id' => [
                    'required',
                    'integer',
                    Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'project_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'task_name' => ['required', 'string', 'max:191'],
                'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
                'due_date' => ['nullable', 'date', 'after_or_equal:today'],
                'has_reminder' => ['nullable', 'boolean'],
                'reminder_date' => ['nullable', 'date', 'after_or_equal:today'],
                'reminder_type' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'day_before_due_date'])],
                'is_repeated' => ['nullable', 'boolean'],
                'repeat_type' => ['nullable', 'string', Rule::in(array_keys(StaffTask::repeatTypeOptions()))],
                'task_description' => ['nullable', 'string', 'max:5000'],
            ]);

            $task = StaffTask::create([
                'company_id' => (int) $user->company_id,
                'staff_id' => $this->resolveAssigneeId($user, (int) $validated['staff_id']),
                'created_by_staff_id' => null,
                'project_id' => $validated['project_id'] ?? null,
                'task_name' => trim((string) $validated['task_name']),
                'status' => $validated['status'],
                'due_date' => $validated['due_date'] ?? null,
                'has_reminder' => (bool) ($validated['has_reminder'] ?? false),
                'reminder_date' => ! empty($validated['has_reminder']) && filled($validated['reminder_date'] ?? null) ? $validated['reminder_date'] : null,
                'reminder_type' => ! empty($validated['has_reminder']) && filled($validated['reminder_type'] ?? null) ? $validated['reminder_type'] : null,
                'is_repeated' => (bool) ($validated['is_repeated'] ?? false),
                'repeat_type' => ! empty($validated['is_repeated']) && filled($validated['repeat_type'] ?? null) ? $validated['repeat_type'] : null,
                'task_description' => $validated['task_description'] ?? null,
            ])->load(['staff:id,name', 'project:id,name,reference_no']);
        } else {
            $validated = $this->validateJson($request, [
                'staff_id' => [
                    'required',
                    'integer',
                    Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'project_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'task_name' => ['required', 'string', 'max:191'],
                'due_date' => ['nullable', 'date', 'after_or_equal:today'],
                'has_reminder' => ['nullable', 'boolean'],
                'reminder_date' => ['nullable', 'date', 'after_or_equal:today'],
                'reminder_type' => ['nullable', 'string', Rule::in(['daily', 'weekly', 'day_before_due_date'])],
                'is_repeated' => ['nullable', 'boolean'],
                'repeat_type' => ['nullable', 'string', Rule::in(array_keys(StaffTask::repeatTypeOptions()))],
                'task_description' => ['nullable', 'string', 'max:5000'],
            ]);

            $task = StaffTask::create([
                'company_id' => (int) $user->company_id,
                'staff_id' => $this->resolveAssigneeId($user, (int) $staffId),
                'created_by_staff_id' => (int) $staffId,
                'project_id' => $validated['project_id'] ?? null,
                'task_name' => trim((string) $validated['task_name']),
                'status' => 'pending',
                'due_date' => $validated['due_date'] ?? null,
                'has_reminder' => (bool) ($validated['has_reminder'] ?? false),
                'reminder_date' => ! empty($validated['has_reminder']) && filled($validated['reminder_date'] ?? null) ? $validated['reminder_date'] : null,
                'reminder_type' => ! empty($validated['has_reminder']) && filled($validated['reminder_type'] ?? null) ? $validated['reminder_type'] : null,
                'is_repeated' => (bool) ($validated['is_repeated'] ?? false),
                'repeat_type' => ! empty($validated['is_repeated']) && filled($validated['repeat_type'] ?? null) ? $validated['repeat_type'] : null,
                'task_description' => $validated['task_description'] ?? null,
            ])->load(['staff:id,name', 'project:id,name,reference_no']);
        }

        return response()->json([
            'message' => 'Task created successfully.',
            'data' => $this->formatTask($task),
        ], 201);
    }

    public function update(Request $request, StaffTask $task, TaskRepeatService $taskRepeatService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'Unauthenticated.');
        abort_unless((int) $task->company_id === (int) $user->company_id, 403);

        $this->normalizeTaskRequest($request);

        if (! $user->isCompanyAdmin()) {
            $staffId = $user->staff?->id;
            abort_unless($staffId && (int) $task->staff_id === (int) $staffId, 403, 'You are not allowed to update this task.');

            $payloadKeys = array_values(array_diff(array_keys($request->all()), ['_token', '_method']));

            if ((int) ($task->created_by_staff_id ?? 0) === (int) $staffId) {
                $onlyStatusUpdate = $request->has('status') && empty(array_diff($payloadKeys, ['status']));

                if ($onlyStatusUpdate) {
                    $validated = $this->validateJson($request, [
                        'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
                    ]);

                    $task->update([
                        'status' => $validated['status'],
                    ]);

                    if ($task->status === 'done' && $task->is_repeated) {
                        $taskRepeatService->createNextOccurrenceFromCompletedTask($task->fresh());
                    }
                } else {
                    $validated = $this->validateJson($request, [
                        'project_id' => [
                            'sometimes',
                            'nullable',
                            'integer',
                            Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                        ],
                        'task_name' => ['sometimes', 'required', 'string', 'max:191'],
                        'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
                        'task_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
                    ]);

                    if (array_key_exists('task_name', $validated)) {
                        $validated['task_name'] = trim((string) $validated['task_name']);
                    }

                    $task->update($validated);

                    if (($validated['status'] ?? null) === 'done' && $task->is_repeated) {
                        $taskRepeatService->createNextOccurrenceFromCompletedTask($task->fresh());
                    }
                }
            } else {
                if (! $request->has('status') || count($payloadKeys) !== 1) {
                    throw new HttpResponseException(response()->json([
                        'message' => 'You can only update the status of tasks assigned to you.',
                        'errors' => [
                            'status' => ['You can only update the status of tasks assigned to you.'],
                        ],
                    ], 422));
                }

                $validated = $this->validateJson($request, [
                    'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
                ]);

                $task->update([
                    'status' => $validated['status'],
                ]);

                if ($task->status === 'done' && $task->is_repeated) {
                    $taskRepeatService->createNextOccurrenceFromCompletedTask($task->fresh());
                }
            }
        } else {
            $payloadKeys = array_keys($request->all());
            $onlyStaffAssignmentUpdate = $request->has('staff_id')
                && empty(array_diff($payloadKeys, ['staff_id']));

            if ($onlyStaffAssignmentUpdate) {
                $validated = $this->validateJson($request, [
                    'staff_id' => [
                        'required',
                        'integer',
                        Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                    ],
                ]);

                $task->update([
                    'staff_id' => (int) $validated['staff_id'],
                ]);

                $task->load(['staff:id,name', 'project:id,name,reference_no']);

                return response()->json([
                    'message' => 'Task staff updated successfully.',
                    'data' => $this->formatTask($task),
                ]);
            }

            $validated = $this->validateJson($request, [
                'staff_id' => [
                    'sometimes',
                    'required',
                    'integer',
                    Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'project_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id)),
                ],
                'task_name' => ['sometimes', 'required', 'string', 'max:191'],
                'status' => ['sometimes', 'required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
                'due_date' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],
                'task_description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ]);

            if (array_key_exists('task_name', $validated)) {
                $validated['task_name'] = trim((string) $validated['task_name']);
            }

            $task->update($validated);

            if (($validated['status'] ?? null) === 'done' && $task->is_repeated) {
                $taskRepeatService->createNextOccurrenceFromCompletedTask($task->fresh());
            }
        }

        $task->load(['staff:id,name', 'project:id,name,reference_no']);

        return response()->json([
            'message' => 'Task updated successfully.',
            'data' => $this->formatTask($task),
        ]);
    }

    public function destroy(Request $request, StaffTask $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'Unauthenticated.');
        abort_unless((int) $task->company_id === (int) $user->company_id, 403);

        if (! $user->isCompanyAdmin()) {
            abort_unless((int) ($task->created_by_staff_id ?? 0) === (int) ($user->staff?->id ?? 0), 403, 'You are not allowed to delete this task.');
        }

        $task->delete();

        return response()->json([
            'message' => 'Task deleted successfully.',
        ]);
    }

    private function formatTask(StaffTask $task): array
    {
        return [
            'id' => $task->id,
            'company_id' => $task->company_id,
            'staff_id' => $task->staff_id,
            'project_id' => $task->project_id,
            'task_name' => $task->task_name,
            'status' => $task->status,
            'status_label' => StaffTask::statusOptions()[$task->status] ?? ucfirst(str_replace('_', ' ', (string) $task->status)),
            'message' => $task->message,
            'updated_by' => $task->updated_by,
            'updated_by_name' => $task->updatedBy?->name,
            'due_date' => optional($task->due_date)->toDateString(),
            'has_reminder' => (bool) $task->has_reminder,
            'reminder_date' => optional($task->reminder_date)->toDateString(),
            'reminder_type' => $task->reminder_type,
            'is_repeated' => (bool) $task->is_repeated,
            'repeat_type' => $task->repeat_type,
            'task_description' => $task->task_description,
            'has_blocker' => (bool) $task->has_blocker,
            'blocker_level' => $task->blocker_level,
            'blocker_description' => $task->blocker_description,
            'staff' => $task->staff ? [
                'id' => $task->staff->id,
                'name' => $task->staff->name,
            ] : null,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
                'reference_no' => $task->project->reference_no,
            ] : null,
        ];
    }

    private function resolveAssigneeId($user, int $requestedStaffId): int
    {
        $staffId = (int) ($user?->staff?->id ?? 0);

        if ($user->isCompanyAdmin()) {
            abort_unless($requestedStaffId > 0, 422, 'Please select an assignee.');

            return $requestedStaffId;
        }

        abort_unless($staffId > 0, 403, 'Staff profile required for task assignment.');

        return $staffId;
    }

    private function formatStaffReminderTasks(StaffTask $task): array
    {
        $reminderDates = $task->reminderDates();

        if ($reminderDates->isEmpty()) {
            return [[
                'id' => $task->id,
                'task_name' => $task->task_name,
                'task_description' => $task->task_description,
                'status' => $task->status,
                'status_label' => StaffTask::statusOptions()[$task->status] ?? ucfirst(str_replace('_', ' ', (string) $task->status)),
                'message' => $task->message,
                'updated_by' => $task->updated_by,
                'updated_by_name' => $task->updatedBy?->name,
                'reminder_date' => null,
                'reminder_comment' => null,
            ]];
        }

        return $reminderDates->map(function ($reminderDate) use ($task) {
            $reminderStatus = $task->reminderStatusForDate($reminderDate);

            return [
                'id' => $task->id,
                'task_name' => $task->task_name,
                'task_description' => $task->task_description,
                'status' => $reminderStatus?->status ?? $task->status,
                'status_label' => $reminderStatus?->reminderStatusLabel()
                    ?? StaffTask::statusOptions()[$task->status]
                    ?? ucfirst(str_replace('_', ' ', (string) $task->status)),
                'message' => $task->message,
                'updated_by' => $task->updated_by,
                'updated_by_name' => $task->updatedBy?->name,
                'reminder_date' => $reminderDate->toDateString(),
                'reminder_comment' => $task->reminderNotesForDate($reminderDate),
            ];
        })->all();
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

    private function normalizeTaskRequest(Request $request): void
    {
        if ($request->has('repeated') && ! $request->has('is_repeated')) {
            $request->merge([
                'is_repeated' => $this->normalizeBooleanInput($request->input('repeated')),
            ]);
        }

        if ($request->has('is_repeated')) {
            $request->merge([
                'is_repeated' => $this->normalizeBooleanInput($request->input('is_repeated')),
            ]);
        }

        if ($request->has('repeat') && ! $request->has('repeat_type')) {
            $request->merge([
                'repeat_type' => $request->input('repeat'),
            ]);
        }
    }

    private function normalizeBooleanInput(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $value = strtolower(trim((string) $value));

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
