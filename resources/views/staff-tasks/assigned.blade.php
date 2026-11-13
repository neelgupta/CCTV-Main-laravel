<x-app-layout>
    <x-slot name="header">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 class="text-2xl font-semibold text-slate-950 leading-tight">My Tasks</h2>
                        <p class="mt-1 text-sm text-slate-500">Tasks assigned to your staff profile.</p>
                    </div>
                    <a href="{{ route('staff.tasks.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Add Task
                    </a>
                </div>
            </div>
        </x-slot>

    <div class="py-6">
        <div class="mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Task</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Project</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Due Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Description</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Update Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($tasks as $task)
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-900">{{ $task->task_name }}</div>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-700">
                                        {{ $task->project?->name ?? '-' }}
                                        @if($task->project?->reference_no)
                                            <div class="text-xs text-slate-400">{{ $task->project->reference_no }}</div>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm">
                                        @php
                                            $statusMeta = match($task->status) {
                                                'done' => ['label' => 'Done', 'class' => 'bg-emerald-100 text-emerald-800'],
                                                'in_progress' => ['label' => 'In Progress', 'class' => 'bg-amber-100 text-amber-800'],
                                                'reschedule' => ['label' => 'Reschedule', 'class' => 'bg-cyan-100 text-cyan-800'],
                                                'cancelled' => ['label' => 'Cancelled', 'class' => 'bg-rose-100 text-rose-800'],
                                                default => ['label' => 'Pending', 'class' => 'bg-slate-100 text-slate-700'],
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusMeta['class'] }}">{{ $statusMeta['label'] }}</span>
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-700">{{ $task->due_date?->format('d M, Y') ?? '-' }}</td>
                                    <td class="px-4 py-4 text-sm text-slate-700">{{ $task->task_description ?: 'No description added.' }}</td>
                                    <td class="px-4 py-4">
                                        @php
                                            $taskStatusEditable = ! in_array($task->status, ['done', 'cancelled'], true);
                                        @endphp
                                        <button
                                            type="button"
                                            class="js-task-status-edit inline-flex items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300"
                                            data-update-url="{{ route('staff.tasks.status.update', $task) }}"
                                            data-task-name="{{ $task->task_name }}"
                                            data-status="{{ $task->status }}"
                                            @disabled(! $taskStatusEditable)
                                        >
                                            {{ $taskStatusEditable ? 'Update Status' : 'Read only' }}
                                        </button>
                                    </td>
                                    <td class="px-4 py-4">
                                        @php
                                            $authUser = auth()->user();
                                            $canManageTasks = $authUser?->isCompanyAdmin() || $authUser?->hasPermission('tasks.manage');
                                            $isTaskCreator = (int) ($task->created_by_staff_id ?? 0) === (int) ($authUser?->staff?->id ?? 0);
                                        @endphp
                                        @if($canManageTasks || $isTaskCreator)
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ route('staff.tasks.edit', $task) }}" class="inline-flex items-center justify-center rounded-xl border border-cyan-200 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-700 hover:bg-cyan-100">
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                </a>
                                                @if($canManageTasks || $isTaskCreator)
                                                    <form method="POST" action="{{ route('staff.tasks.destroy', $task) }}" data-staff-task-delete>
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">No tasks assigned to you yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="border-t border-slate-200 px-4 py-3">
                    {{ $tasks->links() }}
                </div>
            </div>
        </div>
    </div>

    <div id="task-status-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
        <div class="absolute inset-0" data-close-task-status-modal></div>
        <div class="relative z-10 w-full max-w-lg rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Update Task Status</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="task-status-modal-title">Task Status</h3>
                </div>
                <button type="button" data-close-task-status-modal
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close status modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <form id="task-status-form" method="POST" action="" class="space-y-4 px-5 py-5">
                @csrf
                @method('PATCH')
                <div>
                    <label for="task-status-select" class="block text-sm font-medium text-slate-700">Status</label>
                    <select name="status" id="task-status-select" class="mt-1 block w-full rounded-xl border-slate-300 text-sm">
                        @foreach(\App\Models\StaffTask::statusOptions() as $statusKey => $statusLabel)
                            <option value="{{ $statusKey }}" @selected(old('status') === $statusKey)>{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                    @error('status') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="task-status-comment" class="block text-sm font-medium text-slate-700">Notes</label>
                    <textarea name="notes" id="task-status-comment" rows="4" class="mt-1 block w-full rounded-xl border-slate-300 text-sm" placeholder="Add a note about what was done or why the task is delayed.">{{ old('notes') }}</textarea>
                    <p class="mt-1 text-xs text-slate-500">Notes are required when changing the task status.</p>
                    @error('notes') <p class="mt-1 text-sm text-rose-600">{{ $message }}</p> @enderror
                </div>
                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                    <button type="button" data-close-task-status-modal class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                        Save Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const taskStatusModal = document.getElementById('task-status-modal');
                const taskStatusForm = document.getElementById('task-status-form');
                const taskStatusTitle = document.getElementById('task-status-modal-title');
                const taskStatusSelect = document.getElementById('task-status-select');
                const taskStatusComment = document.getElementById('task-status-comment');
                const hasTaskStatusErrors = {{ ($errors->has('status') || $errors->has('notes')) ? 'true' : 'false' }};

                function openTaskStatusModal(button) {
                    const taskName = button.dataset.taskName || 'Task';
                    const updateUrl = button.dataset.updateUrl || '';
                    const status = button.dataset.status || 'pending';

                    if (taskStatusTitle) {
                        taskStatusTitle.textContent = taskName;
                    }

                    if (taskStatusForm) {
                        taskStatusForm.action = updateUrl;
                    }

                    if (taskStatusSelect) {
                        taskStatusSelect.value = status;
                    }

                    if (taskStatusComment) {
                        taskStatusComment.value = '';
                    }

                    taskStatusModal.classList.remove('hidden');
                    taskStatusModal.classList.add('flex');
                    taskStatusModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                }

                function closeTaskStatusModal() {
                    taskStatusModal.classList.add('hidden');
                    taskStatusModal.classList.remove('flex');
                    taskStatusModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                }

                document.querySelectorAll('.js-task-status-edit').forEach((button) => {
                    button.addEventListener('click', function () {
                        openTaskStatusModal(button);
                    });
                });

                document.querySelectorAll('[data-close-task-status-modal]').forEach((button) => {
                    button.addEventListener('click', closeTaskStatusModal);
                });

                taskStatusModal?.addEventListener('click', (event) => {
                    if (event.target === taskStatusModal) closeTaskStatusModal();
                });

                if (hasTaskStatusErrors) {
                    const taskButtons = document.querySelectorAll('.js-task-status-edit');
                    if (taskButtons.length > 0) {
                        openTaskStatusModal(taskButtons[0]);
                    }
                }

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && taskStatusModal && !taskStatusModal.classList.contains('hidden')) {
                        closeTaskStatusModal();
                    }
                });

                document.querySelectorAll('[data-staff-task-delete]').forEach(function (form) {
                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        if (typeof Swal === 'undefined') {
                            if (window.confirm('Delete this task?')) {
                                form.submit();
                            }
                            return;
                        }

                        const result = await Swal.fire({
                            title: 'Delete task?',
                            text: 'This action cannot be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, delete it',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#dc2626',
                            cancelButtonColor: '#64748b',
                            customClass: {
                                popup: 'rounded-2xl',
                            },
                        });

                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
