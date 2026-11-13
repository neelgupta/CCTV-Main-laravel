<?php

namespace App\Services;

use App\Models\StaffTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TaskStatusService
{
    /**
     * Update the status-only fields for a task.
     *
     * @param  array{status: string, message?: ?string}  $data
     */
    public function updateTaskStatus(StaffTask $task, User $user, array $data): StaffTask
    {
        return DB::transaction(function () use ($task, $user, $data): StaffTask {
            $task->forceFill([
                'status' => $data['status'],
                'message' => $data['message'] ?? null,
                'updated_by' => $user->id,
            ])->save();

            $task->refresh();

            if ($task->status === 'done' && $task->is_repeated) {
                app(TaskRepeatService::class)->createNextOccurrenceFromCompletedTask($task);
            }

            return $task->refresh();
        });
    }
}
