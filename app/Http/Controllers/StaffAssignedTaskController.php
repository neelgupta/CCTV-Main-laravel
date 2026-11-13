<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\StaffTask;
use App\Models\StaffTaskStatusUpdate;
use App\Services\TaskRepeatService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAssignedTaskController extends Controller
{
    private function staffContext(): array
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('tasks.view') || $user->hasPermission('tasks.manage') || $user->staff, 403);

        $staffId = $user->staff?->id;
        abort_unless($staffId, 403, 'Staff profile required.');

        return [$user, $staffId];
    }

    public function index()
    {
        [$user, $staffId] = $this->staffContext();

        $tasks = StaffTask::query()
            ->with(['project', 'staff'])
            ->where('company_id', $user->company_id)
            ->where('staff_id', $staffId)
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('staff-tasks.assigned', compact('tasks'));
    }

    public function create()
    {
        [$user] = $this->staffContext();
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('tasks.manage'), 403);

        $projects = Project::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'reference_no']);

        $statusOptions = StaffTask::statusOptions();

        return view('staff-tasks.create-assigned', compact('projects', 'statusOptions'));
    }

    public function store(Request $request)
    {
        [$user, $staffId] = $this->staffContext();
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('tasks.manage'), 403);

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id))],
            'task_name' => ['required', 'string', 'max:191'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'task_description' => ['nullable', 'string', 'max:5000'],
        ]);

        StaffTask::create([
            'company_id' => $user->company_id,
            'staff_id' => $staffId,
            'created_by_staff_id' => $staffId,
            'project_id' => $validated['project_id'] ?? null,
            'task_name' => $validated['task_name'],
            'status' => 'pending',
            'due_date' => $validated['due_date'] ?? null,
            'task_description' => $validated['task_description'] ?? null,
        ]);

        return redirect()->route('staff.tasks.index')->with('success', 'Task created successfully.');
    }

    public function edit(StaffTask $task)
    {
        [$user, $staffId] = $this->staffContext();
        abort_unless((int) $task->company_id === (int) $user->company_id, 403);
        abort_unless(
            $user->isCompanyAdmin()
            || $user->hasPermission('tasks.manage')
            || (int) ($task->created_by_staff_id ?? 0) === (int) $staffId,
            403
        );

        $projects = Project::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'reference_no']);

        return view('staff-tasks.edit-assigned', [
            'task' => $task->load(['project', 'staff']),
            'projects' => $projects,
        ]);
    }

    public function update(Request $request, StaffTask $task)
    {
        [$user, $staffId] = $this->staffContext();
        abort_unless((int) $task->company_id === (int) $user->company_id, 403);
        abort_unless(
            $user->isCompanyAdmin()
            || $user->hasPermission('tasks.manage')
            || (int) ($task->created_by_staff_id ?? 0) === (int) $staffId,
            403
        );

        $validated = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id))],
            'task_name' => ['required', 'string', 'max:191'],
            'due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'task_description' => ['nullable', 'string', 'max:5000'],
        ]);

        $task->update($validated);

        return redirect()->route('staff.tasks.index')->with('success', 'Task updated successfully.');
    }

    public function destroy(StaffTask $task)
    {
        [$user, $staffId] = $this->staffContext();
        abort_unless((int) $task->company_id === (int) $user->company_id, 403);
        abort_unless(
            $user->isCompanyAdmin()
            || $user->hasPermission('tasks.manage')
            || (int) ($task->created_by_staff_id ?? 0) === (int) $staffId,
            403
        );

        $task->delete();

        return redirect()->route('staff.tasks.index')->with('success', 'Task deleted successfully.');
    }

    public function updateStatus(Request $request, StaffTask $task, TaskRepeatService $taskRepeatService)
    {
        $user = auth()->user();

        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('tasks.view') || $user->hasPermission('tasks.manage') || $user->staff, 403);

        $staffId = $user->staff?->id;

        abort_unless($staffId, 403, 'Staff profile required.');
        abort_unless((int) $task->staff_id === (int) $staffId && (int) $task->company_id === (int) $user->company_id, 403);
        abort_unless(! in_array($task->status, ['done', 'cancelled'], true), 422, 'Completed tasks cannot be updated again.');

        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
            'notes' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $notes = trim((string) ($validated['notes'] ?? $validated['comment'] ?? ''));

        if ($notes === '') {
            abort(422, 'Notes are required.');
        }

        $task->update([
            'status' => $validated['status'],
        ]);

        if ($task->status === 'done' && $task->is_repeated) {
            $taskRepeatService->createNextOccurrenceFromCompletedTask($task->fresh());
        }

        StaffTaskStatusUpdate::create([
            'company_id' => (int) $user->company_id,
            'staff_task_id' => $task->id,
            'user_id' => $user->id,
            'status' => $validated['status'],
            'comment' => $notes,
            'notes' => $notes,
            'status_date' => now()->toDateString(),
            'completed_at' => in_array($validated['status'], ['done', 'cancelled'], true) ? now() : null,
            'locked' => in_array($validated['status'], ['done', 'cancelled'], true),
        ]);

        return back()->with('success', 'Task status updated successfully.');
    }
}
