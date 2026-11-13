<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Edit Task</h2>
            <p class="mt-1 text-sm text-slate-500">Update task assignment, project link, or description.</p>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('task-management.update', $task) }}" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm space-y-5" x-data="{
                reminderEnabled: @js((bool) old('has_reminder', $task->has_reminder)),
                repeatEnabled: @js((bool) old('is_repeated', $task->is_repeated))
            }">
                @csrf
                @method('PATCH')
                @php
                    $selectedHasReminder = old('has_reminder', $task->has_reminder);
                    $selectedIsRepeated = old('is_repeated', $task->is_repeated);
                @endphp
                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    @if($isCompanyAdmin)
                        <div>
                            <x-searchable-select
                                name="staff_id"
                                id="staff_id"
                                label="Assignee"
                                :options="$staffMembers->map(fn ($staff) => ['id' => $staff->id, 'label' => $staff->name])"
                                :selected="old('staff_id', $task->staff_id)"
                                placeholder="Search staff..."
                                empty-label="Select assignee"
                                helper-text="Company admins can assign this task to any staff member."
                            />
                            <x-input-error :messages="$errors->get('staff_id')" class="mt-2" />
                        </div>
                    @else
                        <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700">Assignee</p>
                            <p class="mt-1 text-sm text-slate-500">{{ auth()->user()?->staff?->name ?? 'Current logged in user' }}</p>
                            <input type="hidden" name="staff_id" value="{{ $task->staff_id }}">
                        </div>
                    @endif

                    <div>
                        <x-searchable-select
                            name="project_id"
                            id="project_id"
                            label="Project (Optional)"
                            :options="$projects->map(fn ($project) => [
                                'id' => $project->id,
                                'label' => $project->name,
                                'meta' => $project->reference_no,
                            ])"
                            :selected="old('project_id', $task->project_id)"
                            placeholder="Search project..."
                            empty-label="Select project"
                            helper-text="Search and optionally attach a project."
                        />
                        <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-xl border-slate-300">
                            @foreach($statusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}" @selected(old('status', $task->status ?? 'pending') === $statusKey)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="task_name" value="Task Title" />
                        <x-text-input id="task_name" name="task_name" class="mt-1 block w-full rounded-xl" :value="old('task_name', $task->task_name)" />
                        <x-input-error :messages="$errors->get('task_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="due_date" value="Due Date" />
                        <x-text-input id="due_date" name="due_date" type="date" class="mt-1 block w-full rounded-xl" :value="old('due_date', $task->due_date?->format('Y-m-d'))" min="{{ now()->format('Y-m-d') }}" />
                        <x-input-error :messages="$errors->get('due_date')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2 grid gap-5 md:grid-cols-2">
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input type="checkbox" name="has_reminder" value="1" @checked($selectedHasReminder) onchange="window.toggleStaffTaskReminderType && window.toggleStaffTaskReminderType(this.checked)" class="js-reminder-toggle rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                            <span>
                                <span class="block font-semibold text-slate-900">Reminder</span>
                                <span class="block text-xs text-slate-500">Enable a reminder for this task.</span>
                            </span>
                        </label>
                        <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input type="checkbox" name="is_repeated" value="1" @checked($selectedIsRepeated) onchange="window.toggleStaffTaskRepeatType && window.toggleStaffTaskRepeatType(this.checked)" class="js-repeat-toggle rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                            <span>
                                <span class="block font-semibold text-slate-900">Repeated</span>
                                <span class="block text-xs text-slate-500">Repeat this task on a schedule.</span>
                            </span>
                        </label>
                    </div>

                    {{-- <div>
                        <x-input-label for="reminder_date" value="Reminder Date (Optional)" />
                        <x-text-input id="reminder_date" name="reminder_date" type="date" class="mt-1 block w-full rounded-xl" :value="old('reminder_date', optional($task->reminder_date)->format('Y-m-d'))" min="{{ now()->format('Y-m-d') }}" />
                        <x-input-error :messages="$errors->get('reminder_date')" class="mt-2" />
                    </div> --}}

                    <div id="reminder-type-wrap" class="{{ $selectedHasReminder ? '' : 'hidden' }}" @if(! $selectedHasReminder) hidden @endif>
                        <x-input-label for="reminder_type" value="Reminder Type (Optional)" />
                        <select id="reminder_type" name="reminder_type" class="mt-1 block w-full rounded-xl border-slate-300">
                            <option value="">Select reminder type</option>
                            @foreach(['daily' => 'Daily', 'weekly' => 'Weekly', 'day_before_due_date' => 'Day Before Due Date'] as $reminderKey => $reminderLabel)
                                <option value="{{ $reminderKey }}" @selected(old('reminder_type', $task->reminder_type) === $reminderKey)>{{ $reminderLabel }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('reminder_type')" class="mt-2" />
                    </div>

                    <div id="repeat-type-wrap" class="{{ $selectedIsRepeated ? '' : 'hidden' }}" @if(! $selectedIsRepeated) hidden @endif>
                        <x-input-label for="repeat_type" value="Repeat Type (Optional)" />
                        <select id="repeat_type" name="repeat_type" class="mt-1 block w-full rounded-xl border-slate-300">
                            <option value="">Select repeat type</option>
                            @foreach($repeatTypeOptions as $repeatKey => $repeatLabel)
                                <option value="{{ $repeatKey }}" @selected(old('repeat_type', $task->repeat_type) === $repeatKey)>{{ $repeatLabel }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('repeat_type')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2">
                        <div class="flex items-center justify-between">
                            <x-input-label for="task_description" value="Task Description" />
                            <x-speech-to-text target="task_description" />
                        </div>
                        <textarea id="task_description" name="task_description" rows="5" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('task_description', $task->task_description) }}</textarea>
                        <x-input-error :messages="$errors->get('task_description')" class="mt-2" />
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <a href="{{ route('task-management.index') }}" class="text-sm font-semibold text-slate-600 hover:text-slate-900">Cancel</a>
                    <x-primary-button type="submit">Update Task</x-primary-button>
                </div>
            </form>
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
