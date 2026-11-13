<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Edit My Task</h2>
                    <p class="mt-1 text-sm text-slate-500">Update a task you created yourself.</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <form method="POST" action="{{ route('staff.tasks.update', $task) }}" class="space-y-6">
                    @csrf
                    @method('PATCH')

                    <div>
                        <label for="project_id" class="block text-sm font-medium text-slate-700">Project (Optional)</label>
                        <select name="project_id" id="project_id" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                            <option value="">No project</option>
                            @foreach($projects as $project)
                                <option value="{{ $project->id }}" @selected(old('project_id', $task->project_id) == $project->id)>
                                    {{ $project->name }}@if($project->reference_no) ({{ $project->reference_no }})@endif
                                </option>
                            @endforeach
                        </select>
                        @error('project_id') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="task_name" class="block text-sm font-medium text-slate-700">Task Title</label>
                        <input type="text" name="task_name" id="task_name" value="{{ old('task_name', $task->task_name) }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500" required>
                        @error('task_name') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div>
                            <label for="due_date" class="block text-sm font-medium text-slate-700">Due Date (Optional)</label>
                            <input type="date" name="due_date" id="due_date" value="{{ old('due_date', optional($task->due_date)->toDateString()) }}" min="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                            @error('due_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-slate-700">Status</label>
                            <input type="text" value="{{ \App\Models\StaffTask::statusOptions()[$task->status] ?? ucfirst($task->status) }}" disabled class="mt-1 block w-full rounded-xl border-slate-200 bg-slate-50 text-sm text-slate-500">
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <label class="flex items-center gap-3">
                                <input type="checkbox" name="has_reminder" value="1" @checked(old('has_reminder', $task->has_reminder)) onchange="window.toggleStaffTaskReminderType && window.toggleStaffTaskReminderType(this.checked)" class="js-reminder-toggle rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Reminder</span>
                                    <span class="block text-xs text-slate-500">Enable a reminder for this task.</span>
                                </span>
                            </label>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <label class="flex items-center gap-3">
                                <input type="checkbox" name="is_repeated" value="1" @checked(old('is_repeated', $task->is_repeated)) onchange="window.toggleStaffTaskRepeatType && window.toggleStaffTaskRepeatType(this.checked)" class="js-repeat-toggle rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Repeated</span>
                                    <span class="block text-xs text-slate-500">Repeat daily, weekly, monthly, or yearly.</span>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        {{-- <div>
                            <label for="reminder_date" class="block text-sm font-medium text-slate-700">Reminder Date (Optional)</label>
                            <input type="date" name="reminder_date" id="reminder_date" value="{{ old('reminder_date', optional($task->reminder_date)->toDateString()) }}" min="{{ now()->toDateString() }}" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                            @error('reminder_date') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div> --}}
                        <div id="reminder-type-wrap" class="{{ old('has_reminder', $task->has_reminder) ? '' : 'hidden' }}" @if(! old('has_reminder', $task->has_reminder)) hidden @endif>
                            <label for="reminder_type" class="block text-sm font-medium text-slate-700">Reminder Type (Optional)</label>
                            <select name="reminder_type" id="reminder_type" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option value="">Select reminder type</option>
                                @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'day_before_due_date' => 'Day Before Due Date'] as $reminderKey => $reminderLabel)
                                    <option value="{{ $reminderKey }}" @selected(old('reminder_type', $task->reminder_type) === $reminderKey)>{{ $reminderLabel }}</option>
                                @endforeach
                            </select>
                            @error('reminder_type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="grid gap-6 md:grid-cols-2">
                        <div id="repeat-type-wrap" class="{{ old('is_repeated', $task->is_repeated) ? '' : 'hidden' }}" @if(! old('is_repeated', $task->is_repeated)) hidden @endif>
                            <label for="repeat_type" class="block text-sm font-medium text-slate-700">Repeat Type</label>
                            <select name="repeat_type" id="repeat_type" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                <option value="">Select repeat type</option>
                                @foreach(\App\Models\StaffTask::repeatTypeOptions() as $repeatKey => $repeatLabel)
                                    <option value="{{ $repeatKey }}" @selected(old('repeat_type', $task->repeat_type) === $repeatKey)>{{ $repeatLabel }}</option>
                                @endforeach
                            </select>
                            @error('repeat_type') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label for="task_description" class="block text-sm font-medium text-slate-700">Task Description (Optional)</label>
                            <x-speech-to-text target="task_description" />
                        </div>
                        <textarea name="task_description" id="task_description" rows="5" class="mt-1 block w-full rounded-xl border-slate-300 text-sm focus:border-cyan-500 focus:ring-cyan-500">{{ old('task_description', $task->task_description) }}</textarea>
                        @error('task_description') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex items-center justify-end gap-3">
                        <a href="{{ route('staff.tasks.index') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Update Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const reminderToggle = document.querySelector('.js-reminder-toggle');
        const reminderWrap = document.getElementById('reminder-type-wrap');
        const reminderSelect = document.getElementById('reminder_type');
        const repeatToggle = document.querySelector('.js-repeat-toggle');
        const repeatWrap = document.getElementById('repeat-type-wrap');
        const repeatSelect = document.getElementById('repeat_type');

        const syncReminderVisibility = () => {
            if (!reminderToggle || !reminderWrap) return;
            const shouldShow = Boolean(reminderToggle.checked);
            reminderWrap.classList.toggle('hidden', !shouldShow);
            reminderWrap.hidden = !shouldShow;
            if (!shouldShow && reminderSelect) reminderSelect.value = '';
        };

        const syncRepeatVisibility = () => {
            if (!repeatToggle || !repeatWrap) return;
            const shouldShow = Boolean(repeatToggle.checked);
            repeatWrap.classList.toggle('hidden', !shouldShow);
            repeatWrap.hidden = !shouldShow;
            if (!shouldShow && repeatSelect) repeatSelect.value = '';
        };

        syncReminderVisibility();
        syncRepeatVisibility();
        reminderToggle?.addEventListener('change', syncReminderVisibility);
        reminderToggle?.addEventListener('input', syncReminderVisibility);
        repeatToggle?.addEventListener('change', syncRepeatVisibility);
        repeatToggle?.addEventListener('input', syncRepeatVisibility);

        window.toggleStaffTaskReminderType = (checked) => {
            if (reminderWrap) {
                reminderWrap.classList.toggle('hidden', !checked);
                reminderWrap.hidden = !checked;
            }

            if (!checked && reminderSelect) reminderSelect.value = '';
        };

        window.toggleStaffTaskRepeatType = (checked) => {
            if (repeatWrap) {
                repeatWrap.classList.toggle('hidden', !checked);
                repeatWrap.hidden = !checked;
            }

            if (!checked && repeatSelect) repeatSelect.value = '';
        };

        window.toggleStaffTaskReminderType(reminderToggle?.checked ?? false);
        window.toggleStaffTaskRepeatType(repeatToggle?.checked ?? false);
    });
</script>
@endpush
