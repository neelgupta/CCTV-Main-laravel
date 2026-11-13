<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Staff;
use App\Models\StaffTask;
use App\Models\StaffTaskStatusUpdate;
use App\Services\DashboardNotificationService;
use App\Services\TaskRepeatService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class StaffTaskController extends Controller
{
    private function ensureTaskViewer(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasPermission('tasks.view') || $user->hasPermission('tasks.manage')), 403);
    }

    private function ensureTaskManager(): void
    {
        $user = auth()->user();
        abort_unless($user && ($user->isSuperAdmin() || $user->isCompanyAdmin() || $user->hasPermission('tasks.manage')), 403);
    }

    public function index()
    {
        $this->ensureTaskViewer();

        $user = auth()->user();
        $companyId = $user?->company_id;
        $filters = $this->taskFilters(request());

        $baseQuery = StaffTask::query()
            ->with(['staff', 'project'])
            ->where('company_id', $companyId)
            ->when(! $user?->isCompanyAdmin(), function ($query) use ($user) {
                $staffId = $user?->staff?->id;
                abort_unless($staffId, 403, 'Staff profile required.');

                $query->where('staff_id', $staffId);
            });

        $taskQuickCounts = [
            'all' => (clone $baseQuery)->count(),
            'today_due' => (clone $baseQuery)->whereDate('due_date', now()->toDateString())->count(),
            'this_week_due' => (clone $baseQuery)->whereBetween('due_date', [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()])->count(),
            'this_month_due' => (clone $baseQuery)->whereBetween('due_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->count(),
            'low_blocker' => (clone $baseQuery)->where('has_blocker', true)->where('blocker_level', 'low')->count(),
            'medium_blocker' => (clone $baseQuery)->where('has_blocker', true)->where('blocker_level', 'medium')->count(),
            'high_blocker' => (clone $baseQuery)->where('has_blocker', true)->where('blocker_level', 'high')->count(),
        ];

        $tasksQuery = $this->applyTaskFilters($baseQuery, $filters);

        $tasks = $tasksQuery
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $staffMembers = Staff::query()
            ->where('company_id', $companyId)
            ->activeWithActiveSubscription()
            ->orderBy('name')
            ->get(['id', 'name']);

        $projects = Project::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'reference_no']);

        $statusOptions = StaffTask::statusOptions();
        $quickFilterOptions = [
            'all' => ['label' => 'All', 'query' => []],
            'today_due' => ['label' => 'Today Due', 'query' => ['due_range' => 'today']],
            'this_week_due' => ['label' => 'This Week Due', 'query' => ['due_range' => 'week']],
            'this_month_due' => ['label' => 'This Month Due', 'query' => ['due_range' => 'month']],
            'low_blocker' => ['label' => 'Low Level Blocker', 'query' => ['blocker_level' => 'low']],
            'medium_blocker' => ['label' => 'Medium Level Blocker', 'query' => ['blocker_level' => 'medium']],
            'high_blocker' => ['label' => 'High Level Blocker', 'query' => ['blocker_level' => 'high']],
        ];

        return view('staff-tasks.index', compact('tasks', 'staffMembers', 'projects', 'statusOptions', 'filters', 'taskQuickCounts', 'quickFilterOptions'));
    }

    public function create()
    {
        $this->ensureTaskManager();

        $user = auth()->user();
        $companyId = $user?->company_id;
        $isCompanyAdmin = (bool) $user?->isCompanyAdmin();
        $staffMembers = Staff::query()
            ->where('company_id', $companyId)
            ->activeWithActiveSubscription()
            ->orderBy('name')
            ->get(['id', 'name']);
        $projects = Project::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'reference_no']);

        $statusOptions = StaffTask::statusOptions();
        $reminderWeekdayOptions = StaffTask::reminderWeekdayOptions();
        $repeatTypeOptions = StaffTask::repeatTypeOptions();
        $assigneeId = $this->defaultAssigneeId($user);

        return view('staff-tasks.create', compact('staffMembers', 'projects', 'statusOptions', 'reminderWeekdayOptions', 'repeatTypeOptions', 'assigneeId', 'isCompanyAdmin'));
    }

    public function show(StaffTask $task_management)
    {
        $this->ensureTaskViewer();

        $task = $task_management->load(['staff.user', 'project', 'statusUpdates.user']);

        return view('staff-tasks.show', [
            'task' => $task,
            'reminderWeekdayOptions' => StaffTask::reminderWeekdayOptions(),
            'repeatTypeOptions' => StaffTask::repeatTypeOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $this->ensureTaskManager();
        $user = auth()->user();
        $isCompanyAdmin = (bool) $user?->isCompanyAdmin();

        $validated = $request->validate([
            'staff_id' => $isCompanyAdmin
                ? ['required', 'integer', Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $user?->company_id))]
                : ['nullable'],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
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

        $validated = $this->normalizeTaskPayload($validated, $user);

        $task = StaffTask::create($validated);

        return redirect()->route('task-management.index')->with('success', 'Task created successfully.');
    }

    public function edit(StaffTask $task_management)
    {
        $this->ensureTaskManager();

        $companyId = auth()->user()?->company_id;
        $staffMembers = Staff::query()
            ->where('company_id', $companyId)
            ->activeWithActiveSubscription()
            ->orderBy('name')
            ->get(['id', 'name']);
        $projects = Project::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'reference_no']);
        $statusOptions = StaffTask::statusOptions();
        $reminderWeekdayOptions = StaffTask::reminderWeekdayOptions();
        $repeatTypeOptions = StaffTask::repeatTypeOptions();
        $isCompanyAdmin = (bool) auth()->user()?->isCompanyAdmin();

        return view('staff-tasks.edit', [
            'task' => $task_management->load(['staff', 'project']),
            'staffMembers' => $staffMembers,
            'projects' => $projects,
            'statusOptions' => $statusOptions,
            'reminderWeekdayOptions' => $reminderWeekdayOptions,
            'repeatTypeOptions' => $repeatTypeOptions,
            'isCompanyAdmin' => $isCompanyAdmin,
        ]);
    }

    public function update(Request $request, StaffTask $task_management, TaskRepeatService $taskRepeatService)
    {
        $this->ensureTaskManager();

        $onlyStaffAssignmentUpdate = $request->has('staff_id')
            && count(array_diff(array_keys($request->all()), ['staff_id', '_token', '_method'])) === 0;

        if ($onlyStaffAssignmentUpdate) {
            $validated = $request->validate([
                'staff_id' => ['required', 'integer', Rule::exists('staff', 'id')->where(fn($query) => $query->where('company_id', auth()->user()?->company_id))],
            ]);

            $task_management->update([
                'staff_id' => (int) $validated['staff_id'],
            ]);

            $freshTask = $task_management->fresh(['staff']);

            return response()->json([
                'message' => 'Task staff updated successfully.',
                'data' => [
                    'id' => $freshTask?->id ?? $task_management->id,
                    'staff_id' => (int) $validated['staff_id'],
                    'staff' => $freshTask?->staff ? [
                        'id' => $freshTask->staff->id,
                        'name' => $freshTask->staff->name,
                    ] : null,
                ],
            ]);
        }

        $validated = $request->validate([
            'staff_id' => ['nullable', 'integer', Rule::exists('staff', 'id')->where(fn($query) => $query->where('company_id', auth()->user()?->company_id))],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where(fn($query) => $query->where('company_id', auth()->user()?->company_id))],
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

        $validated = $this->normalizeTaskPayload($validated, auth()->user());

        $task_management->update($validated);

        if (($validated['status'] ?? null) === 'done' && $task_management->is_repeated) {
            $taskRepeatService->createNextOccurrenceFromCompletedTask($task_management->fresh());
        }

        return redirect()->route('task-management.index')->with('success', 'Task updated successfully.');
    }

    public function destroy(StaffTask $task_management)
    {
        $this->ensureTaskManager();

        $task_management->delete();

        return redirect()->route('task-management.index')->with('success', 'Task deleted successfully.');
    }

    private function normalizeTaskPayload(array $validated, $user): array
    {
        $validated['staff_id'] = $this->resolvedAssigneeId($validated['staff_id'] ?? null, $user);
        $validated['has_reminder'] = (bool) ($validated['has_reminder'] ?? false);
        $validated['is_repeated'] = (bool) ($validated['is_repeated'] ?? false);
        $validated['reminder_date'] = $validated['has_reminder'] && filled($validated['reminder_date'] ?? null)
            ? $validated['reminder_date']
            : null;
        $validated['reminder_type'] = $validated['has_reminder'] && filled($validated['reminder_type'] ?? null)
            ? $validated['reminder_type']
            : null;
        $validated['repeat_type'] = $validated['is_repeated'] && filled($validated['repeat_type'] ?? null)
            ? $validated['repeat_type']
            : null;
        $validated['reminder_weekdays'] = [];

        return $validated;
    }

    private function defaultAssigneeId($user): int
    {
        $staffId = (int) ($user?->staff?->id ?? 0);

        abort_unless($staffId > 0, 403, 'Staff profile required for task assignment.');

        return $staffId;
    }

    private function resolvedAssigneeId(mixed $requestedStaffId, $user): int
    {
        $defaultStaffId = (int) ($user?->staff?->id ?? 0);

        if ($user?->isCompanyAdmin()) {
            $staffId = (int) ($requestedStaffId ?? 0);

            abort_unless($staffId > 0, 422, 'Please select an assignee.');

            $exists = Staff::query()
                ->where('company_id', $user->company_id)
                ->whereKey($staffId)
                ->exists();

            abort_unless($exists, 422, 'Selected assignee is not available for this company.');

            return $staffId;
        }

        abort_unless($defaultStaffId > 0, 403, 'Staff profile required for task assignment.');

        return $defaultStaffId;
    }

    public function updateReminderStatus(Request $request, StaffTask $task_management)
    {
        $this->ensureTaskManager();

        $validated = $request->validate([
            'reminder_date' => ['required', 'date'],
            'status' => ['required', 'string', Rule::in(['done', 'cancelled'])],
            'notes' => ['required', 'string', 'max:2000'],
        ]);

        $reminderDate = Carbon::parse($validated['reminder_date'])->startOfDay();
        $today = now()->startOfDay();
        $taskManagementDue = $task_management->due_date?->copy()->startOfDay();
        $reminderDates = $task_management->reminderDates();
        $nextEditableReminderDate = $task_management->nextEditableReminderDate();
        $existingUpdate = $task_management->reminderStatusForDate($reminderDate);

        if (! $taskManagementDue || $reminderDate->gt($taskManagementDue)) {
            throw ValidationException::withMessages([
                'reminder_date' => 'Reminder date cannot be after the due date.',
            ]);
        }

        if (! $reminderDates->contains(fn (Carbon $date) => $date->isSameDay($reminderDate))) {
            throw ValidationException::withMessages([
                'reminder_date' => 'This reminder is not part of the generated schedule.',
            ]);
        }

        if ($reminderDate->gt($today)) {
            throw ValidationException::withMessages([
                'reminder_date' => 'This reminder is locked until its scheduled date.',
            ]);
        }

        if ($existingUpdate?->isFinalizedReminder()) {
            throw ValidationException::withMessages([
                'reminder_date' => 'Completed reminders cannot be edited again.',
            ]);
        }

        if (! $nextEditableReminderDate || ! $reminderDate->isSameDay($nextEditableReminderDate)) {
            throw ValidationException::withMessages([
                'reminder_date' => 'You must complete the next pending reminder first.',
            ]);
        }

        $user = auth()->user();

        DB::transaction(function () use ($existingUpdate, $task_management, $reminderDate, $validated, $user): void {
            $payload = [
                'company_id' => (int) $task_management->company_id,
                'user_id' => (int) $user?->id,
                'status' => $validated['status'],
                'comment' => null,
                'notes' => trim((string) $validated['notes']),
                'status_date' => now()->toDateString(),
                'completed_at' => now(),
                'locked' => true,
            ];

            if ($existingUpdate) {
                $existingUpdate->update($payload);
            } else {
                StaffTaskStatusUpdate::create(
                    $payload + [
                        'staff_task_id' => $task_management->id,
                        'reminder_date' => $reminderDate->toDateString(),
                    ]
                );
            }
        });

        $task_management->load(['statusUpdates.user']);

        return back()->with('success', 'Reminder status updated successfully.');
    }

    public function updateBlocker(Request $request, StaffTask $task_management, DashboardNotificationService $dashboardNotificationService)
    {
        $this->ensureTaskViewer();

        $user = auth()->user();
        abort_unless($user && ((int) $task_management->company_id === (int) $user->company_id), 403);

        $validated = $request->validate([
            'blocker_level' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'blocker_description' => ['required', 'string', 'max:5000'],
        ]);

        $task_management->update([
            'has_blocker' => true,
            'blocker_level' => $validated['blocker_level'],
            'blocker_description' => trim((string) $validated['blocker_description']),
        ]);

        $task_management->refresh();
        $dashboardNotificationService->notifyTaskBlockerChanged($task_management->fresh(['staff.user', 'project.manager']));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task blocker updated successfully.',
                'blocker' => $this->blockerPayload($task_management),
            ]);
        }

        return back()->with('success', 'Task blocker updated successfully.');
    }

    public function clearBlocker(Request $request, StaffTask $task_management)
    {
        $this->ensureTaskViewer();

        $user = auth()->user();
        abort_unless($user && ((int) $task_management->company_id === (int) $user->company_id), 403);

        $task_management->update([
            'has_blocker' => false,
            'blocker_level' => null,
            'blocker_description' => null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task blocker resolved successfully.',
                'blocker' => $this->blockerPayload($task_management->fresh()),
            ]);
        }

        return back()->with('success', 'Task blocker resolved successfully.');
    }

    private function taskFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'status' => ['nullable', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
            'staff_id' => ['nullable', 'integer'],
            'project_id' => ['nullable', 'integer'],
            'due_from' => ['nullable', 'date'],
            'due_to' => ['nullable', 'date'],
            'has_blocker' => ['nullable', 'string', Rule::in(['1', '0'])],
            'due_range' => ['nullable', 'string', Rule::in(['today', 'week', 'month'])],
            'blocker_level' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
        ]);

        if (($validated['due_range'] ?? null) === 'today') {
            $validated['due_from'] = now()->toDateString();
            $validated['due_to'] = now()->toDateString();
        } elseif (($validated['due_range'] ?? null) === 'week') {
            $validated['due_from'] = now()->startOfWeek()->toDateString();
            $validated['due_to'] = now()->endOfWeek()->toDateString();
        } elseif (($validated['due_range'] ?? null) === 'month') {
            $validated['due_from'] = now()->startOfMonth()->toDateString();
            $validated['due_to'] = now()->endOfMonth()->toDateString();
        }

        if (filled($validated['blocker_level'] ?? null)) {
            $validated['has_blocker'] = '1';
        }

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : '',
            'status' => $validated['status'] ?? '',
            'staff_id' => $validated['staff_id'] ?? '',
            'project_id' => $validated['project_id'] ?? '',
            'due_from' => $validated['due_from'] ?? '',
            'due_to' => $validated['due_to'] ?? '',
            'has_blocker' => $validated['has_blocker'] ?? '',
            'due_range' => $validated['due_range'] ?? '',
            'blocker_level' => $validated['blocker_level'] ?? '',
        ];
    }

    private function applyTaskFilters($query, array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $status = $filters['status'] ?? '';
        $staffId = $filters['staff_id'] ?? '';
        $projectId = $filters['project_id'] ?? '';
        $dueFrom = $filters['due_from'] ?? '';
        $dueTo = $filters['due_to'] ?? '';
        $hasBlocker = $filters['has_blocker'] ?? '';
        $blockerLevel = $filters['blocker_level'] ?? '';

        return $query
            ->when($search !== '', function ($builder) use ($search) {
                $like = '%' . $search . '%';

                $builder->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('task_name', 'like', $like)
                        ->orWhere('task_description', 'like', $like)
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('name', 'like', $like))
                        ->orWhereHas('project', fn ($projectQuery) => $projectQuery->where('name', 'like', $like));
                });
            })
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->when(filled($staffId), fn ($builder) => $builder->where('staff_id', (int) $staffId))
            ->when(filled($projectId), fn ($builder) => $builder->where('project_id', (int) $projectId))
            ->when(filled($dueFrom), fn ($builder) => $builder->whereDate('due_date', '>=', $dueFrom))
            ->when(filled($dueTo), fn ($builder) => $builder->whereDate('due_date', '<=', $dueTo))
            ->when(filled($blockerLevel), fn ($builder) => $builder->where('has_blocker', true)->where('blocker_level', $blockerLevel))
            ->when($hasBlocker === '1', fn ($builder) => $builder->where('has_blocker', true))
            ->when($hasBlocker === '0', fn ($builder) => $builder->where(function ($q) {
                $q->whereNull('has_blocker')->orWhere('has_blocker', false);
            }));
    }

    private function blockerPayload(StaffTask $task): array
    {
        $level = $task->blocker_level ?: '';

        $levelMeta = match ($level) {
            'high' => ['label' => 'High', 'class' => 'bg-rose-100 text-rose-800'],
            'medium' => ['label' => 'Medium', 'class' => 'bg-amber-100 text-amber-800'],
            'low' => ['label' => 'Low', 'class' => 'bg-emerald-100 text-emerald-800'],
            default => ['label' => 'Low', 'class' => 'bg-emerald-100 text-emerald-800'],
        };

        return [
            'has_blocker' => (bool) $task->has_blocker,
            'level' => $level,
            'description' => (string) ($task->blocker_description ?? ''),
            'title' => $task->has_blocker ? 'Edit Blocker' : 'Add Blocker',
            'trigger_label' => $task->has_blocker ? 'Edit Blocker' : 'Add Blocker',
            'panel_state' => $task->has_blocker ? 'active' : 'empty',
            'status_label' => $task->has_blocker ? 'Active blocker on this task' : 'No blocker added yet.',
            'level_label' => $levelMeta['label'],
            'level_class' => $levelMeta['class'],
        ];
    }
}
