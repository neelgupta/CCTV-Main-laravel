<?php

namespace App\Services;

use App\Models\StaffTask;
use App\Models\TaskRepeatLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TaskRepeatService
{
    public function createNextOccurrenceFromCompletedTask(StaffTask $task): ?StaffTask
    {
        if (! $task->is_repeated || ! $task->due_date) {
            return null;
        }

        if (in_array($task->status, ['cancelled'], true)) {
            return null;
        }

        $repeatType = (string) ($task->repeat_type ?: 'daily');
        $nextDueDate = $this->nextDueDate($task->due_date->copy()->startOfDay(), $repeatType);
        $baseDueDate = $task->repeat_anchor_due_date?->copy()->startOfDay() ?? $task->due_date->copy()->startOfDay();

        if ($this->hasRepeatLog($task->id, $repeatType, $task->due_date)) {
            return null;
        }

        return DB::transaction(function () use ($task, $repeatType, $nextDueDate, $baseDueDate): ?StaffTask {
            $repeatedTask = $task->replicate([
                'last_reminder_sent_at',
                'last_repeat_processed_at',
                'repeat_anchor_due_date',
            ]);

            $repeatedTask->fill([
                'status' => 'pending',
                'due_date' => $nextDueDate->toDateString(),
                'is_repeated' => true,
                'repeat_type' => $task->repeat_type,
                'repeat_anchor_due_date' => $baseDueDate->toDateString(),
                'last_repeat_processed_at' => now(),
                'last_reminder_sent_at' => null,
            ]);

            $repeatedTask->save();

            TaskRepeatLog::create([
                'company_id' => (int) $task->company_id,
                'source_task_id' => $task->id,
                'repeated_task_id' => $repeatedTask->id,
                'repeat_type' => $repeatType,
                'base_due_date' => $baseDueDate->toDateString(),
                'processed_for_date' => $task->due_date->toDateString(),
                'processed_at' => now(),
                'payload' => [
                    'source_task_name' => $task->task_name,
                    'repeated_task_name' => $repeatedTask->task_name,
                    'next_due_date' => $nextDueDate->toDateString(),
                ],
            ]);

            $task->forceFill([
                'last_repeat_processed_at' => now(),
                'repeat_anchor_due_date' => $baseDueDate->toDateString(),
            ])->save();

            return $repeatedTask;
        });
    }

    private function hasRepeatLog(int $taskId, string $repeatType, Carbon $processedForDate): bool
    {
        return TaskRepeatLog::query()
            ->where('source_task_id', $taskId)
            ->where('repeat_type', $repeatType)
            ->whereDate('processed_for_date', $processedForDate->toDateString())
            ->exists();
    }

    private function nextDueDate(Carbon $date, string $repeatType): Carbon
    {
        return match ($repeatType) {
            'weekly' => $date->copy()->addWeek(),
            'monthly' => $date->copy()->addMonthNoOverflow(),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addDay(),
        };
    }
}
