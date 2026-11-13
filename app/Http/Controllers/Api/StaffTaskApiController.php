<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffTask;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class StaffTaskApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403, 'Unauthenticated.');

        $taskId = $request->integer('task_id');
        $isCompanyAdmin = (bool) $user->isCompanyAdmin();

        $baseQuery = StaffTask::query()
            ->where('company_id', $user->company_id)
            ->with(['staff:id,name', 'project:id,name,reference_no']);

        if ($taskId > 0) {
            $task = (clone $baseQuery)
                ->when(! $isCompanyAdmin, fn ($query) => $query->where('staff_id', $user->staff?->id))
                ->findOrFail($taskId);

            abort_unless($isCompanyAdmin || $user->staff, 403, 'No linked staff profile found.');
            abort_unless($isCompanyAdmin || (int) $task->staff_id === (int) $user->staff?->id, 403);

            return response()->json([
                'message' => 'Task details retrieved successfully.',
                'data' => $this->formatTask($task),
            ]);
        }

        if ($isCompanyAdmin) {
            $tasks = $baseQuery->latest()->get();
            $message = 'Task records retrieved successfully.';
        } else {
            abort_unless($user->staff, 403, 'No linked staff profile found.');

            $tasks = $baseQuery
                ->where('staff_id', $user->staff->id)
                ->latest()
                ->get();
            $message = 'Assigned tasks retrieved successfully.';
        }

        return response()->json([
            'message' => $message,
            'data' => collect($tasks)->map(fn (StaffTask $task) => $this->formatTask($task))->values(),
        ]);
    }

    public function updateStatus(Request $request, StaffTask $task): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless((int) $task->company_id === (int) $user->company_id && (int) $task->staff_id === (int) $user->staff->id, 403);

        $validated = $this->validateJson($request, [
            'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
        ]);

        $task->update([
            'status' => $validated['status'],
        ]);

        $task->load(['project:id,name,reference_no']);

        return response()->json([
            'message' => 'Task status updated successfully.',
            'data' => $this->formatTask($task),
        ]);
    }

    private function formatTask(StaffTask $task): array
    {
        return [
            'id' => $task->id,
            'task_name' => $task->task_name,
            'status' => $task->status,
            'status_label' => StaffTask::statusOptions()[$task->status] ?? ucfirst(str_replace('_', ' ', (string) $task->status)),
            'due_date' => optional($task->due_date)->toDateString(),
            'has_reminder' => (bool) $task->has_reminder,
            'reminder_date' => optional($task->reminder_date)->toDateString(),
            'is_repeated' => (bool) $task->is_repeated,
            'repeat_type' => $task->repeat_type,
            'task_description' => $task->task_description,
            'project' => $task->project ? [
                'id' => $task->project->id,
                'name' => $task->project->name,
                'reference_no' => $task->project->reference_no,
            ] : null,
        ];
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
