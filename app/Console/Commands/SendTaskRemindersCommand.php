<?php

namespace App\Console\Commands;

use App\Services\TaskReminderService;
use Illuminate\Console\Command;

class SendTaskRemindersCommand extends Command
{
    protected $signature = 'tasks:send-reminders';

    protected $description = 'Create dashboard reminders for pending tasks until the due date.';

    public function handle(TaskReminderService $service): int
    {
        $sent = $service->processPendingReminders();
        $this->info('Created ' . $sent . ' reminder notification(s).');

        return self::SUCCESS;
    }
}
