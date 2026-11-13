<?php

namespace App\Services;

use App\Models\DashboardNotification;
use App\Models\StaffTask;
use App\Models\TaskReminderLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TaskReminderService
{
    public function processPendingReminders(?Carbon $date = null): int
    {
        $now = $date ?? now();
        $today = $now->copy()->startOfDay();
        $notificationCutoff = $today->copy()->setTime(18, 0, 0);
        $targetDate = $today->copy()->addDay();

        if ($now->lt($notificationCutoff)) {
            return 0;
        }

        $count = 0;

        StaffTask::query()
            ->with(['staff.user', 'project'])
            ->where('has_reminder', true)
            ->whereIn('status', ['pending', 'in_progress', 'reschedule'])
            ->whereDate('due_date', '=', $targetDate->toDateString())
            ->chunkById(100, function (Collection $tasks) use ($today, &$count): void {
                foreach ($tasks as $task) {
                    if ($this->shouldSkipReminder($task, $today)) {
                        continue;
                    }

                    if ($this->sendReminder($task, $today)) {
                        $count++;
                    }
                }
            });

        return $count;
    }

    private function shouldSkipReminder(StaffTask $task, Carbon $date): bool
    {
        return ! $task->due_date
            || $task->due_date->copy()->startOfDay()->lt($date)
            || in_array($task->status, ['done', 'cancelled'], true);
    }

    private function sendReminder(StaffTask $task, Carbon $date): bool
    {
        $user = $task->staff?->user;
        if (! $user instanceof User || ! $user->company_id) {
            return false;
        }

        $reminderType = (string) ($task->reminder_type ?: 'daily');
        $sentForDate = $date->copy()->startOfDay();

        if (! $this->isReminderDue($task, $date, $reminderType)) {
            return false;
        }

        $key = sprintf('task-reminder-%d-%s-%s', $task->id, $reminderType, $sentForDate->toDateString());

        return (bool) DB::transaction(function () use ($task, $user, $date, $reminderType, $sentForDate, $key): bool {
            $alreadyProcessed = TaskReminderLog::query()
                ->where('staff_task_id', $task->id)
                ->where('reminder_type', $reminderType)
                ->whereDate('sent_for_date', $sentForDate->toDateString())
                ->exists();

            if ($alreadyProcessed) {
                return false;
            }

            DashboardNotification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_key' => $key,
                ],
                [
                    'company_id' => (int) $user->company_id,
                    'category' => 'reminder',
                    'source_type' => 'staff_task',
                    'source_id' => (string) $task->id,
                    'title' => 'Task Reminder: ' . $task->task_name,
                    'message' => $this->buildMessage($task, $reminderType),
                    'url' => route('task-management.index'),
                    'notification_date' => $date->toDateString(),
                    'status' => 'unread',
                    'read_at' => null,
                    'archived_at' => null,
                    'payload' => [
                        'task_id' => $task->id,
                        'task_name' => $task->task_name,
                        'due_date' => $task->due_date?->toDateString(),
                        'reminder_type' => $reminderType,
                        'project_name' => $task->project?->name,
                    ],
                ]
            );

            TaskReminderLog::create([
                'company_id' => (int) $task->company_id,
                'staff_task_id' => $task->id,
                'reminder_type' => $reminderType,
                'sent_for_date' => $sentForDate->toDateString(),
                'sent_at' => now(),
                'payload' => [
                    'notification_key' => $key,
                    'task_name' => $task->task_name,
                ],
            ]);

            $task->forceFill([
                'last_reminder_sent_at' => now(),
            ])->save();

            return true;
        });
    }

    private function isReminderDue(StaffTask $task, Carbon $date, string $reminderType): bool
    {
        $dueDate = $task->due_date?->copy()->startOfDay();
        if (! $dueDate || $date->gt($dueDate->copy()->subDay()) || in_array($task->status, ['done', 'cancelled'], true)) {
            return false;
        }

        return match ($reminderType) {
            'day_before_due_date' => $date->isSameDay($dueDate->copy()->subDay()),
            'weekly' => ! $task->last_reminder_sent_at || $task->last_reminder_sent_at->copy()->startOfDay()->diffInDays($date) >= 7,
            default => ! $task->last_reminder_sent_at || ! $task->last_reminder_sent_at->copy()->startOfDay()->isSameDay($date),
        };
    }

    private function buildMessage(StaffTask $task, string $reminderType): string
    {
        $dueDate = $task->due_date?->format('d M, Y') ?? 'soon';

        return match ($reminderType) {
            'weekly' => 'Reminder for tomorrow: due on ' . $dueDate,
            'day_before_due_date' => 'Reminder for tomorrow: due on ' . $dueDate,
            default => 'Reminder for tomorrow: due on ' . $dueDate,
        };
    }
}
