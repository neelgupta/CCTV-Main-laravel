<x-app-layout>
    <x-slot name="header">
        @php
            $authUser = auth()->user();
            $canManageTasks = $authUser?->isSuperAdmin() || $authUser?->isCompanyAdmin() || $authUser?->hasPermission('tasks.manage');
            $hasBlocker = (bool) $task->has_blocker;
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Task Details</h2>
                    <p class="mt-1 text-sm text-slate-500">Review the task, assigned staff, due date, and reminder schedule.</p>
                </div>
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        id="task-blocker-header-trigger"
                        class="inline-flex items-center justify-center rounded-xl border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-800 hover:bg-amber-100"
                        data-blocker-trigger
                        data-blocker-mode="{{ $hasBlocker ? 'edit' : 'add' }}"
                        data-blocker-title="{{ $hasBlocker ? 'Edit Blocker' : 'Add Blocker' }}"
                        data-blocker-level="{{ old('blocker_level', $task->blocker_level ?? '') }}"
                        data-blocker-description="{{ old('blocker_description', $task->blocker_description ?? '') }}"
                    >
                        {{ $hasBlocker ? 'Edit Blocker' : 'Add Blocker' }}
                    </button>
                    @if($canManageTasks)
                        <a href="{{ route('task-management.edit', $task) }}" class="inline-flex items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-700 hover:bg-cyan-100">
                            Edit Task
                        </a>
                    @endif
                    <a href="{{ route('task-management.index') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Back
                    </a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <div class="grid gap-6 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Task Name</p>
                            <h3 class="mt-2 text-2xl font-extrabold text-slate-950">{{ $task->task_name }}</h3>
                            <p class="mt-3 text-sm leading-6 text-slate-600 whitespace-pre-line">
                                {{ $task->task_description ?: 'No description added.' }}
                            </p>
                        </div>

                        @php
                            $statusMeta = match($task->status) {
                                'done' => ['label' => 'Done', 'class' => 'bg-emerald-100 text-emerald-800'],
                                'in_progress' => ['label' => 'In Progress', 'class' => 'bg-amber-100 text-amber-800'],
                                'reschedule' => ['label' => 'Reschedule', 'class' => 'bg-cyan-100 text-cyan-800'],
                                'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-rose-100 text-rose-800'],
                                default => ['label' => 'Pending', 'class' => 'bg-slate-100 text-slate-700'],
                            };
                        @endphp
                        <span class="inline-flex rounded-full px-3 py-1.5 text-xs font-semibold {{ $statusMeta['class'] }}">
                            {{ $statusMeta['label'] }}
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Task Info</h3>
                    <dl class="mt-5 space-y-4">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Assigned Staff</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $task->staff?->name ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Project</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                @if($task->project)
                                    <a href="{{ route('projects.show', $task->project) }}" class="font-semibold text-cyan-700 hover:text-cyan-900">
                                        {{ $task->project->name }}
                                    </a>
                                    @if($task->project->reference_no)
                                        <div class="text-xs text-slate-400">{{ $task->project->reference_no }}</div>
                                    @endif
                                @else
                                    -
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Due Date</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $task->due_date?->format('d M, Y') ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Reminder</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                {{ $task->has_reminder ? 'Enabled' : 'Disabled' }}
                                @if($task->reminder_date)
                                    <div class="text-xs text-slate-400">On {{ $task->reminder_date->format('d M, Y') }}</div>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Repeat</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">
                                {{ $task->is_repeated ? (\App\Models\StaffTask::repeatTypeOptions()[$task->repeat_type] ?? ucfirst((string) $task->repeat_type)) : 'No' }}
                            </dd>
                        </div>
                        <div id="task-blocker-panel" data-task-blocker-panel data-task-blocker-state="{{ $task->has_blocker ? 'active' : 'empty' }}">
                        @if($task->has_blocker)
                            @php
                                $blockerMeta = match ($task->blocker_level) {
                                    'high' => ['label' => 'High', 'class' => 'bg-rose-100 text-rose-800'],
                                    'medium' => ['label' => 'Medium', 'class' => 'bg-amber-100 text-amber-800'],
                                    default => ['label' => 'Low', 'class' => 'bg-emerald-100 text-emerald-800'],
                                };
                            @endphp
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900">Active blocker on this task</dd>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $blockerMeta['class'] }}">
                                        {{ $blockerMeta['label'] }}
                                    </span>
                                </div>
                                <div class="mt-4">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker Description</dt>
                                    <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $task->blocker_description }}</dd>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="edit"
                                        data-blocker-title="Edit Blocker"
                                        data-blocker-level="{{ old('blocker_level', $task->blocker_level ?? '') }}"
                                        data-blocker-description="{{ old('blocker_description', $task->blocker_description ?? '') }}"
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="{{ route('task-management.blocker.clear', $task) }}" data-task-blocker-resolve-form>
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                            Resolve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @else
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-600">No blocker added yet.</dd>
                                    </div>
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="add"
                                        data-blocker-title="Add Blocker"
                                        data-blocker-level=""
                                        data-blocker-description=""
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Add Blocker
                                    </button>
                                </div>
                            </div>
                        @endif
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Created</dt>
                            <dd class="mt-1 text-sm font-medium text-slate-900">{{ $task->created_at?->format('d M, Y h:i A') ?? '-' }}</dd>
                        </div>
                    </dl>
                </div>

                <!-- <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-3">
                    @php
                        $reminderStatusLabels = [
                            'pending' => 'Pending',
                            'done' => 'Done',
                            'cancelled' => 'Blocker',
                        ];
                    @endphp
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Date-wise Reminder Schedule</h3>
                        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                            {{ $task->reminderDates()->count() }} reminder date(s)
                        </span>
                    </div>

                    <div class="mt-4 overflow-hidden rounded-xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Day</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($task->reminderDates() as $reminderDate)
                                    @php
                                        $reminderState = $task->reminderScheduleStateForDate($reminderDate);
                                        $rowStatus = old('reminder_date') === $reminderDate->toDateString()
                                            ? old('status', $reminderState['status'] ?? 'pending')
                                            : ($reminderState['status'] ?? 'pending');
                                        $rowStatusLabel = $reminderStatusLabels[$rowStatus] ?? 'Pending';
                                        $rowStatusClass = match ($reminderState['state']) {
                                            'completed' => ($rowStatus === 'cancelled')
                                                ? 'bg-rose-100 text-rose-800'
                                                : 'bg-emerald-100 text-emerald-800',
                                            'active' => 'bg-amber-100 text-amber-800',
                                            default => 'bg-slate-100 text-slate-700',
                                        };
                                        $rowNotes = $reminderState['notes'] ?? null;
                                    @endphp
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $reminderDate->format('d M, Y') }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $reminderDate->format('l') }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $rowStatusClass }}">
                                                {{ $reminderState['label'] }}
                                            </span>
                                            @if($reminderState['state'] === 'completed' && $rowNotes)
                                                <div class="mt-2 text-xs leading-5 text-slate-500 whitespace-pre-line">{{ $rowNotes }}</div>
                                                <div class="mt-2 text-[11px] leading-5 text-slate-400">
                                                    <div>
                                                        Completed by {{ $reminderState['completed_by'] ?? '-' }}
                                                    </div>
                                                    <div>
                                                        Completed at {{ $reminderState['completed_at']?->format('d M, Y h:i A') ?? '-' }}
                                                    </div>
                                                </div>
                                            @elseif($reminderState['state'] === 'locked')
                                                <div class="mt-2 text-xs text-slate-400">Locked until the previous reminder is completed.</div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">
                                            <form
                                                method="POST"
                                                action="{{ route('task-management.reminder-status.update', $task) }}"
                                                class="js-reminder-row-form flex flex-wrap items-center gap-2"
                                                data-reminder-state="{{ $reminderState['state'] }}"
                                                data-reminder-date-label="{{ $reminderDate->format('d M, Y') }}"
                                                data-task-name="{{ $task->task_name }}"
                                                data-current-status="{{ $rowStatus }}"
                                                data-current-notes="{{ old('reminder_date') === $reminderDate->toDateString() ? old('notes', '') : ($rowNotes ?? '') }}"
                                            >
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="reminder_date" value="{{ $reminderDate->toDateString() }}">
                                                @if($reminderState['editable'] && $canManageTasks)
                                                    <select
                                                        name="status"
                                                        class="js-reminder-inline-status rounded-xl border-slate-300 text-sm"
                                                        data-task-name="{{ $task->task_name }}"
                                                        data-reminder-date-label="{{ $reminderDate->format('d M, Y') }}"
                                                    >
                                                        @foreach($reminderStatusLabels as $statusKey => $statusLabel)
                                                            <option value="{{ $statusKey }}" @selected($rowStatus === $statusKey)>{{ $statusLabel }}</option>
                                                        @endforeach
                                                    </select>
                                                    <button
                                                        type="button"
                                                        onclick="(function(button){const rowForm=button.closest('form');const rowSelect=rowForm?.querySelector('.js-reminder-inline-status');const modal=document.getElementById('reminder-status-modal');const modalForm=document.getElementById('reminder-status-form');const title=document.getElementById('reminder-status-modal-title');const dateLabel=document.getElementById('reminder-status-modal-date');const dateInput=document.getElementById('reminder-date-input');const statusInput=document.getElementById('reminder-status-input');const notesInput=document.getElementById('reminder-notes-input');const notesError=document.getElementById('reminder-notes-error');if(!rowForm||!rowSelect||!modal||!modalForm)return false;if(title) title.textContent=rowForm.dataset.taskName||'Task';if(dateLabel) dateLabel.textContent=rowForm.dataset.reminderDateLabel||'';if(dateInput) dateInput.value=rowForm.querySelector('input[name=&quot;reminder_date&quot;]')?.value||'';if(statusInput) statusInput.value=rowSelect.value||'pending';if(notesInput) notesInput.value=rowForm.dataset.currentNotes||'';if(notesError) notesError.classList.add('hidden');modalForm.action=rowForm.action||'';modal.classList.remove('hidden');modal.classList.add('flex');modal.setAttribute('aria-hidden','false');document.body.classList.add('overflow-hidden');if(notesInput) notesInput.focus();return false;})(this); return false;"
                                                        class="js-open-reminder-modal inline-flex items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                                                    >
                                                        Save
                                                    </button>
                                                @elseif($reminderState['editable'])
                                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                                        Read only
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center rounded-xl bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-500">
                                                        Read only
                                                    </span>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">
                                            No reminder dates generated. Select weekdays and a due date to build the schedule.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div> -->

                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-3">
                    <h3 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Status Comments</h3>
                    <div class="mt-4 space-y-3">
                        @forelse($task->statusUpdates as $update)
                            <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div class="text-sm font-semibold text-slate-900">
                                        {{ $update->reminder_date ? $update->reminderStatusLabel() : (\App\Models\StaffTask::statusOptions()[$update->status] ?? ucfirst($update->status)) }}
                                        @if($update->reminder_date)
                                            <span class="ml-2 rounded-full bg-cyan-100 px-2 py-1 text-[11px] font-semibold text-cyan-700">
                                                {{ $update->reminder_date->format('d M, Y') }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        @if($update->reminder_date)
                                            {{ ($update->completed_at ?? $update->created_at)?->format('d M, Y h:i A') ?? $update->status_date?->format('d M, Y') }}
                                        @else
                                            {{ $update->created_at?->format('d M, Y h:i A') ?? $update->status_date?->format('d M, Y') }}
                                        @endif
                                        @if($update->user?->name)
                                            by {{ $update->user->name }}
                                        @endif
                                    </div>
                                </div>
                                <p class="mt-2 text-sm leading-6 text-slate-600 whitespace-pre-line">
                                    {{ $update->reminder_date ? ($update->reminderNotes() ?? 'No notes added.') : ($update->comment ?? 'No comment added.') }}
                                </p>
                                @if($update->reminder_date)
                                    <div class="mt-3 text-xs text-slate-500">
                                        <div>Completed by {{ $update->user?->name ?? '-' }}</div>
                                        <div>Completed at {{ ($update->completed_at ?? $update->created_at)?->format('d M, Y h:i A') ?? '-' }}</div>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No status comments added yet.</p>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>
    </div>

    <div id="reminder-status-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
        <div class="absolute inset-0" data-close-reminder-status-modal></div>
        <div class="relative z-10 w-full max-w-lg rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Reminder</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="reminder-status-modal-title">Update Reminder</h3>
                    <p class="mt-1 text-sm text-slate-500" id="reminder-status-modal-date"></p>
                </div>
                <button type="button" data-close-reminder-status-modal class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close reminder status modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <form id="reminder-status-form" method="POST" action="" class="space-y-4 px-5 py-5">
                @csrf
                @method('PATCH')
                <input type="hidden" name="reminder_date" id="reminder-date-input">
                <input type="hidden" name="status" id="reminder-status-input">
                <div>
                    <label for="reminder-notes-input" class="block text-sm font-medium text-slate-700">Notes</label>
                    <textarea name="notes" id="reminder-notes-input" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 text-sm" placeholder="Add completion notes for this reminder." required>{{ old('notes') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Notes are required before saving the reminder update.</p>
                    <p id="reminder-notes-error" class="mt-1 hidden text-xs text-rose-600">Notes is mandatory.</p>
                    @error('notes')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" data-close-reminder-status-modal class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save</button>
                </div>
            </form>
        </div>
    </div>

    <div id="blocker-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
        <div class="absolute inset-0" data-close-blocker-modal></div>
        <div class="relative z-10 w-full max-w-xl rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-700">Blocker</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="blocker-modal-title">Add Blocker</h3>
                    <p class="mt-1 text-sm text-slate-500">Capture the blocker type and description for this task.</p>
                </div>
                <button type="button" data-close-blocker-modal class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close blocker modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" /></svg>
                </button>
            </div>
            <form
                id="blocker-form"
                method="POST"
                action="{{ route('task-management.blocker.update', $task) }}"
                class="space-y-4 px-5 py-5"
                novalidate
                data-open-on-load="{{ ($errors->has('blocker_level') || $errors->has('blocker_description')) ? 'true' : 'false' }}"
                data-task-blocker-clear-url="{{ route('task-management.blocker.clear', $task) }}"
            >
                @csrf
                @method('PATCH')
                <div>
                    <label for="blocker_level" class="block text-sm font-medium text-slate-700">Blocker Type</label>
                    <select id="blocker_level" name="blocker_level" class="mt-1 block w-full rounded-xl border-slate-300 text-sm" required aria-describedby="blocker_level_error">
                        <option value="">Select blocker type</option>
                        <option value="low" @selected(old('blocker_level', $task->blocker_level) === 'low')>Low</option>
                        <option value="medium" @selected(old('blocker_level', $task->blocker_level) === 'medium')>Medium</option>
                        <option value="high" @selected(old('blocker_level', $task->blocker_level) === 'high')>High</option>
                    </select>
                    @error('blocker_level')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    <p id="blocker_level_error" class="mt-1 hidden text-xs text-rose-600"></p>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label for="blocker_description" class="block text-sm font-medium text-slate-700">Blocker Description</label>
                        <x-speech-to-text target="blocker_description" />
                    </div>
                    <textarea id="blocker_description" name="blocker_description" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 text-sm" required aria-describedby="blocker_description_error">{{ old('blocker_description', $task->blocker_description) }}</textarea>
                    @error('blocker_description')
                        <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                    @enderror
                    <p id="blocker_description_error" class="mt-1 hidden text-xs text-rose-600"></p>
                </div>

                <p id="blocker_form_error" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm text-rose-700"></p>

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" data-close-blocker-modal class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" data-blocker-save-button class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save</button>
                </div>
            </form>
        </div>
    </div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.__taskBlockerModalSetup) {
                window.__taskBlockerModalSetup = true;
            const blockerModal = document.getElementById('blocker-modal');
            const blockerForm = document.getElementById('blocker-form');
            const blockerTitle = document.getElementById('blocker-modal-title');
            const blockerLevel = document.getElementById('blocker_level');
            const blockerDescription = document.getElementById('blocker_description');
            const blockerLevelError = document.getElementById('blocker_level_error');
            const blockerDescriptionError = document.getElementById('blocker_description_error');
            const blockerFormError = document.getElementById('blocker_form_error');
            const blockerSaveButton = document.querySelector('[data-blocker-save-button]');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const blockerUpdateUrl = @js(route('task-management.blocker.update', $task));
            const blockerClearUrl = @js(route('task-management.blocker.clear', $task));
            const shouldOpenBlockerModal = {{ ($errors->has('blocker_level') || $errors->has('blocker_description')) ? 'true' : 'false' }};
            const defaultBlockerData = {
                title: @js($hasBlocker ? 'Edit Blocker' : 'Add Blocker'),
                level: @js(old('blocker_level', $task->blocker_level ?? '')),
                description: @js(old('blocker_description', $task->blocker_description ?? '')),
            };
            let blockerSubmissionInProgress = false;

            function escapeHtml(value) {
                return String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');
            }

            function clearBlockerErrors() {
                if (blockerLevelError) {
                    blockerLevelError.textContent = '';
                    blockerLevelError.classList.add('hidden');
                }

                if (blockerDescriptionError) {
                    blockerDescriptionError.textContent = '';
                    blockerDescriptionError.classList.add('hidden');
                }

                if (blockerFormError) {
                    blockerFormError.textContent = '';
                    blockerFormError.classList.add('hidden');
                }
            }

            function setFieldError(field, errorEl, message) {
                if (!field || !errorEl) return;

                if (message) {
                    errorEl.textContent = message;
                    errorEl.classList.remove('hidden');
                    field.setAttribute('aria-invalid', 'true');
                    field.classList.add('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-100');
                } else {
                    errorEl.textContent = '';
                    errorEl.classList.add('hidden');
                    field.removeAttribute('aria-invalid');
                    field.classList.remove('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-100');
                }
            }

            function setSubmittingState(isSubmitting) {
                blockerSubmissionInProgress = isSubmitting;

                if (blockerSaveButton) {
                    blockerSaveButton.disabled = isSubmitting;
                    blockerSaveButton.classList.toggle('opacity-70', isSubmitting);
                    blockerSaveButton.classList.toggle('cursor-wait', isSubmitting);
                }
            }

            async function confirmBlockerResolve() {
                if (typeof window.appConfirm === 'function') {
                    return window.appConfirm('Resolve and remove this blocker?', {
                        title: 'Resolve Blocker',
                        icon: 'warning',
                        confirmText: 'Yes, resolve it',
                        cancelText: 'Cancel',
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#64748b',
                    });
                }

                return Promise.resolve(window.confirm('Resolve and remove this blocker?'));
            }

            function updateBlockerTriggerButtons(blocker) {
                const headerButton = document.getElementById('task-blocker-header-trigger');
                if (!headerButton) return;

                const label = blocker.has_blocker ? 'Edit Blocker' : 'Add Blocker';
                headerButton.textContent = label;
                headerButton.dataset.blockerMode = blocker.has_blocker ? 'edit' : 'add';
                headerButton.dataset.blockerTitle = blocker.title || label;
                headerButton.dataset.blockerLevel = blocker.level || '';
                headerButton.dataset.blockerDescription = blocker.description || '';
            }

            function renderBlockerPanel(blocker) {
                const panel = document.getElementById('task-blocker-panel');
                if (!panel) return;

                const html = blocker.has_blocker
                    ? `
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900">Active blocker on this task</dd>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ${escapeHtml(blocker.level_class || 'bg-emerald-100 text-emerald-800')}">
                                        ${escapeHtml(blocker.level_label || 'Low')}
                                    </span>
                                </div>
                                <div class="mt-4">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker Description</dt>
                                    <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">${escapeHtml(blocker.description || '')}</dd>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="edit"
                                        data-blocker-title="${escapeHtml(blocker.title || 'Edit Blocker')}"
                                        data-blocker-level="${escapeHtml(blocker.level || '')}"
                                        data-blocker-description="${escapeHtml(blocker.description || '')}"
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="${blockerClearUrl}" data-task-blocker-resolve-form>
                                        <input type="hidden" name="_token" value="${csrfToken}">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                        Resolve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        `
                    : `
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-600">No blocker added yet.</dd>
                                    </div>
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="add"
                                        data-blocker-title="Add Blocker"
                                        data-blocker-level=""
                                        data-blocker-description=""
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Add Blocker
                                    </button>
                                </div>
                            </div>
                        `;

                panel.outerHTML = `
                    <div id="task-blocker-panel" data-task-blocker-panel data-task-blocker-state="${blocker.has_blocker ? 'active' : 'empty'}">
                        ${html}
                    </div>
                `;
            }

            function openBlockerModal(button = null) {
                clearBlockerErrors();

                const data = button ? {
                    title: button.dataset.blockerTitle || defaultBlockerData.title,
                    level: button.dataset.blockerLevel ?? defaultBlockerData.level,
                    description: button.dataset.blockerDescription ?? defaultBlockerData.description,
                } : defaultBlockerData;

                if (blockerTitle) blockerTitle.textContent = data.title;
                if (blockerLevel) blockerLevel.value = data.level || '';
                if (blockerDescription) blockerDescription.value = data.description || '';

                blockerModal?.classList.remove('hidden');
                blockerModal?.classList.add('flex');
                blockerModal?.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');

                blockerDescription?.focus();
            }

            window.openTaskBlockerModal = function (button) {
                openBlockerModal(button || null);
                return false;
            };

            function closeBlockerModal() {
                blockerModal?.classList.add('hidden');
                blockerModal?.classList.remove('flex');
                blockerModal?.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                clearBlockerErrors();
                setSubmittingState(false);
            }

            document.addEventListener('click', function (event) {
                const button = event.target.closest('[data-blocker-trigger]');

                if (!button) return;

                event.preventDefault();
                openBlockerModal(button);
            });

            document.querySelectorAll('[data-close-blocker-modal]').forEach((button) => button.addEventListener('click', closeBlockerModal));

            document.addEventListener('submit', async function (event) {
                const resolveForm = event.target.closest('[data-task-blocker-resolve-form]');

                if (!resolveForm) {
                    return;
                }

                event.preventDefault();

                const confirmed = await confirmBlockerResolve();

                if (confirmed) {
                    resolveForm.submit();
                }
            });

            blockerModal?.addEventListener('click', (event) => {
                if (event.target === blockerModal) closeBlockerModal();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && blockerModal && !blockerModal.classList.contains('hidden')) {
                    closeBlockerModal();
                }
            });

            if (blockerForm) {
                blockerForm.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    clearBlockerErrors();

                    const level = String(blockerLevel?.value || '').trim();
                    const description = String(blockerDescription?.value || '').trim();
                    let hasValidationErrors = false;

                    if (!level) {
                        setFieldError(blockerLevel, blockerLevelError, 'Please select a blocker type.');
                        hasValidationErrors = true;
                    } else {
                        setFieldError(blockerLevel, blockerLevelError, '');
                    }

                    if (!description) {
                        setFieldError(blockerDescription, blockerDescriptionError, 'Please enter a blocker description.');
                        hasValidationErrors = true;
                    } else {
                        setFieldError(blockerDescription, blockerDescriptionError, '');
                    }

                    if (hasValidationErrors || blockerSubmissionInProgress) {
                        return;
                    }

                    setSubmittingState(true);

                    try {
                        const response = await fetch(blockerUpdateUrl, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: new FormData(blockerForm),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            if (response.status === 422 && payload.errors) {
                                if (payload.errors.blocker_level?.length) {
                                    setFieldError(blockerLevel, blockerLevelError, payload.errors.blocker_level[0]);
                                }
                                if (payload.errors.blocker_description?.length) {
                                    setFieldError(blockerDescription, blockerDescriptionError, payload.errors.blocker_description[0]);
                                }
                                if (!payload.errors.blocker_level?.length && !payload.errors.blocker_description?.length && blockerFormError) {
                                    blockerFormError.textContent = payload.message || 'Please review the blocker details.';
                                    blockerFormError.classList.remove('hidden');
                                }
                                return;
                            }

                            if (blockerFormError) {
                                blockerFormError.textContent = payload.message || 'Unable to save blocker right now.';
                                blockerFormError.classList.remove('hidden');
                            }

                            return;
                        }

                        const blocker = payload.blocker || {};
                        updateBlockerTriggerButtons(blocker);
                        renderBlockerPanel(blocker);
                        closeBlockerModal();
                    } catch (error) {
                        if (blockerFormError) {
                            blockerFormError.textContent = 'Unable to save blocker right now. Please try again.';
                            blockerFormError.classList.remove('hidden');
                        }
                    } finally {
                        setSubmittingState(false);
                    }
                });
            }

            if (shouldOpenBlockerModal) {
                openBlockerModal();
            }
            }

            const modal = document.getElementById('reminder-status-modal');
            const form = document.getElementById('reminder-status-form');
            const title = document.getElementById('reminder-status-modal-title');
            const dateLabel = document.getElementById('reminder-status-modal-date');
            const dateInput = document.getElementById('reminder-date-input');
            const statusInput = document.getElementById('reminder-status-input');
            const notesInput = document.getElementById('reminder-notes-input');
            const notesError = document.getElementById('reminder-notes-error');
            const inlineStatusSelects = document.querySelectorAll('.js-reminder-inline-status');
            const openModalButtons = document.querySelectorAll('.js-open-reminder-modal');
            let activeRowForm = null;
            const hasReminderStatusErrors = {{ ($errors->has('status') || $errors->has('notes') || $errors->has('reminder_date')) ? 'true' : 'false' }};
            const errorReminderDate = @js(old('reminder_date'));

            function syncRowControls(formEl) {
                const rowSelect = formEl?.querySelector('.js-reminder-inline-status');
                const rowButton = formEl?.querySelector('.js-open-reminder-modal');

                if (!rowSelect || !rowButton) return;

                formEl.dataset.selectedStatus = rowSelect.value;
                rowButton.setAttribute('aria-disabled', rowSelect.value === 'pending' ? 'true' : 'false');
                rowButton.classList.toggle('opacity-60', rowSelect.value === 'pending');
            }

            function openModal(selectEl, originatingForm) {
                const formEl = originatingForm || selectEl.closest('form');
                const taskName = formEl?.dataset.taskName || selectEl.dataset.taskName || 'Task';
                const reminderDateLabel = formEl?.dataset.reminderDateLabel || selectEl.dataset.reminderDateLabel || '';
                const reminderDate = formEl?.querySelector('input[name="reminder_date"]')?.value || '';
                const status = selectEl.value || formEl?.dataset.currentStatus || 'pending';
                const notes = formEl?.dataset.currentNotes || '';

                activeRowForm = originatingForm || formEl || null;

                if (title) title.textContent = taskName;
                if (dateLabel) dateLabel.textContent = reminderDateLabel;
                if (dateInput) dateInput.value = reminderDate;
                if (statusInput) statusInput.value = status;
                if (notesInput) notesInput.value = notes;
                if (notesError) notesError.classList.add('hidden');
                if (form) form.action = formEl?.action || '';

                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');

                if (notesInput) {
                    notesInput.focus();
                }
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
                activeRowForm = null;
            }

            inlineStatusSelects.forEach((select) => {
                select.addEventListener('change', function () {
                    const rowForm = select.closest('form');
                    if (!rowForm) return;
                    syncRowControls(rowForm);
                });

                syncRowControls(select.closest('form'));
            });

            window.__openReminderModalFromButton = function (button) {
                const rowForm = button?.closest('form');
                const rowSelect = rowForm?.querySelector('.js-reminder-inline-status');

                if (!rowForm || !rowSelect) return;

                openModal(rowSelect, rowForm);
            };

            document.addEventListener('click', function (event) {
                const button = event.target.closest('.js-open-reminder-modal');

                if (!button) return;

                event.preventDefault();
                window.__openReminderModalFromButton(button);
            });

            document.querySelectorAll('[data-close-reminder-status-modal]').forEach((button) => {
                button.addEventListener('click', closeModal);
            });

            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeModal();
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            if (hasReminderStatusErrors) {
                const firstRowForm = document.querySelector(`.js-reminder-row-form input[name="reminder_date"][value="${errorReminderDate}"]`)?.closest('form')
                    || document.querySelector('.js-reminder-inline-status')?.closest('form');
                const firstRowSelect = firstRowForm?.querySelector('.js-reminder-inline-status');

                if (firstRowSelect && firstRowForm) {
                    openModal(firstRowSelect, firstRowForm);
                }
            }

            if (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    const trimmedNotes = String(notesInput?.value || '').trim();
                    if (!trimmedNotes) {
                        if (notesError) {
                            notesError.classList.remove('hidden');
                        }
                        if (notesInput) {
                            notesInput.focus();
                        }
                        return;
                    }

                    if (!activeRowForm) {
                        return;
                    }

                    const activeRowStatus = activeRowForm.querySelector('.js-reminder-inline-status');

                    if (activeRowStatus && statusInput) {
                        statusInput.value = activeRowStatus.value;
                    }

                    if (notesInput) {
                        notesInput.value = trimmedNotes;
                    }

                    form.submit();
                });
            }
        });
    </script>
@endpush

</x-app-layout>
