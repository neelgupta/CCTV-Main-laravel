<x-app-layout>
    @php
        $authUser = auth()->user();
        $canCreateProject = $authUser && $authUser->hasPermission('projects.create');
        $canEditProject = $authUser && $authUser->hasPermission('projects.edit');
        $canDeleteProject = $authUser && $authUser->hasPermission('projects.delete');
        $statusClasses = [
            'on_track' => 'bg-emerald-100 text-emerald-800',
            'at_risk' => 'bg-amber-100 text-amber-800',
            'delayed' => 'bg-rose-100 text-rose-800',
            'completed' => 'bg-sky-100 text-sky-800',
        ];

        $projectsWithDates = $projects->filter(fn ($project) => $project->start_date && $project->end_date);
        $ganttStart = $projectsWithDates->min('start_date')?->copy() ?? now()->startOfMonth();
        $ganttEnd = $projectsWithDates->max('end_date')?->copy() ?? now()->endOfMonth();

        if ($ganttStart->equalTo($ganttEnd)) {
            $ganttEnd = $ganttEnd->copy()->addDays(6);
        }

        $ganttDays = $ganttStart->diffInDays($ganttEnd) + 1;
        $ganttDates = collect(range(0, $ganttDays - 1))->map(fn ($offset) => $ganttStart->copy()->addDays($offset));
        $defaultFolderStage = collect(array_keys($projectStages ?? []))
            ->first(fn ($stageKey) => $projectsByStage->get($stageKey, collect())->isNotEmpty()) ?? array_key_first($projectStages);
    @endphp
    <x-slot name="header">
        <div class="border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-950/5 backdrop-blur">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Operations</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">{{ __('Project Management') }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Kanban, folder, and gantt views in one place.</p>
                </div>
                <div class="grid grid-cols-3 gap-3">
                    <div class="bg-cyan-50 px-4 py-3 text-center ring-1 ring-cyan-100">
                        <h4 class="text-[10px] font-bold uppercase text-cyan-800">Total</h4>
                        <p class="text-xl font-extrabold text-cyan-950">{{ $projectSummary['total'] }}</p>
                    </div>
                    <div class="bg-emerald-50 px-4 py-3 text-center ring-1 ring-emerald-100">
                        <h4 class="text-[10px] font-bold uppercase text-emerald-800">Done</h4>
                        <p class="text-xl font-extrabold text-emerald-950">{{ $projectSummary['completed'] }}</p>
                    </div>
                    <div class="bg-rose-50 px-4 py-3 text-center ring-1 ring-rose-100">
                        <h4 class="text-[10px] font-bold uppercase text-rose-800">Delayed</h4>
                        <p class="text-xl font-extrabold text-rose-950">{{ $projectSummary['delayed'] }}</p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                    <div class="relative group w-full sm:w-auto">
                        <button type="button" id="toggle-project-filters"
                            aria-expanded="false"
                            aria-controls="project-filter-wrapper"
                            aria-label="Toggle project filters"
                            title="Toggle filters"
                            class="inline-flex w-full items-center justify-center border border-slate-200 bg-white px-4 py-2.5 text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 01.53 1.28l-5.78 5.78v4.19a.75.75 0 01-1.14.64l-2.5-1.67a.75.75 0 01-.34-.64v-2.52L2.22 5.28A.75.75 0 012 4.75z" clip-rule="evenodd" />
                            </svg>
                            <span class="sr-only">Toggle filters</span>
                        </button>
                        <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                            Toggle filters
                        </span>
                    </div>

                    <div class="relative group w-full sm:w-auto">
                        <button type="button" id="open-project-card-fields-modal"
                            aria-label="Card Fields"
                            title="Card Fields"
                            class="inline-flex w-full items-center justify-center border border-violet-200 bg-violet-50 px-4 py-2.5 text-violet-800 shadow-sm transition hover:bg-violet-100 sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3 4.75A1.75 1.75 0 014.75 3h10.5A1.75 1.75 0 0117 4.75v10.5A1.75 1.75 0 0115.25 17H4.75A1.75 1.75 0 013 15.25V4.75zm2 0a.25.25 0 01.25-.25h2.5v11H5.25a.25.25 0 01-.25-.25V4.75zm5.75-.25h2.5a.25.25 0 01.25.25v10.5a.25.25 0 01-.25.25h-2.5v-11z" clip-rule="evenodd" />
                            </svg>
                            <span class="sr-only">Card Fields</span>
                        </button>
                        <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                            Card Fields
                        </span>
                    </div>

                    <div class="relative group w-full sm:w-auto">
                        <a href="{{ route('projects.export', request()->query()) }}"
                            aria-label="Export"
                            title="Export"
                            class="inline-flex w-full items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 14.5a1 1 0 011 1v.5a1.5 1.5 0 001.5 1.5h9A1.5 1.5 0 0016 16v-.5a1 1 0 112 0V16a3.5 3.5 0 01-3.5 3.5h-9A3.5 3.5 0 012 16v-.5a1 1 0 011-1z" />
                                <path d="M9.293 2.293a1 1 0 011.414 0l3.5 3.5a1 1 0 01-1.414 1.414L11 6.414V13a1 1 0 11-2 0V6.414L7.207 7.207a1 1 0 01-1.414-1.414l3.5-3.5z" />
                            </svg>
                            <span class="sr-only">Export</span>
                        </a>
                        <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                            Export
                        </span>
                    </div>

                    @if($canCreateProject)
                        <div class="relative group w-full sm:w-auto">
                            <button type="button" id="open-project-import-modal"
                                aria-label="Import"
                                title="Import"
                                class="inline-flex w-full items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-cyan-800 shadow-sm transition hover:bg-cyan-100 sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M10 3.5a1 1 0 011 1V11l1.793-1.793a1 1 0 111.414 1.414l-3.5 3.5a1 1 0 01-1.414 0l-3.5-3.5a1 1 0 111.414-1.414L9 11V4.5a1 1 0 011-1z" />
                                    <path d="M3 15.5a1 1 0 011 1v.5h12v-.5a1 1 0 112 0V17a2 2 0 01-2 2H4a2 2 0 01-2-2v-.5a1 1 0 011-1z" />
                                </svg>
                                <span class="sr-only">Import</span>
                            </button>
                            <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                Import
                            </span>
                        </div>
                    @endif

                    @if($canCreateProject)
                        <div class="relative group w-full sm:w-auto">
                            <a href="{{ route('projects.create') }}"
                                aria-label="New Project"
                                title="New Project"
                                class="inline-flex w-full items-center justify-center bg-slate-950 px-4 py-2.5 text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800 sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v5.5h5.5a.75.75 0 010 1.5h-5.5v5.5a.75.75 0 01-1.5 0v-5.5h-5.5a.75.75 0 010-1.5h5.5v-5.5A.75.75 0 0110 3z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">New Project</span>
                            </a>
                            <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                New Project
                            </span>
                        </div>
                    @endif

                <div class="grid w-full grid-cols-3 overflow-hidden border border-gray-200 bg-white shadow-sm sm:inline-flex sm:w-auto" role="group" aria-label="View toggle">
                    <div class="relative group">
                        <button type="button" id="project-view-toggle-kanban" class="flex w-full items-center justify-center px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none" title="Kanban View" aria-label="Kanban View" aria-pressed="false">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 3h5v5H3V3zm0 9h5v5H3v-5zm9-9h5v5h-5V3zm0 9h5v5h-5v-5z"/>
                            </svg>
                        </button>
                        <span class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 whitespace-nowrap bg-gray-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-sm transition group-hover:opacity-100">
                            Kanban
                        </span>
                    </div>

                    <div class="relative group border-l border-gray-200">
                        <button type="button" id="project-view-toggle-list" class="flex w-full items-center justify-center px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none" title="Folder View" aria-label="Folder View" aria-pressed="false">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M2 5a2 2 0 012-2h3.172a2 2 0 011.414.586l1.828 1.828H16a2 2 0 012 2v1H2V5z" />
                                <path d="M2 9h16v6a2 2 0 01-2 2H4a2 2 0 01-2-2V9z" />
                            </svg>
                        </button>
                        <span class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 whitespace-nowrap bg-gray-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-sm transition group-hover:opacity-100">
                            Folder
                        </span>
                    </div>

                    <div class="relative group border-l border-gray-200">
                        <button type="button" id="project-view-toggle-gantt" class="flex w-full items-center justify-center px-3 py-2 bg-white text-gray-700 hover:bg-gray-50 focus:outline-none" title="Gantt View" aria-label="Gantt View" aria-pressed="false">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h7a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h10a1 1 0 110 2H4a1 1 0 01-1-1zm10-7a1 1 0 011-1h2a1 1 0 110 2h-2a1 1 0 01-1-1zm1 5a1 1 0 100 2h2a1 1 0 100-2h-2z" />
                            </svg>
                        </button>
                        <span class="pointer-events-none absolute -top-10 left-1/2 -translate-x-1/2 whitespace-nowrap bg-gray-900 px-2 py-1 text-[11px] font-semibold text-white opacity-0 shadow-sm transition group-hover:opacity-100">
                            Gantt
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    @if($canCreateProject)
        <div id="project-import-modal"
            class="{{ $errors->has('project_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50"
            aria-hidden="{{ $errors->has('project_import_file') ? 'false' : 'true' }}"
            data-modal-open="{{ $errors->has('project_import_file') ? 'true' : 'false' }}">
            <div class="absolute inset-0" data-close-project-import-modal></div>
            <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
                <div class="w-full max-w-lg border border-slate-200 bg-white p-6 shadow-2xl max-h-[calc(100vh-2rem)] overflow-y-auto">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Import Projects</p>
                            <h3 class="mt-1 text-xl font-extrabold text-slate-950">Upload your project file</h3>
                            <p class="mt-2 text-sm text-slate-500">Download the sample sheet, fill in your project details and custom field values, then upload it here.</p>
                        </div>
                        <button type="button" data-close-project-import-modal
                            class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                            aria-label="Close import modal">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>

                    <div class="mt-5 border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                        First row will contain the field names. Required columns: <span class="font-semibold">project_name, client_name, email, phone, address</span>.
                    </div>

                    @if ($errors->has('project_import_file'))
                        <div class="mt-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                            @foreach ($errors->get('project_import_file') as $message)
                                <div>{{ $message }}</div>
                            @endforeach
                        </div>
                    @endif

                    <form method="POST" action="{{ route('projects.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                        @csrf
                        <div>
                            <label for="project_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose file</label>
                            <input id="project_import_file" type="file" name="project_import_file" accept=".csv,.xls,.xlsx" class="block w-full border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                            <p class="mt-2 text-xs text-slate-500">Accepted formats: CSV, XLS, XLSX. Maximum size: 10 MB.</p>
                        </div>

                        <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <a href="{{ route('projects.import.sample') }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                                Download Sample
                            </a>
                            <button type="submit" class="inline-flex items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-800 shadow-sm transition hover:bg-cyan-100">
                                Upload File
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div id="project-card-fields-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-3 py-4"
        aria-hidden="true">
        <div class="absolute inset-0" data-close-project-card-fields-modal></div>
        <div class="relative z-10 flex w-full justify-center">
            <div class="w-full max-w-2xl max-h-[calc(100vh-2rem)] overflow-y-auto border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-700">Kanban Card Fields</p>
                        <h3 class="mt-1 text-lg font-extrabold text-slate-950 sm:text-xl">Choose what appears on project cards</h3>
                    </div>
                    <button type="button" data-close-project-card-fields-modal
                        class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close card fields modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 flex flex-col gap-3 border border-violet-100 bg-violet-50/70 px-3 py-3 text-sm text-violet-900 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <button type="button" id="project-card-fields-select-all"
                            class="inline-flex items-center justify-center border border-violet-200 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-violet-800 transition hover:bg-violet-50">
                            Select All
                        </button>
                        <button type="button" id="project-card-fields-reset"
                            class="inline-flex items-center justify-center border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                            Reset Default
                        </button>
                    </div>
                </div>

                <div id="project-card-fields-list" class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    @foreach ($projectKanbanCardFields as $field)
                        <label class="group flex cursor-pointer items-start gap-3 border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-violet-200 hover:bg-violet-50/40">
                            <input
                                type="checkbox"
                                class="mt-1 h-4 w-4 border-slate-300 text-violet-600 focus:ring-violet-500"
                                value="{{ $field['key'] }}"
                                data-project-card-field-checkbox
                                @checked((bool) ($field['default_selected'] ?? false))
                            >
                            <span class="flex-1">
                                <span class="block text-sm font-semibold text-slate-900">{{ $field['label'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="py-6" id="projects-page-content">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="mb-5">
                @if ($errors->has('project_import_file'))
                    <div class="mb-3 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        @foreach ($errors->get('project_import_file') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <div id="project-filter-wrapper" class="hidden border border-slate-200 bg-white p-4 shadow-sm">
                    <form method="GET" action="{{ route('projects.index') }}" id="project-search-form">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                    <div class="grid gap-4 lg:grid-cols-4">
                        <div class="lg:col-span-2">
                            <label for="project-search-input" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                            <div class="inline-flex w-full overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                                <div class="flex items-center px-3 text-slate-400">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0A4 4 0 015 9z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <input
                                    id="project-search-input"
                                    type="search"
                                    name="search"
                                    value="{{ $search }}"
                                    placeholder="Search by project name, reference no, or client"
                                    class="w-full border-0 bg-white px-4 py-3 text-sm text-gray-700 outline-none placeholder:text-gray-400 focus:ring-0"
                                >
                            </div>
                        </div>
                        <div>
                            <label for="project-stage-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Stage</label>
                            <select id="project-stage-filter" name="stage" class="block w-full border-slate-300 text-sm">
                                <option value="">All stages</option>
                                @foreach($projectStages as $stageKey => $stageLabel)
                                    <option value="{{ $stageKey }}" @selected(request('stage') === (string) $stageKey)>{{ is_array($stageLabel) ? ($stageLabel['name'] ?? 'Stage') : $stageLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-status-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Status</label>
                            <select id="project-status-filter" name="status" class="block w-full border-slate-300 text-sm">
                                <option value="">All statuses</option>
                                @foreach($statusOptions as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" @selected(request('status') === (string) $statusKey)>{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-manager-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Manager</label>
                            <select id="project-manager-filter" name="manager" class="block w-full border-slate-300 text-sm">
                                <option value="">All managers</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) request('manager') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-team-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Team Member</label>
                            <select id="project-team-filter" name="team_member" class="block w-full border-slate-300 text-sm">
                                <option value="">All team members</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected((string) request('team_member') === (string) $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-client-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Client</label>
                            <select id="project-client-filter" name="client" class="block w-full border-slate-300 text-sm">
                                <option value="">All clients</option>
                                @foreach($clients as $clientName)
                                    <option value="{{ $clientName }}" @selected(request('client') === (string) $clientName)>{{ $clientName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-state-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
                            <select id="project-state-filter" name="state" class="block w-full border-slate-300 text-sm">
                                <option value="">All states</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}" @selected((string) request('state') === (string) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-city-filter" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
                            <select id="project-city-filter" name="city" class="block w-full border-slate-300 text-sm">
                                <option value="">All cities</option>
                                @foreach($states as $state)
                                    @foreach($state->cities as $city)
                                        <option value="{{ $city->id }}" @selected((string) request('city') === (string) $city->id)>{{ $city->name }} ({{ $state->name }})</option>
                                    @endforeach
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="project-start-date-from" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date From</label>
                            <input id="project-start-date-from" type="date" name="start_date_from" value="{{ request('start_date_from', request('date_from')) }}" class="block w-full border-slate-300 text-sm">
                        </div>
                        <div>
                            <label for="project-start-date-to" class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date To</label>
                            <input id="project-start-date-to" type="date" name="start_date_to" value="{{ request('start_date_to', request('date_to')) }}" class="block w-full border-slate-300 text-sm">
                        </div>

                        @foreach($filterableProjectCustomFields ?? [] as $field)
                            @php
                                $customFieldValue = data_get($projectCustomFieldFilters ?? [], $field['key']) ?? request()->input('custom_fields.' . $field['key']);
                                $fieldType = $field['type'] ?? 'text';
                                $isChoiceField = in_array($fieldType, ['dropdown', 'radiobutton'], true);
                                $isWideField = in_array($fieldType, ['text', 'textarea', 'email'], true);
                            @endphp

                            <div @class(['lg:col-span-2' => $isWideField])>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</label>

                                @if($fieldType === 'checkbox')
                                    <select name="custom_fields[{{ $field['key'] }}]" class="block w-full border-slate-300 text-sm">
                                        <option value="">Any</option>
                                        <option value="1" @selected((string) $customFieldValue === '1' || $customFieldValue === true || $customFieldValue === 'true')>Checked</option>
                                        <option value="0" @selected((string) $customFieldValue === '0' || $customFieldValue === false || $customFieldValue === 'false')>Unchecked</option>
                                    </select>
                                @elseif($isChoiceField)
                                    <select name="custom_fields[{{ $field['key'] }}]" class="block w-full border-slate-300 text-sm">
                                        <option value="">Any</option>
                                        @foreach(($field['options'] ?? []) as $option)
                                            <option value="{{ $option }}" @selected((string) $customFieldValue === (string) $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif($fieldType === 'date')
                                    <input type="date" name="custom_fields[{{ $field['key'] }}]" value="{{ $customFieldValue }}" class="block w-full border-slate-300 text-sm">
                                @elseif($fieldType === 'number')
                                    <input type="number" name="custom_fields[{{ $field['key'] }}]" value="{{ $customFieldValue }}" class="block w-full border-slate-300 text-sm" placeholder="Any">
                                @else
                                    <input type="{{ $fieldType === 'email' ? 'email' : 'text' }}" name="custom_fields[{{ $field['key'] }}]" value="{{ $customFieldValue }}" class="block w-full border-slate-300 text-sm" placeholder="Any">
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <a href="{{ route('projects.index', array_filter(['tab' => $tab], fn ($value) => filled($value))) }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Clear
                        </a>
                        <button type="submit" class="inline-flex items-center justify-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                            Apply Filters
                        </button>
                    </div>
                    </form>
                </div>
            </div>
            <div id="projects-kanban">
                <div
                    id="projects-kanban-scroll"
                    tabindex="0"
                    class="flex overflow-x-auto gap-4 items-start pb-4 outline-none"
                >
                    @foreach($projectStages as $stageKey => $stageLabel)
                        @php
                            $stageData = is_array($stageLabel) ? $stageLabel : ['name' => $stageLabel, 'color' => '#94a3b8'];
                        @endphp
                        <div class="flex-shrink-0 w-full min-w-full max-w-full snap-start border shadow-sm p-3 flex flex-col sm:w-80 sm:min-w-[320px] sm:max-w-[320px] sm:p-4"
                             style="background-color: {{ $stageData['color'] ?? '#94a3b8' }}15; border-color: {{ $stageData['color'] ?? '#94a3b8' }}40;">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="font-bold text-gray-700 uppercase tracking-wider text-sm">
                                    {{ $stageData['name'] ?? 'Stage' }}
                                    <span class="ml-2 bg-gray-200 text-gray-600 px-2 py-1 text-xs" data-count-badge>
                                        {{ $projectsByStage->get($stageKey, collect())->count() }}
                                    </span>
                                </h3>
                                <div class="w-3 h-3" style="background-color: {{ $stageData['color'] ?? '#94a3b8' }}"></div>
                            </div>

                            <div class="project-kanban-column space-y-3 h-[68vh] max-h-[68vh] overflow-y-auto pr-1 pb-3" data-stage="{{ $stageKey }}">
                                @foreach($projectsByStage->get($stageKey, collect()) as $project)
                                    @php
                                        $latestDrawing = $project->drawings->sortByDesc('created_at')->first();
                                        $teamMemberAssignees = collect($project->team_member_ids ?? [])
                                            ->map(function ($memberId) use ($projectTeamMembersById) {
                                                $member = $projectTeamMembersById->get((int) $memberId);

                                                if (! $member) {
                                                    return null;
                                                }

                                                return [
                                                    'name' => (string) $member->name,
                                                ];
                                            })
                                            ->filter(fn ($user) => filled($user['name'] ?? null))
                                            ->values()
                                            ->all();
                                        $projectAssignees = collect()
                                            ->merge($project->manager?->name ? [[
                                                'name' => (string) $project->manager->name,
                                            ]] : [])
                                            ->merge($teamMemberAssignees)
                                            ->unique('name')
                                            ->values()
                                            ->all();
                                        $projectFieldValues = [
                                            'reference_no' => $project->reference_no,
                                            'client_name' => $project->client_name,
                                            'lead' => $project->lead?->client_name,
                                            'contractor' => $project->contractor?->name,
                                            'manager' => $project->manager?->name,
                                            'assignees' => $projectAssignees,
                                            'status' => $project->status,
                                            'current_stage' => $project->stage_label,
                                            'start_date' => $project->start_date?->format('d M Y'),
                                            'end_date' => $project->end_date?->format('d M Y'),
                                            'completion_percentage' => $project->completion_percentage !== null ? (string) $project->completion_percentage : null,
                                            'commission_type' => $project->commission_type,
                                            'commission_value' => $project->commission_value !== null ? number_format((float) $project->commission_value, 2) : null,
                                            'state' => $project->state?->name,
                                            'city' => $project->city?->name,
                                            'created_at' => $project->created_at?->diffForHumans(),
                                            'drawings_count' => (string) $project->drawings->count(),
                                            'materials_count' => (string) ($project->materials_count ?? 0),
                                            'productions_count' => (string) ($project->productions_count ?? 0),
                                            'amcs_count' => (string) ($project->amcs_count ?? 0),
                                        ];
                                    @endphp
                                     <div class="project-kanban-item bg-white p-3 shadow cursor-pointer hover:shadow-md transition sm:p-4"
                                         data-status-url="{{ route('projects.stage.update', $project) }}"
                                          data-completed-amc-url="{{ route('projects.completed-amc.store', $project) }}"
                                          data-project-url="{{ route('projects.show', $project) }}"
                                          data-project-name="{{ $project->name }}"
                                          data-current-stage="{{ $project->current_stage }}"
                                          data-amc-count="{{ $project->amcs_count ?? 0 }}"
                                          data-drawing-count="{{ $project->drawings->count() }}"
                                          data-material-count="{{ $project->materials_count ?? 0 }}"
                                          data-production-count="{{ $project->productions_count ?? 0 }}"
                                          data-production-qa-done-count="{{ $project->productions_qa_done_count ?? 0 }}"
                                          data-drawing-file-name="{{ $latestDrawing?->file_name ?? '' }}"
                                          data-drawing-version="{{ $latestDrawing?->version ?? '' }}"
                                          data-drawing-status="{{ $latestDrawing?->drawing_status ?? '' }}"
                                          data-drawing-added-at="{{ $latestDrawing?->created_at?->format('d M Y, h:i A') ?? '' }}"
                                          data-project-field-values='{{ \Illuminate\Support\Js::encode($projectFieldValues) }}'
                                          data-project-custom-fields='{{ \Illuminate\Support\Js::encode($project->custom_fields ?? []) }}'
                                         onclick="window.location='{{ route('projects.show', $project) }}'">
                                        <div class="flex items-start justify-between gap-3">
                                            <h4 class="min-w-0 flex-1 break-words text-sm font-semibold text-gray-900 sm:text-base">{{ $project->name }}</h4>
                                            <div class="hidden items-center gap-2 sm:flex" onclick="event.stopPropagation();">
                                                @if($canEditProject)
                                                <a href="{{ route('projects.edit', $project) }}" class="inline-flex items-center justify-center w-7 h-7 bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="Edit" onclick="event.stopPropagation();">
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                </a>
                                                @endif
                                                @if($canDeleteProject)
                                                <form method="POST" action="{{ route('projects.destroy', $project) }}" class="project-delete-form" onclick="event.stopPropagation();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex items-center justify-center w-7 h-7 bg-red-50 text-red-700 hover:bg-red-100" title="Delete" onclick="event.stopPropagation();">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                    </button>
                                                </form>
                                                @endif
                                            </div>
                                        </div>
                                        <div class="mt-3 space-y-2" data-project-fields-rendered-group></div>
                                        @if($canEditProject || $canDeleteProject)
                                        <div class="mt-3 grid grid-cols-2 gap-2 sm:hidden" onclick="event.stopPropagation();">
                                            @if($canEditProject)
                                            <a href="{{ route('projects.edit', $project) }}" class="inline-flex items-center justify-center gap-1 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700" title="Edit" onclick="event.stopPropagation();">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                Edit
                                            </a>
                                            @endif
                                            @if($canDeleteProject)
                                            <form method="POST" action="{{ route('projects.destroy', $project) }}" class="project-delete-form" onclick="event.stopPropagation();">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" title="Delete" onclick="event.stopPropagation();">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                    Delete
                                                </button>
                                            </form>
                                            @endif
                                        </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div id="projects-list" class="hidden">
                <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                        <h3 class="text-base font-semibold text-gray-900">Project Explorer</h3>
                        <p class="text-sm text-gray-500 mt-1">Browse projects like Windows folders. Open a stage folder to see the projects inside it.</p>
                    </div>

                    <div id="folder-view-home" class="p-5 bg-gradient-to-b from-white to-slate-50">
                        <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-5 xl:grid-cols-6">
                            @foreach($projectStages as $stageKey => $stageLabel)
                                @php
                                    $stageData = is_array($stageLabel) ? $stageLabel : ['name' => $stageLabel, 'color' => '#94a3b8'];
                                    $stageProjects = $projectsByStage->get($stageKey, collect());
                                @endphp
                                <button
                                    type="button"
                                    class="project-folder-tile group p-3 text-left transition hover:bg-blue-50"
                                    data-folder-stage="{{ $stageKey }}"
                                    aria-pressed="{{ $stageKey === $defaultFolderStage ? 'true' : 'false' }}"
                                >
                                    <div class="mx-auto flex h-20 w-20 items-center justify-center">
                                        <svg viewBox="0 0 64 52" class="h-16 w-16 drop-shadow-sm" aria-hidden="true">
                                            <path d="M4 12C4 8.686 6.686 6 10 6h13.5c2.21 0 4.316.975 5.74 2.662L32 12h22c3.314 0 6 2.686 6 6v4H4v-10z" fill="#F8D873"/>
                                            <path d="M4 18h56v22c0 3.314-2.686 6-6 6H10c-3.314 0-6-2.686-6-6V18z" fill="#FFD24D"/>
                                            <path d="M8 20h48v4H8z" fill="#FFE38D" opacity="0.65"/>
                                        </svg>
                                    </div>
                                    <div class="mt-2 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <span class="inline-flex w-2.5 h-2.5" style="background-color: {{ $stageData['color'] ?? '#94a3b8' }}"></span>
                                            <p class="text-sm font-medium text-gray-900">{{ $stageData['name'] ?? 'Stage' }}</p>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500">{{ $stageProjects->count() }} {{ \Illuminate\Support\Str::plural('project', $stageProjects->count()) }}</p>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div id="folder-view-stage" class="hidden">
                        <div class="border-t border-gray-200 bg-white px-5 py-4">
                            <div class="flex items-center justify-between gap-4">
                                <div class="flex items-center gap-3 min-w-0">
                                    <button id="folder-view-back" type="button" class="inline-flex items-center gap-2 bg-white border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M7.707 14.707a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l2.293 2.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                                        </svg>
                                        Back
                                    </button>
                                    <div class="min-w-0">
                                        <h4 id="folder-view-stage-title" class="text-sm font-semibold text-gray-900 truncate">
                                            {{ $projectStages[$defaultFolderStage]['name'] ?? 'Projects' }}
                                        </h4>
                                        <p id="folder-view-stage-count" class="mt-1 text-xs text-gray-500">
                                            {{ $projectsByStage->get($defaultFolderStage, collect())->count() }} {{ \Illuminate\Support\Str::plural('project', $projectsByStage->get($defaultFolderStage, collect())->count()) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="text-xs text-gray-400 whitespace-nowrap">Stage folder</div>
                            </div>
                        </div>

                        <div class="p-5 bg-slate-50 min-h-[320px]">
                            @foreach($projectStages as $stageKey => $stageLabel)
                                @php
                                    $stageData = is_array($stageLabel) ? $stageLabel : ['name' => $stageLabel, 'color' => '#94a3b8'];
                                    $stageProjects = $projectsByStage->get($stageKey, collect());
                                @endphp
                                <div class="project-folder-panel {{ $stageKey === $defaultFolderStage ? '' : 'hidden' }}" data-folder-panel="{{ $stageKey }}">
                                    @if($stageProjects->isEmpty())
                                        <div class="flex min-h-[220px] items-center justify-center border border-dashed border-gray-300 bg-white text-sm text-gray-500">
                                            No projects inside this folder yet.
                                        </div>
                                    @else
                                        <div class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                            @foreach($stageProjects as $project)
                                                <div class="group relative border border-transparent p-3 hover:border-blue-200 hover:bg-white hover:shadow-sm transition">
                                                    <a href="{{ route('projects.show', $project) }}" class="block">
                                                        <div class="mx-auto flex h-20 w-20 items-center justify-center">
                                                            <svg viewBox="0 0 64 52" class="h-16 w-16 drop-shadow-sm" aria-hidden="true">
                                                                <path d="M4 12C4 8.686 6.686 6 10 6h13.5c2.21 0 4.316.975 5.74 2.662L32 12h22c3.314 0 6 2.686 6 6v4H4v-10z" fill="#F8D873"/>
                                                                <path d="M4 18h56v22c0 3.314-2.686 6-6 6H10c-3.314 0-6-2.686-6-6V18z" fill="#FFD24D"/>
                                                                <path d="M8 20h48v4H8z" fill="#FFE38D" opacity="0.65"/>
                                                            </svg>
                                                        </div>
                                                        <div class="mt-3 text-center">
                                                            <p class="text-sm font-medium leading-5 text-gray-900 break-words">{{ $project->name }}</p>
                                                            <p class="mt-1 text-xs text-gray-500">{{ $project->reference_no }}</p>
                                                            <p class="mt-1 text-xs text-gray-500">{{ $project->client_name ?: 'No client' }}</p>
                                                        </div>
                                                    </a>
                                                    <div class="mt-3 flex flex-wrap justify-center gap-2">
                                                        <span class="inline-flex items-center px-2 py-1 text-[11px] font-semibold {{ $statusClasses[$project->status] ?? 'bg-gray-100 text-gray-700' }}">
                                                            {{ $statusOptions[$project->status] ?? str($project->status)->headline() }}
                                                        </span>
                                                        <span class="inline-flex items-center bg-slate-200 px-2 py-1 text-[11px] font-medium text-slate-700">
                                                            {{ $project->completion_percentage }}%
                                                        </span>
                                                    </div>
                                                    <div class="mt-3 flex items-center justify-center gap-2 opacity-0 transition group-hover:opacity-100">
                                                        <a href="{{ route('projects.edit', $project) }}" class="inline-flex items-center justify-center w-8 h-8 bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="Edit">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path d="M17.414 2.586a2 2 0 010 2.828l-1.086 1.086-2.828-2.828 1.086-1.086a2 2 0 012.828 0z" />
                                                                <path d="M2 13.5V18h4.5l9.793-9.793-2.828-2.828L2 13.5z" />
                                                            </svg>
                                                        </a>
                                                        <form method="POST" action="{{ route('projects.destroy', $project) }}" class="project-delete-form">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="inline-flex items-center justify-center w-8 h-8 bg-red-50 text-red-700 hover:bg-red-100" title="Delete">
                                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                    <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 100 2h.293l.853 10.66A2 2 0 007.14 18h5.72a2 2 0 001.994-1.34L15.707 6H16a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zm2 6a1 1 0 10-2 0v7a1 1 0 102 0V8zm-4 0a1 1 0 10-2 0v7a1 1 0 102 0V8zm6 0a1 1 0 10-2 0v7a1 1 0 102 0V8z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div id="projects-gantt" class="hidden">
                <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-5 py-4 border-b border-gray-100 bg-gradient-to-r from-slate-50 to-white">
                        <h3 class="text-base font-semibold text-gray-900">Project Gantt Chart</h3>
                        <p class="text-sm text-gray-500 mt-1">Timeline based on each project&apos;s start and deadline dates.</p>
                    </div>

                    @if($projects->isEmpty())
                        <div class="px-5 py-10 text-center text-sm text-gray-500">No projects available yet.</div>
                    @elseif($projectsWithDates->isEmpty())
                        <div class="px-5 py-10 text-center text-sm text-gray-500">Add both a start date and deadline to projects to display the Gantt chart.</div>
                    @else
                        <div class="overflow-x-auto">
                            <div class="min-w-max">
                                <div class="grid border-b border-gray-200 bg-slate-50" style="grid-template-columns: 260px repeat({{ $ganttDays }}, minmax(42px, 1fr));">
                                    <div class="sticky left-0 z-10 bg-slate-50 border-r border-gray-200 px-4 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                        Project
                                    </div>
                                    @foreach($ganttDates as $date)
                                        <div class="px-2 py-3 text-center border-l border-gray-200">
                                            <p class="text-[10px] uppercase tracking-wide text-gray-400">{{ $date->format('M') }}</p>
                                            <p class="text-xs font-semibold text-gray-700">{{ $date->format('d') }}</p>
                                        </div>
                                    @endforeach
                                </div>

                                @foreach($projects as $project)
                                    @php
                                        $hasTimeline = $project->start_date && $project->end_date;
                                        $startOffset = $hasTimeline ? $ganttStart->diffInDays($project->start_date) : 0;
                                        $durationDays = $hasTimeline ? max($project->start_date->diffInDays($project->end_date) + 1, 1) : 1;
                                        $projectStage = $projectStages[$project->current_stage] ?? ['name' => $project->stage_label, 'color' => '#94a3b8'];
                                        $projectStageColor = $projectStage['color'] ?? '#94a3b8';
                                        $projectStageName = $projectStage['name'] ?? $project->stage_label;
                                    @endphp
                                    <div class="grid border-b border-gray-100" style="grid-template-columns: 260px repeat({{ $ganttDays }}, minmax(42px, 1fr));">
                                        <div class="sticky left-0 z-10 border-r border-gray-200 bg-white px-4 py-4">
                                            <a href="{{ route('projects.show', $project) }}" class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">
                                                {{ $project->name }}
                                            </a>
                                            <p class="text-xs text-gray-500 mt-1">{{ $project->reference_no }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ $projectStageName }}</p>
                                        </div>

                                        @if($hasTimeline)
                                            <div class="relative px-0 py-3" style="grid-column: 2 / -1;">
                                                <div class="absolute inset-0 grid" style="grid-template-columns: repeat({{ $ganttDays }}, minmax(42px, 1fr));">
                                                    @foreach($ganttDates as $date)
                                                        <div class="border-l border-gray-100 {{ $date->isWeekend() ? 'bg-slate-50/70' : 'bg-white' }}"></div>
                                                    @endforeach
                                                </div>
                                                <div class="relative grid h-full items-center" style="grid-template-columns: repeat({{ $ganttDays }}, minmax(42px, 1fr));">
                                                    <div class="h-10 opacity-80 shadow-sm flex items-center px-3 text-white text-xs font-semibold overflow-hidden whitespace-nowrap"
                                                         style="background-color: {{ $projectStageColor }}; grid-column: {{ $startOffset + 1 }} / span {{ $durationDays }};">
                                                        {{ $project->start_date->format('d M') }} to {{ $project->end_date->format('d M') }}
                                                    </div>
                                                </div>
                                            </div>
                                        @else
                                            <div class="px-4 py-4 text-sm text-gray-400" style="grid-column: 2 / -1;">
                                                Missing start date or deadline.
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div id="project-stage-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-6 sm:py-8">
        <div class="absolute inset-0 bg-gray-900/50"></div>
        <div class="relative z-10 w-full flex justify-center">
            <div class="w-full max-w-lg max-h-[calc(100dvh-3rem)] overflow-hidden border border-gray-200 bg-white shadow-xl">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <div>
                        <h3 id="project-stage-modal-title" class="text-base font-semibold text-gray-900">Move Project To Material</h3>
                        <p id="project-stage-modal-subtitle" class="mt-1 text-sm text-gray-600">Confirm this stage change.</p>
                    </div>
                    <button id="project-stage-modal-close" type="button" class="text-gray-400 hover:text-gray-600">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
                <div class="max-h-[calc(100dvh-11rem)] overflow-y-auto px-4 py-4 space-y-3 sm:px-5">
                    <div class="border border-indigo-100 bg-indigo-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-700">Project</p>
                        <p id="project-stage-modal-project-name" class="mt-1 text-sm font-semibold text-indigo-900"></p>
                    </div>
                    <div class="grid grid-cols-2 gap-3 text-sm">
                        <div class="border border-gray-200 px-3 py-2">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Drawings Uploaded</p>
                            <p id="project-stage-modal-drawing-count" class="mt-1 font-semibold text-gray-900"></p>
                        </div>
                        <div class="border border-gray-200 px-3 py-2">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Materials Added</p>
                            <p id="project-stage-modal-material-count" class="mt-1 font-semibold text-gray-900"></p>
                        </div>
                        <div class="border border-gray-200 px-3 py-2">
                            <p class="text-xs uppercase tracking-wider text-gray-500">Production Updates</p>
                            <p id="project-stage-modal-production-count" class="mt-1 font-semibold text-gray-900"></p>
                        </div>
                        <div class="border border-gray-200 px-3 py-2">
                            <p class="text-xs uppercase tracking-wider text-gray-500">QA Done</p>
                            <p id="project-stage-modal-qa-done-count" class="mt-1 font-semibold text-gray-900"></p>
                        </div>
                    </div>
                    <div class="border border-gray-200 px-4 py-3 space-y-2">
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Latest Drawing File</p>
                            <p id="project-stage-modal-drawing-file" class="mt-1 text-sm font-semibold text-gray-900"></p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <p class="text-xs uppercase tracking-wider text-gray-500">Latest Version</p>
                                <p id="project-stage-modal-drawing-version" class="mt-1 font-semibold text-gray-900"></p>
                            </div>
                            <div>
                                <p class="text-xs uppercase tracking-wider text-gray-500">Drawing Status</p>
                                <p id="project-stage-modal-drawing-status" class="mt-1 font-semibold text-gray-900"></p>
                            </div>
                        </div>
                        <div>
                            <p class="text-xs uppercase tracking-wider text-gray-500">Uploaded On</p>
                            <p id="project-stage-modal-drawing-added-at" class="mt-1 font-semibold text-gray-900"></p>
                        </div>
                    </div>
                    <p id="project-stage-modal-empty-note" class="hidden border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        No drawing details were found for this project. You can still continue if this move is intentional.
                    </p>
                    <p id="project-stage-modal-material-note" class="hidden border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        No materials are added yet. Add materials before moving to the next stage.
                    </p>
                </div>
                <div class="flex justify-end gap-2 border-t border-gray-100 px-4 py-3 sm:px-5">
                    <button id="project-stage-modal-cancel" type="button" class="inline-flex items-center border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                        Cancel
                    </button>
                    <button id="project-stage-modal-confirm" type="button" class="inline-flex items-center border border-transparent bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700">
                        Confirm Move
                    </button>
                </div>
            </div>
        </div>
    </div>
    <style>
        #projects-kanban-scroll {
            cursor: grab;
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior-x: contain;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        #projects-kanban-scroll:active {
            cursor: grabbing;
        }

        .project-kanban-column {
            min-height: 68vh;
            touch-action: pan-y;
            user-select: none;
            -webkit-user-select: none;
            scrollbar-gutter: stable;
        }

        #projects-kanban-scroll .project-kanban-item {
            touch-action: manipulation;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
            will-change: transform;
        }

        body.project-kanban-dragging,
        body.project-kanban-dragging #projects-kanban-scroll,
        body.project-kanban-dragging #projects-kanban-scroll * {
            cursor: grabbing !important;
            user-select: none !important;
            -webkit-user-select: none !important;
        }

        body.project-kanban-dragging #projects-kanban-scroll {
            scroll-behavior: auto;
        }

        .sortable-fallback {
            transform: rotate(2deg) scale(1.02) !important;
            opacity: 0.92 !important;
            pointer-events: none !important;
            user-select: none;
            -webkit-user-select: none;
            box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.22), 0 8px 16px -4px rgba(0, 0, 0, 0.1) !important;
            border-radius: 6px !important;
            z-index: 99999 !important;
        }

        .sortable-ghost {
            background: #e0e7ff !important;
            border: 2px dashed #818cf8 !important;
            box-shadow: none !important;
            opacity: 0.6;
            border-radius: 6px;
        }

        .sortable-ghost > * {
            visibility: hidden;
        }

        .sortable-chosen,
        .sortable-drag {
            user-select: none;
            -webkit-user-select: none;
        }
        </style>
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterToggle = document.getElementById('toggle-project-filters');
            const filterWrapper = document.getElementById('project-filter-wrapper');
            const filterForm = document.getElementById('project-search-form');

            if (filterToggle && filterWrapper && filterForm) {
                const hasActiveFilters = Array.from(filterForm.querySelectorAll('input, select, textarea'))
                    .some((field) => {
                        if (field.type === 'hidden') {
                            return false;
                        }

                        return String(field.value ?? '').trim() !== '';
                    });

                const syncToggle = (open) => {
                    filterWrapper.classList.toggle('hidden', !open);
                    filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                syncToggle(hasActiveFilters);

                filterToggle.addEventListener('click', () => {
                    syncToggle(filterWrapper.classList.contains('hidden'));
                });
            }

            const modal = document.getElementById('project-import-modal');
            const openButton = document.getElementById('open-project-import-modal');

            if (!modal || !openButton) {
                return;
            }

            const closeButtons = modal.querySelectorAll('[data-close-project-import-modal]');

            function openModal() {
                modal.classList.remove('hidden');
                modal.setAttribute('aria-hidden', 'false');
                document.body.classList.add('overflow-hidden');
            }

            function closeModal() {
                modal.classList.add('hidden');
                modal.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('overflow-hidden');
            }

            openButton.addEventListener('click', openModal);
            closeButtons.forEach((button) => button.addEventListener('click', closeModal));

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                    closeModal();
                }
            });

            if (modal.dataset.modalOpen === 'true') {
                openModal();
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const kanbanWrapper = document.getElementById('projects-kanban-scroll');
            const stageLabels = @js($projectStages);
            const completedStageAmcPlans = @js(
                $completedStageAmcPlans->map(fn ($plan) => [
                    'id' => (string) $plan->id,
                    'name' => $plan->name,
                    'duration_months' => (int) $plan->duration_months,
                    'visit_count' => (int) $plan->visit_count,
                    'price' => (float) $plan->price,
                    'description' => $plan->description,
                    'service_mode' => $plan->service_mode ?: 'scheduled',
                ])->values()
            );
            const projectStageModal = document.getElementById('project-stage-modal');
            const projectStageModalClose = document.getElementById('project-stage-modal-close');
            const projectStageModalCancel = document.getElementById('project-stage-modal-cancel');
            const projectStageModalConfirm = document.getElementById('project-stage-modal-confirm');
            const projectCardFieldsModal = document.getElementById('project-card-fields-modal');
            const projectCardFieldsOpenButton = document.getElementById('open-project-card-fields-modal');
            const projectCardFieldsSelectAll = document.getElementById('project-card-fields-select-all');
            const projectCardFieldsReset = document.getElementById('project-card-fields-reset');
            const projectCardFieldCheckboxes = Array.from(document.querySelectorAll('[data-project-card-field-checkbox]'));
            const projectCardFieldOptions = @js($projectKanbanCardFields);
            const projectCardFieldPreferenceKey = `projectKanbanCardFields:${@js($authUser?->company_id ?? 'global')}:${@js($authUser?->id ?? 'guest')}`;
            const projectStatusToneClasses = @js($statusClasses);
            const projectsPageContent = document.getElementById('projects-page-content');
            let pendingStageChange = null;

            function lockProjectBackground() {
                document.body.classList.add('overflow-hidden');

                if (projectsPageContent) {
                    projectsPageContent.classList.add('pointer-events-none', 'select-none');
                    projectsPageContent.setAttribute('aria-hidden', 'true');
                    projectsPageContent.setAttribute('inert', '');
                }
            }

            function unlockProjectBackground() {
                document.body.classList.remove('overflow-hidden');

                if (projectsPageContent) {
                    projectsPageContent.classList.remove('pointer-events-none', 'select-none');
                    projectsPageContent.removeAttribute('aria-hidden');
                    projectsPageContent.removeAttribute('inert');
                }
            }

            function readProjectCardFieldPreferences() {
                try {
                    const raw = window.localStorage.getItem(projectCardFieldPreferenceKey);
                    if (!raw) return null;

                    const parsed = JSON.parse(raw);
                    return Array.isArray(parsed) ? parsed : null;
                } catch (error) {
                    return null;
                }
            }

            function writeProjectCardFieldPreferences(selectedKeys) {
                try {
                    window.localStorage.setItem(projectCardFieldPreferenceKey, JSON.stringify(selectedKeys));
                } catch (error) {}
            }

            function defaultProjectCardFieldKeys() {
                return projectCardFieldOptions
                    .filter((field) => field.default_selected)
                    .map((field) => String(field.key));
            }

            function availableProjectCardFieldKeys() {
                return new Set(projectCardFieldOptions.map((field) => String(field.key)));
            }

            function normalizeProjectCardFieldKeys(rawKeys) {
                const availableKeys = availableProjectCardFieldKeys();
                const filtered = Array.isArray(rawKeys)
                    ? rawKeys.map((key) => String(key)).filter((key) => availableKeys.has(key))
                    : [];

                if (Array.isArray(rawKeys) && rawKeys.length > 0 && filtered.length === 0) {
                    return defaultProjectCardFieldKeys();
                }

                return filtered;
            }

            function getActiveProjectCardFieldKeys() {
                const storedKeys = readProjectCardFieldPreferences();
                if (storedKeys === null) {
                    return defaultProjectCardFieldKeys();
                }

                return normalizeProjectCardFieldKeys(storedKeys);
            }

            function syncProjectCardFieldCheckboxes(selectedKeys) {
                const selected = new Set(selectedKeys);
                projectCardFieldCheckboxes.forEach((checkbox) => {
                    checkbox.checked = selected.has(String(checkbox.value));
                });
            }

            function formatProjectCardFieldValue(fieldKey, fieldValue, rawFieldData = {}) {
                if (fieldValue === null || fieldValue === undefined) {
                    return null;
                }

                if (Array.isArray(fieldValue)) {
                    const values = fieldValue.map((value) => String(value).trim()).filter(Boolean);
                    if (!values.length) return null;
                    return values.join(', ');
                }

                const textValue = String(fieldValue).trim();
                if (textValue === '') {
                    return null;
                }

                if (fieldKey === 'status') {
                    return textValue.replace(/_/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
                }

                if (fieldKey === 'completion_percentage') {
                    return `${textValue}%`;
                }

                if (fieldKey === 'commission_value') {
                    return `₹${textValue}`;
                }

                if (fieldKey.startsWith('custom:')) {
                    const customType = String(rawFieldData.type || 'text');
                    if (customType === 'checkbox') {
                        const normalized = textValue.toLowerCase();
                        if (['1', 'true', 'yes', 'on'].includes(normalized)) return 'Yes';
                        if (['0', 'false', 'no', 'off'].includes(normalized)) return 'No';
                    }

                    return textValue;
                }

                return textValue;
            }

            function fieldVariantClass(fieldKey, formattedValue) {
                if (fieldKey === 'status') {
                    const normalized = String(formattedValue || '').toLowerCase();
                    if (normalized === 'completed') return projectStatusToneClasses.completed || 'bg-sky-100 text-sky-800';
                    if (normalized === 'delayed') return projectStatusToneClasses.delayed || 'bg-rose-100 text-rose-800';
                    if (normalized === 'at risk') return projectStatusToneClasses.at_risk || 'bg-amber-100 text-amber-800';
                    return projectStatusToneClasses.on_track || 'bg-emerald-100 text-emerald-800';
                }

                if (fieldKey === 'reference_no') {
                    return 'text-indigo-700';
                }

                return 'text-slate-900';
            }

            function createProjectCardValueNode(fieldKey, formattedValue, rawValue) {
                if (fieldKey === 'status') {
                    const badge = document.createElement('span');
                    badge.className = `inline-flex items-center px-2 py-1 text-[10px] font-semibold capitalize ${fieldVariantClass(fieldKey, formattedValue)}`;
                    badge.textContent = formattedValue;
                    return badge;
                }

                const value = document.createElement('span');
                value.className = `${fieldVariantClass(fieldKey, formattedValue)} break-words`;
                value.textContent = formattedValue;
                return value;
            }

            function createProjectCardAvatar(name, colorClass = 'bg-indigo-500') {
                const avatar = document.createElement('div');
                avatar.className = `inline-flex h-6 w-6 items-center justify-center ring-2 ring-white text-[10px] font-semibold text-white ${colorClass}`;
                avatar.textContent = String(name || '?').trim().charAt(0).toUpperCase() || '?';
                return avatar;
            }

            function createProjectCardStaffNode(assignees = []) {
                const wrap = document.createElement('div');
                wrap.className = 'flex min-w-0 items-center -space-x-2 overflow-visible';

                const staffItems = [];
                (Array.isArray(assignees) ? assignees : []).forEach((staff, index) => {
                    if (!staff || typeof staff !== 'object') {
                        const normalized = String(staff || '').trim();
                        if (normalized && !staffItems.some((item) => item.name === normalized)) {
                            staffItems.push({
                                name: normalized,
                                colorClass: index === 0 ? 'bg-indigo-500' : 'bg-slate-500',
                            });
                        }
                        return;
                    }

                    const name = String(staff.name || '').trim();
                    if (!name || staffItems.some((item) => item.name === name)) {
                        return;
                    }

                    staffItems.push({
                        name,
                        colorClass: index === 0 ? 'bg-indigo-500' : 'bg-slate-500',
                    });
                });

                staffItems.slice(0, 3).forEach((staff, index) => {
                    const avatarWrap = document.createElement('div');
                    avatarWrap.className = 'group relative inline-flex flex-shrink-0';
                    avatarWrap.appendChild(createProjectCardAvatar(staff.name, index === 0 ? staff.colorClass : 'bg-slate-500'));

                    const tooltip = document.createElement('span');
                    tooltip.className = 'pointer-events-none absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 whitespace-nowrap border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 opacity-0 shadow-lg transition group-hover:opacity-100';
                    tooltip.textContent = staff.name;
                    avatarWrap.appendChild(tooltip);

                    wrap.appendChild(avatarWrap);
                });

                if (staffItems.length > 3) {
                    const overflowWrap = document.createElement('div');
                    overflowWrap.className = 'group relative inline-flex flex-shrink-0';
                    overflowWrap.appendChild(createProjectCardAvatar(`+${staffItems.length - 3}`, 'bg-slate-500'));

                    const overflowTooltip = document.createElement('span');
                    overflowTooltip.className = 'pointer-events-none absolute left-1/2 top-full z-20 mt-2 -translate-x-1/2 whitespace-nowrap border border-slate-200 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 opacity-0 shadow-lg transition group-hover:opacity-100';
                    overflowTooltip.textContent = staffItems.slice(3).map((staff) => staff.name).join(', ');
                    overflowWrap.appendChild(overflowTooltip);

                    wrap.appendChild(overflowWrap);
                }

                if (!staffItems.length) {
                    return null;
                }

                return wrap;
            }

            function renderProjectCardFieldGroups() {
                const selectedKeys = new Set(getActiveProjectCardFieldKeys());
                const cards = document.querySelectorAll('#projects-kanban .project-kanban-item');

                cards.forEach((card) => {
                    const rawValues = (() => {
                        try {
                            const parsed = JSON.parse(card.getAttribute('data-project-field-values') || '{}');
                            return parsed && typeof parsed === 'object' ? parsed : {};
                        } catch (error) {
                            return {};
                        }
                    })();

                    const rawCustomValues = (() => {
                        try {
                            const parsed = JSON.parse(card.getAttribute('data-project-custom-fields') || '{}');
                            return parsed && typeof parsed === 'object' ? parsed : {};
                        } catch (error) {
                            return {};
                        }
                    })();

                    let fieldGroup = card.querySelector('[data-project-fields-rendered-group]');
                    if (!fieldGroup) {
                        fieldGroup = document.createElement('div');
                        fieldGroup.setAttribute('data-project-fields-rendered-group', '1');
                        fieldGroup.className = 'mt-3 space-y-2';
                        card.appendChild(fieldGroup);
                    }

                    fieldGroup.innerHTML = '';

                    projectCardFieldOptions.forEach((fieldDefinition) => {
                        const fieldKey = String(fieldDefinition.key || '');
                        if (!fieldKey || !selectedKeys.has(fieldKey)) return;
                        if (['manager', 'team_members', 'status'].includes(fieldKey)) return;

                        let rawValue = null;
                        let rawFieldData = fieldDefinition;

                        if (fieldKey.startsWith('custom:')) {
                            const customKey = fieldKey.slice('custom:'.length);
                            rawValue = Object.prototype.hasOwnProperty.call(rawCustomValues, customKey)
                                ? rawCustomValues[customKey]
                                : null;
                        } else {
                            rawValue = Object.prototype.hasOwnProperty.call(rawValues, fieldKey)
                                ? rawValues[fieldKey]
                                : null;
                        }

                        const formattedValue = formatProjectCardFieldValue(fieldKey, rawValue, rawFieldData);
                        if (formattedValue === null || formattedValue === '') return;

                        const row = document.createElement('div');
                        row.className = 'text-xs';
                        row.dataset.kanbanField = '1';
                        row.dataset.fieldKey = fieldKey;
                        row.dataset.fieldHasValue = '1';

                        const label = document.createElement('span');
                        label.className = 'font-semibold uppercase tracking-wide text-slate-500';
                        label.textContent = fieldDefinition.label || fieldKey;

                        const valueWrap = document.createElement('div');
                        valueWrap.className = 'text-right font-semibold text-slate-900';
                        valueWrap.appendChild(createProjectCardValueNode(fieldKey, formattedValue, rawValue));

                        const rowInner = document.createElement('div');
                        rowInner.className = 'flex items-start justify-between gap-3';
                        rowInner.appendChild(label);
                        rowInner.appendChild(valueWrap);
                        row.appendChild(rowInner);
                        fieldGroup.appendChild(row);
                    });

                    const assignees = Array.isArray(rawValues.assignees) ? rawValues.assignees : [];
                    const statusValue = selectedKeys.has('status')
                        ? (Object.prototype.hasOwnProperty.call(rawValues, 'status') ? rawValues.status : null)
                        : null;

                    const footerItems = [];
                    if (assignees.length) {
                        footerItems.push('staff');
                    }
                    if (statusValue) {
                        footerItems.push('status');
                    }

                    if (footerItems.length) {
                        const footerRow = document.createElement('div');
                        footerRow.className = 'mt-3 flex items-center justify-between gap-3 border-t border-slate-100 pt-3';
                        footerRow.setAttribute('data-project-fields-footer', '1');

                        const leftSide = document.createElement('div');
                        leftSide.className = 'min-w-0 flex-1';

                        const rightSide = document.createElement('div');
                        rightSide.className = 'flex-shrink-0';

                        if (assignees.length) {
                            const staffNode = createProjectCardStaffNode(assignees);
                            if (staffNode) {
                                leftSide.appendChild(staffNode);
                            }
                        }

                        if (statusValue) {
                            const statusText = formatProjectCardFieldValue('status', statusValue, {});
                            if (statusText) {
                                rightSide.appendChild(createProjectCardValueNode('status', statusText, statusValue));
                            }
                        }

                        if (leftSide.childElementCount) {
                            footerRow.appendChild(leftSide);
                        }

                        if (rightSide.childElementCount) {
                            footerRow.appendChild(rightSide);
                        }

                        if (footerRow.childElementCount) {
                            fieldGroup.appendChild(footerRow);
                        }
                    }

                    fieldGroup.classList.toggle('hidden', fieldGroup.children.length === 0);
                });
            }

            function updateProjectCardFieldModalState(selectedKeys) {
                syncProjectCardFieldCheckboxes(selectedKeys);
                renderProjectCardFieldGroups();
            }

            function openProjectCardFieldsModal() {
                if (!projectCardFieldsModal) return;
                if (activeProjectView !== 'kanban') {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'info',
                            title: 'Kanban view only',
                            text: 'This card fields popup is available only in Kanban view.',
                            confirmButtonColor: '#4f46e5',
                        });
                    } else {
                        alert('This card fields popup is available only in Kanban view.');
                    }
                    return;
                }
                projectCardFieldsModal.classList.remove('hidden');
                projectCardFieldsModal.classList.add('flex');
                projectCardFieldsModal.setAttribute('aria-hidden', 'false');
                lockProjectBackground();
            }

            function closeProjectCardFieldsModal() {
                if (!projectCardFieldsModal) return;
                projectCardFieldsModal.classList.add('hidden');
                projectCardFieldsModal.classList.remove('flex');
                projectCardFieldsModal.setAttribute('aria-hidden', 'true');
                unlockProjectBackground();
            }

            function getStageLabel(stageKey) {
                const stageData = stageLabels?.[stageKey];

                if (stageData && typeof stageData === 'object') {
                    return stageData.name || stageKey;
                }

                return stageData || stageKey;
            }

            function updateProjectStage(statusUrl, payload) {
                return fetch(statusUrl, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                }).then(async (response) => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                        throw new Error(firstFieldError || data?.message || 'Stage update failed.');
                    }

                    return data;
                });
            }

            function createCompletedStageAmc(createUrl, payload) {
                return fetch(createUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(payload)
                }).then(async (response) => {
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                        throw new Error(firstFieldError || data?.message || 'AMC creation failed.');
                    }

                    return data;
                });
            }

            function refreshKanbanCounts() {
                const board = document.getElementById('projects-kanban');
                if (!board) return;

                board.querySelectorAll('.project-kanban-column').forEach((lane) => {
                    const wrapper = lane.closest('.flex-shrink-0');
                    if (!wrapper) return;

                    const badge = wrapper.querySelector('[data-count-badge]');
                    if (!badge) return;

                    badge.textContent = String(lane.querySelectorAll('.project-kanban-item').length);
                });
            }

            function revertMove(moveContext) {
                if (!moveContext?.item || !moveContext?.from) return;
                const insertBeforeNode = moveContext.from.children[moveContext.oldIndex] || null;
                moveContext.from.insertBefore(moveContext.item, insertBeforeNode);
                refreshKanbanCounts();
            }

            function moveProjectCardToStageTop(moveContext) {
                if (!moveContext?.item || !moveContext?.to) return;
                moveContext.to.prepend(moveContext.item);
                moveContext.to.scrollTop = 0;
                moveContext.item.setAttribute('data-current-stage', moveContext.newStage || '');
                refreshKanbanCounts();
            }

            function promptCompletedStageAmc(moveContext) {
                if (!moveContext?.item || moveContext.newStage !== 'completed' || moveContext.oldStage === 'completed') {
                    return Promise.resolve();
                }

                const item = moveContext.item;
                const existingAmcCount = Number(item.getAttribute('data-amc-count') || 0);

                if (!window.Swal || existingAmcCount > 0) {
                    return Promise.resolve();
                }

                if (completedStageAmcPlans.length === 0) {
                    return Swal.fire({
                        title: 'Project marked completed',
                        text: 'No active 1-year AMC plans are available right now.',
                        icon: 'info',
                        confirmButtonColor: '#4f46e5',
                        confirmButtonText: 'OK'
                    });
                }

                const selectOptions = completedStageAmcPlans.reduce((options, plan) => {
                    const modeLabel = {
                        scheduled: 'Scheduled Visits Only',
                        on_call: 'On Call Only',
                        hybrid: 'Hybrid (Visits + On Call)',
                    }[plan.service_mode] || 'Scheduled Visits Only';
                    const visitLabel = plan.service_mode === 'on_call' ? 'No scheduled visits' : `${plan.visit_count} visits`;
                    options[plan.id] = `${plan.name} - Rs. ${Number(plan.price || 0).toFixed(2)} - ${modeLabel} - ${visitLabel}`;
                    return options;
                }, {});

                return Swal.fire({
                    title: 'Select 1 Year AMC Plan',
                    text: `Project "${item.getAttribute('data-project-name') || 'Project'}" is now completed. Choose an AMC plan.`,
                    input: 'select',
                    inputOptions: selectOptions,
                    inputPlaceholder: 'Choose AMC plan',
                    showCancelButton: true,
                    showDenyButton: true,
                    denyButtonText: 'Skip',
                    confirmButtonText: 'Create AMC',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#4f46e5',
                    denyButtonColor: '#64748b',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Please select an AMC plan.';
                        }
                    },
                    footer: 'Only active 1-year AMC plans are shown here.'
                }).then((result) => {
                    if (result.isDenied || !result.isConfirmed) {
                        return null;
                    }

                    return createCompletedStageAmc(item.getAttribute('data-completed-amc-url'), { amc_plan_id: result.value })
                        .then((data) => {
                            if (!data?.already_exists) {
                                item.setAttribute('data-amc-count', String(existingAmcCount + 1));
                            }

                            return Swal.fire({
                                title: data?.already_exists ? 'AMC already exists' : 'AMC created',
                                text: data?.message || 'AMC created successfully.',
                                icon: 'success',
                                showCancelButton: true,
                                confirmButtonColor: '#4f46e5',
                                confirmButtonText: 'View AMC',
                                cancelButtonText: 'Stay here'
                            }).then((viewResult) => {
                                if (viewResult.isConfirmed && data?.redirect_url) {
                                    window.location = data.redirect_url;
                                }
                            });
                        })
                        .catch((error) => {
                            return Swal.fire({
                                title: 'AMC creation failed',
                                text: error?.message || 'AMC creation failed.',
                                icon: 'error',
                                confirmButtonColor: '#4f46e5',
                                confirmButtonText: 'OK'
                            });
                        });
                });
            }

            function closeProjectStageModal() {
                if (!projectStageModal) return;
                projectStageModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            function openProjectStageModal(moveContext) {
                if (!projectStageModal) return;

                pendingStageChange = moveContext;

                const item = moveContext.item;
                const stageLabel = getStageLabel(moveContext.newStage);
                const subtitle = document.getElementById('project-stage-modal-subtitle');

                document.getElementById('project-stage-modal-title').textContent = `Move Project To ${stageLabel}`;
                if (subtitle) {
                    subtitle.textContent = 'Confirm this stage change.';
                }

                document.getElementById('project-stage-modal-project-name').textContent = item.getAttribute('data-project-name') || 'Project';
                document.getElementById('project-stage-modal-drawing-count').textContent = item.getAttribute('data-drawing-count') || '0';
                document.getElementById('project-stage-modal-material-count').textContent = item.getAttribute('data-material-count') || '0';
                document.getElementById('project-stage-modal-production-count').textContent = item.getAttribute('data-production-count') || '0';
                document.getElementById('project-stage-modal-qa-done-count').textContent = item.getAttribute('data-production-qa-done-count') || '0';
                document.getElementById('project-stage-modal-drawing-file').textContent = item.getAttribute('data-drawing-file-name') || 'No drawing uploaded';
                document.getElementById('project-stage-modal-drawing-version').textContent = item.getAttribute('data-drawing-version') || 'N/A';
                document.getElementById('project-stage-modal-drawing-status').textContent = item.getAttribute('data-drawing-status') || 'N/A';
                document.getElementById('project-stage-modal-drawing-added-at').textContent = item.getAttribute('data-drawing-added-at') || 'N/A';

                const hasDrawing = Boolean(item.getAttribute('data-drawing-file-name'));
                document.getElementById('project-stage-modal-empty-note')?.classList.toggle('hidden', hasDrawing);
                document.getElementById('project-stage-modal-material-note')?.classList.add('hidden');

                projectStageModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function showMoveError(error) {
                const message = error?.message || 'Stage update failed.';

                if (window.Swal) {
                    Swal.fire({
                        title: 'Move failed',
                        text: message,
                        icon: 'error',
                        confirmButtonColor: '#4f46e5',
                        confirmButtonText: 'OK'
                    });
                    return;
                }

                alert(message);
            }

            const viewButtons = {
                kanban: document.getElementById('project-view-toggle-kanban'),
                folder: document.getElementById('project-view-toggle-list'),
                gantt: document.getElementById('project-view-toggle-gantt'),
            };
            const projectViews = {
                kanban: document.getElementById('projects-kanban'),
                folder: document.getElementById('projects-list'),
                gantt: document.getElementById('projects-gantt'),
            };
            const folderHome = document.getElementById('folder-view-home');
            const folderStage = document.getElementById('folder-view-stage');
            const folderStageTitle = document.getElementById('folder-view-stage-title');
            const folderStageCount = document.getElementById('folder-view-stage-count');
            const folderBackButton = document.getElementById('folder-view-back');
            const folderTiles = Array.from(document.querySelectorAll('[data-folder-stage]'));
            const folderPanels = Array.from(document.querySelectorAll('[data-folder-panel]'));
            const folderStageMeta = @js(
                collect($projectStages)->mapWithKeys(function ($stageLabel, $stageKey) use ($projectsByStage) {
                    $stageData = is_array($stageLabel) ? $stageLabel : ['name' => $stageLabel];

                    return [
                        $stageKey => [
                            'name' => $stageData['name'] ?? 'Stage',
                            'count' => $projectsByStage->get($stageKey, collect())->count(),
                        ],
                    ];
                })
            );
            const defaultFolderStage = @js($defaultFolderStage);
            const projectViewStorageKey = 'projectsView';
            let activeFolderStage = defaultFolderStage;
            let activeProjectView = 'kanban';

            function normalizeProjectView(viewName) {
                if (viewName === 'list') return 'folder';
                return Object.prototype.hasOwnProperty.call(projectViews, viewName) ? viewName : 'kanban';
            }

            function setProjectViewButtonState(button, isActive) {
                if (!button) return;

                button.classList.toggle('bg-gray-800', isActive);
                button.classList.toggle('text-white', isActive);
                button.classList.toggle('bg-white', !isActive);
                button.classList.toggle('text-gray-700', !isActive);
                button.classList.toggle('hover:bg-gray-50', !isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            }

            function showFolderHome() {
                if (folderHome) folderHome.classList.remove('hidden');
                if (folderStage) folderStage.classList.add('hidden');
            }

            function openFolderStage(stageKey) {
                const stageMeta = folderStageMeta?.[stageKey];
                if (!stageMeta) {
                    showFolderHome();
                    return;
                }

                activeFolderStage = stageKey;

                if (folderHome) folderHome.classList.add('hidden');
                if (folderStage) folderStage.classList.remove('hidden');

                if (folderStageTitle) folderStageTitle.textContent = stageMeta.name || 'Projects';
                if (folderStageCount) {
                    const count = Number(stageMeta.count || 0);
                    folderStageCount.textContent = `${count} ${count === 1 ? 'project' : 'projects'}`;
                }

                folderTiles.forEach((tile) => {
                    tile.setAttribute('aria-pressed', tile.getAttribute('data-folder-stage') === stageKey ? 'true' : 'false');
                });

                folderPanels.forEach((panel) => {
                    panel.classList.toggle('hidden', panel.getAttribute('data-folder-panel') !== stageKey);
                });
            }

            function setProjectView(viewName) {
                const resolvedView = normalizeProjectView(viewName);
                activeProjectView = resolvedView;

                Object.entries(projectViews).forEach(([key, element]) => {
                    if (!element) return;
                    element.classList.toggle('hidden', key !== resolvedView);
                });

                setProjectViewButtonState(viewButtons.kanban, resolvedView === 'kanban');
                setProjectViewButtonState(viewButtons.folder, resolvedView === 'folder');
                setProjectViewButtonState(viewButtons.gantt, resolvedView === 'gantt');

                if (resolvedView === 'folder') {
                    openFolderStage(activeFolderStage || defaultFolderStage);
                    showFolderHome();
                }

                try {
                    window.localStorage.setItem(projectViewStorageKey, resolvedView);
                } catch (error) {}
            }

            let isProjectKanbanDragging = false;
            let projectKanbanAutoScrollFrame = null;
            let latestDragClientX = null;
            let suppressProjectOpenUntil = 0;

            function clearActiveTextSelection() {
                const selection = window.getSelection?.();
                if (selection && selection.rangeCount) selection.removeAllRanges();
            }

            function getDragClientX(event) {
                if (!event) return null;
                if (typeof event.clientX === 'number') return event.clientX;
                const touch = event.touches?.[0] || event.changedTouches?.[0];
                return touch && typeof touch.clientX === 'number' ? touch.clientX : null;
            }

            function trackProjectKanbanDragPointer(event) {
                if (!isProjectKanbanDragging) return;
                const clientX = getDragClientX(event);
                if (clientX !== null && clientX !== 0) latestDragClientX = clientX;
            }

            function runProjectKanbanAutoScroll() {
                if (!isProjectKanbanDragging || !kanbanWrapper) {
                    projectKanbanAutoScrollFrame = null;
                    return;
                }

                if (latestDragClientX !== null) {
                    const rect = kanbanWrapper.getBoundingClientRect();
                    const edgeThreshold = 140;
                    const maxStep = 34;
                    let scrollDelta = 0;

                    if (latestDragClientX > rect.right - edgeThreshold) {
                        const distance = latestDragClientX - (rect.right - edgeThreshold);
                        scrollDelta = Math.min(maxStep, Math.max(8, distance * 0.28));
                    } else if (latestDragClientX < rect.left + edgeThreshold) {
                        const distance = (rect.left + edgeThreshold) - latestDragClientX;
                        scrollDelta = -Math.min(maxStep, Math.max(8, distance * 0.28));
                    }

                    if (scrollDelta !== 0) {
                        kanbanWrapper.scrollLeft += scrollDelta;
                        clearActiveTextSelection();
                    }
                }

                projectKanbanAutoScrollFrame = window.requestAnimationFrame(runProjectKanbanAutoScroll);
            }

            function startProjectKanbanDrag() {
                isProjectKanbanDragging = true;
                document.body.classList.add('project-kanban-dragging');
                clearActiveTextSelection();

                if (!projectKanbanAutoScrollFrame) {
                    projectKanbanAutoScrollFrame = window.requestAnimationFrame(runProjectKanbanAutoScroll);
                }
            }

            function finishProjectKanbanDrag() {
                isProjectKanbanDragging = false;
                document.body.classList.remove('project-kanban-dragging');
                suppressProjectOpenUntil = Date.now() + 250;
                latestDragClientX = null;
                clearActiveTextSelection();

                if (projectKanbanAutoScrollFrame) {
                    window.cancelAnimationFrame(projectKanbanAutoScrollFrame);
                    projectKanbanAutoScrollFrame = null;
                }
            }

            if (kanbanWrapper) {
                kanbanWrapper.addEventListener('mousedown', function (event) {
                    if (event.target.closest('.project-kanban-item')) clearActiveTextSelection();
                });

                kanbanWrapper.addEventListener('selectstart', function (event) {
                    if (event.target.closest('.project-kanban-item')) event.preventDefault();
                });

                kanbanWrapper.addEventListener('click', function (event) {
                    if (Date.now() > suppressProjectOpenUntil) return;
                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }, true);

                document.addEventListener('pointermove', trackProjectKanbanDragPointer, { passive: true });
                document.addEventListener('mousemove', trackProjectKanbanDragPointer, { passive: true });
                document.addEventListener('touchmove', trackProjectKanbanDragPointer, { passive: true });

                document.addEventListener('drag', function (event) {
                    trackProjectKanbanDragPointer(event);
                }, { passive: true });

                kanbanWrapper.addEventListener('dragover', function (event) {
                    trackProjectKanbanDragPointer(event);
                }, { passive: true });

                document.addEventListener('selectstart', function (event) {
                    if (isProjectKanbanDragging) event.preventDefault();
                });
            }

            if (projectStageModalClose) {
                projectStageModalClose.addEventListener('click', function () {
                    pendingStageChange = null;
                    closeProjectStageModal();
                });
            }

            if (projectStageModalCancel) {
                projectStageModalCancel.addEventListener('click', function () {
                    pendingStageChange = null;
                    closeProjectStageModal();
                });
            }

            if (projectStageModalConfirm) {
                projectStageModalConfirm.addEventListener('click', function () {
                    if (!pendingStageChange) return;

                    const moveContext = pendingStageChange;
                    pendingStageChange = null;

                    updateProjectStage(moveContext.statusUrl, {
                        current_stage: moveContext.newStage
                    })
                        .then(() => {
                            moveProjectCardToStageTop(moveContext);
                            closeProjectStageModal();
                            return promptCompletedStageAmc(moveContext);
                        })
                        .catch((error) => {
                            closeProjectStageModal();
                            showMoveError(error);
                        });
                });
            }

            if (viewButtons.kanban) {
                viewButtons.kanban.addEventListener('click', function () {
                    setProjectView('kanban');
                });
            }

            if (viewButtons.folder) {
                viewButtons.folder.addEventListener('click', function () {
                    setProjectView('folder');
                });
            }

            if (viewButtons.gantt) {
                viewButtons.gantt.addEventListener('click', function () {
                    setProjectView('gantt');
                });
            }

            folderTiles.forEach((tile) => {
                tile.addEventListener('click', function () {
                    openFolderStage(tile.getAttribute('data-folder-stage'));
                });
            });

            if (folderBackButton) {
                folderBackButton.addEventListener('click', function () {
                    showFolderHome();
                });
            }

            let initialProjectView = 'kanban';

            try {
                const storedProjectView = window.localStorage.getItem(projectViewStorageKey);
                if (storedProjectView) {
                    initialProjectView = normalizeProjectView(storedProjectView);
                }
            } catch (error) {}

            setProjectView(initialProjectView);

            const initialProjectCardFieldSelection = getActiveProjectCardFieldKeys();
            updateProjectCardFieldModalState(initialProjectCardFieldSelection);

            if (projectCardFieldsOpenButton) {
                projectCardFieldsOpenButton.addEventListener('click', openProjectCardFieldsModal);
            }

            document.querySelectorAll('[data-close-project-card-fields-modal]').forEach((button) => {
                button.addEventListener('click', closeProjectCardFieldsModal);
            });

            if (projectCardFieldsModal) {
                projectCardFieldsModal.addEventListener('click', function (event) {
                    if (event.target === projectCardFieldsModal) {
                        closeProjectCardFieldsModal();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && projectCardFieldsModal && !projectCardFieldsModal.classList.contains('hidden')) {
                    closeProjectCardFieldsModal();
                }
            });

            projectCardFieldCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', function () {
                    const selectedKeys = projectCardFieldCheckboxes
                        .filter((input) => input.checked)
                        .map((input) => String(input.value));

                    writeProjectCardFieldPreferences(selectedKeys);
                    renderProjectCardFieldGroups();
                });
            });

            if (projectCardFieldsSelectAll) {
                projectCardFieldsSelectAll.addEventListener('click', function () {
                    const allKeys = projectCardFieldCheckboxes.map((input) => String(input.value));
                    syncProjectCardFieldCheckboxes(allKeys);
                    writeProjectCardFieldPreferences(allKeys);
                    renderProjectCardFieldGroups();
                });
            }

            if (projectCardFieldsReset) {
                projectCardFieldsReset.addEventListener('click', function () {
                    const defaultKeys = defaultProjectCardFieldKeys();
                    syncProjectCardFieldCheckboxes(defaultKeys);
                    writeProjectCardFieldPreferences(defaultKeys);
                    renderProjectCardFieldGroups();
                });
            }

            // SORTABLE INIT
            document.querySelectorAll('.project-kanban-column').forEach(column => {

                new Sortable(column, {
                    group: 'project-kanban',

                    animation: 80,

                    ghostClass: 'sortable-ghost',

                    dragClass: 'sortable-drag',

                    chosenClass: 'sortable-chosen',

                    forceFallback: false,

                    fallbackOnBody: false,

                    swapThreshold: 0.65,

                    scroll: true,

                    bubbleScroll: true,

                    scrollSensitivity: 120,

                    scrollSpeed: 18,

                    fallbackTolerance: 8,

                    emptyInsertThreshold: 20,

                    onStart: function (evt) {
                        startProjectKanbanDrag();

                        try {
                            const item = evt.item;
                            const dragImg = item.cloneNode(true);
                            dragImg.style.cssText = 'position:fixed;top:-9999px;left:-9999px;' +
                                'width:' + item.offsetWidth + 'px;' +
                                'transform:rotate(2deg) scale(1.02);' +
                                'box-shadow:0 20px 40px -10px rgba(0,0,0,0.25),0 8px 16px -4px rgba(0,0,0,0.12);' +
                                'border-radius:6px;background:white;opacity:1;z-index:-1;';
                            document.body.appendChild(dragImg);

                            if (evt.originalEvent && evt.originalEvent.dataTransfer) {
                                evt.originalEvent.dataTransfer.setDragImage(
                                    dragImg,
                                    Math.floor(item.offsetWidth / 2),
                                    30
                                );
                            }

                            setTimeout(function () {
                                if (dragImg.parentNode) dragImg.parentNode.removeChild(dragImg);
                            }, 100);
                        } catch (error) {}
                    },

                    onMove: function (evt, originalEvent) {
                        trackProjectKanbanDragPointer(originalEvent);
                        return true;
                    },

                    onEnd: function (evt) {

                        finishProjectKanbanDrag();

                        if (evt.from === evt.to) return;

                        const statusUrl = evt.item.getAttribute('data-status-url');

                        const oldStage = evt.from.getAttribute('data-stage');

                        const newStage = evt.to.getAttribute('data-stage');

                        const moveContext = {
                            item: evt.item,
                            from: evt.from,
                            to: evt.to,
                            oldIndex: evt.oldIndex,
                            statusUrl,
                            oldStage,
                            newStage
                        };

                        const materialCount = Number(
                            evt.item.getAttribute('data-material-count') || 0
                        );

                        const projectUrl =
                            evt.item.getAttribute('data-project-url') || '';

                        const needsMaterials = [
                            'production',
                            'ready_to_dispatch',
                            'delivery',
                            'installation',
                            'completed'
                        ].includes(newStage);

                        // VALIDATION
                        if (needsMaterials && materialCount === 0) {

                            revertMove(moveContext);

                            if (window.Swal) {
                                Swal.fire({
                                    title: 'Add materials first',
                                    text: 'No materials are added for this project.',
                                    icon: 'warning',
                                    showCancelButton: true,
                                    confirmButtonColor: '#4f46e5',
                                    confirmButtonText: 'Add Materials',
                                    cancelButtonText: 'Cancel'
                                }).then((result) => {
                                    if (result.isConfirmed && projectUrl) {
                                        window.location =
                                            `${projectUrl}?tab=material`;
                                    }
                                });
                            }

                            return;
                        }

                        // OPEN MODAL
                        revertMove(moveContext);

                        openProjectStageModal(moveContext);
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
