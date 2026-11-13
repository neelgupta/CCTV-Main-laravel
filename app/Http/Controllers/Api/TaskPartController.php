<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTaskPartsRequest;
use App\Http\Requests\Api\UpdateTaskPartRequest;
use App\Models\TaskPart;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class TaskPartController extends Controller
{
    public function index(int $task_id): JsonResponse
    {
        $task = $this->resolveTask($task_id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $parts = TaskPart::query()
            ->where('task_id', $task->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (TaskPart $taskPart) => $this->formatTaskPart($taskPart))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'message' => 'Parts fetched successfully.',
            'data' => $parts,
        ]);
    }

    public function store(StoreTaskPartsRequest $request, int $task_id): JsonResponse
    {
        $task = $this->resolveTaskForRequest($request, $task_id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $validated = $request->validated();
        $savedParts = [];

        try {
            DB::transaction(function () use ($validated, $task, &$savedParts): void {
                foreach ($validated['parts'] as $part) {
                    $taskPart = TaskPart::create([
                        'task_id' => $task->id,
                        'part_name' => trim((string) $part['part_name']),
                        'qty' => $part['qty'],
                        'gst' => $part['gst'],
                        'price' => $part['price'],
                    ]);

                    $savedParts[] = $this->formatTaskPart($taskPart);
                }
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to save parts.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Parts saved successfully.',
            'data' => $savedParts,
        ], 201);
    }

    public function update(UpdateTaskPartRequest $request, int $task_id, int $part_id): JsonResponse
    {
        $task = $this->resolveTaskForRequest($request, $task_id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $part = TaskPart::query()
            ->whereKey($part_id)
            ->where('task_id', $task->id)
            ->first();

        if (! $part) {
            return response()->json([
                'success' => false,
                'message' => 'Part not found.',
            ], 404);
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($part, $validated): void {
                $part->update([
                    'part_name' => trim((string) $validated['part_name']),
                    'qty' => $validated['qty'],
                    'gst' => $validated['gst'],
                    'price' => $validated['price'],
                ]);
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update part.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Part updated successfully.',
            'data' => $this->formatTaskPart($part->fresh()),
        ]);
    }

    public function destroy(int $task_id, int $part_id): JsonResponse
    {
        $task = $this->resolveTask($task_id);

        if (! $task) {
            return response()->json([
                'success' => false,
                'message' => 'Task not found.',
            ], 404);
        }

        $part = TaskPart::query()
            ->whereKey($part_id)
            ->where('task_id', $task->id)
            ->first();

        if (! $part) {
            return response()->json([
                'success' => false,
                'message' => 'Part not found.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($part): void {
                $part->delete();
            });
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete part.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Part deleted successfully.',
        ]);
    }

    private function resolveTaskForRequest(StoreTaskPartsRequest|UpdateTaskPartRequest $request, int $task_id): ?Ticket
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Unauthenticated.');
        }

        return $this->resolveTask($task_id, (int) $user->company_id);
    }

    private function resolveTask(int $task_id, ?int $companyId = null): ?Ticket
    {
        return Ticket::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->whereKey($task_id)
            ->first();
    }

    private function formatTaskPart(TaskPart $taskPart): array
    {
        return [
            'id' => $taskPart->id,
            'task_id' => $taskPart->task_id,
            'part_name' => $taskPart->part_name,
            'qty' => (float) $taskPart->qty,
            'gst' => (float) $taskPart->gst,
            'price' => (float) $taskPart->price,
        ];
    }
}
