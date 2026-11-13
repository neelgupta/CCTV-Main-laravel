<x-app-layout>
    @php
        $authUser = auth()->user();
        $isCompanyAdmin = $authUser && ($authUser->isSuperAdmin() || $authUser->isCompanyAdmin());
        $canCreateCompanyFromLead = $isCompanyAdmin && (bool) ($authUser->company?->allow_create_company_from_lead ?? false);
        $canCreateLead = $authUser && $authUser->hasPermission('leads.create');
        $canEditLead = $authUser && $authUser->hasPermission('leads.edit');
        $canDeleteLead = $authUser && $authUser->hasPermission('leads.delete');
        $canUpdateLeadStatus = $authUser && $authUser->hasPermission('leads.status.update');
        $canCommentLead = $authUser && $authUser->hasPermission('leads.comment');
        $assignedInitials = function (string $name): string {
            $parts = preg_split('/\s+/', trim($name)) ?: [];

            if (!count($parts) || trim((string) ($parts[0] ?? '')) === '') {
                return '?';
            }

            if (count($parts) === 1) {
                return strtoupper(substr($parts[0], 0, 1));
            }

            return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
        };
    @endphp
    <x-slot name="header">
        <div class="">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">CRM Module</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">{{ __('Lead Management') }}</h2>
                </div>

                @if ($companyStats)
                    @php
                        $followupMetricCards = [
                            ['key' => 'total', 'label' => 'Total Follow-ups', 'value' => $companyStats['total_followups'] ?? 0, 'tone' => 'cyan'],
                            ['key' => 'today', 'label' => 'Today Followups', 'value' => $companyStats['today_followups'] ?? 0, 'tone' => 'blue'],
                            ['key' => 'done_today', 'label' => 'Done TodayFollowups', 'value' => $companyStats['done_today'] ?? 0, 'tone' => 'emerald'],
                            ['key' => 'pending_today', 'label' => 'Pending Today Followups', 'value' => $companyStats['pending_today'] ?? 0, 'tone' => 'amber'],
                            ['key' => 'missed', 'label' => 'Missed Follow-ups', 'value' => $companyStats['missed_followups'] ?? 0, 'tone' => 'rose'],
                        ];
                        $followupMetricUrl = fn (string $key) => route('leads.index', array_merge(request()->except(['followup_card', 'leads_page']), [
                            'followup_card' => $key,
                            'view' => 'list',
                        ]));
                        $followupMetricTones = [
                            'cyan' => 'bg-cyan-50 text-cyan-950 ring-cyan-100 hover:bg-cyan-100',
                            'blue' => 'bg-blue-50 text-blue-950 ring-blue-100 hover:bg-blue-100',
                            'emerald' => 'bg-emerald-50 text-emerald-950 ring-emerald-100 hover:bg-emerald-100',
                            'amber' => 'bg-amber-50 text-amber-950 ring-amber-100 hover:bg-amber-100',
                            'rose' => 'bg-rose-50 text-rose-950 ring-rose-100 hover:bg-rose-100',
                        ];
                        $followupMetricLabelTones = [
                            'cyan' => 'text-cyan-800',
                            'blue' => 'text-blue-800',
                            'emerald' => 'text-emerald-800',
                            'amber' => 'text-amber-800',
                            'rose' => 'text-rose-800',
                        ];
                    @endphp
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-5">
                        @foreach ($followupMetricCards as $card)
                            @php
                                $isActiveFollowupMetric = ($followupCardFilter ?? null) === $card['key'];
                            @endphp
                            <a href="{{ $followupMetricUrl($card['key']) }}"
                                class="{{ $followupMetricTones[$card['tone']] }} px-4 py-3 text-center ring-1 transition {{ $isActiveFollowupMetric ? 'outline outline-2 outline-offset-2 outline-slate-700' : '' }}"
                                title="Show {{ strtolower($card['label']) }} list">
                                <h4 class="{{ $followupMetricLabelTones[$card['tone']] }} text-[10px] font-bold uppercase">{{ $card['label'] }}</h4>
                                <p class="text-xl font-extrabold">{{ $card['value'] }}</p>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                    <div class="relative group w-full sm:w-auto">
                        <button
                            type="button"
                            id="toggle-lead-filters"
                            aria-expanded="false"
                            aria-controls="lead-filter-wrapper"
                            aria-label="Toggle filters"
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
                        <button
                            type="button"
                            id="open-lead-card-fields-modal"
                            aria-label="Card Fields"
                            title="Card Fields"
                            class="inline-flex w-full items-center justify-center  border border-violet-200 bg-violet-50 px-4 py-2.5 text-violet-800 shadow-sm transition hover:bg-violet-100 sm:w-auto">
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
                        <a href="{{ route('leads.export', request()->query()) }}"
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

                    @if ($isCompanyAdmin)
                        <div class="relative group w-full sm:w-auto">
                            <button type="button" id="open-lead-transfer-modal"
                                aria-label="Transfer Leads"
                                title="Transfer Leads"
                                class="inline-flex w-full items-center justify-center border border-amber-200 bg-amber-50 px-4 py-2.5 text-amber-800 shadow-sm transition hover:bg-amber-100 sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8z" />
                                    <path d="M12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
                                </svg>
                                <span class="sr-only">Transfer Leads</span>
                            </button>
                            <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                Transfer Leads
                            </span>
                        </div>
                    @endif

                    @if ($canCreateLead)
                        <div class="relative group w-full sm:w-auto">
                        <button type="button" id="open-lead-import-modal"
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

                    @if ($authUser?->company?->allow_appointment_module ?? false)
                        <div class="relative group w-full sm:w-auto">
                            <a href="{{ route('appointments.index') }}"
                                aria-label="Appointments"
                                title="Appointments"
                                class="inline-flex w-full items-center justify-center border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-indigo-700 shadow-sm transition hover:bg-indigo-100 sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">Appointments</span>
                            </a>
                            <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                Appointments
                            </span>
                        </div>
                    @endif

                    @if ($canCreateLead)
                        <div class="relative group w-full sm:w-auto">
                            <a href="{{ route('leads.create') }}"
                                aria-label="New Lead"
                                title="New Lead"
                                class="inline-flex w-full items-center justify-center bg-[#C22032] px-4 py-2.5 text-white shadow-lg shadow-rose-900/10 transition hover:bg-[#d63384] sm:w-auto">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M10 3a.75.75 0 01.75.75v5.5h5.5a.75.75 0 010 1.5h-5.5v5.5a.75.75 0 01-1.5 0v-5.5h-5.5a.75.75 0 010-1.5h5.5v-5.5A.75.75 0 0110 3z" clip-rule="evenodd" />
                                </svg>
                                <span class="sr-only">New Lead</span>
                            </a>
                            <span class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                New Lead
                            </span>
                        </div>
                    @endif

                    <div class="inline-flex w-full overflow-hidden border border-slate-200 bg-white shadow-sm sm:w-auto"
                        role="group" aria-label="View toggle">
                        <div class="relative group flex-1 sm:flex-none">
                            <button type="button" id="view-toggle-kanban"
                                class="flex w-full items-center justify-center px-4 py-3 bg-gray-800 text-white sm:px-3 sm:py-2"
                                title="Kanban View" aria-label="Kanban View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor" aria-hidden="true">
                                    <path d="M3 3h5v5H3V3zm0 9h5v5H3v-5zm9-9h5v5h-5V3zm0 9h5v5h-5v-5z" />
                                </svg>
                            </button>
                            <span
                                class="absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                Kanban View
                            </span>
                        </div>

                        <div class="relative group flex-1 border-l border-gray-200 sm:flex-none">
                            <button type="button" id="view-toggle-list"
                                class="flex w-full items-center justify-center px-4 py-3 text-gray-700 hover:bg-gray-50 sm:px-3 sm:py-2"
                                title="List View" aria-label="List View">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                                    fill="currentColor" aria-hidden="true">
                                    <path d="M4 4h12v2H4V4zm0 5h12v2H4V9zm0 5h12v2H4v-2z" />
                                </svg>
                            </button>
                            <span
                                class="absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg transition-opacity group-hover:block">
                                List View
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    @if ($canCreateLead)
        <div id="lead-import-modal"
            class="{{ $errors->has('lead_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6"
            aria-hidden="{{ $errors->has('lead_import_file') ? 'false' : 'true' }}"
            data-modal-open="{{ $errors->has('lead_import_file') ? 'true' : 'false' }}">
            <div class="absolute inset-0" data-close-lead-import-modal></div>
            <div class="relative z-10 w-full max-w-lg border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Import Leads</p>
                        <h3 class="mt-1 text-xl font-extrabold text-slate-950">Upload your lead file</h3>
                        <p class="mt-2 text-sm text-slate-500">Download the sample sheet, add your lead data, then upload it here.</p>
                    </div>
                    <button type="button" data-close-lead-import-modal
                        class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close import modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    First row will contain the field names. Required columns: <span class="font-semibold">name, email, phone, address, lead_category</span>.
                </div>

                @if ($errors->has('lead_import_file'))
                    <div class="mt-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        @foreach ($errors->get('lead_import_file') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('leads.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="lead_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose file</label>
                        <input id="lead_import_file" type="file" name="lead_import_file" accept=".csv,.xls,.xlsx"
                            class="block w-full border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                        <p class="mt-2 text-xs text-slate-500">Accepted formats: CSV, XLS, XLSX. Maximum size: 10 MB.</p>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('leads.import.sample') }}"
                            class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                            Download Sample
                        </a>
                        <button type="submit"
                            class="inline-flex items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-800 shadow-sm transition hover:bg-cyan-100">
                            Upload File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div id="lead-card-fields-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-3 py-4"
        aria-hidden="true">
        <div class="absolute inset-0" data-close-lead-card-fields-modal></div>
        <div class="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-4 py-4 sm:px-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-violet-700">Kanban Card Fields</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950 sm:text-xl">Choose what appears on lead cards</h3>
                </div>
                <button type="button" data-close-lead-card-fields-modal
                    class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close card fields modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-4 py-4 sm:px-5">
                <div class="flex flex-col gap-3 border border-violet-100 bg-violet-50/70 px-3 py-3 text-sm text-violet-900 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-2">
                        <button type="button" id="lead-card-fields-select-all"
                            class="inline-flex items-center justify-center border border-violet-200 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-violet-800 transition hover:bg-violet-50">
                            Select All
                        </button>
                        <button type="button" id="lead-card-fields-reset"
                            class="inline-flex items-center justify-center border border-slate-200 bg-white px-3 py-2 text-[11px] font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                            Reset Default
                        </button>
                    </div>
                </div>

                <div id="lead-card-fields-list" class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                    @foreach ($kanbanCardFields as $field)
                        <label class="group flex cursor-pointer items-start gap-3 border border-slate-200 bg-white px-3 py-2.5 shadow-sm transition hover:border-violet-200 hover:bg-violet-50/40">
                            <input
                                type="checkbox"
                                class="mt-1 h-4 w-4 border-slate-300 text-violet-600 focus:ring-violet-500"
                                value="{{ $field['key'] }}"
                                data-lead-card-field-checkbox
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

    <div class="py-0" id="leads-page-content">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="mb-5">
                @if ($errors->has('lead_import_file'))
                    <div class="mb-3 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        @foreach ($errors->get('lead_import_file') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                {{-- <div class="mb-3 border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    Lead import required columns: <span class="font-semibold">name, phone, lead_category</span>. Optional columns like email, address, status, priority, estimated_value, next_follow_up, source, referral_person_name, gst_number, pan_number, state, city, and assigned_to are also supported.
                </div> --}}

                @php
                    $cityOptionsByState = $states->mapWithKeys(function ($state) {
                        return [
                            $state->id => $state->cities->map(fn ($city) => [
                                'id' => $city->id,
                                'name' => $city->name,
                            ])->values()->all(),
                        ];
                    });
                    $leadStatusFilterUrl = fn ($statusId) => route('leads.index', array_merge(request()->except(['status', 'followup_card', 'leads_page']), [
                        'status' => $statusId,
                        'view' => 'list',
                    ]));
                    $leadStatusCountFor = fn ($statusId) => (int) ($leadStatusCounts[$statusId] ?? 0);
                    $leadStatusChipBase = 'inline-flex items-center gap-2 border px-4 py-2 text-xs font-semibold transition hover:shadow-sm';
                    $followupStatusFilterUrl = fn (string $status) => route('leads.index', array_merge(request()->except(['followup_status', 'followup_card', 'status', 'leads_page']), [
                        'followup_status' => $status,
                        'view' => 'list',
                    ]));
                    $followupChipBase = 'inline-flex items-center gap-2 border px-4 py-2 text-xs font-semibold transition hover:shadow-sm';
                    $followupChipUrl = fn (string $key) => route('leads.index', array_merge(request()->except(['followup_card', 'status', 'leads_page']), [
                        'followup_card' => $key,
                        'view' => 'list',
                    ]));
                    $followupChipTone = [
                        'total' => 'bg-cyan-50 text-cyan-900 border-cyan-200 hover:bg-cyan-100',
                        'today' => 'bg-blue-50 text-blue-900 border-blue-200 hover:bg-blue-100',
                        'done_today' => 'bg-emerald-50 text-emerald-900 border-emerald-200 hover:bg-emerald-100',
                        'pending_today' => 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100',
                        'missed' => 'bg-rose-50 text-rose-900 border-rose-200 hover:bg-rose-100',
                    ];
                    $followupStatusChipBase = 'inline-flex items-center gap-2 border px-4 py-2 text-xs font-semibold transition hover:shadow-sm';
                    $followupStatusTone = [
                        'Pending' => 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100',
                        'Done' => 'bg-emerald-50 text-emerald-900 border-emerald-200 hover:bg-emerald-100',
                        'Not Interested' => 'bg-rose-50 text-rose-900 border-rose-200 hover:bg-rose-100',
                        'Converted' => 'bg-violet-50 text-violet-900 border-violet-200 hover:bg-violet-100',
                        'Callback' => 'bg-orange-50 text-orange-900 border-orange-200 hover:bg-orange-100',
                        'Follow-up Required' => 'bg-yellow-50 text-yellow-900 border-yellow-200 hover:bg-yellow-100',
                    ];
                @endphp

                <form method="GET" action="{{ route('leads.index') }}" id="lead-search-form" class="w-full">
                    @if ($followupCardFilter)
                        <input type="hidden" name="followup_card" value="{{ $followupCardFilter }}">
                        <input type="hidden" name="view" value="list">
                    @endif
                    <div id="lead-filter-wrapper" class="hidden mt-3 border border-slate-200 bg-white p-4 shadow-sm">

                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Filters</p>
                                <p class="mt-1 text-sm text-slate-500">Search and narrow leads by month, year, and advanced fields.</p>
                            </div>
                        </div>

                        <div id="lead-filter-panel" class="mt-4">
                            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                                <div class="inline-flex w-full overflow-hidden bg-white shadow-sm ring-1 ring-slate-200 md:col-span-2">
                                    <div class="flex items-center px-3 text-slate-400">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                            fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0A4 4 0 015 9z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input id="lead-search-input" type="search" name="search" value="{{ $search }}"
                                        placeholder="Search by lead name, price, or category"
                                        class="w-full border-0 bg-white px-4 py-3 text-sm text-gray-700 outline-none placeholder:text-gray-400 focus:ring-0">
                                </div>

                                <div class="grid grid-cols-2 gap-3">
                                    <select id="lead-month-filter" name="month"
                                        class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Months</option>
                                        @foreach (range(1, 12) as $monthNumber)
                                            <option value="{{ $monthNumber }}" @selected((int) $monthFilter === $monthNumber)>
                                                {{ \Carbon\Carbon::create()->month($monthNumber)->format('F') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <select id="lead-year-filter" name="year"
                                        class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Years</option>
                                        @foreach ($years as $year)
                                            <option value="{{ $year }}" @selected((int) $yearFilter === (int) $year)>
                                                {{ $year }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div class="mt-3 border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="mb-4">
                                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">Advanced Filters</p>
                                    <p class="mt-1 text-sm text-slate-500">Filter leads by pipeline context, assignment, source, and location.</p>
                                </div>

                                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                                    {{-- All Select Filters --}}
                                    <select name="category" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Categories</option>
                                        @foreach ($categories as $category)
                                            <option value="{{ $category->id }}" @selected((string) $categoryFilter === (string) $category->id)>{{ $category->name }}</option>
                                        @endforeach
                                    </select>

                                    <select name="source" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Sources</option>
                                        @foreach ($leadSources as $leadSource)
                                            <option value="{{ $leadSource['name'] }}" @selected((string) $sourceFilter === (string) $leadSource['name'])>{{ $leadSource['name'] }}</option>
                                        @endforeach
                                    </select>

                                    <select name="assigned_to" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Employees</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}" @selected((string) $assignedToFilter === (string) $user->id)>{{ $user->name }}</option>
                                        @endforeach
                                    </select>
{{--
                                    <select name="state_id" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All States</option>
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" @selected((string) $stateFilter === (string) $state->id)>{{ $state->name }}</option>
                                        @endforeach
                                    </select>

                                    <select name="city_id" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                        <option value="">All Cities</option>
                                    </select> --}}

                                    @foreach ($filterableLeadCustomFields ?? [] as $field)
                                        @php
                                            $customFieldValue = data_get($customFieldFilters ?? [], $field['key']) ?? request()->input('custom_fields.' . $field['key']);
                                            $fieldType = $field['type'] ?? 'text';
                                            $isChoiceField = in_array($fieldType, ['dropdown', 'radiobutton'], true);
                                            $isWideField = in_array($fieldType, ['text', 'textarea', 'email'], true);
                                        @endphp

                                        <div @class(['md:col-span-2' => $isWideField])>
                                            <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</label>

                                            @if($fieldType === 'checkbox')
                                                <select
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                >
                                                    <option value="">Any</option>
                                                    <option value="1" @selected((string) $customFieldValue === '1' || $customFieldValue === true || $customFieldValue === 'true')>Checked</option>
                                                    <option value="0" @selected((string) $customFieldValue === '0' || $customFieldValue === false || $customFieldValue === 'false')>Unchecked</option>
                                                </select>
                                            @elseif($isChoiceField)
                                                <select
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                >
                                                    <option value="">Any</option>
                                                    @foreach(($field['options'] ?? []) as $option)
                                                        <option value="{{ $option }}" @selected((string) $customFieldValue === (string) $option)>{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                            @elseif($fieldType === 'date')
                                                <input
                                                    type="date"
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    value="{{ $customFieldValue }}"
                                                    class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                >
                                            @elseif($fieldType === 'number')
                                                <input
                                                    type="number"
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    value="{{ $customFieldValue }}"
                                                    class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                    placeholder="Any"
                                                >
                                            @else
                                                <input
                                                    type="{{ $fieldType === 'email' ? 'email' : 'text' }}"
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    value="{{ $customFieldValue }}"
                                                    class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"
                                                    placeholder="Any"
                                                >
                                            @endif
                                        </div>
                                    @endforeach

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Follow Up From</label>
                                        <input type="date" name="follow_up_from" value="{{ $followUpFromFilter }}" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                    </div>

                                    <div>
                                        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Follow Up To</label>
                                        <input type="date" name="follow_up_to" value="{{ $followUpToFilter }}" class="w-full border border-slate-200 bg-white px-3 py-3 text-sm text-slate-700 shadow-sm focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100">
                                    </div>

                                    {{-- Buttons placed in the final grid slot --}}
                                    <div class="flex items-end gap-2 pb-0.5">
                                        <button type="submit" class="flex-1 bg-slate-950 px-3 py-3 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800">
                                            Apply
                                        </button>
                                        <a href="{{ route('leads.index') }}" class="flex-1 text-center border border-slate-300 bg-white px-3 py-3 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                                            Reset
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 space-y-4 border border-slate-200 bg-white/80 p-4 shadow-sm">
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Lead Status</h3>
                                    @if(request()->filled('status'))
                                        <a href="{{ route('leads.index', array_merge(request()->except(['status', 'leads_page']), ['view' => 'list'])) }}"
                                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">
                                            Clear
                                        </a>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($allStatuses as $leadStatus)
                                        @php
                                            $isActiveLeadStatus = (string) request('status') === (string) $leadStatus->id;
                                            $leadStatusCount = $leadStatusCountFor($leadStatus->id);
                                            $statusColor = $leadStatus->color ?: '#64748b';
                                        @endphp
                                        <a href="{{ $leadStatusFilterUrl($leadStatus->id) }}"
                                            class="{{ $leadStatusChipBase }} {{ $isActiveLeadStatus ? 'ring-2 ring-offset-2 ring-slate-700' : '' }}"
                                            style="background-color: {{ $statusColor }}14; color: {{ $statusColor }}; border-color: {{ $statusColor }}33;">
                                            <span>{{ $leadStatus->name }}</span>
                                            <span class="rounded-md bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ $leadStatusCount }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>

                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <h3 class="text-xs font-bold uppercase tracking-[0.18em] text-slate-500">Follow Up Status</h3>
                                    @if(request()->filled('followup_status'))
                                        <a href="{{ route('leads.index', array_merge(request()->except(['followup_status', 'leads_page']), ['view' => 'list'])) }}"
                                            class="text-xs font-semibold text-slate-500 hover:text-slate-900">
                                            Clear
                                        </a>
                                    @endif
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($followupStatusLabels as $followupStatusLabel)
                                        @php
                                            $isActiveFollowupStatus = (string) request('followup_status') === $followupStatusLabel;
                                            $followupStatusCount = (int) ($followupStatusCounts[$followupStatusLabel] ?? 0);
                                        @endphp
                                        <a href="{{ $followupStatusFilterUrl($followupStatusLabel) }}"
                                            class="{{ $followupStatusChipBase }} {{ $followupStatusTone[$followupStatusLabel] ?? 'bg-slate-50 text-slate-900 border-slate-200 hover:bg-slate-100' }} {{ $isActiveFollowupStatus ? 'ring-2 ring-offset-2 ring-slate-700' : '' }}">
                                            <span>{{ $followupStatusLabel }}</span>
                                            <span class="rounded-md bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ $followupStatusCount }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>




            @php
                $wonStatus = $statuses->first(function ($status) {
                    return strtolower((string) $status->name) === 'won';
                });
                $wonStatusId = $wonStatus?->id;
                $lossStatus = $statuses->first(function ($status) {
                    return in_array(strtolower(trim((string) $status->name)), ['loss', 'lost'], true);
                });
                $lossStatusId = $lossStatus?->id;
                $todayDate = now()->format('Y-m-d');
            @endphp

           <div @class([
                'flex overflow-x-auto pb-4 gap-4 items-start scrollbar-hide custom-kanban-container',
                'hidden' => $requestedView === 'list',
           ]) id="kanban-board"
                data-won-status-id="{{ $wonStatusId }}" data-loss-status-id="{{ $lossStatusId }}" data-today="{{ $todayDate }}">
                @foreach ($statuses as $status)
                    {{-- Updated the background and border to use dynamic status color with transparency --}}
                    <div class="flex-shrink-0 w-full min-w-full max-w-full snap-start shadow p-4 flex flex-col border sm:w-80 sm:min-w-[320px] sm:max-w-[320px]"
                        style="background-color: {{ $status->color }}15; border-color: {{ $status->color }}40;">

                        <div class="flex justify-between items-center mb-4">
                            <h3 class="font-bold text-gray-700 uppercase tracking-wider text-sm">
                                {{ $status->name }}
                                <span class="ml-2 bg-gray-200 text-gray-600 px-2 py-1 text-xs"
                                    data-count-badge>
                                    {{ $status->leads->count() }}
                                </span>
                            </h3>
                            <div class="w-3 h-3" style="background-color: {{ $status->color }}"></div>
                        </div>

                        <div class="kanban-column space-y-3 max-h-[68vh] overflow-y-auto pr-1 h-[68vh]"
                            data-status-id="{{ $status->id }}"
                            data-status-name="{{ strtolower((string) $status->name) }}">
                            @foreach ($status->leads as $lead)
                                @php
                                    $leadStatusName = strtolower(trim((string) $status->name));
                                    $isKanbanTerminalStage = in_array($leadStatusName, ['won', 'loss'], true);
                                    $kanbanNextSchedule = $lead->followupSchedules->first(fn ($schedule) => strtolower((string) $schedule->status) === 'pending');
                                    $kanbanNextDate = $kanbanNextSchedule?->followup_date ?? $lead->next_follow_up;
                                    $kanbanNextStatus = $kanbanNextSchedule?->status ?? ($kanbanNextDate ? 'pending' : null);
                                @endphp
                                <div class="kanban-item bg-white p-4 shadow cursor-pointer hover:shadow-md transition"
                                    data-lead-id="{{ $lead->getRouteKey() }}"
                                    @if ($canUpdateLeadStatus) data-status-url="{{ route('leads.status.update', $lead) }}" @endif
                                    data-client-name="{{ $lead->client_name }}"
                                    data-has-project="{{ $lead->project ? '1' : '0' }}"
                                    data-show-url="{{ route('leads.show', $lead) }}"
                                    data-edit-url="{{ route('leads.edit', $lead) }}"
                                    data-contact-url="{{ route('leads.contact.log', $lead) }}"
                                    data-calendar-url="{{ route('leads.calendar.store', $lead) }}"
                                    data-comment-url="{{ route('leads.comment', $lead) }}"
                                    data-followup-save-url="{{ route('leads.follow-up.save', $lead) }}"
                                    data-followup-details-url="{{ route('leads.follow-up.details', $lead) }}"
                                    data-followup-history-url="{{ route('leads.follow-up.history', $lead) }}"
                                    data-followup-schedule-id="{{ $kanbanNextSchedule?->id }}"
                                    data-followup-date="{{ $kanbanNextDate?->toDateString() }}"
                                    data-followup-status="{{ $kanbanNextStatus ?? 'pending' }}"
                                    data-email="{{ $lead->email ?? '' }}" data-mobile="{{ $lead->mobile ?? '' }}"
                                    data-phone="{{ $lead->phone ?? '' }}"
                                    data-category="{{ $lead->category?->name ?? '' }}"
                                    data-has-company="{{ $lead->created_company_id ? '1' : '0' }}"
                                    data-created-company-name="{{ $lead->createdCompany?->name ?? '' }}"
                                    data-convert-company-url="{{ route('leads.convert-company', $lead) }}"
                                    data-value="{{ number_format($lead->estimated_value, 2) }}"
                                    data-priority="{{ $lead->priority }}" data-status-name="{{ $status->name }}"
                                    data-status-color="{{ $status->color }}"
                                    data-kanban-field-values='{{ \Illuminate\Support\Js::encode([
                                        'category' => $lead->category?->name,
                                        'estimated_value' => $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : null,
                                        'priority' => $lead->priority,
                                        'assigned_users' => $lead->assignedUsers->pluck('name')->filter()->values()->all(),
                                        'assigned_to' => $lead->assignedTo?->name,
                                        'created_at' => $lead->created_at?->diffForHumans(),
                                        'email' => $lead->email,
                                        'mobile' => $lead->mobile,
                                        'phone' => $lead->phone,
                                        'source' => $lead->source,
                                        'state' => $lead->state?->name,
                                        'city' => $lead->city?->name,
                                        'next_follow_up' => $lead->next_follow_up?->format('M d, Y'),
                                        'gst_number' => $lead->gst_number,
                                        'pan_number' => $lead->pan_number,
                                        'address' => $lead->address,
                                        'referral_person_name' => $lead->referral_person_name,
                                    ]) }}'
                                    data-kanban-custom-fields='{{ \Illuminate\Support\Js::encode($lead->custom_fields ?? []) }}'
                                    @php
                                        $leadAssignedUsers = $lead->assignedUsers->isNotEmpty()
                                            ? $lead->assignedUsers->map(fn ($u) => ['name' => (string) $u->name])->values()->all()
                                            : ($lead->assignedTo?->name ? [['name' => (string) $lead->assignedTo->name]] : []);
                                        $leadAssignedUserIds = $lead->assignedUsers->isNotEmpty()
                                            ? $lead->assignedUsers->pluck('id')->values()->all()
                                            : ($lead->assignedTo?->id ? [(string) $lead->assignedTo->id] : []);
                                    @endphp
                                    data-assign-url="{{ route('leads.assigned.update', $lead) }}"
                                    data-assigned-users='{{ \Illuminate\Support\Js::encode($leadAssignedUsers) }}'
                                    data-assigned-ids='{{ \Illuminate\Support\Js::encode($leadAssignedUserIds) }}'
                                    data-activities='{{ \Illuminate\Support\Js::encode($lead->activities->map(fn ($activity) => [
                                        "type" => (string) $activity->type,
                                        "description" => (string) $activity->description,
                                        "occurred_at" => $activity->occurred_at?->toIso8601String(),
                                        "user_name" => (string) ($activity->user->name ?? "System"),
                                    ])->values()->all()) }}'
                                    data-comments='{{ \Illuminate\Support\Js::encode($lead->comments->map(fn ($comment) => [
                                        "comment" => (string) $comment->comment,
                                        "created_at" => $comment->created_at?->toIso8601String(),
                                        "user_name" => (string) ($comment->user->name ?? "System"),
                                    ])->values()->all()) }}'
                                    onclick="openLeadPreview(this)">

                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-semibold text-gray-900 truncate mr-2" title="{{ $lead->client_name }}">{{ $lead->client_name }}
                                        </h4>
                                        <div class="flex items-center gap-1.5 flex-shrink-0"
                                            onclick="event.stopPropagation();">
                                            @if ($isKanbanTerminalStage)
                                                <span class="inline-flex items-center justify-center w-7 h-7 bg-slate-100 text-slate-300 cursor-not-allowed"
                                                    title="Follow-up disabled for Won/Loss stages"
                                                    aria-disabled="true">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </span>
                                            @else
                                                <button type="button"
                                                    class="js-open-kanban-followup inline-flex items-center justify-center w-7 h-7 bg-amber-50 text-amber-700 hover:bg-amber-100 transition"
                                                    title="{{ $kanbanNextDate ? 'Next Follow-up (' . $kanbanNextDate->format('d/m/Y') . ')' : 'Set Next Follow-up' }}">
                                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                </button>
                                            @endif
                                            @if ($canEditLead)
                                                <a href="{{ route('leads.edit', $lead) }}"
                                                    class="inline-flex items-center justify-center w-7 h-7 bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                                    title="Edit">
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 640 640" fill="currentColor">
                                                        <path
                                                            d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                                                    </svg>
                                                </a>
                                            @endif
                                            @if ($canCreateCompanyFromLead)
                                                @if ($lead->created_company_id)
                                                    <span
                                                        class="inline-flex items-center justify-center w-7 h-7 bg-slate-100 text-slate-400 cursor-not-allowed"
                                                        title="Company already created: {{ $lead->createdCompany?->name ?? 'Company' }}"
                                                        aria-disabled="true">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                    </span>
                                                @else
                                                    <button type="button"
                                                        onclick="event.stopPropagation(); openLeadConvertToCompanyModal('{{ route('leads.convert-company', $lead) }}', '{{ addslashes($lead->client_name) }}', '{{ addslashes($lead->email ?? '') }}', '{{ addslashes(preg_replace('/\D+/', '', (string) ($lead->mobile ?: $lead->phone))) }}');"
                                                        class="inline-flex items-center justify-center w-7 h-7 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                                                        title="Create Company from Lead">
                                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                    </button>
                                                @endif
                                            @endif
                                            @if ($canDeleteLead)
                                                <form method="POST" action="{{ route('leads.destroy', $lead) }}"
                                                    class="lead-delete-form" onclick="event.stopPropagation();">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="inline-flex items-center justify-center w-7 h-7 bg-red-50 text-red-700 hover:bg-red-100"
                                                        title="Delete">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 640 640" fill="currentColor">
                                                            <path
                                                                d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>

                                    <p class="text-xs text-gray-500 mb-2">{{ $lead->category?->name ?? '-' }}</p>

                                    <div class="flex justify-between items-center mt-3">
                                        <span
                                            class="text-xs font-bold text-indigo-600">₹{{ number_format($lead->estimated_value, 2) }}</span>
                                        <span
                                            class="text-[10px] text-gray-400">{{ $lead->created_at->diffForHumans() }}</span>
                                    </div>

                                    @if ($lead->assignedUsers && $lead->assignedUsers->count() > 0)
                                        <div
                                            class="mt-2 pt-2 border-t border-gray-100 flex items-center justify-between">
                                            <div class="flex items-center -space-x-2 overflow-hidden">
                                                {{-- Loop through all assigned employees --}}
                                                @foreach ($lead->assignedUsers as $user)
                                                    <div class="inline-block h-6 w-6 ring-2 ring-white bg-indigo-500 flex items-center justify-center text-[10px] text-white flex-shrink-0"
                                                        title="{{ $user->name }}">
                                                        {{ substr($user->name, 0, 1) }}
                                                    </div>
                                                @endforeach
                                            </div>

                                            <div class="flex-shrink-0">
                                                @if ($lead->priority === 'high')
                                                    <span
                                                        class="bg-red-100 text-red-800 text-[10px] font-medium px-2 py-0.5">High</span>
                                                @elseif($lead->priority === 'medium')
                                                    <span
                                                        class="bg-yellow-100 text-yellow-800 text-[10px] font-medium px-2 py-0.5">Medium</span>
                                                @else
                                                    <span
                                                        class="bg-green-100 text-green-800 text-[10px] font-medium px-2 py-0.5">Low</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            <div @class([
                'hidden' => $requestedView !== 'list',
            ]) id="leads-list">
                <div class="ww-table-shell mt-0">
                    <div class="ww-table-scroll">
                    <table class="lead-list-table min-w-full divide-y divide-slate-200">
                        <thead class="bg-[#C22032]">
                            <tr>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Client</th>
                                     <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Next Follow Up</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Status</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Follow Up Status</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Last Action</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Value</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Assigned</th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold text-white uppercase tracking-wider">
                                    Next Follow up</th>
                                @if ($canEditLead || $canDeleteLead)
                                    <th
                                        class="px-4 py-3 text-right text-xs font-semibold text-white uppercase tracking-wider">
                                        Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($listLeads as $lead)

                                @php
                                    $status = $lead->status;
                                    $leadStatusName = strtolower(trim((string) $status?->name));
                                    $isTerminalFollowUpStage = in_array($leadStatusName, ['won', 'loss'], true);
                                    $lastFollowup = $lead->followupHistories->first();
                                    $nextFollowupSchedule = $lead->followupSchedules->first(fn ($schedule) => strtolower((string) $schedule->status) === 'pending');
                                    $nextFollowupDate = $nextFollowupSchedule?->followup_date ?? $lead->next_follow_up;
                                    $nextFollowupStatus = $nextFollowupSchedule?->status ?? ($nextFollowupDate ? 'pending' : null);
                                    $lastSummary = trim((string) ($lastFollowup?->summary ?? ''));
                                    $lastSummaryPreview = $lastSummary !== '' ? \Illuminate\Support\Str::of($lastSummary)->substr(0, 20) : '-';
                                    $lastActionStatus = trim((string) ($lastFollowup?->status ?? ''));
                                    $lastActionStatusKey = strtolower($lastActionStatus);
                                    $lastActionStatusColor = match ($lastActionStatusKey) {
                                        'pending' => '#f59e0b',
                                        'done' => '#16a34a',
                                        'completed' => '#16a34a',
                                        'closed' => '#334155',
                                        'cancelled', 'canceled', 'missed', 'not interested' => '#dc2626',
                                        'callback' => '#f97316',
                                        'follow-up required', 'follow up required' => '#eab308',
                                        'converted' => '#7c3aed',
                                        default => '#64748b',
                                    };
                                @endphp
                                <tr class="cursor-pointer transition-colors duration-200 hover:bg-slate-50/80"
                                        data-lead-id="{{ $lead->getRouteKey() }}"
                                        data-client-name="{{ $lead->client_name }}"
                                        data-has-project="{{ $lead->project ? '1' : '0' }}"
                                        data-show-url="{{ route('leads.show', $lead) }}"
                                        data-edit-url="{{ route('leads.edit', $lead) }}"
                                        data-contact-url="{{ route('leads.contact.log', $lead) }}"
                                        data-calendar-url="{{ route('leads.calendar.store', $lead) }}"
                                        data-comment-url="{{ route('leads.comment', $lead) }}"
                                        data-followup-save-url="{{ route('leads.follow-up.save', $lead) }}"
                                        data-followup-details-url="{{ route('leads.follow-up.details', $lead) }}"
                                        data-followup-history-url="{{ route('leads.follow-up.history', $lead) }}"
                                        data-followup-schedule-id="{{ $nextFollowupSchedule?->id }}"
                                        data-followup-date="{{ $nextFollowupDate?->toDateString() }}"
                                        data-followup-status="{{ $nextFollowupStatus ?? 'pending' }}"
                                        data-email="{{ $lead->email ?? '' }}"
                                        data-mobile="{{ $lead->mobile ?? '' }}"
                                        data-phone="{{ $lead->phone ?? '' }}"
                                        data-category="{{ $lead->comments->last()?->comment ?? '' }}"
                                        data-has-company="{{ $lead->created_company_id ? '1' : '0' }}"
                                        data-created-company-name="{{ $lead->createdCompany?->name ?? '' }}"
                                        data-convert-company-url="{{ route('leads.convert-company', $lead) }}"
                                        data-value="{{ number_format($lead->estimated_value, 2) }}"
                                        data-priority="{{ $lead->priority }}" data-status-name="{{ $status?->name }}"
                                        data-status-color="{{ $status?->color }}"
                                        data-kanban-field-values='{{ \Illuminate\Support\Js::encode([
                                            'category' => $lead->category?->name,
                                            'estimated_value' => $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : null,
                                            'priority' => $lead->priority,
                                            'assigned_users' => $lead->assignedUsers->pluck('name')->filter()->values()->all(),
                                            'assigned_to' => $lead->assignedTo?->name,
                                            'created_at' => $lead->created_at?->diffForHumans(),
                                            'email' => $lead->email,
                                            'mobile' => $lead->mobile,
                                            'phone' => $lead->phone,
                                            'source' => $lead->source,
                                            'state' => $lead->state?->name,
                                            'city' => $lead->city?->name,
                                            'next_follow_up' => $lead->next_follow_up?->format('M d, Y'),
                                            'gst_number' => $lead->gst_number,
                                            'pan_number' => $lead->pan_number,
                                            'address' => $lead->address,
                                            'referral_person_name' => $lead->referral_person_name,
                                        ]) }}'
                                        data-kanban-custom-fields='{{ \Illuminate\Support\Js::encode($lead->custom_fields ?? []) }}'
                                        data-assigned-users='{{ \Illuminate\Support\Js::encode($lead->assignedUsers->map(fn ($u) => ["name" => (string) $u->name])->values()->all()) }}'
                                        data-activities='{{ \Illuminate\Support\Js::encode($lead->activities->map(fn ($activity) => [
                                            "type" => (string) $activity->type,
                                            "description" => (string) $activity->description,
                                            "occurred_at" => $activity->occurred_at?->toIso8601String(),
                                            "user_name" => (string) ($activity->user->name ?? "System"),
                                        ])->values()->all()) }}'
                                        data-comments='{{ \Illuminate\Support\Js::encode($lead->comments->map(fn ($comment) => [
                                            "comment" => (string) $comment->comment,
                                            "created_at" => $comment->created_at?->toIso8601String(),
                                            "user_name" => (string) ($comment->user->name ?? "System"),
                                        ])->values()->all()) }}'
                                        onclick="openLeadPreview(this)">
                                        <td class="px-4 py-3 text-sm text-gray-900" onclick="event.stopPropagation()">
                                            <a href="{{ route('leads.show', $lead) }}"
                                                class="font-semibold text-indigo-700 hover:text-indigo-900">
                                                {{ $lead->client_name }}
                                            </a>
                                            <div class="text-xs text-gray-500">
                                                {{ $lead->created_at->diffForHumans() }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            {{ $nextFollowupDate?->format('Y-m-d') ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <span
                                                class="inline-flex items-center px-2 py-1 text-xs font-semibold"
                                                style="background-color: {{ $status?->color }}20; color: {{ $status?->color }};">
                                                {{ $status?->name }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            @if ($lastActionStatus !== '')
                                                <span
                                                    class="inline-flex items-center px-2.5 py-1 text-[11px] font-semibold"
                                                    style="background-color: {{ $lastActionStatusColor }}18; color: {{ $lastActionStatusColor }}; border: 1px solid {{ $lastActionStatusColor }}30;">
                                                    {{ \Illuminate\Support\Str::headline($lastActionStatus) }}
                                                </span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700">
                                            <span>
                                                {{ $lastFollowup?->followup_date?->format('d/m/Y') ?? '-' }}
                                            </span><br>
                                            <span
                                                title="{{ $lastSummary }}"
                                                class="cursor-help"
                                            >
                                                {{ $lastSummaryPreview }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                            ₹{{ number_format($lead->estimated_value, 2) }}</td>
                                        @php
                                            $assignedUsers = $lead->assignedUsers->isNotEmpty()
                                                ? $lead->assignedUsers->map(fn ($u) => ['name' => (string) $u->name])->values()->all()
                                                : ($lead->assignedTo?->name ? [['name' => (string) $lead->assignedTo->name]] : []);
                                            $assignedUserIds = $lead->assignedUsers->isNotEmpty()
                                                ? $lead->assignedUsers->pluck('id')->values()->all()
                                                : ($lead->assignedTo?->id ? [(string) $lead->assignedTo->id] : []);
                                            $visibleAssignedUsers = array_slice($assignedUsers, 0, 3);
                                            $overflowAssignedUsers = array_slice($assignedUsers, 3);
                                        @endphp
                                        <td class="px-4 py-3 text-sm text-gray-700" onclick="event.stopPropagation()">
                                            @if ($canEditLead)
                                                <button type="button"
                                                    class="js-assign-edit group inline-flex w-full items-center px-2 py-1 -mx-2 text-left text-gray-700 transition hover:bg-indigo-50 hover:text-indigo-700"
                                                    title="Click to update assigned staff"
                                                    data-assign-url="{{ route('leads.assigned.update', $lead) }}"
                                                    data-client-name="{{ $lead->client_name }}"
                                                    data-assigned-users='{{ \Illuminate\Support\Js::encode($assignedUsers) }}'
                                                    data-assigned-ids='{{ \Illuminate\Support\Js::encode($assignedUserIds) }}'>
                                                    <div data-assigned-display>
                                                        @if (count($assignedUsers) > 0)
                                                            <div class="flex items-center -space-x-2 overflow-hidden">
                                                                @foreach ($visibleAssignedUsers as $assignedUser)
                                                                    <span
                                                                        class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border border-white bg-indigo-500 text-[10px] font-semibold text-white shadow-sm"
                                                                        title="{{ $assignedUser['name'] }}"
                                                                        aria-label="{{ $assignedUser['name'] }}">
                                                                        {{ $assignedInitials($assignedUser['name']) }}
                                                                    </span>
                                                                @endforeach
                                                                @if (count($overflowAssignedUsers) > 0)
                                                                    <span
                                                                        class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center border border-white bg-slate-200 text-[10px] font-semibold text-slate-700 shadow-sm"
                                                                        title="{{ collect($overflowAssignedUsers)->pluck('name')->join(', ') }}"
                                                                        aria-label="{{ count($overflowAssignedUsers) }} more assigned users">
                                                                        +{{ count($overflowAssignedUsers) }}
                                                                    </span>
                                                                @endif
                                                            </div>
                                                        @else
                                                            <span class="text-gray-500">-</span>
                                                        @endif
                                                    </div>
                                                </button>
                                            @else
                                                <div data-assigned-display>
                                                    @if (count($assignedUsers) > 0)
                                                        <div class="flex items-center -space-x-2 overflow-hidden">
                                                            @foreach ($visibleAssignedUsers as $assignedUser)
                                                                <span
                                                                    class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center border border-white bg-indigo-500 text-[10px] font-semibold text-white shadow-sm"
                                                                    title="{{ $assignedUser['name'] }}"
                                                                    aria-label="{{ $assignedUser['name'] }}">
                                                                    {{ $assignedInitials($assignedUser['name']) }}
                                                                </span>
                                                            @endforeach
                                                            @if (count($overflowAssignedUsers) > 0)
                                                                <span
                                                                    class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center border border-white bg-slate-200 text-[10px] font-semibold text-slate-700 shadow-sm"
                                                                    title="{{ collect($overflowAssignedUsers)->pluck('name')->join(', ') }}"
                                                                    aria-label="{{ count($overflowAssignedUsers) }} more assigned users">
                                                                    +{{ count($overflowAssignedUsers) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                        @else
                                                            <span class="text-gray-500">-</span>
                                                        @endif
                                                </div>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm text-gray-700" onclick="event.stopPropagation()">
                                            @if ($isTerminalFollowUpStage)
                                                <span class="inline-flex px-2 py-1 text-left font-semibold text-slate-400 opacity-70" title="Disabled for Won/Loss stages">
                                                    Next Follow up
                                                </span>
                                            @else
                                                <button type="button"
                                                    class="js-open-list-followup px-2 py-1 -mx-2 text-left font-semibold transition {{ $nextFollowupDate ? 'text-indigo-700 hover:bg-indigo-50 hover:text-indigo-900' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-700' }}"
                                                    data-lead-row-id="{{ $lead->getRouteKey() }}"
                                                    title="{{ $nextFollowupDate ? 'Open next follow-up' : 'Set next follow-up' }}">
                                                    Next Follow up
                                                </button>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
                                            <div class="inline-flex items-center gap-2">
                                                @if ($canEditLead)
                                                    <a href="{{ route('leads.edit', $lead) }}"
                                                        class="inline-flex items-center justify-center w-8 h-8 bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                                        title="Edit">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                                            viewBox="0 0 640 640" fill="currentColor">
                                                            <path
                                                                d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                                                        </svg>

                                                    </a>
                                                @endif
                                                @if ($canCreateCompanyFromLead)
                                                    @if ($lead->created_company_id)
                                                        <span
                                                            class="inline-flex items-center justify-center w-8 h-8 bg-slate-100 text-slate-400 cursor-not-allowed"
                                                            title="Company already created: {{ $lead->createdCompany?->name ?? 'Company' }}"
                                                            aria-disabled="true">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                        </span>
                                                    @else
                                                        <button type="button"
                                                            onclick="openLeadConvertToCompanyModal('{{ route('leads.convert-company', $lead) }}', '{{ addslashes($lead->client_name) }}', '{{ addslashes($lead->email ?? '') }}', '{{ addslashes(preg_replace('/\D+/', '', (string) ($lead->mobile ?: $lead->phone))) }}');"
                                                            class="inline-flex items-center justify-center w-8 h-8 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition"
                                                            title="Create Company from Lead">
                                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                            </svg>
                                                        </button>
                                                    @endif
                                                @endif
                                                @if ($canDeleteLead)
                                                    <form method="POST" action="{{ route('leads.destroy', $lead) }}"
                                                        class="lead-delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="inline-flex items-center justify-center w-8 h-8 bg-red-50 text-red-700 hover:bg-red-100"
                                                            title="Delete">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg"
                                                                viewBox="0 0 640 640" fill="currentColor">
                                                                <path
                                                                    d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                            @empty
                                    <tr>
                                        <td colspan="{{ ($canEditLead || $canDeleteLead) ? 8 : 7 }}" class="px-4 py-10 text-center text-sm text-gray-500">
                                            No leads found.
                                        </td>
                                    </tr>
                            @endforelse
                        </tbody>
                    </table>
                    </div>
                </div>

                @if ($listLeads->hasPages())
                    <div class="mt-4">
                        {{ $listLeads->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($canEditLead)
        <div id="lead-assign-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
            <div class="absolute inset-0" data-close-lead-assign-modal></div>
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Assign Staff</p>
                        <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="lead-assign-modal-title">Update Assigned Staff</h3>
                    </div>
                    <button type="button" data-close-lead-assign-modal
                        class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close assign modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="border-b border-slate-100 px-5 py-3">
                    <div class="flex items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2">
                        <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" /></svg>
                        <input type="text" id="lead-assign-search" placeholder="Search staff..."
                            class="w-full border-0 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0">
                    </div>
                </div>

                <div id="lead-assign-list" class="flex-1 overflow-y-auto px-5 py-3 space-y-1.5">
                    @foreach ($users as $user)
                        <label class="js-assign-option flex cursor-pointer items-center gap-3 border border-slate-200 bg-white px-3 py-2.5 transition hover:border-cyan-200 hover:bg-cyan-50/40"
                            data-staff-name="{{ \Illuminate\Support\Str::lower($user->name) }}">
                            <input type="checkbox" value="{{ $user->id }}"
                                class="js-assign-checkbox h-4 w-4 border-slate-300 text-cyan-600 focus:ring-cyan-500">
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center bg-cyan-500 text-[11px] font-bold text-white">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </span>
                            <span class="text-sm font-medium text-slate-800">{{ $user->name }}</span>
                        </label>
                    @endforeach
                    <p id="lead-assign-empty" class="hidden px-1 py-4 text-center text-sm text-slate-400">No staff match your search.</p>
                </div>

                <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                    <span class="text-xs text-slate-500"><span id="lead-assign-selected-count">0</span> selected</span>
                    <div class="flex items-center gap-2">
                        <button type="button" data-close-lead-assign-modal
                            class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="button" id="lead-assign-save"
                            class="inline-flex items-center justify-center bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60">
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($isCompanyAdmin)
        {{-- Lead Transfer Modal --}}
        <div id="lead-transfer-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="true">
            <div class="absolute inset-0" data-close-lead-transfer-modal></div>
            <div class="relative z-10 flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-6 py-4 bg-gradient-to-r from-amber-50 to-white">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-amber-700">Lead Re-assignment</p>
                        <h3 class="mt-1 text-lg font-extrabold text-slate-950">Transfer Leads</h3>
                    </div>
                    <button type="button" data-close-lead-transfer-modal
                        class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close transfer modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <form id="lead-transfer-form" class="flex flex-col flex-1 overflow-hidden" onsubmit="return false;">
                    <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
                        {{-- From & To Selectors --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="transfer-from-user" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Transfer From (Source Staff) <span class="text-rose-500">*</span>
                                </label>
                                <select id="transfer-from-user" class="w-full border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100">
                                    <option value="">-- Select Source Staff --</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role ?: 'Staff' }})</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">Select the staff member whose leads will be transferred.</p>
                            </div>

                            <div>
                                <label for="transfer-to-user" class="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Transfer To (Target Staff) <span class="text-rose-500">*</span>
                                </label>
                                <select id="transfer-to-user" class="w-full border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 shadow-sm focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100" required>
                                    <option value="">-- Select Target Staff --</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role ?: 'Staff' }})</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-slate-500">Selected leads will be reassigned to this staff member.</p>
                            </div>
                        </div>

                        {{-- Leads Container of Selected Source Staff --}}
                        <div id="transfer-leads-container" class="border border-slate-200 bg-white">
                            <div class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-2.5">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" id="transfer-modal-select-all"
                                        class="h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer" disabled>
                                    <label for="transfer-modal-select-all" class="text-xs font-bold uppercase tracking-wider text-slate-700 cursor-pointer">
                                        Select All Leads (<span id="transfer-modal-leads-count">0</span>)
                                    </label>
                                </div>
                                <span class="text-xs font-semibold text-amber-700" id="transfer-modal-selected-summary">0 selected</span>
                            </div>

                            <div id="transfer-modal-leads-list" class="max-h-64 overflow-y-auto divide-y divide-slate-100 p-2">
                                <div id="transfer-modal-placeholder" class="py-8 text-center text-xs text-slate-400">
                                    Please select a source staff above to view and choose their leads.
                                </div>
                                <div id="transfer-modal-loading" class="hidden py-8 text-center text-xs text-slate-600">
                                    <div class="inline-flex items-center justify-center mb-2">
                                        <div class="h-6 w-6 rounded-full border-2 border-indigo-200 border-t-indigo-600 animate-spin"></div>
                                    </div>
                                    <div class="font-medium text-slate-600">Loading staff leads...</div>
                                </div>
                                <div id="transfer-modal-empty" class="hidden py-8 text-center text-xs text-slate-400">
                                    No leads found for this staff member.
                                </div>
                            </div>
                        </div>
                        <p class="text-[11px] text-slate-400 italic">
                            * Note: Only the assigned staff is updated. All comments, activities, appointments, quotations, documents, and histories remain completely untouched.
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-6 py-4 bg-slate-50">
                        <button type="button" data-close-lead-transfer-modal
                            class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-100">
                            Cancel
                        </button>
                        <button type="button" id="lead-transfer-submit-btn"
                            class="inline-flex items-center justify-center bg-amber-600 px-5 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-amber-700 disabled:opacity-50">
                            Confirm Transfer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
    <style>
    /* Add the custom class you referenced */
        .custom-kanban-container {
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch; /* Better mobile scrolling */
        }

        #kanban-board {
            cursor: grab;
            display: flex;
            overflow-x: auto;
            overflow-y: hidden;
            overscroll-behavior-x: contain;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
        }

        #kanban-board:active {
            cursor: grabbing;
        }

        .kanban-column {
            min-height: 68vh;
            touch-action: pan-y;
            user-select: none;
            -webkit-user-select: none;
        }

        #kanban-board .kanban-item {
            touch-action: manipulation;
            user-select: none;
            -webkit-user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
            will-change: transform;
        }

        #kanban-board .kanban-item > p.text-xs.text-gray-500.mb-2,
        #kanban-board .kanban-item > div.flex.justify-between.items-center.mt-3,
        #kanban-board .kanban-item > div.mt-2.pt-2.border-t.border-gray-100.flex.items-center.justify-between {
            display: none !important;
        }

        body.kanban-dragging,
        body.kanban-dragging #kanban-board,
        body.kanban-dragging #kanban-board * {
            cursor: grabbing !important;
            user-select: none !important;
            -webkit-user-select: none !important;
        }

        body.kanban-dragging #kanban-board {
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
                const modal = document.getElementById('lead-import-modal');
                const openButton = document.getElementById('open-lead-import-modal');

                if (!modal || !openButton) {
                    return;
                }

                const closeButtons = modal.querySelectorAll('[data-close-lead-import-modal]');

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
            // ── Lead Preview Panel ──────────────────────────────────────────────
            let _currentContactUrl = '';
            let _currentCalendarUrl = '';
            let _currentCommentUrl = '';
            let _currentComments = [];

            function escapeLeadPreviewHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function getLeadActivityIcon(type) {
                if (type === 'status_change') {
                    return `
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                    `;
                }

                if (type === 'company_conversion') {
                    return `
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    `;
                }

                if (type === 'note') {
                    return `
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                        </svg>
                    `;
                }

                return `
                    <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                `;
            }

            function formatLeadActivityTime(value) {
                if (!value) return '';

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return '';

                return date.toLocaleString([], {
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function formatLeadCommentTime(value) {
                if (!value) return '';

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) return '';

                return date.toLocaleString([], {
                    month: 'short',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

            function renderLeadComments(comments) {
                const listEl = document.getElementById('lp-comments-list');
                const emptyEl = document.getElementById('lp-comments-empty');
                const countEl = document.getElementById('lp-comments-count');
                const items = Array.isArray(comments) ? comments : [];

                if (countEl) {
                    countEl.textContent = String(items.length);
                }

                if (!listEl || !emptyEl) {
                    return;
                }

                if (!items.length) {
                    listEl.innerHTML = '';
                    emptyEl.classList.remove('hidden');
                    return;
                }

                emptyEl.classList.add('hidden');
                listEl.innerHTML = items.map((comment, index) => {
                    const isLast = index === items.length - 1;
                    const userName = escapeLeadPreviewHtml(comment.user_name || 'System');
                    const body = escapeLeadPreviewHtml(comment.comment || '');
                    const occurredAt = escapeLeadPreviewHtml(formatLeadCommentTime(comment.created_at));
                    const initials = escapeLeadPreviewHtml((String(comment.user_name || 'S').trim().charAt(0) || 'S').toUpperCase());

                    return `
                        <div class="relative flex gap-3 ${isLast ? '' : 'pb-3'}">
                            ${isLast ? '' : '<span class="absolute left-[15px] top-8 h-full w-px bg-slate-200"></span>'}
                            <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center bg-indigo-600 text-[10px] font-bold text-white ring-4 ring-white">
                                ${initials}
                            </div>
                            <div class="min-w-0 flex-1 bg-slate-50 px-3 py-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <p class="min-w-0 truncate text-sm font-semibold text-slate-900">${userName}</p>
                                    <span class="flex-shrink-0 text-[10px] text-slate-400">${occurredAt}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap break-words text-sm leading-5 text-slate-600">${body || 'Comment added'}</p>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function appendLeadComment(comment) {
                _currentComments = [comment, ..._currentComments];
                renderLeadComments(_currentComments);
            }

            function renderLeadActivities(activities) {
                const listEl = document.getElementById('lp-activity-list');
                const emptyEl = document.getElementById('lp-activity-empty');
                const items = Array.isArray(activities) ? activities : [];

                if (!items.length) {
                    listEl.innerHTML = '';
                    emptyEl.classList.remove('hidden');
                    return;
                }

                emptyEl.classList.add('hidden');
                listEl.innerHTML = items.map((activity, index) => {
                    const isLast = index === items.length - 1;
                    const description = escapeLeadPreviewHtml(activity.description || '');
                    const userName = escapeLeadPreviewHtml(activity.user_name || 'System');
                    const occurredAt = escapeLeadPreviewHtml(formatLeadActivityTime(activity.occurred_at));

                    return `
                        <div class="relative flex gap-3 ${isLast ? '' : 'pb-4'}">
                            ${isLast ? '' : '<span class="absolute left-[15px] top-8 h-full w-px bg-slate-200"></span>'}
                            <div class="relative z-10 flex h-8 w-8 flex-shrink-0 items-center justify-center bg-slate-500 ring-4 ring-white">
                                ${getLeadActivityIcon(activity.type)}
                            </div>
                            <div class="min-w-0 flex-1 pt-0.5">
                                <p class="text-sm leading-5 text-slate-600">
                                    ${description || 'Activity updated'}
                                    <span class="font-medium text-slate-900">by ${userName}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-400">${occurredAt}</p>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            function syncLeadPreviewSectionVisibility() {
                const contactForm = document.getElementById('lp-contact-form');
                const calendarForm = document.getElementById('lp-calendar-form');
                const tabsSection = document.getElementById('lp-tabs-section');
                const showTabs = contactForm.classList.contains('hidden') && calendarForm.classList.contains('hidden');

                if (tabsSection) {
                    tabsSection.classList.toggle('hidden', !showTabs);
                }
            }

            function switchLeadPreviewTab(tab) {
                const commentsSection = document.getElementById('lp-comments-section');
                const activitySection = document.getElementById('lp-activity-section');
                const commentsTab = document.getElementById('lp-tab-comments');
                const activityTab = document.getElementById('lp-tab-activity');
                const commentsCount = document.getElementById('lp-comments-count');

                if (tab === 'comments') {
                    commentsSection.classList.remove('hidden');
                    activitySection.classList.add('hidden');
                    commentsTab.classList.add('border-indigo-500', 'text-indigo-600');
                    commentsTab.classList.remove('border-transparent', 'text-slate-400');
                    activityTab.classList.remove('border-indigo-500', 'text-indigo-600');
                    activityTab.classList.add('border-transparent', 'text-slate-400');
                    if (commentsCount) {
                        commentsCount.classList.remove('hidden');
                        commentsCount.className = 'inline-flex items-center bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-600';
                    }
                } else {
                    activitySection.classList.remove('hidden');
                    commentsSection.classList.add('hidden');
                    activityTab.classList.add('border-indigo-500', 'text-indigo-600');
                    activityTab.classList.remove('border-transparent', 'text-slate-400');
                    commentsTab.classList.remove('border-indigo-500', 'text-indigo-600');
                    commentsTab.classList.add('border-transparent', 'text-slate-400');
                    if (commentsCount) {
                        commentsCount.className = 'inline-flex items-center bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500';
                    }
                }
            }

            function openLeadPreview(el) {
                const panel = document.getElementById('lead-preview-panel');
                const overlay = document.getElementById('lead-preview-overlay');

                const clientName = el.dataset.clientName || '';
                const showUrl = el.dataset.showUrl || '#';
                const editUrl = el.dataset.editUrl || '#';
                const email = el.dataset.email || '';
                const mobile = el.dataset.mobile || '';
                const category = el.dataset.category || '-';
                const value = el.dataset.value || '0.00';
                const priority = el.dataset.priority || 'medium';
                const statusName = el.dataset.statusName || '';
                const statusColor = el.dataset.statusColor || '#64748b';
                const contactUrl = el.dataset.contactUrl || '';
                const calendarUrl = el.dataset.calendarUrl || '';
                const commentUrl = el.dataset.commentUrl || '';

                _currentContactUrl = contactUrl;
                _currentCalendarUrl = calendarUrl;
                _currentCommentUrl = commentUrl;

                let assignedUsers = [];
                try {
                    assignedUsers = JSON.parse(el.dataset.assignedUsers || '[]');
                } catch (e) {}

                let activities = [];
                try {
                    activities = JSON.parse(el.dataset.activities || '[]');
                } catch (e) {}

                let comments = [];
                try {
                    comments = JSON.parse(el.dataset.comments || '[]');
                } catch (e) {}

                // Header
                document.getElementById('lp-client-name').textContent = clientName;

                const statusBadge = document.getElementById('lp-status-badge');
                statusBadge.textContent = statusName;
                statusBadge.style.backgroundColor = statusColor + '22';
                statusBadge.style.color = statusColor;

                const prioColors = {
                    high: ['bg-red-100', 'text-red-700'],
                    medium: ['bg-yellow-100', 'text-yellow-700'],
                    low: ['bg-green-100', 'text-green-700']
                };
                const prioLabels = {
                    high: 'High',
                    medium: 'Medium',
                    low: 'Low'
                };
                const prioBadge = document.getElementById('lp-priority-badge');
                prioBadge.className = 'inline-flex px-2 py-0.5 text-xs font-semibold ' + (prioColors[priority] ||
                    prioColors.medium).join(' ');
                prioBadge.textContent = prioLabels[priority] || priority;

                // Contact
                const mobileRow = document.getElementById('lp-mobile-row');
                const mobileLink = document.getElementById('lp-mobile-link');
                if (mobile) {
                    mobileLink.href = 'tel:' + mobile;
                    mobileLink.textContent = mobile;
                    mobileRow.classList.remove('hidden');
                } else {
                    mobileRow.classList.add('hidden');
                }

                const emailRow = document.getElementById('lp-email-row');
                const emailLink = document.getElementById('lp-email-link');
                if (email) {
                    emailLink.href = 'mailto:' + email;
                    emailLink.textContent = email;
                    emailRow.classList.remove('hidden');
                } else {
                    emailRow.classList.add('hidden');
                }

                // Details
                document.getElementById('lp-category').textContent = category || '-';
                document.getElementById('lp-value').textContent = '₹' + value;
                document.getElementById('lp-assigned').textContent = assignedUsers.length ?
                    assignedUsers.map(u => u.name).join(', ') :
                    '-';

                // Quick action links
                const callBtn = document.getElementById('lp-call-btn');
                if (mobile) {
                    callBtn.href = 'tel:' + mobile;
                    callBtn.classList.remove('opacity-40', 'pointer-events-none');
                } else {
                    callBtn.href = '#';
                    callBtn.classList.add('opacity-40', 'pointer-events-none');
                }

                const emailBtn = document.getElementById('lp-email-btn');
                if (email) {
                    emailBtn.href = 'mailto:' + email;
                    emailBtn.classList.remove('opacity-40', 'pointer-events-none');
                } else {
                    emailBtn.href = '#';
                    emailBtn.classList.add('opacity-40', 'pointer-events-none');
                }

                document.getElementById('lp-view-btn').href = showUrl;
                const editBtn = document.getElementById('lp-edit-btn');
                if (editBtn) editBtn.href = editUrl;
                const transferBtn = document.getElementById('lp-transfer-btn');
                if (transferBtn) {
                    transferBtn.dataset.leadId = el.dataset.leadId || '';
                    transferBtn.dataset.clientName = clientName;
                }

                const convertCompanyBtn = document.getElementById('lp-convert-company-btn');
                if (convertCompanyBtn) {
                    const hasCompany = el.dataset.hasCompany === '1';
                    const convertUrl = el.dataset.convertCompanyUrl || '';
                    const rawPhone = (el.dataset.mobile || el.dataset.phone || '').replace(/\D+/g, '');
                    if (convertUrl) {
                        convertCompanyBtn.classList.remove('hidden');
                        if (hasCompany) {
                            convertCompanyBtn.classList.add('opacity-40', 'cursor-not-allowed', 'pointer-events-none');
                            convertCompanyBtn.setAttribute('title', 'Company already created');
                            convertCompanyBtn.onclick = null;
                        } else {
                            convertCompanyBtn.classList.remove('opacity-40', 'cursor-not-allowed', 'pointer-events-none');
                            convertCompanyBtn.setAttribute('title', 'Create Company from Lead');
                            convertCompanyBtn.onclick = function () {
                                openLeadConvertToCompanyModal(convertUrl, clientName, email, rawPhone);
                            };
                        }
                    } else {
                        convertCompanyBtn.classList.add('hidden');
                        convertCompanyBtn.onclick = null;
                    }
                }

                // Pre-fill forms
                const now = new Date();
                now.setSeconds(0, 0);
                const localDT = now.getFullYear() + '-' +
                    String(now.getMonth() + 1).padStart(2, '0') + '-' +
                    String(now.getDate()).padStart(2, '0') + 'T' +
                    String(now.getHours()).padStart(2, '0') + ':' +
                    String(now.getMinutes()).padStart(2, '0');
                document.getElementById('lp-occurred-at').value = localDT;

                const tomorrow = new Date(now);
                tomorrow.setDate(tomorrow.getDate() + 1);
                document.getElementById('lp-cal-date').value = tomorrow.getFullYear() + '-' +
                    String(tomorrow.getMonth() + 1).padStart(2, '0') + '-' +
                    String(tomorrow.getDate()).padStart(2, '0');
                document.getElementById('lp-cal-title').value = 'Follow up: ' + clientName;

                // Reset inline sections and messages
                ['lp-contact-form', 'lp-calendar-form'].forEach(id => document.getElementById(id).classList.add('hidden'));
                ['lp-contact-msg', 'lp-cal-msg'].forEach(id => {
                    const el = document.getElementById(id);
                    el.classList.add('hidden');
                    el.textContent = '';
                });
                ['lp-contact-location-wrap', 'lp-contact-link-wrap', 'lp-calendar-location-wrap', 'lp-calendar-link-wrap']
                    .forEach(id => document.getElementById(id).classList.add('hidden'));
                renderLeadActivities(activities);
                _currentComments = Array.isArray(comments) ? comments : [];
                renderLeadComments(_currentComments);
                switchLeadPreviewTab('comments');
                syncLeadPreviewSectionVisibility();

                // Show
                overlay.classList.remove('hidden');
                panel.classList.remove('translate-x-full');
                document.body.classList.add('overflow-hidden');
            }

            function closeLeadPreview() {
                const panel = document.getElementById('lead-preview-panel');
                const overlay = document.getElementById('lead-preview-overlay');
                panel.classList.add('translate-x-full');
                overlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            function toggleSection(id) {
                const el = document.getElementById(id);
                const isHidden = el.classList.toggle('hidden');
                if (!isHidden) {
                    // hide the other
                    const other = id === 'lp-contact-form' ? 'lp-calendar-form' : 'lp-contact-form';
                    document.getElementById(other).classList.add('hidden');
                }
                syncLeadPreviewSectionVisibility();
            }

            function toggleMeetingFields(selectEl, context) {
                const isContact = context === 'contact';
                const locationWrap = document.getElementById(isContact ? 'lp-contact-location-wrap' : 'lp-calendar-location-wrap');
                const linkWrap = document.getElementById(isContact ? 'lp-contact-link-wrap' : 'lp-calendar-link-wrap');
                const mode = selectEl?.value || '';

                locationWrap.classList.toggle('hidden', mode !== 'offline');
                linkWrap.classList.toggle('hidden', mode !== 'online');
            }

            function submitLeadForm(event, type) {
                event.preventDefault();
                const isContact = type === 'contact';
                const formEl = document.getElementById(isContact ? 'lp-contact-form-el' : 'lp-calendar-form-el');
                const msgEl = document.getElementById(isContact ? 'lp-contact-msg' : 'lp-cal-msg');
                const url = isContact ? _currentContactUrl : _currentCalendarUrl;
                const submitBtn = formEl.querySelector('button[type="submit"]');

                if (!url) return;

                const formData = new FormData(formEl);

                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving…';
                msgEl.classList.add('hidden');

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] ||
                            'Request failed.');
                        return data;
                    })
                    .then(() => {
                        msgEl.textContent = isContact ? 'Contact logged!' : 'Added to calendar!';
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-green-100 text-green-800';
                        formEl.reset();
                        toggleMeetingFields(formEl.querySelector('select[name="meeting_type"]'), isContact ? 'contact' : 'calendar');
                    })
                    .catch(err => {
                        msgEl.textContent = err.message;
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-red-100 text-red-700';
                    })
                    .finally(() => {
                        msgEl.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = isContact ? 'Save Contact Log' : 'Add to Calendar';
                    });
            }

            function submitLeadForm(event, type) {
                event.preventDefault();
                const isContact = type === 'contact';
                const isComment = type === 'comment';
                const formEl = isContact
                    ? document.getElementById('lp-contact-form-el')
                    : (isComment ? document.getElementById('lp-comment-form-el') : document.getElementById('lp-calendar-form-el'));
                const msgEl = isContact
                    ? document.getElementById('lp-contact-msg')
                    : (isComment ? document.getElementById('lp-comment-msg') : document.getElementById('lp-cal-msg'));
                const url = isContact ? _currentContactUrl : (isComment ? _currentCommentUrl : _currentCalendarUrl);
                const submitBtn = formEl?.querySelector('button[type="submit"]');

                if (!formEl || !msgEl || !submitBtn || !url) return;

                const formData = new FormData(formEl);

                if (isComment) {
                    const textarea = formEl.querySelector('textarea[name="comment"]');
                    const commentValue = String(textarea?.value || '').trim();
                    const minLength = Number(textarea?.dataset.min || 5);
                    const maxLength = Number(textarea?.dataset.max || 500);

                    if (!commentValue) {
                        msgEl.textContent = 'Please enter a comment before posting.';
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-red-100 text-red-700';
                        msgEl.classList.remove('hidden');
                        return;
                    }

                    if (commentValue.length < minLength) {
                        msgEl.textContent = `Your comment must be at least ${minLength} characters long.`;
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-red-100 text-red-700';
                        msgEl.classList.remove('hidden');
                        return;
                    }

                    if (commentValue.length > maxLength) {
                        msgEl.textContent = `Your comment exceeds the limit of ${maxLength} characters.`;
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-red-100 text-red-700';
                        msgEl.classList.remove('hidden');
                        return;
                    }
                }

                submitBtn.disabled = true;
                submitBtn.textContent = isComment ? 'Posting…' : 'Saving…';
                msgEl.classList.add('hidden');

                fetch(url, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    })
                    .then(async res => {
                        const data = await res.json().catch(() => ({}));
                        if (!res.ok) throw new Error(data.message || Object.values(data.errors || {})[0]?.[0] ||
                            'Request failed.');
                        return data;
                    })
                    .then((data) => {
                        msgEl.textContent = isContact ? 'Contact logged!' : (isComment ? 'Comment added!' : 'Added to calendar!');
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-green-100 text-green-800';
                        formEl.reset();

                        if (isContact) {
                            toggleMeetingFields(formEl.querySelector('select[name="meeting_type"]'), 'contact');
                        } else if (!isComment) {
                            toggleMeetingFields(formEl.querySelector('select[name="meeting_type"]'), 'calendar');
                        } else if (data?.comment) {
                            appendLeadComment(data.comment);
                        }
                    })
                    .catch(err => {
                        msgEl.textContent = err.message;
                        msgEl.className = 'text-xs font-medium py-1.5 px-2 bg-red-100 text-red-700';
                    })
                    .finally(() => {
                        msgEl.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = isContact ? 'Save Contact Log' : (isComment ? 'Post Comment' : 'Add to Calendar');
                    });
            }

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const cardFieldsModal = document.getElementById('lead-card-fields-modal');
                    if (cardFieldsModal && !cardFieldsModal.classList.contains('hidden')) {
                        cardFieldsModal.classList.add('hidden');
                        cardFieldsModal.classList.remove('flex');
                        cardFieldsModal.setAttribute('aria-hidden', 'true');
                        document.body.classList.remove('overflow-hidden');
                        const leadsPage = document.getElementById('leads-page-content');
                        if (leadsPage) {
                            leadsPage.classList.remove('pointer-events-none', 'select-none');
                            leadsPage.removeAttribute('aria-hidden');
                            leadsPage.removeAttribute('inert');
                        }
                        return;
                    }

                    const panel = document.getElementById('lead-preview-panel');
                    if (panel && !panel.classList.contains('translate-x-full')) closeLeadPreview();
                }
            });
            // ────────────────────────────────────────────────────────────────────

            document.addEventListener('DOMContentLoaded', function() {
                const kanbanBtn = document.getElementById('view-toggle-kanban');
                const listBtn = document.getElementById('view-toggle-list');
                const kanbanBoard = document.getElementById('kanban-board');
                const leadsList = document.getElementById('leads-list');
                const leadFilterToggle = document.getElementById('toggle-lead-filters');
                const leadFilterWrapper = document.getElementById('lead-filter-wrapper');
                const leadFilterPanel = document.getElementById('lead-filter-panel');
                const wonStatusId = kanbanBoard?.getAttribute('data-won-status-id');
                const lossStatusId = kanbanBoard?.getAttribute('data-loss-status-id');
                const todayDate = kanbanBoard?.getAttribute('data-today') || '';
                const wonModal = document.getElementById('won-project-modal');
                const wonModalForm = document.getElementById('won-project-form');
                const wonModalClose = document.getElementById('won-project-modal-close');
                const wonModalCancel = document.getElementById('won-project-modal-cancel');
                const wonLeadName = document.getElementById('won-project-lead-name');
                const wonProjectName = document.getElementById('won-project-name');
                const wonProjectDescription = document.getElementById('won-project-description');
                const wonProjectStartDate = document.getElementById('won-project-start-date');
                const wonProjectEndDate = document.getElementById('won-project-end-date');
                const wonProjectStatus = document.getElementById('won-project-status');
                const lossModal = document.getElementById('loss-reason-modal');
                const lossModalForm = document.getElementById('loss-reason-form');
                const lossModalClose = document.getElementById('loss-reason-modal-close');
                const lossModalCancel = document.getElementById('loss-reason-modal-cancel');
                const lossLeadName = document.getElementById('loss-reason-lead-name');
                const lossReasonPreset = document.getElementById('loss-reason-preset');
                const lossReasonText = document.getElementById('loss-reason-text');
                const lossReasonError = document.getElementById('loss-reason-error');
                const pageContent = document.getElementById('leads-page-content');
                const leadSearchForm = document.getElementById('lead-search-form');
                const leadSearchInput = document.getElementById('lead-search-input');
                const leadMonthFilter = document.getElementById('lead-month-filter');
                const leadYearFilter = document.getElementById('lead-year-filter');
                const leadCategoryFilter = document.getElementById('lead-category-filter');
                const leadSourceFilter = document.getElementById('lead-source-filter');
                const leadAssignedFilter = document.getElementById('lead-assigned-filter');
                const leadStateFilter = document.getElementById('lead-state-filter');
                const leadCityFilter = document.getElementById('lead-city-filter');
                const leadFollowUpFromFilter = document.getElementById('lead-follow-up-from');
                const leadFollowUpToFilter = document.getElementById('lead-follow-up-to');
                const leadCardFieldsModal = document.getElementById('lead-card-fields-modal');
                const leadCardFieldsOpenButton = document.getElementById('open-lead-card-fields-modal');
                const leadCardFieldsSelectAll = document.getElementById('lead-card-fields-select-all');
                const leadCardFieldsReset = document.getElementById('lead-card-fields-reset');
                const leadCardFieldCheckboxes = Array.from(document.querySelectorAll('[data-lead-card-field-checkbox]'));
                const leadCardFieldOptions = @js($kanbanCardFields);
                const leadCardFieldPreferenceKey = `leadKanbanCardFields:${@js($authUser?->company_id ?? 'global')}:${@js($authUser?->id ?? 'guest')}`;
                const cityOptionsByState = @js($cityOptionsByState);
                const selectedCityId = @js($cityFilter ? (string) $cityFilter : '');

                let pendingWonMove = null;
                let pendingLossMove = null;
                let searchTimer = null;

                function submitLeadSearchForm() {
                    if (!leadSearchForm) return;
                    leadSearchForm.requestSubmit();
                }

                function populateLeadCityOptions(stateId, selectedValue = '') {
                    if (!leadCityFilter) return;

                    const normalizedStateId = String(stateId || '');
                    const cities = normalizedStateId ? (cityOptionsByState[normalizedStateId] || []) : [];

                    leadCityFilter.innerHTML = '<option value="">All Cities</option>';

                    cities.forEach((city) => {
                        const option = document.createElement('option');
                        option.value = String(city.id);
                        option.textContent = city.name;

                        if (String(selectedValue) === String(city.id)) {
                            option.selected = true;
                        }

                        leadCityFilter.appendChild(option);
                    });

                    leadCityFilter.disabled = normalizedStateId !== '' && cities.length === 0;
                }

                function showLeadAlert(message, title = 'Notice', icon = 'info') {
                    if (window.appAlert) {
                        window.appAlert(message, title, icon);
                        return;
                    }

                    if (window.Swal) {
                        Swal.fire({
                            title,
                            text: message,
                            icon,
                            confirmButtonColor: '#0f172a'
                        });
                        return;
                    }

                    alert(message);
                }

                function readLeadCardFieldPreferences() {
                    try {
                        const raw = localStorage.getItem(leadCardFieldPreferenceKey);
                        if (!raw) return null;

                        const parsed = JSON.parse(raw);
                        return Array.isArray(parsed) ? parsed : null;
                    } catch (error) {
                        return null;
                    }
                }

                function writeLeadCardFieldPreferences(selectedKeys) {
                    try {
                        localStorage.setItem(leadCardFieldPreferenceKey, JSON.stringify(selectedKeys));
                    } catch (error) {}
                }

                function defaultLeadCardFieldKeys() {
                    return leadCardFieldOptions
                        .filter((field) => field.default_selected)
                        .map((field) => String(field.key));
                }

                function availableLeadCardFieldKeys() {
                    return new Set(leadCardFieldOptions.map((field) => String(field.key)));
                }

                function normalizeLeadCardFieldKeys(rawKeys) {
                    const availableKeys = availableLeadCardFieldKeys();
                    const filtered = Array.isArray(rawKeys)
                        ? rawKeys.map((key) => String(key)).filter((key) => availableKeys.has(key))
                        : [];

                    if (Array.isArray(rawKeys) && rawKeys.length > 0 && filtered.length === 0) {
                        return defaultLeadCardFieldKeys();
                    }

                    return filtered;
                }

                function getActiveLeadCardFieldKeys() {
                    const storedKeys = readLeadCardFieldPreferences();
                    if (storedKeys === null) {
                        return defaultLeadCardFieldKeys();
                    }

                    return normalizeLeadCardFieldKeys(storedKeys);
                }

                function syncLeadCardFieldCheckboxes(selectedKeys) {
                    const selected = new Set(selectedKeys);
                    leadCardFieldCheckboxes.forEach((checkbox) => {
                        checkbox.checked = selected.has(String(checkbox.value));
                    });
                }

                function formatLeadCardFieldValue(fieldKey, fieldValue, rawFieldData = {}) {
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

                    if (fieldKey === 'priority') {
                        return textValue.charAt(0).toUpperCase() + textValue.slice(1);
                    }

                    if (fieldKey === 'estimated_value') {
                        return `\u20B9${textValue}`;
                    }

                    if (fieldKey === 'assigned_users') {
                        return textValue;
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
                    if (fieldKey === 'priority') {
                        const normalized = String(formattedValue || '').toLowerCase();
                        if (normalized === 'high') return 'bg-red-100 text-red-800';
                        if (normalized === 'medium') return 'bg-amber-100 text-amber-800';
                        return 'bg-emerald-100 text-emerald-800';
                    }

                    return '';
                }

                function getLeadFieldRenderTarget(card) {
                    if (!card) return null;

                    if (card.tagName === 'TR') {
                        return card.querySelector('td:first-child');
                    }

                    return card;
                }

                function ensureLeadFieldGroup(card) {
                    const target = getLeadFieldRenderTarget(card);
                    if (!target) return null;

                    let fieldGroup = target.querySelector('[data-kanban-field-rendered-group]');
                    if (fieldGroup) {
                        return fieldGroup;
                    }

                    fieldGroup = document.createElement('div');
                    fieldGroup.setAttribute('data-kanban-field-rendered-group', '1');
                    fieldGroup.className = 'mt-2 flex flex-wrap gap-2';

                    if (card.tagName === 'TR') {
                        target.appendChild(fieldGroup);
                    } else {
                        const header = card.querySelector(':scope > .flex.justify-between.items-start.mb-2');
                        if (header && header.nextSibling) {
                            card.insertBefore(fieldGroup, header.nextSibling);
                        } else {
                            card.appendChild(fieldGroup);
                        }
                    }

                    return fieldGroup;
                }

                function createLeadFieldChip(fieldKey, labelText, formattedValue, card = null) {
                    const chip = document.createElement('div');
                    const baseClasses = fieldKey === 'priority'
                        ? 'inline-flex max-w-full items-center gap-1 border px-2.5 py-1 text-[11px] leading-4'
                        : 'inline-flex max-w-full items-center gap-1 border px-2.5 py-1 text-[11px] leading-4 border-slate-200 bg-slate-50 text-slate-700';
                    chip.className = `${baseClasses} ${fieldVariantClass(fieldKey, formattedValue)}`;

                    const label = document.createElement('span');
                    label.className = 'font-semibold uppercase tracking-wide text-inherit';
                    label.textContent = `${labelText} :`;

                    const value = document.createElement('span');
                    value.className = 'min-w-0 break-words font-medium text-inherit';
                    value.textContent = formattedValue;

                    chip.appendChild(label);
                    chip.appendChild(value);

                    @if ($canEditLead)
                    if (fieldKey === 'assigned_users' && card && !card.matches('tr')) {
                        const editBtn = document.createElement('button');
                        editBtn.type = 'button';
                        editBtn.className = 'js-assign-edit ml-1 inline-flex h-4 w-4 items-center justify-center rounded text-cyan-700 hover:bg-cyan-100 hover:text-cyan-900 transition flex-shrink-0';
                        editBtn.title = 'Update Assigned Staff';
                        editBtn.setAttribute('aria-label', 'Update Assigned Staff');
                        editBtn.dataset.assignUrl = card.getAttribute('data-assign-url') || '';
                        editBtn.dataset.clientName = card.getAttribute('data-client-name') || '';
                        editBtn.dataset.assignedUsers = card.getAttribute('data-assigned-users') || '[]';
                        editBtn.dataset.assignedIds = card.getAttribute('data-assigned-ids') || '[]';
                        editBtn.innerHTML = `
                            <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
                            </svg>
                        `;
                        editBtn.addEventListener('click', function(e) {
                            e.preventDefault();
                            e.stopPropagation();
                            if (typeof window.openLeadAssignModal === 'function') {
                                window.openLeadAssignModal(editBtn);
                            }
                        });
                        chip.appendChild(editBtn);
                    }
                    @endif

                    return chip;
                }

                function renderLeadCardFieldGroups() {
                    const selectedKeys = new Set(getActiveLeadCardFieldKeys());
                    const cards = document.querySelectorAll('#kanban-board .kanban-item, #leads-list tbody tr[data-lead-id]');

                    cards.forEach((card) => {
                        const rawValues = (() => {
                            try {
                                const parsed = JSON.parse(card.getAttribute('data-kanban-field-values') || '{}');
                                return parsed && typeof parsed === 'object' ? parsed : {};
                            } catch (error) {
                                return {};
                            }
                        })();

                        const rawCustomValues = (() => {
                            try {
                                const parsed = JSON.parse(card.getAttribute('data-kanban-custom-fields') || '{}');
                                return parsed && typeof parsed === 'object' ? parsed : {};
                            } catch (error) {
                                return {};
                            }
                        })();

                        const fieldGroup = ensureLeadFieldGroup(card);
                        if (!fieldGroup) return;

                        fieldGroup.innerHTML = '';
                        const isListRow = card.tagName === 'TR';

                        leadCardFieldOptions.forEach((fieldDefinition) => {
                            const fieldKey = String(fieldDefinition.key || '');
                            if (!fieldKey || !selectedKeys.has(fieldKey)) return;

                            let rawValue = null;
                            let rawFieldData = fieldDefinition;

                            if (fieldKey.startsWith('custom:')) {
                                const customKey = fieldKey.slice('custom:'.length);
                                rawValue = Object.prototype.hasOwnProperty.call(rawCustomValues, customKey)
                                    ? rawCustomValues[customKey]
                                    : null;
                                rawFieldData = fieldDefinition;
                            } else {
                                rawValue = Object.prototype.hasOwnProperty.call(rawValues, fieldKey)
                                    ? rawValues[fieldKey]
                                    : null;
                            }

                            if (fieldKey === 'assigned_users' && Array.isArray(rawValue) && rawValue.length === 0) {
                                rawValue = Object.prototype.hasOwnProperty.call(rawValues, 'assigned_to')
                                    ? rawValues.assigned_to
                                    : null;
                            }

                            const formattedValue = formatLeadCardFieldValue(fieldKey, rawValue, rawFieldData);
                            if (formattedValue === null || formattedValue === '') return;

                            const chip = createLeadFieldChip(fieldKey, fieldDefinition.label || fieldKey, formattedValue, card);
                            if (isListRow) {
                                chip.classList.add('shadow-sm');
                            }
                            fieldGroup.appendChild(chip);
                        });

                        fieldGroup.classList.toggle('hidden', fieldGroup.children.length === 0);
                    });
                }
                window.renderLeadCardFieldGroups = renderLeadCardFieldGroups;

                function updateLeadCardFieldModalState(selectedKeys) {
                    syncLeadCardFieldCheckboxes(selectedKeys);
                    renderLeadCardFieldGroups();
                }

                function openLeadCardFieldsModal() {
                    if (!leadCardFieldsModal) return;
                    leadCardFieldsModal.classList.remove('hidden');
                    leadCardFieldsModal.classList.add('flex');
                    leadCardFieldsModal.setAttribute('aria-hidden', 'false');
                    lockBackground();
                }

                function closeLeadCardFieldsModal() {
                    if (!leadCardFieldsModal) return;
                    leadCardFieldsModal.classList.add('hidden');
                    leadCardFieldsModal.classList.remove('flex');
                    leadCardFieldsModal.setAttribute('aria-hidden', 'true');
                    unlockBackground();
                }

                function updateLeadFilterToggle(open) {
                    if (!leadFilterToggle || !leadFilterWrapper) return;

                    leadFilterWrapper.classList.toggle('hidden', !open);
                    leadFilterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');

                    const label = leadFilterToggle.querySelector('span');
                    if (label) {
                        label.textContent = open ? 'Hide Filters' : 'Filters';
                    }
                }

                function hasActiveLeadFilters() {
                    if (!leadFilterPanel) return false;

                    return Array.from(leadFilterPanel.querySelectorAll('input, select, textarea')).some((field) => {
                        if (!('value' in field)) return false;
                        return String(field.value ?? '').trim() !== '';
                    });
                }

                function setActive(btn, active) {
                    if (!btn) return;
                    if (active) {
                        btn.classList.remove('bg-white', 'text-gray-700', 'hover:bg-gray-50');
                        btn.classList.add('bg-gray-800', 'text-white');
                    } else {
                        btn.classList.remove('bg-gray-800', 'text-white');
                        btn.classList.add('bg-white', 'text-gray-700', 'hover:bg-gray-50');
                    }
                }

                function setView(view) {
                    const isList = view === 'list';
                    if (kanbanBoard) kanbanBoard.style.display = isList ? 'none' : '';
                    if (leadsList) leadsList.classList.toggle('hidden', !isList);
                    setActive(kanbanBtn, !isList);
                    setActive(listBtn, isList);
                    try {
                        localStorage.setItem('leadsView', view);
                    } catch (e) {}
                }

                if (leadSearchInput && leadSearchForm) {
                    leadSearchInput.addEventListener('keyup', function(e) {
                        clearTimeout(searchTimer);
                        if (e.key === 'Enter') {
                            submitLeadSearchForm();
                            return;
                        }
                        searchTimer = setTimeout(() => {
                            submitLeadSearchForm();
                        }, 800);
                    });
                }

                populateLeadCityOptions(leadStateFilter?.value || '', selectedCityId);

                [leadMonthFilter, leadYearFilter, leadCategoryFilter, leadSourceFilter, leadAssignedFilter].forEach((filterElement) => {
                    if (!filterElement) return;

                    filterElement.addEventListener('change', function() {
                        submitLeadSearchForm();
                    });
                });

                if (leadStateFilter) {
                    leadStateFilter.addEventListener('change', function() {
                        populateLeadCityOptions(this.value, '');
                    });
                }

                if (leadCityFilter) {
                    leadCityFilter.addEventListener('change', function() {
                        submitLeadSearchForm();
                    });
                }

                [leadFollowUpFromFilter, leadFollowUpToFilter].forEach((filterElement) => {
                    if (!filterElement) return;

                    filterElement.addEventListener('change', function() {
                        submitLeadSearchForm();
                    });
                });

                if (leadFilterToggle && leadFilterWrapper && leadFilterPanel) {
                    updateLeadFilterToggle(hasActiveLeadFilters());
                    leadFilterToggle.addEventListener('click', () => {
                        const isHidden = leadFilterWrapper.classList.contains('hidden');
                        updateLeadFilterToggle(isHidden);
                    });
                }

                if (kanbanBtn) kanbanBtn.addEventListener('click', () => setView('kanban'));
                if (listBtn) listBtn.addEventListener('click', () => setView('list'));

                const initialView = @js($requestedView);
                setView(initialView);

                const initialLeadCardFieldSelection = getActiveLeadCardFieldKeys();
                updateLeadCardFieldModalState(initialLeadCardFieldSelection);

                if (leadCardFieldsOpenButton) {
                    leadCardFieldsOpenButton.addEventListener('click', openLeadCardFieldsModal);
                }

                document.querySelectorAll('[data-close-lead-card-fields-modal]').forEach((button) => {
                    button.addEventListener('click', closeLeadCardFieldsModal);
                });

                if (leadCardFieldsModal) {
                    leadCardFieldsModal.addEventListener('click', function(event) {
                        if (event.target === leadCardFieldsModal) {
                            closeLeadCardFieldsModal();
                        }
                    });
                }

                leadCardFieldCheckboxes.forEach((checkbox) => {
                    checkbox.addEventListener('change', function() {
                        const selectedKeys = leadCardFieldCheckboxes
                            .filter((input) => input.checked)
                            .map((input) => String(input.value));

                        writeLeadCardFieldPreferences(selectedKeys);
                        renderLeadCardFieldGroups();
                    });
                });

                if (leadCardFieldsSelectAll) {
                    leadCardFieldsSelectAll.addEventListener('click', function() {
                        const allKeys = leadCardFieldCheckboxes.map((input) => String(input.value));
                        syncLeadCardFieldCheckboxes(allKeys);
                        writeLeadCardFieldPreferences(allKeys);
                        renderLeadCardFieldGroups();
                    });
                }

                if (leadCardFieldsReset) {
                    leadCardFieldsReset.addEventListener('click', function() {
                        const defaultKeys = defaultLeadCardFieldKeys();
                        syncLeadCardFieldCheckboxes(defaultKeys);
                        writeLeadCardFieldPreferences(defaultKeys);
                        renderLeadCardFieldGroups();
                    });
                }

                function updateLeadStatus(statusUrl, payload) {
                    return fetch(statusUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify(payload)
                    }).then(async response => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstFieldError = data?.errors ? Object.values(data.errors)[0]?.[0] :
                            null;
                            throw new Error(firstFieldError || data?.message || 'Status update failed.');
                        }
                        return data;
                    });
                }

                function revertMove(moveContext) {
                    if (!moveContext?.item || !moveContext?.from) return;
                    const insertBeforeNode = moveContext.from.children[moveContext.oldIndex] || null;
                    moveContext.from.insertBefore(moveContext.item, insertBeforeNode);
                    refreshKanbanCounts();
                }

                function moveLeadCardToStageTop(moveContext) {
                    if (!moveContext?.item || !moveContext?.to) return;
                    moveContext.to.prepend(moveContext.item);
                    moveContext.to.scrollTop = 0;
                    refreshKanbanCounts();
                }

                function refreshKanbanCounts() {
                    const board = document.getElementById('kanban-board');
                    if (!board) return;

                    const lanes = board.querySelectorAll('.kanban-column');
                    lanes.forEach((lane) => {
                        const wrapper = lane.closest('.flex-shrink-0');
                        if (!wrapper) return;

                        const badge = wrapper.querySelector('[data-count-badge]');
                        if (!badge) return;

                        badge.textContent = String(lane.querySelectorAll('.kanban-item').length);
                    });
                }

                function openWonModal(moveContext) {
                    if (!wonModal) return;
                    pendingWonMove = moveContext;

                    const clientName = moveContext.item?.getAttribute('data-client-name') || 'Client';
                    if (wonLeadName) wonLeadName.textContent = clientName;
                    if (wonProjectName) wonProjectName.value = `Project: ${clientName}`;
                    if (wonProjectDescription) wonProjectDescription.value = `Converted from lead: ${clientName}`;
                    if (wonProjectStartDate) {
                        wonProjectStartDate.value = todayDate;
                        wonProjectStartDate.min = todayDate;
                    }
                    if (wonProjectEndDate) {
                        wonProjectEndDate.value = '';
                        wonProjectEndDate.min = todayDate;
                    }
                    if (wonProjectStatus) wonProjectStatus.value = 'pending';

                    wonModal.classList.remove('hidden');
                    lockBackground();
                }

                function closeWonModal(revert = false) {
                    if (!wonModal) return;
                    wonModal.classList.add('hidden');
                    unlockBackground();
                    if (revert) revertMove(pendingWonMove);
                    pendingWonMove = null;
                }

                function openLossModal(moveContext) {
                    if (!lossModal) return;
                    pendingLossMove = moveContext;

                    const clientName = moveContext.item?.getAttribute('data-client-name') || 'Client';
                    if (lossLeadName) lossLeadName.textContent = clientName;
                    if (lossReasonPreset) lossReasonPreset.value = '';
                    if (lossReasonText) lossReasonText.value = '';
                    if (lossReasonError) lossReasonError.classList.add('hidden');

                    lossModal.classList.remove('hidden');
                    lockBackground();
                    setTimeout(() => {
                        if (lossReasonPreset) lossReasonPreset.focus();
                    }, 50);
                }

                function closeLossModal(revert = false) {
                    if (!lossModal) return;
                    lossModal.classList.add('hidden');
                    unlockBackground();
                    if (revert) revertMove(pendingLossMove);
                    pendingLossMove = null;
                }

                function lockBackground() {
                    document.body.classList.add('overflow-hidden');

                    if (pageContent) {
                        pageContent.classList.add('pointer-events-none', 'select-none');
                        pageContent.setAttribute('aria-hidden', 'true');
                        pageContent.setAttribute('inert', '');
                    }
                }

                function unlockBackground() {
                    document.body.classList.remove('overflow-hidden');

                    if (pageContent) {
                        pageContent.classList.remove('pointer-events-none', 'select-none');
                        pageContent.removeAttribute('aria-hidden');
                        pageContent.removeAttribute('inert');
                    }
                }
                @if ($canUpdateLeadStatus)
                    const board = document.getElementById('kanban-board');
                    let isKanbanDragging = false;
                    let kanbanAutoScrollFrame = null;
                    let latestDragClientX = null;
                    let suppressLeadPreviewUntil = 0;

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

                    function trackKanbanDragPointer(event) {
                        if (!isKanbanDragging) return;
                        const clientX = getDragClientX(event);
                        if (clientX !== null) latestDragClientX = clientX;
                    }

                    function runKanbanAutoScroll() {
                        if (!isKanbanDragging || !board) {
                            kanbanAutoScrollFrame = null;
                            return;
                        }

                        if (latestDragClientX !== null) {
                            const rect = board.getBoundingClientRect();
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
                                board.scrollLeft += scrollDelta;
                                clearActiveTextSelection();
                            }
                        }

                        kanbanAutoScrollFrame = window.requestAnimationFrame(runKanbanAutoScroll);
                    }

                    function startKanbanDrag() {
                        isKanbanDragging = true;
                        document.body.classList.add('kanban-dragging');
                        clearActiveTextSelection();
                        if (!kanbanAutoScrollFrame) {
                            kanbanAutoScrollFrame = window.requestAnimationFrame(runKanbanAutoScroll);
                        }
                    }

                    function finishKanbanDrag() {
                        isKanbanDragging = false;
                        document.body.classList.remove('kanban-dragging');
                        suppressLeadPreviewUntil = Date.now() + 250;
                        latestDragClientX = null;
                        clearActiveTextSelection();

                        if (kanbanAutoScrollFrame) {
                            window.cancelAnimationFrame(kanbanAutoScrollFrame);
                            kanbanAutoScrollFrame = null;
                        }
                    }

                    if (board) {
                        board.addEventListener('mousedown', function(e) {
                            if (e.target.closest('.kanban-item')) clearActiveTextSelection();
                        });

                        board.addEventListener('selectstart', function(e) {
                            if (e.target.closest('.kanban-item')) e.preventDefault();
                        });

                        board.addEventListener('click', function(e) {
                            if (Date.now() > suppressLeadPreviewUntil) return;
                            e.preventDefault();
                            e.stopPropagation();
                            e.stopImmediatePropagation();
                        }, true);

                        document.addEventListener('pointermove', trackKanbanDragPointer, { passive: true });
                        document.addEventListener('mousemove', trackKanbanDragPointer, { passive: true });
                        document.addEventListener('touchmove', trackKanbanDragPointer, { passive: true });

                        // Track cursor during native DnD (forceFallback: false)
                        // mousemove/pointermove are suppressed by the browser during native drag
                        document.addEventListener('drag', function(e) {
                            if (isKanbanDragging && e.clientX !== 0) {
                                latestDragClientX = e.clientX;
                            }
                        }, { passive: true });
                        board.addEventListener('dragover', function(e) {
                            if (isKanbanDragging) {
                                latestDragClientX = e.clientX;
                            }
                        }, { passive: true });
                        document.addEventListener('selectstart', function(e) {
                            if (isKanbanDragging) e.preventDefault();
                        });
                    }

                    const columns = document.querySelectorAll('.kanban-column');
                    columns.forEach(column => {
                       new Sortable(column, {

                            group: 'kanban',
                            animation: 80,
                            ghostClass: 'sortable-ghost',
                            chosenClass: 'sortable-chosen',
                            dragClass: 'sortable-drag',

                            forceFallback: false,
                            fallbackOnBody: false,
                            fallbackTolerance: 8,

                            scroll: true,
                            bubbleScroll: true,
                            scrollSensitivity: 120,
                            scrollSpeed: 18,

                            onStart: function(evt) {
                                startKanbanDrag();
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
                                    setTimeout(function() {
                                        if (dragImg.parentNode) dragImg.parentNode.removeChild(dragImg);
                                    }, 100);
                                } catch(e) {}
                            },

                            onMove: function(evt, originalEvent) {
                                trackKanbanDragPointer(originalEvent);
                                return true;
                            },

                            onEnd: function(evt) {
                                finishKanbanDrag();

                                if (evt.from === evt.to) return;

                                refreshKanbanCounts();

                                const statusUrl = evt.item.getAttribute('data-status-url');
                                const newStatusId = evt.to.getAttribute('data-status-id');
                                const newStatusName = (evt.to.getAttribute('data-status-name') || '').toLowerCase();

                                const hasProject = String(
                                    evt.item.getAttribute('data-has-project') || '0'
                                ) === '1';

                                const moveContext = {
                                    item: evt.item,
                                    from: evt.from,
                                    to: evt.to,
                                    oldIndex: evt.oldIndex,
                                    statusUrl,
                                    newStatusId
                                };

                                if (
                                    (wonStatusId && String(newStatusId) === String(wonStatusId)) ||
                                    newStatusName === 'won'
                                ) {

                                    if (hasProject) {

                                        revertMove(moveContext);

                                        showLeadAlert(
                                            'This lead is already converted to a project.',
                                            'Lead Already Converted',
                                            'info'
                                        );

                                        return;
                                    }

                                    openWonModal(moveContext);
                                    return;
                                }

                                if (
                                    (lossStatusId && String(newStatusId) === String(lossStatusId)) ||
                                    newStatusName === 'loss' ||
                                    newStatusName === 'lost'
                                ) {
                                    openLossModal(moveContext);
                                    return;
                                }

                                updateLeadStatus(statusUrl, {
                                    lead_status_id: newStatusId
                                })
                                .then(() => {
                                    moveLeadCardToStageTop(moveContext);
                                })
                                .catch(error => {

                                    revertMove(moveContext);

                                    showLeadAlert(
                                        error.message,
                                        'Lead Update Failed',
                                        'error'
                                    );

                                });
                            }
                        });
                    });
                @endif

                if (wonProjectStartDate && wonProjectEndDate) {
                    wonProjectStartDate.addEventListener('change', function() {
                        const minEnd = wonProjectStartDate.value || todayDate;
                        wonProjectEndDate.min = minEnd;
                        if (wonProjectEndDate.value && wonProjectEndDate.value < minEnd) {
                            wonProjectEndDate.value = '';
                        }
                    });
                }

                if (wonModalForm) {
                    wonModalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        if (!pendingWonMove) return;

                        const payload = {
                            lead_status_id: pendingWonMove.newStatusId,
                            project_name: wonProjectName?.value || '',
                            project_description: wonProjectDescription?.value || '',
                            project_start_date: wonProjectStartDate?.value || '',
                            project_end_date: wonProjectEndDate?.value || '',
                            project_status: wonProjectStatus?.value || 'pending'
                        };

                        updateLeadStatus(pendingWonMove.statusUrl, payload)
                            .then(() => {
                                if (pendingWonMove?.item) {
                                    pendingWonMove.item.setAttribute('data-has-project', '1');
                                }
                                moveLeadCardToStageTop(pendingWonMove);
                                closeWonModal(false);
                            })
                            .catch(error => {
                                closeWonModal(true);
                                showLeadAlert(error.message, 'Lead Update Failed', 'error');
                            });
                    });
                }

                if (wonModalClose) wonModalClose.addEventListener('click', () => closeWonModal(true));
                if (wonModalCancel) wonModalCancel.addEventListener('click', () => closeWonModal(true));
                if (wonModal) {
                    wonModal.addEventListener('click', function(e) {
                        if (e.target === wonModal || e.target.classList.contains('won-modal-overlay'))
                            closeWonModal(true);
                    });
                }

                if (lossReasonPreset && lossReasonText) {
                    lossReasonPreset.addEventListener('change', function() {
                        if (this.value) {
                            lossReasonText.value = this.value;
                            if (lossReasonError) lossReasonError.classList.add('hidden');
                        }
                    });
                }

                if (lossReasonText) {
                    lossReasonText.addEventListener('input', function() {
                        if (this.value.trim() && lossReasonError) {
                            lossReasonError.classList.add('hidden');
                        }
                    });
                }

                if (lossModalForm) {
                    lossModalForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        if (!pendingLossMove) return;

                        const reason = (lossReasonText?.value || '').trim();
                        if (!reason) {
                            if (lossReasonError) lossReasonError.classList.remove('hidden');
                            if (lossReasonText) lossReasonText.focus();
                            return;
                        }

                        const payload = {
                            lead_status_id: pendingLossMove.newStatusId,
                            loss_reason: reason
                        };

                        updateLeadStatus(pendingLossMove.statusUrl, payload)
                            .then(() => {
                                moveLeadCardToStageTop(pendingLossMove);
                                closeLossModal(false);
                            })
                            .catch(error => {
                                closeLossModal(true);
                                showLeadAlert(error.message, 'Lead Update Failed', 'error');
                            });
                    });
                }

                if (lossModalClose) lossModalClose.addEventListener('click', () => closeLossModal(true));
                if (lossModalCancel) lossModalCancel.addEventListener('click', () => closeLossModal(true));
                if (lossModal) {
                    lossModal.addEventListener('click', function(e) {
                        if (e.target === lossModal || e.target.classList.contains('loss-modal-overlay'))
                            closeLossModal(true);
                    });
                }

                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        if (wonModal && !wonModal.classList.contains('hidden')) {
                            closeWonModal(true);
                        } else if (lossModal && !lossModal.classList.contains('hidden')) {
                            closeLossModal(true);
                        }
                    }
                });

                function showDeleteConfirm(form) {
                    if (!window.Swal) {
                        if (confirm('Are you sure you want to delete this lead?')) {
                            form.submit();
                        }
                        return;
                    }

                    Swal.fire({
                        title: 'Delete this lead?',
                        text: 'This action cannot be undone.',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#dc2626',
                        cancelButtonColor: '#64748b',
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            form.submit();
                        }
                    });
                }

                document.querySelectorAll('.lead-delete-form button[type="submit"]').forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const form = btn.closest('form');
                        if (!form) return;
                        showDeleteConfirm(form);
                    });
                });

                @if (session('lead_deleted_success'))
                    showLeadAlert(@js(session('lead_deleted_success')), 'Deleted successfully', 'success');
                @endif
            });
        </script>

        @if ($canEditLead)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('lead-assign-modal');
                if (!modal) return;

                const optionList = document.getElementById('lead-assign-list');
                const checkboxes = () => Array.from(modal.querySelectorAll('.js-assign-checkbox'));
                const options = () => Array.from(modal.querySelectorAll('.js-assign-option'));
                const searchInput = document.getElementById('lead-assign-search');
                const emptyMsg = document.getElementById('lead-assign-empty');
                const countEl = document.getElementById('lead-assign-selected-count');
                const titleEl = document.getElementById('lead-assign-modal-title');
                const saveBtn = document.getElementById('lead-assign-save');
                const csrfToken = '{{ csrf_token() }}';

                let activeButton = null;
                let activeUrl = '';

                function updateCount() {
                    if (countEl) countEl.textContent = String(checkboxes().filter((c) => c.checked).length);
                }

                function filterStaff(term) {
                    const needle = (term || '').trim().toLowerCase();
                    let visible = 0;
                    options().forEach((opt) => {
                        const name = opt.dataset.staffName || '';
                        const match = needle === '' || name.includes(needle);
                        opt.classList.toggle('hidden', !match);
                        if (match) visible++;
                    });
                    if (emptyMsg) emptyMsg.classList.toggle('hidden', visible !== 0);
                }

                function openModal(button) {
                    activeButton = button;
                    activeUrl = button.dataset.assignUrl || '';
                    const clientName = button.dataset.clientName || 'Lead';
                    if (titleEl) titleEl.textContent = 'Assign Staff — ' + clientName;

                    let assignedIds = [];
                    try { assignedIds = JSON.parse(button.dataset.assignedIds || '[]'); } catch (e) {}
                    const assignedSet = new Set(assignedIds.map((id) => String(id)));

                    checkboxes().forEach((cb) => { cb.checked = assignedSet.has(String(cb.value)); });
                    if (searchInput) searchInput.value = '';
                    filterStaff('');
                    updateCount();

                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                    if (optionList) optionList.scrollTop = 0;
                }

                window.openLeadAssignModal = openModal;

                function closeModal() {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    activeButton = null;
                    activeUrl = '';
                }

                function notify(type, message) {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: type,
                            title: message,
                            showConfirmButton: false,
                            timer: 2500,
                            timerProgressBar: true,
                        });
                    } else if (type === 'error') {
                        alert(message);
                    }
                }

                function escapeHtml(value) {
                    return String(value ?? '')
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function getInitials(name) {
                    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return '?';
                    if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();
                    return (parts[0].slice(0, 1) + parts[parts.length - 1].slice(0, 1)).toUpperCase();
                }

                function renderAssignedUsers(users = [], options = {}) {
                    const assignedUsers = Array.isArray(users)
                        ? users.filter((user) => String(user?.name || '').trim() !== '')
                        : [];
                    const maxVisible = Number.isInteger(options.maxVisible) ? options.maxVisible : 3;

                    if (!assignedUsers.length) {
                        return '<span class="text-gray-500">-</span>';
                    }

                    const visibleUsers = assignedUsers.slice(0, maxVisible);
                    const overflowUsers = assignedUsers.slice(maxVisible);

                    return `
                        <div class="flex items-center -space-x-2 overflow-hidden">
                            ${visibleUsers.map((user) => `
                                <span
                                    class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center border border-white bg-indigo-500 text-[10px] font-semibold text-white shadow-sm"
                                    title="${escapeHtml(user.name || 'Assigned user')}"
                                    aria-label="${escapeHtml(user.name || 'Assigned user')}">
                                    ${escapeHtml(getInitials(user.name))}
                                </span>
                            `).join('')}
                            ${overflowUsers.length ? `
                                <span
                                    class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center border border-white bg-slate-200 text-[10px] font-semibold text-slate-700 shadow-sm"
                                    title="${escapeHtml(overflowUsers.map((user) => user.name).join(', '))}"
                                    aria-label="${escapeHtml(`${overflowUsers.length} more assigned users`)}">
                                    +${overflowUsers.length}
                                </span>
                            ` : ''}
                        </div>
                    `;
                }

                function saveAssignment() {
                    if (!activeUrl) return;
                    const selectedIds = checkboxes().filter((c) => c.checked).map((c) => c.value);

                    saveBtn.disabled = true;
                    const originalText = saveBtn.textContent;
                    saveBtn.textContent = 'Saving...';

                    fetch(activeUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ assigned_to: selectedIds }),
                    })
                    .then(async (response) => {
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok) {
                            const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                            throw new Error(firstError || data?.message || 'Failed to update assigned staff.');
                        }
                        return data;
                    })
                    .then((data) => {
                        if (activeButton) {
                            const assignedUsers = Array.isArray(data.assigned_users)
                                ? data.assigned_users
                                : ((data.assigned_label && data.assigned_label !== '-'
                                    ? data.assigned_label.split(', ')
                                    : []).map((name) => ({ name })));
                            const assignedUserIds = data.assigned_user_ids || [];
                            const userNames = assignedUsers.map((u) => u.name).filter(Boolean);

                            activeButton.dataset.assignedIds = JSON.stringify(assignedUserIds);
                            activeButton.dataset.assignedUsers = JSON.stringify(assignedUsers);

                            // If button is inside list table row
                            const row = activeButton.closest('tr');
                            if (row) {
                                const displayEl = row.querySelector('[data-assigned-display]');
                                if (displayEl) displayEl.innerHTML = renderAssignedUsers(assignedUsers);
                                row.setAttribute('data-assigned-users', JSON.stringify(assignedUsers));
                                try {
                                    const fieldVals = JSON.parse(row.getAttribute('data-kanban-field-values') || '{}');
                                    fieldVals.assigned_users = userNames;
                                    fieldVals.assigned_to = userNames[0] || null;
                                    row.setAttribute('data-kanban-field-values', JSON.stringify(fieldVals));
                                } catch (e) {}
                            }

                            // If button is inside kanban item card
                            const kanbanCard = activeButton.closest('.kanban-item');
                            if (kanbanCard) {
                                kanbanCard.setAttribute('data-assigned-users', JSON.stringify(assignedUsers));
                                try {
                                    const fieldVals = JSON.parse(kanbanCard.getAttribute('data-kanban-field-values') || '{}');
                                    fieldVals.assigned_users = userNames;
                                    fieldVals.assigned_to = userNames[0] || null;
                                    kanbanCard.setAttribute('data-kanban-field-values', JSON.stringify(fieldVals));
                                } catch (e) {}
                            }

                            // Also sync paired row/card across list and kanban if both exist in DOM
                            const leadId = (row || kanbanCard)?.dataset.leadId;
                            if (leadId) {
                                document.querySelectorAll(`[data-lead-id="${leadId}"]`).forEach((element) => {
                                    element.setAttribute('data-assigned-users', JSON.stringify(assignedUsers));
                                    try {
                                        const fieldVals = JSON.parse(element.getAttribute('data-kanban-field-values') || '{}');
                                        fieldVals.assigned_users = userNames;
                                        fieldVals.assigned_to = userNames[0] || null;
                                        element.setAttribute('data-kanban-field-values', JSON.stringify(fieldVals));
                                    } catch (e) {}

                                    const btn = element.querySelector('.js-assign-edit');
                                    if (btn) {
                                        btn.dataset.assignedIds = JSON.stringify(assignedUserIds);
                                        btn.dataset.assignedUsers = JSON.stringify(assignedUsers);
                                    }

                                    const displayEl = element.querySelector('[data-assigned-display]');
                                    if (displayEl) displayEl.innerHTML = renderAssignedUsers(assignedUsers);
                                });
                            }

                            if (typeof window.renderLeadCardFieldGroups === 'function') {
                                window.renderLeadCardFieldGroups();
                            }
                        }
                        closeModal();
                        notify('success', data.message || 'Assigned staff updated.');
                    })
                    .catch((error) => {
                        notify('error', error.message || 'Failed to update assigned staff.');
                    })
                    .finally(() => {
                        saveBtn.disabled = false;
                        saveBtn.textContent = originalText;
                    });
                }

                document.querySelectorAll('.js-assign-edit').forEach((button) => {
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        event.stopPropagation();
                        openModal(button);
                    });
                });

                modal.querySelectorAll('[data-close-lead-assign-modal]').forEach((el) => {
                    el.addEventListener('click', closeModal);
                });

                if (searchInput) searchInput.addEventListener('input', (e) => filterStaff(e.target.value));
                if (optionList) optionList.addEventListener('change', updateCount);
                if (saveBtn) saveBtn.addEventListener('click', saveAssignment);

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            });
        </script>
        @endif
    @endpush

    {{-- Lead Preview Panel --}}
    <div id="lead-preview-overlay" class="hidden fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-[1px]"
        onclick="closeLeadPreview()"></div>
    <aside id="lead-preview-panel"
        class="fixed top-0 right-0 z-50 h-full w-full max-w-sm bg-white shadow-2xl border-l border-slate-200 flex flex-col translate-x-full transition-transform duration-300 ease-in-out overflow-hidden">
        {{-- Panel Header --}}
        <div
            class="flex items-start justify-between p-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white flex-shrink-0">
            <div class="min-w-0 flex-1 pr-2">
                <p id="lp-client-name" class="text-base font-bold text-slate-900 truncate"></p>
                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                    <span id="lp-status-badge"
                        class="inline-flex px-2 py-0.5 text-xs font-semibold"></span>
                    <span id="lp-priority-badge"
                        class="inline-flex px-2 py-0.5 text-xs font-semibold"></span>
                </div>
            </div>
            <button onclick="closeLeadPreview()"
                class="flex-shrink-0 text-slate-400 hover:text-slate-600 transition-colors p-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>

        <div class="flex min-h-0 flex-1 flex-col">
            <div class="flex-shrink-0">
            {{-- Contact Info --}}
            <div class="px-4 py-3 border-b border-slate-100 space-y-2">
                <div id="lp-mobile-row" class="flex items-center gap-2 text-sm">
                    <svg class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                    </svg>
                    <a id="lp-mobile-link" href="#" class="text-indigo-700 hover:underline font-medium"></a>
                </div>
                <div id="lp-email-row" class="flex items-center gap-2 text-sm">
                    <svg class="h-4 w-4 text-slate-400 flex-shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                    <a id="lp-email-link" href="#"
                        class="text-indigo-700 hover:underline truncate max-w-[200px]"></a>
                </div>
            </div>

            {{-- Lead Details --}}
            <div class="px-4 py-3 border-b border-slate-100 space-y-2 text-sm">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Category</span>
                    <span id="lp-category" class="font-medium text-slate-800"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Est. Value</span>
                    <span id="lp-value" class="font-bold text-indigo-700"></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Assigned To</span>
                    <span id="lp-assigned"
                        class="font-medium text-slate-800 text-right max-w-[180px] truncate"></span>
                </div>
            </div>

            {{-- Quick Actions --}}
            <div class="px-4 py-3 border-b border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">Quick Actions</p>
                <div class="grid grid-cols-2 gap-2">
                    <a id="lp-call-btn" href="#"
                        class="inline-flex items-center justify-center gap-1.5 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                        </svg>
                        Call
                    </a>
                    <a id="lp-email-btn" href="#"
                        class="inline-flex items-center justify-center gap-1.5 bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Email
                    </a>
                    <button onclick="toggleSection('lp-contact-form')"
                        class="inline-flex items-center justify-center gap-1.5 bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Log Contact
                    </button>
                    <button onclick="toggleSection('lp-calendar-form')"
                        class="inline-flex items-center justify-center gap-1.5 bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100 transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                        Add Todo
                    </button>
                </div>
            </div>

            </div>

            <div class="min-h-0 flex-1 overflow-y-auto">

            {{-- Activity / Comments Tabs --}}
            <div id="lp-tabs-section" class="border-b border-slate-100">
                {{-- Tab Navigation --}}
                <div class="flex border-b border-slate-100 px-4">
                    <button id="lp-tab-comments" onclick="switchLeadPreviewTab('comments')"
                        class="relative -mb-px py-2.5 px-4 text-xs font-semibold uppercase tracking-widest border-b-2 border-indigo-500 text-indigo-600 transition flex items-center gap-1.5">
                        Comments
                        <span id="lp-comments-count" class="inline-flex items-center bg-indigo-100 px-2 py-0.5 text-[10px] font-semibold text-indigo-600">0</span>
                    </button>
                    <button id="lp-tab-activity" onclick="switchLeadPreviewTab('activity')"
                        class="relative -mb-px py-2.5 px-4 text-xs font-semibold uppercase tracking-widest border-b-2 border-transparent text-slate-400 hover:text-slate-600 transition">
                        Activity
                    </button>
                </div>

                {{-- Comments Panel --}}
                <div id="lp-comments-section" class="px-4 py-3">
                    <div id="lp-comments-empty"
                        class="hidden border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                        No comments yet.
                    </div>
                    <div id="lp-comments-list" class="space-y-0.5"></div>

                    @if ($canCommentLead)
                        <form id="lp-comment-form-el" class="mt-4 space-y-2.5" onsubmit="submitLeadForm(event, 'comment')">
                            @csrf
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Add Internal Comment</label>
                                <textarea name="comment" rows="3" maxlength="500" data-min="5" data-max="500"
                                    placeholder="Write a private note for your team..."
                                    class="mt-1 w-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 focus:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-100"></textarea>
                            </div>
                            <div id="lp-comment-msg" class="hidden text-xs font-medium py-1.5 px-2"></div>
                            <button type="submit"
                                class="inline-flex w-full items-center justify-center bg-slate-950 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-slate-800">
                                Post Comment
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Activity Panel --}}
                <div id="lp-activity-section" class="hidden px-4 py-3">
                    <div id="lp-activity-empty"
                        class="hidden border border-dashed border-slate-200 bg-slate-50 px-3 py-6 text-center text-sm text-slate-500">
                        No activity recorded yet.
                    </div>
                    <div id="lp-activity-list" class="space-y-0.5"></div>
                </div>
            </div>

            {{-- Log Contact Form --}}
            <div id="lp-contact-form" class="hidden px-4 py-3 border-b border-slate-100 bg-violet-50/40">
                <p class="text-[10px] font-bold uppercase tracking-widest text-violet-600 mb-3">Log Contact</p>
                <form id="lp-contact-form-el" onsubmit="submitLeadForm(event, 'contact')">
                    @csrf
                    <div class="space-y-2.5">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Contact Type</label>
                            <select name="type" required
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                                <option value="call">📞 Call</option>
                                <option value="email">✉️ Email</option>
                                <option value="meeting">🤝 Meeting</option>
                                <option value="site_visit">📍 Site Visit</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Date & Time</label>
                            <input type="datetime-local" name="occurred_at" id="lp-occurred-at" required
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Assign Staff (Optional)</label>
                            <div x-data="{
                                    open: false,
                                    search: '',
                                    selected: [],
                                    users: @js($users->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name, 'role' => (string) ($user->role ?? 'employee')])->values()->all()),
                                    toggle(id) {
                                        id = Number(id);
                                        if (this.selected.includes(id)) {
                                            this.selected = this.selected.filter(item => item !== id);
                                            return;
                                        }
                                        this.selected.push(id);
                                    },
                                    getUserName(id) {
                                        const user = this.users.find(item => Number(item.id) === Number(id));
                                        return user ? user.name : 'Unknown';
                                    },
                                    matches(user) {
                                        const keyword = this.search.trim().toLowerCase();
                                        if (!keyword) return true;
                                        return user.name.toLowerCase().includes(keyword) || user.role.toLowerCase().includes(keyword);
                                    },
                                    get filteredUsers() {
                                        return this.users.filter(user => this.matches(user));
                                    }
                                }">
                                <div class="relative">
                                    <div
                                        @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
                                        @click.outside="open = false"
                                        class="min-h-[42px] w-full border border-slate-200 bg-white px-3 py-2 focus-within:ring-2 focus-within:ring-violet-400"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <template x-for="id in selected" :key="'contact-selected-'+id">
                                                <span class="inline-flex items-center gap-1 border border-violet-200 bg-violet-50 px-2 py-1 text-xs font-semibold text-violet-700">
                                                    <span x-text="getUserName(id)"></span>
                                                    <button type="button" @click.stop="toggle(id)" class="text-violet-400 hover:text-violet-900">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </span>
                                            </template>
                                            <input
                                                x-ref="searchInput"
                                                x-model="search"
                                                @focus="open = true"
                                                type="text"
                                                class="min-w-[170px] flex-1 border-0 p-1 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                                placeholder="Search and assign team members..."
                                            >
                                        </div>
                                    </div>

                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 transform scale-95"
                                        x-transition:enter-end="opacity-100 transform scale-100"
                                        class="absolute z-50 mt-1 max-h-[410px] w-full overflow-y-auto overscroll-contain border border-gray-200 bg-white shadow-xl"
                                        style="display: none;"
                                    >
                                        <template x-if="filteredUsers.length === 0">
                                            <div class="px-4 py-3 text-sm text-slate-500">No employees found.</div>
                                        </template>
                                        <template x-for="user in filteredUsers" :key="'contact-option-'+user.id">
                                            <button
                                                type="button"
                                                @click="toggle(user.id)"
                                                class="flex w-full items-center justify-between border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-none hover:bg-violet-50"
                                                :class="selected.includes(user.id) ? 'bg-violet-50/60 text-violet-700' : 'text-slate-700'"
                                            >
                                                <span class="font-medium">
                                                    <span x-text="user.name"></span>
                                                    <span class="text-[10px] text-slate-400">[<span x-text="user.role"></span>]</span>
                                                </span>
                                                <span x-show="selected.includes(user.id)" class="bg-violet-600 p-0.5">
                                                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </span>
                                            </button>
                                        </template>
                                    </div>

                                    <template x-for="id in selected" :key="'contact-submit-'+id">
                                        <input type="hidden" name="assigned_staff_ids[]" :value="id">
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Meeting Type (Optional)</label>
                            <select name="meeting_type" onchange="toggleMeetingFields(this, 'contact')" class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                                <option value="">Select type</option>
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                            </select>
                        </div>
                        <div id="lp-contact-location-wrap" class="hidden">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Location (Offline)</label>
                            <input type="text" name="meeting_location" placeholder="Client office / site"
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                        </div>
                        <div id="lp-contact-link-wrap" class="hidden">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Meeting Link (Online)</label>
                            <input type="text" name="meeting_link" placeholder="https://..."
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-slate-600">Notes</label>
                                <x-speech-to-text target="lp-contact-description" />
                            </div>
                            <textarea id="lp-contact-description" name="description" rows="2" placeholder="What was discussed..."
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-violet-400 resize-none"></textarea>
                        </div>
                        <div id="lp-contact-msg" class="hidden text-xs font-medium py-1.5 px-2"></div>
                        <button type="submit"
                            class="w-full bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700 transition">
                            Save Contact Log
                        </button>
                    </div>
                </form>
            </div>

            {{-- Add to Calendar Form --}}
            <div id="lp-calendar-form" class="hidden px-4 py-3 border-b border-slate-100 bg-amber-50/40">
                <p class="text-[10px] font-bold uppercase tracking-widest text-amber-600 mb-3">Add to Calendar</p>
                <form id="lp-calendar-form-el" onsubmit="submitLeadForm(event, 'calendar')">
                    @csrf
                    <div class="space-y-2.5">
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Title</label>
                            <input type="text" name="title" id="lp-cal-title" required
                                placeholder="Follow up: ..."
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Date</label>
                            <input type="date" name="event_date" id="lp-cal-date" required
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Time (Optional)</label>
                            <input type="time" name="event_time"
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Type</label>
                            <select name="event_type"
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="task">Task</option>
                                <option value="appointment">Appointment</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Assign Staff (Optional)</label>
                            <div x-data="{
                                    open: false,
                                    search: '',
                                    selected: [],
                                    users: @js($users->map(fn ($user) => ['id' => (int) $user->id, 'name' => (string) $user->name, 'role' => (string) ($user->role ?? 'employee')])->values()->all()),
                                    toggle(id) {
                                        id = Number(id);
                                        if (this.selected.includes(id)) {
                                            this.selected = this.selected.filter(item => item !== id);
                                            return;
                                        }
                                        this.selected.push(id);
                                    },
                                    getUserName(id) {
                                        const user = this.users.find(item => Number(item.id) === Number(id));
                                        return user ? user.name : 'Unknown';
                                    },
                                    matches(user) {
                                        const keyword = this.search.trim().toLowerCase();
                                        if (!keyword) return true;
                                        return user.name.toLowerCase().includes(keyword) || user.role.toLowerCase().includes(keyword);
                                    },
                                    get filteredUsers() {
                                        return this.users.filter(user => this.matches(user));
                                    }
                                }">
                                <div class="relative">
                                    <div
                                        @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
                                        @click.outside="open = false"
                                        class="min-h-[42px] w-full border border-slate-200 bg-white px-3 py-2 focus-within:ring-2 focus-within:ring-amber-400"
                                    >
                                        <div class="flex flex-wrap items-center gap-2">
                                            <template x-for="id in selected" :key="'calendar-selected-'+id">
                                                <span class="inline-flex items-center gap-1 border border-amber-200 bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">
                                                    <span x-text="getUserName(id)"></span>
                                                    <button type="button" @click.stop="toggle(id)" class="text-amber-500 hover:text-amber-900">
                                                        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                    </button>
                                                </span>
                                            </template>
                                            <input
                                                x-ref="searchInput"
                                                x-model="search"
                                                @focus="open = true"
                                                type="text"
                                                class="min-w-[170px] flex-1 border-0 p-1 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                                placeholder="Search and assign team members..."
                                            >
                                        </div>
                                    </div>

                                    <div
                                        x-show="open"
                                        x-transition:enter="transition ease-out duration-100"
                                        x-transition:enter-start="opacity-0 transform scale-95"
                                        x-transition:enter-end="opacity-100 transform scale-100"
                                        class="absolute z-50 mt-1 max-h-[410px] w-full overflow-y-auto overscroll-contain border border-gray-200 bg-white shadow-xl"
                                        style="display: none;"
                                    >
                                        <template x-if="filteredUsers.length === 0">
                                            <div class="px-4 py-3 text-sm text-slate-500">No employees found.</div>
                                        </template>
                                        <template x-for="user in filteredUsers" :key="'calendar-option-'+user.id">
                                            <button
                                                type="button"
                                                @click="toggle(user.id)"
                                                class="flex w-full items-center justify-between border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-none hover:bg-amber-50"
                                                :class="selected.includes(user.id) ? 'bg-amber-50/70 text-amber-700' : 'text-slate-700'"
                                            >
                                                <span class="font-medium">
                                                    <span x-text="user.name"></span>
                                                    <span class="text-[10px] text-slate-400">[<span x-text="user.role"></span>]</span>
                                                </span>
                                                <span x-show="selected.includes(user.id)" class="bg-amber-500 p-0.5">
                                                    <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                                </span>
                                            </button>
                                        </template>
                                    </div>

                                    <template x-for="id in selected" :key="'calendar-submit-'+id">
                                        <input type="hidden" name="assigned_staff_ids[]" :value="id">
                                    </template>
                                </div>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-600 mb-1">Meeting Type (Optional)</label>
                            <select name="meeting_type" onchange="toggleMeetingFields(this, 'calendar')" class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                                <option value="">Select type</option>
                                <option value="online">Online</option>
                                <option value="offline">Offline</option>
                            </select>
                        </div>
                        <div id="lp-calendar-location-wrap" class="hidden">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Location (Offline)</label>
                            <input type="text" name="meeting_location" placeholder="Office / client site"
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div id="lp-calendar-link-wrap" class="hidden">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Meeting Link (Online)</label>
                            <input type="text" name="meeting_link" placeholder="https://..."
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400">
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-1">
                                <label class="block text-xs font-medium text-slate-600">Description</label>
                                <x-speech-to-text target="lp-cal-description" />
                            </div>
                            <textarea id="lp-cal-description" name="description" rows="2" placeholder="Optional notes..."
                                class="w-full border border-slate-200 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 resize-none"></textarea>
                        </div>
                        <div id="lp-cal-msg" class="hidden text-xs font-medium py-1.5 px-2"></div>
                        <button type="submit"
                            class="w-full bg-amber-500 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-600 transition">
                            Add to Calendar
                        </button>
                    </div>
                </form>
            </div>
        </div>

            </div>

        {{-- Footer Actions --}}
        <div class="flex-shrink-0 px-4 py-3 border-t border-slate-100 bg-white flex gap-2">
            <a id="lp-view-btn" href="#"
                class="flex-1 inline-flex items-center justify-center gap-1.5 bg-slate-900 px-3 py-2.5 text-xs font-semibold text-white hover:bg-slate-700 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                </svg>
                View Full Details
            </a>
            @if ($canEditLead)
                <a id="lp-edit-btn" href="#"
                    class="inline-flex items-center justify-center gap-1.5 bg-indigo-50 px-3 py-2.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"
                        fill="currentColor">
                        <path
                            d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                    </svg>
                    Edit
                </a>
            @endif
            @if ($isCompanyAdmin)
                <button type="button" id="lp-transfer-btn"
                    class="inline-flex items-center justify-center gap-1.5 bg-amber-50 px-3 py-2.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 transition"
                    title="Transfer this lead to another staff member">
                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M8 5a1 1 0 100 2h5.586l-1.293 1.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L13.586 5H8z" />
                        <path d="M12 15a1 1 0 100-2H6.414l1.293-1.293a1 1 0 10-1.414-1.414l-3 3a1 1 0 000 1.414l3 3a1 1 0 001.414-1.414L6.414 15H12z" />
                    </svg>
                    Transfer
                </button>
            @endif
        </div>
    </aside>

    <div id="won-project-modal" class="hidden fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="won-project-modal-title" role="dialog" aria-modal="true">
        <div class="won-modal-overlay absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]"></div>
        <div class="relative min-h-full flex items-center justify-center p-4">
            <div
                class="w-full max-w-xl bg-white shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
                <div
                    class="flex items-start justify-between p-5 border-b border-slate-100 bg-gradient-to-r from-slate-50 to-white">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Move Lead To Won</h3>
                        <p class="mt-1 text-sm text-slate-600">Confirm project details before saving the status change.
                        </p>
                    </div>
                    <button id="won-project-modal-close" type="button"
                        class="text-slate-400 hover:text-slate-600 transition-colors">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                            fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <form id="won-project-form" method="POST" class="flex flex-col h-full">
                    <div class="px-5 py-4 border-b border-slate-100 bg-slate-50/60">
                        <h3 id="won-project-modal-title" class="text-base font-semibold text-slate-900">
                            Project Conversion
                        </h3>
                        <p class="mt-1 text-sm text-slate-600">
                            Lead <span id="won-project-lead-name" class="font-semibold text-slate-900"></span> will
                            move to <span class="font-semibold">Won</span> and convert to project.
                        </p>
                    </div>

                    <div class="p-5 space-y-4 overflow-y-auto flex-1 max-h-[70vh]">
                        <div>
                            <x-input-label for="won-project-name" value="Project Name" />
                            <x-text-input id="won-project-name" name="project_name" class="mt-1 block w-full"
                                required />
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="won-project-description" value="Description" />
                                <x-speech-to-text target="won-project-description" />
                            </div>
                            <textarea id="won-project-description" name="project_description" rows="2"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"></textarea>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <x-input-label for="won-project-start-date" value="Start Date" />
                                <x-text-input id="won-project-start-date" name="project_start_date" type="date"
                                    min="{{ $todayDate }}" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label for="won-project-end-date" value="End Date" />
                                <x-text-input id="won-project-end-date" name="project_end_date" type="date"
                                    min="{{ $todayDate }}" class="mt-1 block w-full" />
                            </div>
                        </div>
                        <div>
                            <x-input-label for="won-project-status" value="Project Status" />
                            <select id="won-project-status" name="project_status"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 bg-white flex justify-end gap-2">
                        <button id="won-project-modal-cancel" type="button"
                            class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-xs font-semibold text-slate-700 uppercase tracking-widest hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-emerald-700">
                            Save & Move To Won
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div id="loss-reason-modal" class="hidden fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="loss-reason-modal-title" role="dialog" aria-modal="true">
        <div class="loss-modal-overlay absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]"></div>
        <div class="relative min-h-full flex items-center justify-center p-4">
            <div
                class="w-full max-w-lg bg-white shadow-2xl border border-slate-200 flex flex-col rounded-2xl">
                <div
                    class="flex items-start justify-between p-5 border-b border-slate-100 bg-gradient-to-r from-rose-50 to-white rounded-t-2xl">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-rose-100 text-rose-600">
                            <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-slate-900">Move Lead To Loss</h3>
                            <p class="mt-0.5 text-xs text-slate-500">Please provide a reason why this lead was lost.</p>
                        </div>
                    </div>
                    <button id="loss-reason-modal-close" type="button"
                        class="text-slate-400 hover:text-slate-600 transition-colors">
                        <span class="sr-only">Close</span>
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20"
                            fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <form id="loss-reason-form" method="POST" class="flex flex-col h-full">
                    <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/60">
                        <p class="text-xs text-slate-600">
                            Lead: <span id="loss-reason-lead-name" class="font-semibold text-slate-900"></span>
                        </p>
                    </div>

                    <div class="p-5 space-y-4 overflow-visible">
                        <div class="relative z-20">
                            <label for="loss-reason-preset" class="block text-xs font-semibold uppercase tracking-wider text-slate-600 mb-1">
                                Common Reason
                            </label>
                            <select id="loss-reason-preset"
                                class="mt-1 block w-full border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm text-sm">
                                <option value="">-- Select a reason (or write below) --</option>
                                @foreach ($defaultLossReasons ?? \App\Models\Lead::defaultLossReasons() as $presetReason)
                                    <option value="{{ $presetReason }}">{{ $presetReason }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <label for="loss-reason-text" class="block text-xs font-semibold uppercase tracking-wider text-slate-600">
                                    Loss Reason <span class="text-rose-600">*</span>
                                </label>
                                <x-speech-to-text target="loss-reason-text" />
                            </div>
                            <textarea id="loss-reason-text" name="loss_reason" rows="3" required
                                placeholder="Explain why the lead was lost or not interested..."
                                class="mt-1 block w-full border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm text-sm"
                                maxlength="1000"></textarea>
                            <p id="loss-reason-error" class="hidden mt-1 text-xs text-rose-600">Please provide a loss reason.</p>
                        </div>
                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 bg-white flex justify-end gap-2 rounded-b-2xl">
                        <button id="loss-reason-modal-cancel" type="button"
                            class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-xs font-semibold text-slate-700 uppercase tracking-widest hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-rose-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-rose-700">
                            Confirm & Move To Loss
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('partials.lead-followup-modal')

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('lead-follow-up-modal');
            const form = document.getElementById('lead-follow-up-form');
            const closeBtn = document.getElementById('close-lead-follow-up-modal');
            const cancelBtn = document.getElementById('cancel-lead-follow-up-modal');
            const saveBtn = document.getElementById('save-lead-follow-up-btn');
            const summaryInput = document.getElementById('lead-follow-up-remark');
            const dateInput = document.getElementById('lead-follow-up-date');
            const statusInput = document.getElementById('lead-status-select');
            const scheduleInput = document.getElementById('lead-follow-up-schedule-id');
            const currentDateText = document.getElementById('lead-follow-up-current-date');
            const leadNameText = document.getElementById('lead-follow-up-lead-name');
            const detailLink = document.getElementById('lead-follow-up-detail-link');
            const today = @json($todayDate ?? now()->toDateString());

            const escapeHtml = (value = '') => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatStatusLabel = (status = '') => String(status || 'Pending')
                .replaceAll('_', ' ')
                .replaceAll('-', ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());

            const showError = (message) => {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Unable to save',
                        text: message,
                        confirmButtonColor: '#0f172a',
                    });
                    return;
                }
                alert(message);
            };

            const setText = (id, value, fallback = 'N/A') => {
                const el = document.getElementById(id);
                if (el) el.textContent = value || fallback;
            };

            const setFollowUpLocked = (locked) => {
                document.getElementById('lead-follow-up-done-notice')?.classList.toggle('hidden', !locked);
                if (summaryInput) summaryInput.disabled = locked;
                if (dateInput) dateInput.disabled = locked;
                if (statusInput) statusInput.disabled = locked;
                if (saveBtn) saveBtn.disabled = locked;
            };

            const leadFollowUpValidator = $('#lead-follow-up-form').validate({
                ignore: [],
                errorClass: 'text-xs text-red-600',
                errorElement: 'span',
                highlight: function (element) {
                    element.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                unhighlight: function (element) {
                    element.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                rules: {
                    summary: { required: true, maxlength: 5000 },
                    next_followup_date: { required: true, min: @json($todayDate ?? now()->toDateString()) }
                },
                messages: {
                    summary: { required: 'Please enter today\'s follow-up summary.' },
                    next_followup_date: {
                        required: 'Please choose next follow-up date.',
                        min: 'Please choose today or a future date.'
                    }
                },
                errorPlacement: function (error, element) {
                    if (element.attr('name') === 'summary') {
                        const slot = document.getElementById('lead-follow-up-remark-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    if (element.attr('name') === 'next_followup_date') {
                        const slot = document.getElementById('lead-follow-up-date-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    error.insertAfter(element);
                }
            });

            const setModalLoading = () => {
                document.getElementById('lead-follow-up-loading')?.classList.remove('hidden');
                document.getElementById('lead-follow-up-info')?.classList.add('hidden');
                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) historyWrap.innerHTML = '<div class="text-slate-500">Loading follow-up summaries...</div>';
            };

            const renderLeadDetails = (payload = {}) => {
                const lead = payload.lead || {};
                const histories = Array.isArray(payload.latest_followup_summaries) ? payload.latest_followup_summaries : [];
                const customFields = Array.isArray(lead.custom_fields) ? lead.custom_fields : [];

                document.getElementById('lead-follow-up-loading')?.classList.add('hidden');
                document.getElementById('lead-follow-up-info')?.classList.remove('hidden');
                if (leadNameText) leadNameText.textContent = lead.name || 'Lead';
                setText('lead-info-company', lead.company_name);
                setText('lead-info-status', lead.status);
                setText('lead-info-followup-status', lead.last_follow_up_status ? formatStatusLabel(lead.last_follow_up_status) : null);
                setText('lead-info-mobile', lead.mobile);
                setText('lead-info-email', lead.email);
                setText('lead-info-address', lead.address);
                setText('lead-info-last-summary', lead.last_follow_up_summary, 'No follow-up history yet.');
                setFollowUpLocked(lead.can_add_followup === false);

                const customWrap = document.getElementById('lead-info-custom-fields');
                if (customWrap) {
                    customWrap.innerHTML = customFields.length
                        ? customFields.map((field) => `
                            <div class="bg-white p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(field.label || 'Custom Field')}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">${escapeHtml(field.value || 'N/A')}</p>
                            </div>
                        `).join('')
                        : '<div class="bg-white p-3 text-sm text-slate-500">No custom fields.</div>';
                }

                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) {
                    historyWrap.innerHTML = histories.length
                        ? histories.map((item) => `
                            <div class="border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-slate-500">${escapeHtml(formatStatusLabel(item.status))}</span>
                                    <span class="text-[11px] font-semibold text-slate-400">${escapeHtml(item.followup_date || '')}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-5 text-slate-700">${escapeHtml(item.summary || '')}</p>
                            </div>
                        `).join('')
                        : '<div class="text-slate-500">No follow-up summaries yet.</div>';
                }
            };

            const openFollowupModal = (row) => {
                if (!modal || !form || !row) return;

                const leadName = row.dataset.clientName || 'Lead';
                const followupDate = row.dataset.followupDate || '';
                const followupStatus = row.dataset.followupStatus || 'pending';
                const showUrl = row.dataset.showUrl || '#';

                form.action = row.dataset.followupSaveUrl || '';
                if (scheduleInput) scheduleInput.value = row.dataset.followupScheduleId || '';
                if (summaryInput) summaryInput.value = '';
                if (dateInput) {
                    dateInput.value = '';
                    dateInput.min = today;
                }
                if (statusInput) statusInput.value = 'Pending';
                setFollowUpLocked(false);
                if (leadNameText) leadNameText.textContent = leadName;
                if (currentDateText) {
                    currentDateText.textContent = followupDate
                        ? `Current schedule: ${followupDate} | ${formatStatusLabel(followupStatus)}`
                        : 'No pending follow-up schedule found. You can still save one.';
                }
                if (detailLink) {
                    detailLink.href = showUrl;
                    detailLink.classList.toggle('pointer-events-none', !showUrl || showUrl === '#');
                    detailLink.classList.toggle('opacity-40', !showUrl || showUrl === '#');
                }

                setModalLoading();
                modal.classList.remove('hidden');
                modal.classList.add('flex');

                if (row.dataset.followupDetailsUrl) {
                    fetch(row.dataset.followupDetailsUrl, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject(new Error('Unable to load lead details.')))
                        .then(renderLeadDetails)
                        .catch(() => {
                            const loading = document.getElementById('lead-follow-up-loading');
                            if (loading) loading.textContent = 'Unable to load lead details. You can still save the follow-up.';
                        });
                }

                setTimeout(() => summaryInput?.focus(), 0);
            };

            const closeFollowupModal = () => {
                modal?.classList.add('hidden');
                modal?.classList.remove('flex');
            };

            document.querySelectorAll('.js-open-list-followup').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openFollowupModal(button.closest('tr'));
                });
            });

            document.querySelectorAll('.js-open-kanban-followup').forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    event.stopPropagation();
                    openFollowupModal(button.closest('.kanban-item'));
                });
            });

            closeBtn?.addEventListener('click', closeFollowupModal);
            cancelBtn?.addEventListener('click', closeFollowupModal);
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) closeFollowupModal();
            });

            form?.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (saveBtn?.disabled) {
                    return;
                }

                const summary = String(summaryInput?.value || '').trim();
                const nextDate = String(dateInput?.value || '').trim();

                if (!leadFollowUpValidator.form()) {
                    if (!summary) {
                        summaryInput?.focus();
                    } else {
                        dateInput?.focus();
                    }
                    return;
                }

                if (!form.action) {
                    showError('Unable to find follow-up save URL for this lead.');
                    return;
                }

                saveBtn?.setAttribute('disabled', 'disabled');
                if (saveBtn) saveBtn.textContent = 'Saving...';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token()),
                            'Accept': 'application/json',
                        },
                        body: new FormData(form),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data?.success) {
                        throw new Error(data?.message || Object.values(data?.errors || {})?.[0]?.[0] || 'Unable to save follow-up.');
                    }

                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Follow-up saved',
                            text: data?.message || 'Next follow-up scheduled successfully.',
                            confirmButtonColor: '#0f172a',
                        });
                    }

                    window.location.reload();
                } catch (error) {
                    showError(error.message || 'Unable to save follow-up.');
                } finally {
                    saveBtn?.removeAttribute('disabled');
                    if (saveBtn) saveBtn.textContent = 'Save Follow-up';
                }
            });
        });
    </script>

    @if ($isCompanyAdmin)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const transferModal = document.getElementById('lead-transfer-modal');
                const transferOpenBtn = document.getElementById('open-lead-transfer-modal');
                const lpTransferBtn = document.getElementById('lp-transfer-btn');

                // Modal elements
                const fromUserSelect = document.getElementById('transfer-from-user');
                const toUserSelect = document.getElementById('transfer-to-user');
                const modalSelectAll = document.getElementById('transfer-modal-select-all');
                const modalLeadsCount = document.getElementById('transfer-modal-leads-count');
                const modalSelectedSummary = document.getElementById('transfer-modal-selected-summary');
                const modalLeadsList = document.getElementById('transfer-modal-leads-list');
                const modalPlaceholder = document.getElementById('transfer-modal-placeholder');
                const modalLoading = document.getElementById('transfer-modal-loading');
                const modalEmpty = document.getElementById('transfer-modal-empty');
                const transferSubmitBtn = document.getElementById('lead-transfer-submit-btn');

                let currentStaffLeads = []; // Array of leads fetched for the selected "From" user
                let modalSelectedUuids = new Set(); // Set of lead uuids selected inside modal

                function syncModalLeadSelectionUI() {
                    const total = currentStaffLeads.length;
                    const selectedCount = modalSelectedUuids.size;

                    if (modalLeadsCount) modalLeadsCount.textContent = String(total);
                    if (modalSelectedSummary) modalSelectedSummary.textContent = `${selectedCount} selected`;

                    if (modalSelectAll) {
                        modalSelectAll.disabled = total === 0;
                        modalSelectAll.checked = total > 0 && selectedCount === total;
                        modalSelectAll.indeterminate = selectedCount > 0 && selectedCount < total;
                    }

                    modalLeadsList.querySelectorAll('.js-modal-lead-checkbox').forEach((cb) => {
                        cb.checked = modalSelectedUuids.has(cb.value);
                    });
                }

                function renderStaffLeads(leads) {
                    currentStaffLeads = leads || [];
                    modalSelectedUuids.clear();

                    modalLoading.classList.add('hidden');
                    modalPlaceholder.classList.add('hidden');

                    if (currentStaffLeads.length === 0) {
                        modalEmpty.classList.remove('hidden');
                        modalLeadsList.querySelectorAll('.js-staff-lead-item').forEach(el => el.remove());
                    } else {
                        modalEmpty.classList.add('hidden');
                        modalLeadsList.querySelectorAll('.js-staff-lead-item').forEach(el => el.remove());

                        const fragment = document.createDocumentFragment();
                        currentStaffLeads.forEach((lead) => {
                            const item = document.createElement('label');
                            item.className = 'js-staff-lead-item flex items-center justify-between gap-3 p-2.5 transition hover:bg-amber-50/60 cursor-pointer border-b border-slate-100 last:border-b-0';
                            item.innerHTML = `
                                <div class="flex items-center gap-3 min-w-0">
                                    <input type="checkbox" value="${lead.uuid}" class="js-modal-lead-checkbox h-4 w-4 rounded border-slate-300 text-amber-600 focus:ring-amber-500 cursor-pointer">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-xs text-slate-900 truncate">${escapeLeadPreviewHtml(lead.client_name)}</div>
                                        <div class="text-[11px] text-slate-500">${lead.mobile ? escapeLeadPreviewHtml(lead.mobile) : (lead.email ? escapeLeadPreviewHtml(lead.email) : '-')}</div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 flex-shrink-0 text-right">
                                    <span class="inline-flex px-2 py-0.5 text-[10px] font-semibold" style="background-color: ${lead.status_color}22; color: ${lead.status_color};">
                                        ${escapeLeadPreviewHtml(lead.status_name)}
                                    </span>
                                    <span class="text-xs font-bold text-slate-700">₹${lead.estimated_value}</span>
                                </div>
                            `;
                            fragment.appendChild(item);
                        });
                        modalLeadsList.appendChild(fragment);
                    }

                    syncModalLeadSelectionUI();
                }

                async function fetchStaffLeads(userId) {
                    if (!userId) {
                        currentStaffLeads = [];
                        modalSelectedUuids.clear();
                        modalLoading.classList.add('hidden');
                        modalEmpty.classList.add('hidden');
                        modalPlaceholder.classList.remove('hidden');
                        modalLeadsList.querySelectorAll('.js-staff-lead-item').forEach(el => el.remove());
                        syncModalLeadSelectionUI();
                        return;
                    }

                    modalPlaceholder.classList.add('hidden');
                    modalEmpty.classList.add('hidden');
                    modalLeadsList.querySelectorAll('.js-staff-lead-item').forEach(el => el.remove());
                    modalLoading.classList.remove('hidden');

                    try {
                        const url = '{{ route("leads.staff-leads", ":id") }}'.replace(':id', userId);
                        const res = await fetch(url, {
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            }
                        });
                        const data = await res.json();
                        if (res.ok && data.success) {
                            renderStaffLeads(data.leads);
                        } else {
                            throw new Error(data.message || 'Unable to fetch staff leads.');
                        }
                    } catch (e) {
                        modalLoading.classList.add('hidden');
                        modalEmpty.textContent = e.message || 'Error loading leads.';
                        modalEmpty.classList.remove('hidden');
                    }
                }

                function openTransferModal(preselectedFromUserId = null) {
                    if (!transferModal) return;

                    if (preselectedFromUserId && fromUserSelect) {
                        fromUserSelect.value = String(preselectedFromUserId);
                    }

                    if (fromUserSelect?.value) {
                        fetchStaffLeads(fromUserSelect.value);
                    } else {
                        currentStaffLeads = [];
                        modalSelectedUuids.clear();
                        modalLoading.classList.add('hidden');
                        modalEmpty.classList.add('hidden');
                        modalPlaceholder.classList.remove('hidden');
                        modalLeadsList.querySelectorAll('.js-staff-lead-item').forEach(el => el.remove());
                        syncModalLeadSelectionUI();
                    }

                    transferModal.classList.remove('hidden');
                    transferModal.classList.add('flex');
                    document.body.classList.add('overflow-hidden');
                }

                function closeTransferModal() {
                    if (!transferModal) return;
                    transferModal.classList.add('hidden');
                    transferModal.classList.remove('flex');
                    document.body.classList.remove('overflow-hidden');
                }

                // Modal close listeners
                transferModal?.querySelectorAll('[data-close-lead-transfer-modal]').forEach((el) => {
                    el.addEventListener('click', closeTransferModal);
                });

                // When user selects source staff in "From" dropdown
                fromUserSelect?.addEventListener('change', function () {
                    fetchStaffLeads(this.value);
                });

                // Modal Select All checkbox
                modalSelectAll?.addEventListener('change', function () {
                    if (modalSelectAll.checked) {
                        currentStaffLeads.forEach(l => modalSelectedUuids.add(l.uuid));
                    } else {
                        modalSelectedUuids.clear();
                    }
                    syncModalLeadSelectionUI();
                });

                // Individual lead checkbox toggle inside modal
                modalLeadsList?.addEventListener('change', function (e) {
                    const cb = e.target.closest('.js-modal-lead-checkbox');
                    if (cb) {
                        if (cb.checked) {
                            modalSelectedUuids.add(cb.value);
                        } else {
                            modalSelectedUuids.delete(cb.value);
                        }
                        syncModalLeadSelectionUI();
                    }
                });

                // Open modal triggers
                transferOpenBtn?.addEventListener('click', () => {
                    openTransferModal();
                });

                lpTransferBtn?.addEventListener('click', () => {
                    openTransferModal();
                });

                // Submit Transfer
                transferSubmitBtn?.addEventListener('click', async function () {
                    const fromUserId = fromUserSelect?.value;
                    const toUserId = toUserSelect?.value;

                    if (!fromUserId) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'warning', title: 'Source Staff Required', text: 'Please select a source staff member ("Transfer From").' });
                        } else {
                            alert('Please select a source staff member.');
                        }
                        fromUserSelect?.focus();
                        return;
                    }

                    if (!toUserId) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'warning', title: 'Target Staff Required', text: 'Please select a target staff member ("Transfer To").' });
                        } else {
                            alert('Please select a target staff member.');
                        }
                        toUserSelect?.focus();
                        return;
                    }

                    if (String(fromUserId) === String(toUserId)) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'warning', title: 'Invalid Selection', text: 'Source ("From") and target ("To") staff cannot be the same person.' });
                        } else {
                            alert('Source ("From") and target ("To") staff cannot be the same person.');
                        }
                        return;
                    }

                    const selectedLeadIds = Array.from(modalSelectedUuids);
                    if (selectedLeadIds.length === 0) {
                        if (window.Swal) {
                            Swal.fire({ icon: 'warning', title: 'No Leads Selected', text: 'Please select at least one lead from the list to transfer.' });
                        } else {
                            alert('Please select at least one lead to transfer.');
                        }
                        return;
                    }

                    const isAllSelected = selectedLeadIds.length === currentStaffLeads.length;
                    const fromStaffName = fromUserSelect?.options[fromUserSelect.selectedIndex]?.text || 'source staff';
                    const targetStaffName = toUserSelect?.options[toUserSelect.selectedIndex]?.text || 'target staff';

                    const confirmText = isAllSelected
                        ? `Transfer all ${selectedLeadIds.length} lead(s) of ${fromStaffName} to ${targetStaffName}?`
                        : `Transfer ${selectedLeadIds.length} selected lead(s) of ${fromStaffName} to ${targetStaffName}?`;

                    if (window.Swal) {
                        const result = await Swal.fire({
                            title: 'Confirm Lead Transfer',
                            text: confirmText,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#d97706',
                            cancelButtonColor: '#64748b',
                            confirmButtonText: 'Yes, transfer leads',
                        });
                        if (!result.isConfirmed) return;
                    } else if (!confirm(confirmText)) {
                        return;
                    }

                    transferSubmitBtn.disabled = true;
                    transferSubmitBtn.textContent = 'Transferring...';

                    const payload = {
                        from_user_id: fromUserId,
                        to_user_id: toUserId,
                        transfer_all: isAllSelected,
                        lead_ids: selectedLeadIds,
                    };

                    try {
                        const response = await fetch('{{ route('leads.transfer') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            },
                            body: JSON.stringify(payload),
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Failed to transfer leads.');
                        }

                        if (window.Swal) {
                            await Swal.fire({
                                icon: 'success',
                                title: 'Transfer Complete',
                                text: data.message,
                                confirmButtonColor: '#0f172a',
                            });
                        } else {
                            alert(data.message);
                        }

                        window.location.reload();
                    } catch (err) {
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Transfer Failed',
                                text: err.message || 'Something went wrong.',
                            });
                        } else {
                            alert(err.message || 'Something went wrong.');
                        }
                    } finally {
                        transferSubmitBtn.disabled = false;
                        transferSubmitBtn.textContent = 'Confirm Transfer';
                    }
                });
            });
        </script>
    @endif
    @if ($canCreateCompanyFromLead)
        <div id="index-convert-to-company-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeLeadConvertToCompanyModal()"></div>

            <div class="min-h-full flex items-center justify-center p-4">
                <div class="relative bg-white border border-slate-200 shadow-2xl max-w-lg w-full overflow-hidden" onclick="event.stopPropagation()">

                    <div class="px-5 py-4 bg-slate-900 text-white flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="p-1.5 bg-emerald-500/20 text-emerald-400">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                            </div>
                            <h3 class="font-bold text-sm tracking-wide uppercase">Create Company From Lead</h3>
                        </div>
                        <button type="button" onclick="closeLeadConvertToCompanyModal()" class="text-slate-400 hover:text-white transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form id="index-convert-to-company-form" method="POST" action="" novalidate>
                        @csrf
                        <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                            <div class="rounded-xl border border-blue-100 bg-blue-50/80 p-3 text-xs text-blue-900">
                                <p class="font-semibold">Onboarding Integration</p>
                                <p class="mt-0.5 text-blue-700">A new organization will be created for this lead. Its onboarding flow will start on Step 4 (subscription & license selection) upon first login.</p>
                            </div>

                            <div>
                                <x-input-label for="index_conv_company_name" value="Company Name *" />
                                <x-text-input id="index_conv_company_name" name="company_name" type="text" class="mt-1 block w-full text-sm" required />
                                <p id="index_conv_company_name_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="index_conv_email" value="Company Admin Email *" />
                                <x-text-input id="index_conv_email" name="email" type="email" class="mt-1 block w-full text-sm" required placeholder="admin@example.com" />
                                <p class="mt-1 text-[11px] text-slate-500">Provide the email address that will be used for the company administrator account.</p>
                                <p id="index_conv_email_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="index_conv_phone_number" value="Mobile / Contact Number (10 digits) *" />
                                <x-text-input id="index_conv_phone_number" name="phone_number" type="tel" maxlength="10" class="mt-1 block w-full text-sm" required placeholder="e.g. 9876543210" />
                                <p id="index_conv_phone_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="index_conv_admin_name" value="Admin Contact Person (Optional)" />
                                <x-text-input id="index_conv_admin_name" name="admin_name" type="text" class="mt-1 block w-full text-sm" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="index_conv_password" value="Initial Password *" />
                                    <div class="relative mt-1">
                                        <x-text-input id="index_conv_password" name="password" type="password" class="block w-full pr-10 text-sm" placeholder="At least 8 characters" required />
                                        <button type="button" onclick="togglePasswordVisibility('index_conv_password', 'index_conv_password_eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none" title="Show/Hide password">
                                            <svg id="index_conv_password_eye" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="index_conv_password_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                                </div>
                                <div>
                                    <x-input-label for="index_conv_password_confirmation" value="Confirm Password *" />
                                    <div class="relative mt-1">
                                        <x-text-input id="index_conv_password_confirmation" name="password_confirmation" type="password" class="block w-full pr-10 text-sm" placeholder="Repeat password" required />
                                        <button type="button" onclick="togglePasswordVisibility('index_conv_password_confirmation', 'index_conv_password_confirm_eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none" title="Show/Hide password">
                                            <svg id="index_conv_password_confirm_eye" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="index_conv_password_confirm_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
                            <button type="button" onclick="closeLeadConvertToCompanyModal()" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-xs font-semibold text-slate-700 uppercase tracking-widest hover:bg-slate-50 transition">
                                Cancel
                            </button>
                            <button type="submit" id="index-conv-submit-btn" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-emerald-700 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Create Company
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function togglePasswordVisibility(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                if (icon) {
                    if (isPassword) {
                        // eye-slash SVG
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
                    } else {
                        // eye SVG
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                    }
                }
            }
            window.togglePasswordVisibility = togglePasswordVisibility;

            function setFieldError(fieldId, errorId, message) {
                const input = document.getElementById(fieldId);
                const err = document.getElementById(errorId);
                if (!err) return;
                if (message) {
                    err.textContent = message;
                    err.classList.remove('hidden');
                    if (input) {
                        input.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                    }
                } else {
                    err.textContent = '';
                    err.classList.add('hidden');
                    if (input) {
                        input.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                    }
                }
            }

            function resetConversionErrors() {
                setFieldError('index_conv_company_name', 'index_conv_company_name_error', '');
                setFieldError('index_conv_email', 'index_conv_email_error', '');
                setFieldError('index_conv_phone_number', 'index_conv_phone_error', '');
                setFieldError('index_conv_password', 'index_conv_password_error', '');
                setFieldError('index_conv_password_confirmation', 'index_conv_password_confirm_error', '');
            }

            function openLeadConvertToCompanyModal(actionUrl, clientName, email, phone) {
                const modal = document.getElementById('index-convert-to-company-modal');
                const form = document.getElementById('index-convert-to-company-form');
                if (!modal || !form) return;

                resetConversionErrors();

                form.action = actionUrl;
                document.getElementById('index_conv_company_name').value = clientName || '';
                document.getElementById('index_conv_email').value = email || '';
                document.getElementById('index_conv_phone_number').value = phone || '';
                document.getElementById('index_conv_admin_name').value = (clientName ? (clientName + ' Admin') : '');
                document.getElementById('index_conv_password').value = '';
                document.getElementById('index_conv_password_confirmation').value = '';

                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            }

            function closeLeadConvertToCompanyModal() {
                const modal = document.getElementById('index-convert-to-company-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    resetConversionErrors();
                }
            }

            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('index-convert-to-company-form');
                if (!form) return;

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    resetConversionErrors();
                    let isValid = true;

                    const nameInput = document.getElementById('index_conv_company_name');
                    const companyName = nameInput ? nameInput.value.trim() : '';
                    if (!companyName || companyName.length < 2) {
                        setFieldError('index_conv_company_name', 'index_conv_company_name_error', 'Company name is required (at least 2 characters).');
                        isValid = false;
                    }

                    const emailInput = document.getElementById('index_conv_email');
                    const email = emailInput ? emailInput.value.trim() : '';
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!email) {
                        setFieldError('index_conv_email', 'index_conv_email_error', 'Company admin email is required.');
                        isValid = false;
                    } else if (!emailRegex.test(email)) {
                        setFieldError('index_conv_email', 'index_conv_email_error', 'Please enter a valid email address.');
                        isValid = false;
                    }

                    const phoneInput = document.getElementById('index_conv_phone_number');
                    const rawPhone = phoneInput ? phoneInput.value.replace(/\D+/g, '') : '';
                    if (!rawPhone) {
                        setFieldError('index_conv_phone_number', 'index_conv_phone_error', 'A 10-digit mobile number is required.');
                        isValid = false;
                    } else if (rawPhone.length !== 10) {
                        setFieldError('index_conv_phone_number', 'index_conv_phone_error', 'Please enter a valid 10-digit mobile number.');
                        isValid = false;
                    }

                    const passInput = document.getElementById('index_conv_password');
                    const confirmInput = document.getElementById('index_conv_password_confirmation');
                    const pass = passInput ? passInput.value : '';
                    const confirmPass = confirmInput ? confirmInput.value : '';
                    if (!pass) {
                        setFieldError('index_conv_password', 'index_conv_password_error', 'Password is required.');
                        isValid = false;
                    } else if (pass.length < 8) {
                        setFieldError('index_conv_password', 'index_conv_password_error', 'Password must be at least 8 characters.');
                        isValid = false;
                    }
                    if (!confirmPass) {
                        setFieldError('index_conv_password_confirmation', 'index_conv_password_confirm_error', 'Please confirm your password.');
                        isValid = false;
                    } else if (pass && pass !== confirmPass) {
                        setFieldError('index_conv_password_confirmation', 'index_conv_password_confirm_error', 'Passwords do not match.');
                        isValid = false;
                    }

                    if (!isValid) {
                        return false;
                    }

                    const submitBtn = document.getElementById('index-conv-submit-btn');
                    const originalBtnHtml = submitBtn ? submitBtn.innerHTML : '';
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                        submitBtn.innerHTML = `
                            <span class="inline-flex items-center gap-2">
                                <span class="h-4 w-4 rounded-full border-2 border-white/30 border-t-white animate-spin"></span>
                                <span>Creating...</span>
                            </span>
                        `;
                    }

                    try {
                        const formData = new FormData(form);
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: formData
                        });

                        const result = await response.json().catch(() => null);

                        if (response.ok && result && result.success) {
                            closeLeadConvertToCompanyModal();
                            if (window.Swal) {
                                await Swal.fire({
                                    icon: 'success',
                                    title: 'Company Created',
                                    text: result.message || 'Company created successfully from lead.',
                                });
                            }
                            window.location.reload();
                            return;
                        }

                        // Handle validation failure or conflict
                        const errors = (result && result.errors) || {};
                        let alertMessage = result ? (result.message || 'Company creation failed.') : 'An error occurred while creating company.';

                        if (errors.email && errors.email[0]) {
                            setFieldError('index_conv_email', 'index_conv_email_error', errors.email[0]);
                        }
                        if (errors.phone_number && errors.phone_number[0]) {
                            setFieldError('index_conv_phone_number', 'index_conv_phone_error', errors.phone_number[0]);
                        }
                        if (errors.company_name && errors.company_name[0]) {
                            setFieldError('index_conv_company_name', 'index_conv_company_name_error', errors.company_name[0]);
                        }
                        if (errors.password && errors.password[0]) {
                            setFieldError('index_conv_password', 'index_conv_password_error', errors.password[0]);
                        }

                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Company Already Exists',
                                text: alertMessage,
                            });
                        } else {
                            alert(alertMessage);
                        }
                    } catch (err) {
                        console.error('Lead conversion error:', err);
                        if (window.Swal) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Conversion Failed',
                                text: 'An unexpected error occurred while communicating with the server.',
                            });
                        } else {
                            alert('An unexpected error occurred while communicating with the server.');
                        }
                    } finally {
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.classList.remove('opacity-75', 'cursor-not-allowed');
                            submitBtn.innerHTML = originalBtnHtml;
                        }
                    }
                });
            });

            window.openLeadConvertToCompanyModal = openLeadConvertToCompanyModal;
            window.closeLeadConvertToCompanyModal = closeLeadConvertToCompanyModal;

            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('index-convert-to-company-modal');
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeLeadConvertToCompanyModal();
                }
            });
        </script>
    @endif
</x-app-layout>
