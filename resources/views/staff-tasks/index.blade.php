<x-app-layout>
    <x-slot name="header">
        @php
            $authUser = auth()->user();
            $canManageTasks = $authUser?->isSuperAdmin() || $authUser?->isCompanyAdmin() || $authUser?->hasPermission('tasks.manage');
            $filterQuery = request()->except('page');
            $taskFilterUrl = route('task-management.index', $filterQuery);
            $statusOptions = $statusOptions ?? \App\Models\StaffTask::statusOptions();
            $taskQuickCounts = $taskQuickCounts ?? [
                'all' => 0,
                'today_due' => 0,
                'this_week_due' => 0,
                'this_month_due' => 0,
                'low_blocker' => 0,
                'medium_blocker' => 0,
                'high_blocker' => 0,
            ];
            $quickFilterOptions = $quickFilterOptions ?? [];
            $activeQuickFilter = request('due_range')
                ?: (request('blocker_level') ? request('blocker_level') . '_blocker' : 'all');
            $hasActiveFilters = request()->filled('search')
                || request()->filled('status')
                || request()->filled('staff_id')
                || request()->filled('project_id')
                || request()->filled('due_from')
                || request()->filled('due_to')
                || request()->filled('has_blocker')
                || request()->filled('due_range')
                || request()->filled('blocker_level');
        @endphp
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Task Management</h2>
                    <p class="mt-1 text-sm text-slate-500">Assign tasks to staff, attach them to projects, and track due dates.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        id="toggle-task-filters"
                        aria-expanded="{{ $hasActiveFilters ? 'true' : 'false' }}"
                        aria-controls="task-filter-wrapper"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50"
                        title="Toggle filters"
                        aria-label="Toggle task filters"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 01.53 1.28l-5.78 5.78v4.19a.75.75 0 01-1.14.64l-2.5-1.67a.75.75 0 01-.34-.64v-2.52L2.22 5.28A.75.75 0 012 4.75z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    @if($canManageTasks)
                        <a href="{{ route('task-management.create') }}" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Add Task
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <style>
            .task-reminder-chip {
                position: relative;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 2rem;
                height: 2rem;
                border-radius: 9999px;
                border: 1px solid rgb(191 219 254);
                background: linear-gradient(180deg, rgb(239 246 255), rgb(219 234 254));
                color: rgb(30 64 175);
                font-size: 0.65rem;
                font-weight: 800;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                cursor: default;
                transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            }

            .task-reminder-chip:hover {
                transform: translateY(-1px) scale(1.05);
                box-shadow: 0 10px 20px rgba(37, 99, 235, 0.15);
                background: linear-gradient(180deg, rgb(191 219 254), rgb(147 197 253));
            }

            .task-reminder-tooltip {
                position: absolute;
                left: 50%;
                top: calc(100% + 10px);
                z-index: 20;
                min-width: 128px;
                transform: translateX(-50%);
                border-radius: 0.75rem;
                border: 1px solid rgb(226 232 240);
                background: rgb(15 23 42);
                padding: 0.55rem 0.75rem;
                color: white;
                font-size: 0.72rem;
                line-height: 1.2;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.18s ease, transform 0.18s ease;
                transform-origin: top center;
                box-shadow: 0 12px 30px rgba(15, 23, 42, 0.22);
            }

            .task-reminder-tooltip::before {
                content: '';
                position: absolute;
                left: 50%;
                top: -5px;
                width: 10px;
                height: 10px;
                transform: translateX(-50%) rotate(45deg);
                background: rgb(15 23 42);
                border-left: 1px solid rgb(226 232 240);
                border-top: 1px solid rgb(226 232 240);
            }

            .task-reminder-chip:hover .task-reminder-tooltip {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }

            .task-reminder-more {
                position: relative;
            }

            .task-reminder-more-button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2rem;
                height: 2rem;
                border-radius: 9999px;
                border: 1px solid rgb(251 191 36);
                background: linear-gradient(180deg, rgb(254 249 195), rgb(253 230 138));
                color: rgb(146 64 14);
                font-size: 0.7rem;
                font-weight: 800;
                line-height: 1;
                transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
            }

            .task-reminder-more-button:hover {
                transform: translateY(-1px) scale(1.05);
                background: linear-gradient(180deg, rgb(253 224 71), rgb(252 211 77));
                box-shadow: 0 10px 20px rgba(217, 119, 6, 0.14);
            }

            .task-reminder-more-popover {
                position: absolute;
                top: calc(100% + 10px);
                right: 0;
                z-index: 30;
                min-width: 210px;
                max-width: 280px;
                border-radius: 0.9rem;
                border: 1px solid rgb(226 232 240);
                background: white;
                padding: 0.75rem;
                box-shadow: 0 18px 40px rgba(15, 23, 42, 0.18);
                opacity: 0;
                visibility: hidden;
                transform: translateY(-4px);
                transition: opacity 0.18s ease, transform 0.18s ease, visibility 0.18s ease;
            }

            .task-reminder-more.is-open .task-reminder-more-popover {
                opacity: 1;
                visibility: visible;
                transform: translateY(0);
            }

            .task-reminder-more-popover::before {
                content: '';
                position: absolute;
                top: -5px;
                right: 16px;
                width: 10px;
                height: 10px;
                transform: rotate(45deg);
                background: white;
                border-left: 1px solid rgb(226 232 240);
                border-top: 1px solid rgb(226 232 240);
            }

            .task-reminder-popover-item {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.45rem 0.15rem;
                font-size: 0.74rem;
                color: rgb(51 65 85);
            }

            .task-reminder-popover-item + .task-reminder-popover-item {
                border-top: 1px solid rgb(241 245 249);
            }
        </style>
        <div class="mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif

            <div id="task-filter-wrapper" class="{{ $hasActiveFilters ? '' : 'hidden' }} mb-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">Task Filters</h3>
                        <p class="text-xs text-slate-500">Search tasks by name, staff, project, status, and due date.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('task-management.index') }}"
                           class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50">
                            Reset Filters
                        </a>
                    </div>
                </div>

                <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                    @php
                        $taskChipBase = 'rounded-2xl border px-4 py-3 text-left transition';
                        $taskChipStyles = [
                            'all' => 'border-indigo-300 bg-indigo-50 text-indigo-800',
                            'today_due' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'this_week_due' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'this_month_due' => 'border-sky-200 bg-sky-50 text-sky-900',
                            'low_blocker' => 'border-emerald-200 bg-emerald-50 text-emerald-900',
                            'medium_blocker' => 'border-amber-200 bg-amber-50 text-amber-900',
                            'high_blocker' => 'border-rose-200 bg-rose-50 text-rose-900',
                        ];
                        $taskQuickLinks = [
                            'all' => route('task-management.index', request()->except(['page', 'due_range', 'blocker_level'])),
                            'today_due' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['due_range' => 'today'])),
                            'this_week_due' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['due_range' => 'week'])),
                            'this_month_due' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['due_range' => 'month'])),
                            'low_blocker' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['blocker_level' => 'low'])),
                            'medium_blocker' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['blocker_level' => 'medium'])),
                            'high_blocker' => route('task-management.index', array_merge(request()->except(['page', 'due_range', 'blocker_level']), ['blocker_level' => 'high'])),
                        ];
                        $taskQuickLabels = [
                            'all' => 'All',
                            'today_due' => 'Today Due',
                            'this_week_due' => 'This Week Due',
                            'this_month_due' => 'This Month Due',
                            'low_blocker' => 'Low Level Blocker',
                            'medium_blocker' => 'Medium Level Blocker',
                            'high_blocker' => 'High Level Blocker',
                        ];
                    @endphp
                    @foreach($taskQuickLabels as $quickKey => $quickLabel)
                        <a href="{{ $taskQuickLinks[$quickKey] }}"
                           class="{{ $taskChipBase }} {{ $taskChipStyles[$quickKey] ?? 'border-slate-200 bg-white text-slate-900' }} {{ $activeQuickFilter === $quickKey ? 'ring-2 ring-offset-2 ring-indigo-300' : '' }}">
                            <div class="text-xs font-semibold uppercase tracking-wide">{{ $quickLabel }}</div>
                            <div class="mt-1 text-2xl font-bold">{{ $taskQuickCounts[$quickKey] ?? 0 }}</div>
                        </a>
                    @endforeach
                </div>

                <form method="GET" action="{{ route('task-management.index') }}" class="mt-4">
                    @if(request()->filled('due_range'))
                        <input type="hidden" name="due_range" value="{{ request('due_range') }}">
                    @endif
                    @if(request()->filled('blocker_level'))
                        <input type="hidden" name="blocker_level" value="{{ request('blocker_level') }}">
                    @endif
                    <div class="grid gap-4 lg:grid-cols-5">
                        <div class="lg:col-span-2">
                            <label for="task-search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                            <input id="task-search" type="search" name="search" value="{{ $filters['search'] ?? '' }}"
                                   placeholder="Task, description, staff, project"
                                   class="block w-full rounded-xl border-slate-300 text-sm">
                        </div>
                        <div>
                            <label for="task-status" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                            <select id="task-status" name="status" class="block w-full rounded-xl border-slate-300 text-sm">
                                <option value="">All status</option>
                                @foreach($statusOptions as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" @selected(($filters['status'] ?? '') === $statusKey)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task-staff" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Staff</label>
                            <select id="task-staff" name="staff_id" class="block w-full rounded-xl border-slate-300 text-sm">
                                <option value="">All staff</option>
                                @foreach($staffMembers as $staff)
                                    <option value="{{ $staff->id }}" @selected((string) ($filters['staff_id'] ?? '') === (string) $staff->id)>{{ $staff->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task-project" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Project</label>
                            <select id="task-project" name="project_id" class="block w-full rounded-xl border-slate-300 text-sm">
                                <option value="">All projects</option>
                                @foreach($projects as $project)
                                    <option value="{{ $project->id }}" @selected((string) ($filters['project_id'] ?? '') === (string) $project->id)>
                                        {{ $project->name }}{{ $project->reference_no ? ' (' . $project->reference_no . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="task-has-blocker" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Blocker</label>
                            <select id="task-has-blocker" name="has_blocker" class="block w-full rounded-xl border-slate-300 text-sm">
                                <option value="">All</option>
                                <option value="1" @selected(($filters['has_blocker'] ?? '') === '1')>With blocker</option>
                                <option value="0" @selected(($filters['has_blocker'] ?? '') === '0')>Without blocker</option>
                            </select>
                        </div>
                        <div>
                            <label for="task-due-from" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Due From</label>
                            <input id="task-due-from" type="date" name="due_from" value="{{ $filters['due_from'] ?? '' }}" class="block w-full rounded-xl border-slate-300 text-sm">
                        </div>
                        <div>
                            <label for="task-due-to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Due To</label>
                            <input id="task-due-to" type="date" name="due_to" value="{{ $filters['due_to'] ?? '' }}" class="block w-full rounded-xl border-slate-300 text-sm">
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-2">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                            Apply Filters
                        </button>
                        <a href="{{ route('task-management.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Clear
                        </a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Task</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Staff</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Project</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Due Date</th>
                                {{-- <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Reminders</th> --}}
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500 text-white">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($tasks as $task)
                                <tr>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-900">{{ $task->task_name }}</div>
                                        <div class="mt-1 line-clamp-2 text-sm text-slate-500">{{ $task->task_description ?: 'No description added.' }}</div>
                                        @if($task->has_blocker)
                                            @php
                                                $blockerBadge = match ($task->blocker_level) {
                                                    'high' => ['label' => 'High Blocker', 'class' => 'bg-rose-100 text-rose-800'],
                                                    'medium' => ['label' => 'Medium Blocker', 'class' => 'bg-amber-100 text-amber-800'],
                                                    default => ['label' => 'Low Blocker', 'class' => 'bg-emerald-100 text-emerald-800'],
                                                };
                                            @endphp
                                            <span class="mt-2 inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $blockerBadge['class'] }}">
                                                {{ $blockerBadge['label'] }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm text-slate-700">
                                        @if($canManageTasks)
                                            <button
                                                type="button"
                                                class="js-task-staff-edit inline-flex items-center gap-2 rounded-lg px-3 py-2 text-left font-medium text-slate-700 transition hover:bg-cyan-50 hover:text-cyan-800"
                                                title="Click to update assigned staff"
                                                data-update-url="{{ route('task-management.update', $task) }}"
                                                data-task-name="{{ $task->task_name }}"
                                                data-staff-id="{{ $task->staff_id }}"
                                                data-staff-name="{{ $task->staff?->name ?? 'Select staff' }}"
                                            >
                                                <span class="task-staff-display">{{ $task->staff?->name ?? '-' }}</span>
                                            </button>
                                        @else
                                            <span class="font-medium text-slate-700">{{ $task->staff?->name ?? '-' }}</span>
                                        @endif
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
                                    <td class="px-4 py-4 text-sm text-slate-700">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="whitespace-nowrap">{{ $task->due_date?->format('d M, Y') ?? '-' }}</span>
                                          </div>
                                    </td>
                                    {{-- <td class="px-4 py-4 text-sm text-slate-700">
                                            @php
                                                $reminderDates = collect();
                                                if ($task->due_date && filled($task->reminder_weekdays)) {
                                                    $startDate = now()->startOfDay();
                                                    $endDate = $task->due_date->copy()->startOfDay();
                                                    $weekdayOrder = collect($task->reminder_weekdays)
                                                        ->map(fn ($weekday) => strtolower(trim((string) $weekday)))
                                                        ->filter()
                                                        ->values()
                                                        ->all();

                                                    $cursor = $startDate->copy();
                                                    while ($cursor->lte($endDate)) {
                                                        if (in_array(strtolower($cursor->format('l')), $weekdayOrder, true)) {
                                                            $reminderDates->push($cursor->copy());
                                                        }

                                                        $cursor->addDay();
                                                    }
                                                }
                                            @endphp

                                            @if($reminderDates->isNotEmpty())
                                                <div class="flex flex-wrap items-center gap-0">
                                                    @foreach($reminderDates->take(5) as $reminderDate)
                                                        @php
                                                            $weekdayLabel = $reminderDate->format('D');
                                                            $fullWeekday = $reminderDate->format('l');
                                                        @endphp
                                                        <span class="task-reminder-chip group">
                                                            {{ $weekdayLabel }}
                                                            <span class="task-reminder-tooltip">
                                                                <span class="block font-semibold">{{ $fullWeekday }}</span>
                                                                <span class="block text-slate-300">{{ $reminderDate->format('d M Y') }}</span>
                                                            </span>
                                                        </span>
                                                    @endforeach
                                                    @if($reminderDates->count() > 5)
                                                        <div class="task-reminder-more">
                                                            <button
                                                                type="button"
                                                                class="task-reminder-more-button"
                                                                data-task-reminder-more-toggle
                                                                aria-expanded="false"
                                                                aria-label="Show additional reminders"
                                                            >
                                                                +{{ $reminderDates->count() - 5 }}
                                                            </button>
                                                            <div class="task-reminder-more-popover" data-task-reminder-more-popover>
                                                                <div class="mb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">More reminders</div>
                                                                @foreach($reminderDates->skip(5) as $extraReminderDate)
                                                                    <div class="task-reminder-popover-item">
                                                                        <span class="font-semibold text-slate-700">{{ $extraReminderDate->format('l') }}</span>
                                                                        <span class="text-slate-500">{{ $extraReminderDate->format('d M Y') }}</span>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endif
                                </td> --}}
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-end gap-4">
                                            <a href="{{ route('task-management.show', $task) }}"
                                                class="rounded-lg p-2 text-slate-600 transition hover:bg-slate-50 hover:text-slate-900"
                                                title="View Task">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                    <path d="M320 144C209.3 144 113.4 208.5 64.2 304C113.4 399.5 209.3 464 320 464C430.7 464 526.6 399.5 575.8 304C526.6 208.5 430.7 144 320 144zM320 400C267 400 224 357 224 304C224 251 267 208 320 208C373 208 416 251 416 304C416 357 373 400 320 400zM320 256C293.5 256 272 277.5 272 304C272 330.5 293.5 352 320 352C346.5 352 368 330.5 368 304C368 277.5 346.5 256 320 256z"/>
                                                </svg>
                                            </a>

                                            @if($canManageTasks)
                                                <a href="{{ route('task-management.edit', $task) }}"
                                                    class="rounded-lg p-2 text-cyan-600 transition hover:bg-cyan-50 hover:text-cyan-800"
                                                    title="Edit Task">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                        <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                    </svg>
                                                </a>

                                                <form method="POST" action="{{ route('task-management.destroy', $task) }}" data-task-delete-form>
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 hover:text-rose-800" title="Delete Task">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                            <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">No tasks created yet.</td>
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

    <div id="task-staff-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
        <div class="absolute inset-0" data-close-task-staff-modal></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Assign Staff</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="task-staff-modal-title">Update Assigned Staff</h3>
                </div>
                <button type="button" data-close-task-staff-modal
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close assign modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="border-b border-slate-100 px-5 py-3">
                <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2">
                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" /></svg>
                    <input type="text" id="task-staff-search" placeholder="Search staff..."
                        class="w-full border-0 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0">
                </div>
            </div>

            <div id="task-staff-list" class="flex-1 overflow-y-auto px-5 py-3 space-y-1.5">
                @foreach($staffMembers as $staff)
                    <label class="js-task-staff-option flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-3 py-2.5 transition hover:border-cyan-200 hover:bg-cyan-50/40"
                        data-staff-name="{{ \Illuminate\Support\Str::lower($staff->name) }}">
                        <input type="radio" name="task_staff_choice" value="{{ $staff->id }}"
                            class="js-task-staff-radio h-4 w-4 border-slate-300 text-cyan-600 focus:ring-cyan-500">
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-cyan-500 text-[11px] font-bold text-white">
                            {{ strtoupper(substr($staff->name, 0, 1)) }}
                        </span>
                        <span class="text-sm font-medium text-slate-800">{{ $staff->name }}</span>
                    </label>
                @endforeach
                <p id="task-staff-empty" class="hidden px-1 py-4 text-center text-sm text-slate-400">No staff match your search.</p>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <span class="text-xs text-slate-500">Selected: <span id="task-staff-selected-name">-</span></span>
                <div class="flex items-center gap-2">
                    <button type="button" data-close-task-staff-modal
                        class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="task-staff-save"
                        class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const filterToggle = document.getElementById('toggle-task-filters');
                const filterWrapper = document.getElementById('task-filter-wrapper');

                if (filterToggle && filterWrapper) {
                    const syncToggle = () => {
                        const isHidden = filterWrapper.classList.contains('hidden');
                        filterToggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
                    };

                    syncToggle();

                    filterToggle.addEventListener('click', function () {
                        filterWrapper.classList.toggle('hidden');
                        syncToggle();
                    });
                }

                const taskStaffModal = document.getElementById('task-staff-modal');
                const taskStaffTitle = document.getElementById('task-staff-modal-title');
                const taskStaffSearch = document.getElementById('task-staff-search');
                const taskStaffEmpty = document.getElementById('task-staff-empty');
                const taskStaffSelectedName = document.getElementById('task-staff-selected-name');
                const taskStaffSave = document.getElementById('task-staff-save');
                const taskStaffOptions = Array.from(document.querySelectorAll('.js-task-staff-option'));
                const taskStaffRadios = Array.from(document.querySelectorAll('.js-task-staff-radio'));
                const csrfToken = '{{ csrf_token() }}';
                let activeTaskStaffButton = null;
                let activeTaskStaffUrl = '';

                function filterTaskStaff(term) {
                    const needle = String(term || '').trim().toLowerCase();
                    let visible = 0;

                    taskStaffOptions.forEach((option) => {
                        const name = option.dataset.staffName || '';
                        const match = needle === '' || name.includes(needle);
                        option.classList.toggle('hidden', !match);
                        if (match) visible++;
                    });

                    if (taskStaffEmpty) taskStaffEmpty.classList.toggle('hidden', visible !== 0);
                }

                function openTaskStaffModal(button) {
                    activeTaskStaffButton = button;
                    activeTaskStaffUrl = button.dataset.updateUrl || '';
                    const taskName = button.dataset.taskName || 'Task';
                    const staffId = String(button.dataset.staffId || '');
                    const staffName = button.dataset.staffName || 'Select staff';

                    if (taskStaffTitle) taskStaffTitle.textContent = 'Update Assigned Staff — ' + taskName;
                    if (taskStaffSelectedName) taskStaffSelectedName.textContent = staffName;
                    if (taskStaffSearch) taskStaffSearch.value = '';

                    taskStaffRadios.forEach((radio) => {
                        radio.checked = String(radio.value) === staffId;
                    });

                    filterTaskStaff('');
                    taskStaffModal.classList.remove('hidden');
                    taskStaffModal.classList.add('flex');
                    taskStaffModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                }

                function closeTaskStaffModal() {
                    taskStaffModal.classList.add('hidden');
                    taskStaffModal.classList.remove('flex');
                    taskStaffModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    activeTaskStaffButton = null;
                    activeTaskStaffUrl = '';
                }

                async function saveTaskStaff() {
                    if (!activeTaskStaffUrl || !activeTaskStaffButton) return;

                    const selected = taskStaffRadios.find((radio) => radio.checked);
                    if (!selected) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'warning', title: 'Select staff', text: 'Please choose a staff member.' });
                        }
                        return;
                    }

                    taskStaffSave.disabled = true;
                    const originalText = taskStaffSave.textContent;
                    taskStaffSave.textContent = 'Saving...';

                    try {
                        const response = await fetch(activeTaskStaffUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({ staff_id: selected.value }),
                        });

                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = Object.values(data?.errors || {}).flat()?.[0];
                            throw new Error(firstError || data?.message || 'Failed to update task staff.');
                        }

                        const displayEl = activeTaskStaffButton.querySelector('.task-staff-display');
                        if (displayEl) {
                            displayEl.textContent = data?.data?.staff?.name || 'Select staff';
                        }
                        activeTaskStaffButton.dataset.staffId = data?.data?.staff?.id || selected.value;
                        activeTaskStaffButton.dataset.staffName = data?.data?.staff?.name || '';

                        closeTaskStaffModal();

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: data?.message || 'Task staff updated.',
                                showConfirmButton: false,
                                timer: 2000,
                            });
                        }
                    } catch (error) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Update failed', text: error.message || 'Failed to update task staff.' });
                        }
                    } finally {
                        taskStaffSave.disabled = false;
                        taskStaffSave.textContent = originalText;
                    }
                }

                document.querySelectorAll('.js-task-staff-edit').forEach((button) => {
                    button.addEventListener('click', function () {
                        openTaskStaffModal(button);
                    });
                });

                taskStaffSearch?.addEventListener('input', (event) => filterTaskStaff(event.target.value));
                taskStaffSave?.addEventListener('click', saveTaskStaff);
                document.querySelectorAll('[data-close-task-staff-modal]').forEach((button) => {
                    button.addEventListener('click', closeTaskStaffModal);
                });
                taskStaffModal?.addEventListener('click', (event) => {
                    if (event.target === taskStaffModal) closeTaskStaffModal();
                });
                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && taskStaffModal && !taskStaffModal.classList.contains('hidden')) {
                        closeTaskStaffModal();
                    }
                });

                document.querySelectorAll('[data-task-delete-form]').forEach(function (form) {
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

                document.querySelectorAll('[data-task-reminder-more-toggle]').forEach(function (button) {
                    const wrapper = button.closest('.task-reminder-more');
                    const popover = wrapper?.querySelector('[data-task-reminder-more-popover]');
                    if (!wrapper || !popover) return;

                    button.addEventListener('click', function (event) {
                        event.stopPropagation();

                        const willOpen = !wrapper.classList.contains('is-open');
                        document.querySelectorAll('.task-reminder-more.is-open').forEach(function (openWrapper) {
                            if (openWrapper !== wrapper) {
                                openWrapper.classList.remove('is-open');
                                const openButton = openWrapper.querySelector('[data-task-reminder-more-toggle]');
                                if (openButton) openButton.setAttribute('aria-expanded', 'false');
                            }
                        });

                        wrapper.classList.toggle('is-open', willOpen);
                        button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                    });
                });

                document.addEventListener('click', function (event) {
                    if (!event.target.closest('.task-reminder-more')) {
                        document.querySelectorAll('.task-reminder-more.is-open').forEach(function (openWrapper) {
                            openWrapper.classList.remove('is-open');
                            const openButton = openWrapper.querySelector('[data-task-reminder-more-toggle]');
                            if (openButton) openButton.setAttribute('aria-expanded', 'false');
                        });
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
