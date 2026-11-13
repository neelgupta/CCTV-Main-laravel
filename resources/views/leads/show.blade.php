@php
                    $todayDate = now()->format('Y-m-d');
                    $assignedToLabel = $lead->assignedUsers->isNotEmpty()
                        ? $lead->assignedUsers->pluck('name')->join(', ')
                        : $lead->assignedTo?->name ?? 'Unassigned';
                    $projectStartDate = $lead->next_follow_up
                        ? \Carbon\Carbon::parse($lead->next_follow_up)->format('Y-m-d')
                        : $todayDate;
                    $wonStatus = $statuses->first(function ($status) {
                        return strtolower((string) $status->name) === 'won';
                    });
                    $authUser = auth()->user();
                    $canEditLead = $authUser && $authUser->hasPermission('leads.edit');
                    $canUpdateLeadStatus = $authUser && $authUser->hasPermission('leads.status.update');
                    $canCommentLead = $authUser && $authUser->hasPermission('leads.comment');
                    $canManageQuotation = $authUser && $authUser->hasPermission('leads.quotation.manage');
                    $canManageBoq = $canManageQuotation && (bool) ($lead->company?->enable_boq ?? true);
                    $canUploadSurvey = $authUser && $authUser->hasPermission('leads.survey.upload');
                    $canUploadDocuments = $authUser && $authUser->hasPermission('leads.documents.upload');
                    $canDownloadSummary = $authUser && $authUser->hasPermission('leads.summary.download');
                    $canCreateCompanyFromLead = $authUser && ($authUser->isSuperAdmin() || $authUser->isCompanyAdmin()) && (bool) ($lead->company?->allow_create_company_from_lead ?? false);
                    $leadCustomFields = collect($lead->company?->normalizedLeadCustomFields() ?? []);
                    $customFieldValues = $lead->custom_fields ?? [];
                @endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h2 class="min-w-0 overflow-hidden whitespace-nowrap text-ellipsis text-xl font-semibold leading-tight text-gray-800 sm:text-2xl">
                {{ __('Lead Details') }}: <span class="inline">{{ $lead->client_name }}</span>
            </h2>
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                @if ($canEditLead)
                    <a href="{{ route('leads.edit', $lead) }}"
                        class="inline-flex items-center gap-1.5 border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 shadow-sm transition hover:bg-indigo-100">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                    </a>
                @endif
                <span class="inline-flex px-3 py-1 text-xs font-bold sm:text-sm"
                    style="background-color: {{ $lead->status->color }}22; color: {{ $lead->status->color }}">
                    {{ $lead->status->name }}
                </span>
                @if (filled($lead->loss_reason))
                    <span class="inline-flex items-center gap-1.5 bg-red-100 text-red-700 px-3 py-1 text-xs font-bold sm:text-sm">
                        <svg class="w-3.5 h-3.5 text-red-600" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <span>Loss Reason: {{ $lead->loss_reason }}</span>
                    </span>
                @endif
                <span class="inline-flex bg-indigo-100 px-3 py-1 text-xs font-bold text-indigo-700 sm:text-sm">
                    Stage {{ $lead->stage_indicator }}
                </span>
                @if ($canCreateCompanyFromLead)
                    @if ($lead->createdCompany)
                        <button
                            type="button"
                            disabled
                            class="inline-flex items-center gap-1.5 border border-slate-300 bg-slate-100 px-3.5 py-2 text-xs font-semibold uppercase tracking-widest text-slate-400 cursor-not-allowed shadow-none"
                            title="Company already created: {{ $lead->createdCompany->name }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            <span>Company Created</span>
                        </button>
                    @else
                        <button
                            type="button"
                            onclick="document.getElementById('convert-to-company-modal').classList.remove('hidden'); document.body.classList.add('overflow-hidden');"
                            class="inline-flex items-center gap-1.5 border border-emerald-600 bg-emerald-600 px-3.5 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm hover:bg-emerald-700 transition"
                            title="Create a new company account from this lead">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            Create Company
                        </button>
                    @endif
                @endif
                <button
                    type="button"
                    onclick="document.getElementById('log-contact-modal').classList.remove('hidden'); document.body.classList.add('overflow-hidden');"
                    class="inline-flex items-center gap-1.5 bg-violet-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow hover:bg-violet-700 transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    Log Contact
                </button>
            </div>
        </div>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <div class="py-12" id="lead-details-page-content">
        <div class="w-full">
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">


                <div class="p-4 text-gray-900 sm:p-6" x-data="{
                    tab: @js(old('tab', request('tab', 'overview'))),
                    approveModalOpen: false,
                    statusWonModalOpen: false,
                    saveTemplateModalOpen: false,
                    templateFormAction: '',
                    templateQuoteId: null,
                    templateName: '',
                    templateDescription: '',
                    openSaveTemplateModal(id, actionUrl) {
                        this.templateQuoteId = id;
                        this.templateFormAction = actionUrl;
                        this.templateName = 'Quotation Template #' + id;
                        this.templateDescription = '';
                        this.saveTemplateModalOpen = true;
                    },
                    closeSaveTemplateModal() {
                        this.saveTemplateModalOpen = false;
                        this.templateQuoteId = null;
                        this.templateFormAction = '';
                    },
                    approveQuoteId: null,
                    approveQuoteVersion: '',
                    approveFormAction: '',
                    isProjectConverted: @js((bool) $lead->project),
                    today: @js($todayDate),
                    wonStatusId: @js($wonStatus?->id),
                    pendingStatusId: @js($lead->lead_status_id),
                    quoteDecisionUrlTemplate: @js(route('leads.quotation.decision', [$lead, '__QUOTE__'])),
                    projectDetail: {
                        name: @js('Project: ' . $lead->client_name),
                        description: @js('Converted from lead: ' . $lead->client_name),
                        start_date: @js($projectStartDate),
                        end_date: '',
                        status: 'pending',
                    },
                    showConvertedAlert() {
                        if (window.appAlert) {
                            window.appAlert('This lead is already converted to a project.', 'Lead Already Converted', 'info');
                            return;
                        }

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Lead Already Converted',
                                text: 'This lead is already converted to a project.',
                                icon: 'info',
                                confirmButtonColor: '#0f172a',
                            });
                            return;
                        }

                        alert('This lead is already converted to a project.');
                    },
                    openApproveModal(id, version) {
                        if (this.isProjectConverted) {
                            this.showConvertedAlert();
                            return;
                        }
                        this.approveQuoteId = id;
                        this.approveQuoteVersion = version;
                        this.approveFormAction = this.quoteDecisionUrlTemplate.replace('__QUOTE__', id);
                        this.approveModalOpen = true;
                        this.lockPage();
                    },
                    closeApproveModal() {
                        this.approveModalOpen = false;
                        this.approveQuoteId = null;
                        this.approveQuoteVersion = '';
                        this.approveFormAction = '';
                        this.unlockPage();
                    },
                    openStatusWonModal() {
                        if (this.isProjectConverted) {
                            this.showConvertedAlert();
                            return;
                        }
                        this.statusWonModalOpen = true;
                        this.lockPage();
                    },
                    closeStatusWonModal() {
                        this.statusWonModalOpen = false;
                        this.unlockPage();
                    },
                    submitStatusChange(event) {
                        if (this.wonStatusId && String(this.pendingStatusId) === String(this.wonStatusId)) {
                            this.openStatusWonModal();
                            return;
                        }

                        event.target.submit();
                    },
                    syncEndDateMin() {
                        if (this.projectDetail.end_date && this.projectDetail.end_date < (this.projectDetail.start_date || this.today)) {
                            this.projectDetail.end_date = '';
                        }
                    },
                    lockPage() {
                        document.body.classList.add('overflow-hidden');
                    },
                    unlockPage() {
                        document.body.classList.remove('overflow-hidden');
                    },
                    materialRow: {
                        material_name: '',
                        qty: 1,
                        unit: '',
                        rate: 0,
                        gst: 0,
                        note: '',
                    },
                    unitOptions: @js(($units ?? collect())->pluck('name')->values()->all()),
                    materialRows: [
                        { material_name: '', qty: 1, unit: '', rate: 0, gst: 0, note: '' }
                    ],
                    unitOptionsFor(selectedUnit = '') {
                        const selected = String(selectedUnit || '').trim();
                        return selected && !this.unitOptions.includes(selected)
                            ? [...this.unitOptions, selected]
                            : this.unitOptions;
                    },
                    addMaterialRow() {
                        this.materialRows.push({ material_name: '', qty: 1, unit: '', rate: 0, gst: 0, note: '' });
                    },
                    removeMaterialRow(index) {
                        if (this.materialRows.length > 1) {
                            this.materialRows.splice(index, 1);
                        }
                    },
                    materialRowTotal(row) {
                        const qty = Math.max(0, Number(row.qty || 0));
                        const rate = Math.max(0, Number(row.rate || 0));
                        return (qty * rate).toFixed(2);
                    },
                    materialGrandTotal() {
                        return this.materialRows.reduce((sum, row) => {
                            return sum + (Math.max(0, Number(row.qty || 0)) * Math.max(0, Number(row.rate || 0)));
                        }, 0).toFixed(2);
                    },
                    submitMaterials(event) {
                        const filledRows = this.materialRows
                            .filter(row => String(row.material_name || '').trim() !== '' || String(row.unit || '').trim() !== '' || Number(row.qty || 0) > 0 || Number(row.rate || 0) > 0 || String(row.note || '').trim() !== '')
                            .map(row => ({
                                material_name: String(row.material_name || '').trim(),
                                qty: Number(row.qty || 0),
                                unit: String(row.unit || '').trim(),
                                rate: Number(row.rate || 0),
                                note: String(row.note || '').trim(),
                            }));

                        if (!filledRows.length) {
                            Swal.fire({
                                title: 'Required Fields',
                                text: 'Please add at least one material.',
                                icon: 'warning',
                                confirmButtonColor: '#4f46e5',
                            });
                            return;
                        }

                        for (let i = 0; i < filledRows.length; i++) {
                            const row = filledRows[i];
                            if (!row.material_name) {
                                Swal.fire({
                                    title: 'Required Fields',
                                    text: `Row ${i + 1}: Material Name is required.`,
                                    icon: 'warning',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }

                            if (row.material_name.length > 191) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: `Row ${i + 1}: Material Name must not exceed 191 characters.`,
                                    icon: 'error',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }

                            if (!Number.isFinite(row.qty) || row.qty <= 0) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: `Row ${i + 1}: Quantity must be greater than 0.`,
                                    icon: 'warning',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }

                            if (!Number.isFinite(row.rate) || row.rate < 0) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: `Row ${i + 1}: Price cannot be negative.`,
                                    icon: 'warning',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }

                            if (row.unit.length > 50) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: `Row ${i + 1}: Unit must not exceed 50 characters.`,
                                    icon: 'error',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }

                            if (row.note.length > 2000) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: `Row ${i + 1}: Note must not exceed 2000 characters.`,
                                    icon: 'error',
                                    confirmButtonColor: '#4f46e5',
                                });
                                return;
                            }
                        }

                        this.materialRows = filledRows;
                        event.target.submit();
                    }
                }">


                    <div id="lead-details-main-content">
                        <div class="mb-4 border-b border-gray-200">
                            <div class="w-full overflow-x-auto pb-1 touch-pan-x [-webkit-overflow-scrolling:touch]">
                                <nav class="-mb-px flex w-max flex-nowrap gap-3 pr-2 sm:gap-8">
                                <button @click="tab = 'overview'"
                                    :class="tab === 'overview' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Overview
                                </button>
                                <button @click="tab = 'survey'"
                                    :class="tab === 'survey' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Survey
                                </button>
                                <button @click="tab = 'material'"
                                    :class="tab === 'material' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Plan Material
                                </button>
                                <button @click="tab = 'quotation'"
                                    :class="tab === 'quotation' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Quotation
                                </button>
                                @if ($canManageBoq)
                                    <button @click="tab = 'boq'"
                                        :class="tab === 'boq' ? 'border-indigo-500 text-indigo-600' :
                                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                        BOQ
                                    </button>
                                @endif
                                <button @click="tab = 'summary'"
                                    :class="tab === 'summary' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Summary
                                </button>

                                <button @click="tab = 'documents'"
                                    :class="tab === 'documents' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Documents
                                </button>
                                <button @click="tab = 'activity'"
                                    :class="tab === 'activity' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Activity
                                </button>
                                <button @click="tab = 'followup_history'"
                                    :class="tab === 'followup_history' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Follow-up History
                                </button>
                                <button @click="tab = 'comments'"
                                    :class="tab === 'comments' ? 'border-indigo-500 text-indigo-600' :
                                        'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                    class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                    Comments
                                </button>
                                @if($lead->company?->allow_appointment_module ?? false)
                                    <button @click="tab = 'appointments'"
                                        :class="tab === 'appointments' ? 'border-indigo-500 text-indigo-600' :
                                            'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                                        class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium sm:px-1 sm:py-4 sm:text-sm">
                                        Appointments
                                        @if($lead->appointments->count())
                                            <span class="ml-1.5 rounded-full bg-indigo-100 px-2 py-0.5 text-xs font-semibold text-indigo-700">{{ $lead->appointments->count() }}</span>
                                        @endif
                                    </button>
                                @endif
                                </nav>
                            </div>
                        </div>

                        <div class="mt-3 sm:mt-4">
                            <div x-show="tab === 'overview'">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <h3 class="text-lg font-bold mb-4">Contact Information</h3>
                                        <div class="space-y-3">
                                            <p><span class="font-semibold">Email:</span> {{ $lead->email ?? 'N/A' }}</p>
                                            <p><span class="font-semibold">Mobile:</span> {{ $lead->mobile ?? 'N/A' }}
                                            </p>
                                            <p><span class="font-semibold">GST Number:</span> {{ $lead->gst_number ?? 'N/A' }}</p>
                                            <p><span class="font-semibold">PAN Number:</span> {{ $lead->pan_number ?? 'N/A' }}</p>
                                            <p><span class="font-semibold">Address:</span> {{ $lead->address ?? 'N/A' }}
                                            </p>
                                        </div>
                                    </div>
                                    <div>
                                        <h3 class="text-lg font-bold mb-4">Lead Details</h3>
                                        <div class="space-y-3">
                                            <p><span class="font-semibold">Category:</span> {{ $lead->category->name }}
                                            </p>
                                            <p><span class="font-semibold">Source:</span> {{ $lead->source ?? 'N/A' }}
                                            </p>
                                            <p><span class="font-semibold">Referral Person Name:</span> {{ $lead->referral_person_name ?? 'N/A' }}
                                            </p>
                                            <p><span class="font-semibold">Assigned To:</span> {{ $assignedToLabel }}
                                            </p>
                                            <p><span class="font-semibold">Created By:</span> {{ $lead->creator?->name ?? 'N/A' }}
                                            </p>
                                            <p>
                                                <span class="font-semibold">Converted Project:</span>
                                                @if ($lead->project)
                                                    <a href="{{ route('projects.show', $lead->project) }}"
                                                        class="text-indigo-600 hover:text-indigo-800 hover:underline font-medium">
                                                        Go to Project
                                                    </a>
                                                @else
                                                    <span class="text-gray-500">Not converted to a project</span>
                                                @endif
                                            </p>
                                            <p>
                                                <span class="font-semibold">Created Company:</span>
                                                @if ($lead->createdCompany)
                                                    <span class="inline-flex items-center gap-1 font-medium text-emerald-700">
                                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                        </svg>
                                                        {{ $lead->createdCompany->name }}
                                                        @if ($lead->createdCompany->hasCompletedOnboarding())
                                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800">Onboarded</span>
                                                        @else
                                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Pending Setup</span>
                                                        @endif
                                                    </span>
                                                @elseif ($canCreateCompanyFromLead)
                                                    <button type="button"
                                                        onclick="document.getElementById('convert-to-company-modal').classList.remove('hidden'); document.body.classList.add('overflow-hidden');"
                                                        class="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 hover:text-emerald-900 hover:underline">
                                                        <span>Create Company</span> &rarr;
                                                    </button>
                                                @else
                                                    <span class="text-gray-500">Not created</span>
                                                @endif
                                            </p>
                                            <p><span class="font-semibold">Estimated Value:</span>
                                                ₹{{ number_format($lead->estimated_value, 2) }}</p>
                                            <p><span class="font-semibold">Priority:</span>
                                                <span
                                                    class="capitalize {{ $lead->priority === 'high' ? 'text-red-600 font-bold' : ($lead->priority === 'medium' ? 'text-yellow-600' : 'text-green-600') }}">
                                                    {{ $lead->priority }}
                                                </span>
                                            </p>
                                            <p><span class="font-semibold">Next Follow Up:</span>
                                                {{ $lead->next_follow_up ? \Carbon\Carbon::parse($lead->next_follow_up)->format('M d, Y') : 'Not scheduled' }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                @if($leadCustomFields->isNotEmpty())
                                    <div class="mt-6 border border-slate-200 bg-slate-50/70 p-5">
                                        <h3 class="text-lg font-bold mb-4">Custom Fields</h3>
                                        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                                            @foreach($leadCustomFields as $field)
                                                @php
                                                    $value = data_get($customFieldValues, $field['key']);
                                                    $displayValue = $lead->formatCustomFieldValue($field, $value);
                                                @endphp
                                                <div class="border border-slate-200 bg-white p-4">
                                                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $field['label'] }}</p>
                                                    <p class="mt-1 text-sm font-medium text-slate-900">{{ $displayValue }}</p>
                                                    @if(!empty($field['help_text']))
                                                        <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div x-show="tab === 'activity'">
                                <div class="flow-root">
                                    <ul role="list" class="-mb-8">
                                        @foreach ($lead->activities as $activity)
                                            <li>
                                                <div class="relative pb-8">
                                                    @if (!$loop->last)
                                                        <span
                                                            class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200"
                                                            aria-hidden="true"></span>
                                                    @endif
                                                    <div class="relative flex space-x-3">
                                                        <div>
                                                            <span
                                                                class="h-8 w-8 {{ $activity->type === 'company_conversion' ? 'bg-emerald-600' : 'bg-gray-400' }} flex items-center justify-center ring-8 ring-white">
                                                                @if ($activity->type === 'status_change')
                                                                    <svg class="h-5 w-5 text-white" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                                                    </svg>
                                                                @elseif($activity->type === 'company_conversion')
                                                                    <svg class="h-5 w-5 text-white" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                                                    </svg>
                                                                @elseif($activity->type === 'note')
                                                                    <svg class="h-5 w-5 text-white" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
                                                                    </svg>
                                                                @else
                                                                    <svg class="h-5 w-5 text-white" fill="none"
                                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                                        <path stroke-linecap="round"
                                                                            stroke-linejoin="round" stroke-width="2"
                                                                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                                    </svg>
                                                                @endif
                                                            </span>
                                                        </div>
                                                        <div
                                                            class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                            <div>
                                                                <p class="text-sm text-gray-500">
                                                                    {{ $activity->description }} <span
                                                                        class="font-medium text-gray-900">by
                                                                        {{ $activity->user->name ?? 'System' }}</span>
                                                                </p>
                                                            </div>
                                                            <div
                                                                class="whitespace-nowrap text-right text-sm text-gray-500">
                                                                <time class="activity-time"
                                                                    datetime="{{ $activity->occurred_at?->toIso8601String() ?? '' }}">
                                                                    {{ $activity->occurred_at?->format('M d, H:i') }}
                                                                </time>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </div>

                            <div x-show="tab === 'followup_history'">
                                <div class="border border-slate-200 bg-white shadow-sm">
                                    <div class="border-b border-slate-100 px-5 py-4">
                                        <h3 class="text-lg font-bold text-slate-950">Follow-up History</h3>
                                        <p class="mt-1 text-sm text-slate-500">All follow-up summaries, selected statuses, and next scheduled dates for this lead.</p>
                                    </div>
                                    <div class="p-5">
                                        @forelse($lead->followupHistories as $history)
                                            @php
                                                $normalizedHistoryStatus = strtolower(trim((string) $history->status));
                                                $historyStatusClass = match ($normalizedHistoryStatus) {
                                                    'done' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                                    'not interested' => 'bg-rose-50 text-rose-700 ring-rose-200',
                                                    'converted' => 'bg-purple-50 text-purple-700 ring-purple-200',
                                                    'callback' => 'bg-orange-50 text-orange-700 ring-orange-200',
                                                    'follow-up required', 'follow up required' => 'bg-yellow-50 text-yellow-700 ring-yellow-200',
                                                    default => 'bg-blue-50 text-blue-700 ring-blue-200',
                                                };
                                            @endphp
                                            <div class="mb-4 border border-slate-200 bg-slate-50/70 p-4 last:mb-0">
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                    <div>
                                                        <span class="inline-flex px-3 py-1 text-xs font-bold ring-1 {{ $historyStatusClass }}">
                                                            {{ \Illuminate\Support\Str::headline($history->status ?: 'Pending') }}
                                                        </span>
                                                        <p class="mt-3 whitespace-pre-wrap text-sm leading-6 text-slate-800">{{ $history->summary }}</p>
                                                    </div>
                                                    <div class="shrink-0 text-sm text-slate-500 sm:text-right">
                                                        <p><span class="font-semibold text-slate-700">Follow-up:</span> {{ $history->followup_date?->format('M d, Y') ?? 'N/A' }}</p>
                                                        <p><span class="font-semibold text-slate-700">Next:</span> {{ $history->next_followup_date?->format('M d, Y') ?? 'N/A' }}</p>
                                                        <p><span class="font-semibold text-slate-700">By:</span> {{ $history->creator?->name ?? 'System' }}</p>
                                                        <p class="text-xs">{{ $history->created_at?->format('M d, Y h:i A') }}</p>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="border border-dashed border-slate-200 bg-slate-50 px-5 py-10 text-center text-sm text-slate-500">
                                                No follow-up history recorded yet.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <div x-show="tab === 'survey'">
                                @if ($canUploadSurvey)
                                    <form id="survey-upload-form" action="{{ route('leads.survey', $lead) }}"
                                        method="POST" enctype="multipart/form-data" class="mb-6 swal-form" novalidate>
                                        @csrf
                                        <div class="flex flex-col gap-4 md:flex-row md:items-start">
                                            <div class="w-full md:flex-[1.15]">
                                                <x-input-label for="survey_photo" value="Upload Survey Photo" />
                                                <label for="survey_photo"
                                                    class="mt-1 flex items-center justify-between gap-3 border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition">
                                                    <span class="text-sm font-medium text-indigo-700">Choose
                                                        Image</span>

                                                </label>
                                                <input id="survey_photo" type="file" name="survey_photo"
                                                    accept=".jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif"
                                                    class="sr-only" data-media-type="image" data-max-size="2097152"
                                                    required>
                                                <p id="survey-photo-file-name" class="mt-1 text-xs text-gray-500">No
                                                    file selected.</p>
                                                <p id="survey-photo-error" class="mt-1 min-h-[20px] text-xs text-red-600 hidden">
                                                </p>
                                                <x-input-error :messages="$errors->get('survey_photo')" class="mt-2" />
                                            </div>
                                            <div class="w-full md:flex-1">
                                                <x-input-label for="survey_description" value="Description" />
                                                <x-text-input id="survey_description" name="description"
                                                    class="mt-1 block w-full" />
                                                <div class="mt-1 min-h-[20px]"></div>
                                            </div>
                                            <div class="flex-none self-start pt-7">
                                                <x-primary-button class="h-10">Upload</x-primary-button>
                                            </div>
                                        </div>
                                    </form>
                                @endif

                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    @forelse($lead->surveys as $survey)
                                        <div class="relative">
                                            @php
                                                $surveyImageUrl = $survey->display_url;
                                            @endphp

                                            @if ($surveyImageUrl)
                                                <img src="{{ $surveyImageUrl }}" alt="Survey Photo"
                                                    class="shadow w-full h-40 object-cover" loading="lazy"
                                                    onerror="this.onerror=null; this.src='{{ $surveyImageUrl }}';">
                                            @else
                                                <div
                                                    class="shadow w-full h-40 bg-gray-100 text-gray-500 text-sm flex items-center justify-center">
                                                    Image not available
                                                </div>
                                            @endif
                                            <p class="text-xs mt-1 text-gray-500">{{ $survey->description }}</p>
                                        </div>
                                    @empty
                                        <p class="col-span-full text-center py-10 text-gray-500 italic">No survey
                                            photos uploaded yet.</p>
                                    @endforelse
                                </div>
                            </div>
                            <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    const surveyForm = document.getElementById('survey-upload-form');

                                    if (surveyForm) {
                                        surveyForm.addEventListener('submit', function (e) {
                                            const fileInput = document.getElementById('survey_photo');

                                            // Check if file input is empty
                                            if (!fileInput.files || fileInput.files.length === 0) {
                                                // Stop form from submitting
                                                e.preventDefault();

                                                // Show SweetAlert popup
                                                Swal.fire({
                                                    icon: 'warning',
                                                    title: 'File Required',
                                                    text: 'Please select a survey photo before clicking upload.',
                                                    confirmButtonColor: '#C22032', // Matches your indigo theme
                                                    confirmButtonText: 'Understood'
                                                });

                                                return false;
                                            }

                                            // Optional: Check file size (since you have data-max-size="2097152")
                                            const maxSize = parseInt(fileInput.getAttribute('data-max-size'));
                                            if (fileInput.files[0].size > maxSize) {
                                                e.preventDefault();
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'File Too Large',
                                                    text: 'The selected image exceeds the 2MB limit.',
                                                    confirmButtonColor: '#4f46e5'
                                                });
                                                return false;
                                            }
                                        });
                                    }
                                });
                            </script>
                            <div x-show="tab === 'material'" x-data="materialComponent()">
                                @php
                                    $materialRows = $lead->materials;
                                    // Calculation moved to JS for consistency, but keeping PHP variable for initial state if needed
                                    $materialGrandTotal = (float) $materialRows->sum('total');
                                    $leadMaterialUnits = collect($units ?? [])
                                        ->pluck('name')
                                        ->merge(($boqProducts ?? collect())->pluck('unit'))
                                        ->merge($materialRows->pluck('unit'))
                                        ->map(fn ($u) => trim((string) $u))
                                        ->filter()
                                        ->unique()
                                        ->values();
                                    $leadMaterialGsts = collect($gstRates ?? [])
                                        ->pluck('rate')
                                        ->merge(($boqProducts ?? collect())->pluck('gst'))
                                        ->merge([0, 5, 9, 12, 18, 28])
                                        ->map(fn ($r) => (float) $r)
                                        ->filter(fn ($r) => $r >= 0)
                                        ->unique(fn ($r) => number_format((float) $r, 2, '.', ''))
                                        ->sort()
                                        ->values();
                                @endphp

                                            <template x-if="errorMessage">
                                                <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-700 text-sm">
                                                    <span x-text="errorMessage"></span>
                                                </div>
                                            </template>
                                        <div class="mb-4">
                                            <button type="button" @click="openProductModal()"
                                                class="inline-flex items-center gap-2 border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                <span class="text-base leading-none">+</span>
                                                Add New Product
                                            </button>
                                        </div>

                                        <!-- Add New Product Modal Dialog -->
                                        <div x-show="productModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
                                            aria-labelledby="lead-material-product-modal-title" role="dialog" aria-modal="true"
                                            @keydown.escape.window="closeProductModal()">
                                            <div class="relative min-h-full flex items-center justify-center p-4">
                                                <div x-show="productModalOpen" x-transition.opacity
                                                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                                    @click="closeProductModal()"></div>

                                                <div x-show="productModalOpen" x-transition
                                                    class="relative w-full max-w-3xl overflow-hidden bg-white text-left shadow-2xl border border-gray-200 flex flex-col my-8">
                                                    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 bg-gray-50/75">
                                                        <div>
                                                            <h3 id="lead-material-product-modal-title" class="text-base font-bold text-gray-900">Add New Product</h3>
                                                            <p class="text-xs text-gray-600 mt-0.5">Saves to the product master and adds it to this lead material list.</p>
                                                        </div>
                                                        <button type="button" @click="closeProductModal()"
                                                            class="rounded-md p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                            aria-label="Close add new product modal" title="Close">
                                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                            </svg>
                                                        </button>
                                                    </div>

                                                    <form id="lead-material-product-form" action="{{ route('leads.materials.products.store', $lead) }}" method="POST" enctype="multipart/form-data" novalidate data-skip-loader="1">
                                                        @csrf
                                                        <input type="hidden" name="tab" value="material">

                                                        <div class="p-6 max-h-[calc(85vh-130px)] overflow-y-auto space-y-4">
                                                            <div class="grid gap-4 md:grid-cols-2">
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_category_id" value="Category *" />
                                                                    <select id="lead_product_category_id" name="category_id" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                        <option value="">Select category</option>
                                                                        @foreach(($boqCategories ?? collect()) as $category)
                                                                            <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    <span id="lead_product_category_id_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_name" value="Product Name *" />
                                                                    <input id="lead_product_name" name="name" type="text" maxlength="191" value="{{ old('name') }}" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                    <span id="lead_product_name_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_brand" value="Brand" />
                                                                    <input id="lead_product_brand" name="brand" type="text" maxlength="191" value="{{ old('brand') }}" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                    <span id="lead_product_brand_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_model" value="Model" />
                                                                    <input id="lead_product_model" name="model" type="text" maxlength="191" value="{{ old('model') }}" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                    <span id="lead_product_model_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>

                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_unit" value="Unit *" />
                                                                    <select id="lead_product_unit" name="unit" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                        <option value="">Select unit</option>
                                                                        @foreach(($units ?? collect()) as $unit)
                                                                            <option value="{{ $unit->name }}" {{ old('unit') == $unit->name ? 'selected' : '' }}>{{ $unit->name }}</option>
                                                                        @endforeach
                                                                    </select>
                                                                    <span id="lead_product_unit_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_price" value="Unit Price (₹) *" />
                                                                    <input id="lead_product_price" name="price" type="number" min="0" step="0.01" value="{{ old('price', '0') }}" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                    <span id="lead_product_price_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_gst" value="GST (%) *" />
                                                                    <select id="lead_product_gst" name="gst" required class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500">
                                                                        <option value="0" {{ old('gst') === '0' || old('gst') === 0 ? 'selected' : '' }}>0.00%</option>
                                                                        @foreach(($gstRates ?? collect()) as $gstRate)
                                                                            @if((float) $gstRate->rate > 0)
                                                                                <option value="{{ $gstRate->rate }}" {{ old('gst') == $gstRate->rate ? 'selected' : '' }}>{{ number_format((float) $gstRate->rate, 2) }}%</option>
                                                                            @endif
                                                                        @endforeach
                                                                    </select>
                                                                    <span id="lead_product_gst_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="space-y-1">
                                                                    <x-input-label for="lead_product_image" value="Product Image" />
                                                                    <input id="lead_product_image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full border border-gray-300 bg-white text-sm text-gray-700 file:mr-3 file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700">
                                                                    <span id="lead_product_image_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>

                                                                <div class="md:col-span-2 space-y-1">
                                                                    <div class="flex items-center justify-between">
                                                                        <x-input-label for="lead_product_description" value="Description" />
                                                                        <x-speech-to-text target="lead_product_description" />
                                                                    </div>
                                                                    <textarea id="lead_product_description" name="description" rows="2" maxlength="1000" class="mt-1 block w-full rounded border-gray-300 text-sm focus:ring-indigo-500" placeholder="Product description...">{{ old('description') }}</textarea>
                                                                    <span id="lead_product_description_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                                <div class="md:col-span-2 space-y-1">
                                                                    <x-input-label for="lead_product_note" value="Material Note" />
                                                                    <textarea id="lead_product_note" name="note" rows="2" maxlength="2000" class="mt-1 block w-full border-gray-300 text-sm focus:ring-indigo-500" placeholder="Optional note for lead material...">{{ old('note') }}</textarea>
                                                                    <span id="lead_product_note_error" class="block min-h-[18px] text-xs text-red-600 opacity-0"></span>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <div class="flex items-center justify-end gap-3 border-t border-gray-200 bg-gray-50/75 px-6 py-3">
                                                            <button type="button" @click="closeProductModal()"
                                                                class="inline-flex justify-center border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                                Cancel
                                                            </button>
                                                            <button type="submit"
                                                                class="inline-flex justify-center border border-transparent bg-indigo-600 px-5 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                                                Add Product
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                            <form id="lead-material-form" @submit.prevent="submitForm($event.target)" action="{{ route('leads.materials.store', $lead) }}" method="POST" class="mb-4 bg-gray-50 p-3 border border-gray-200">
                                                @csrf
                                                <div class="space-y-2">
                                                    <div class="hidden lg:grid grid-cols-12 gap-2 px-2 pb-1">
                                                        <div class="col-span-2 text-[10px] font-bold uppercase text-gray-500">Material Name</div>
                                                        <div class="col-span-1 text-[10px] font-bold uppercase text-gray-500">Qty</div>
                                                        <div class="col-span-1 text-[10px] font-bold uppercase text-gray-500">Unit</div>
                                                        <div class="col-span-2 text-[10px] font-bold uppercase text-gray-500">Price</div>
                                                        <div class="col-span-1 text-[10px] font-bold uppercase text-gray-500">GST %</div>
                                                        <div class="col-span-2 text-[10px] font-bold uppercase text-gray-500">Note/Instructions</div>
                                                        <div class="col-span-2 text-[10px] font-bold uppercase text-gray-500 text-right">Total</div>
                                                        <div class="col-span-1"></div>
                                                    </div>

                                                    <template x-for="(row, index) in materialRows" :key="index">
                                                        <div class="border border-gray-200 bg-white p-2 shadow-sm">
                                                            <template x-if="boqProducts.length > 0">
                                                                <div class="mb-2">
                                                                    <select x-model="row.product_id"
                                                                        @change="selectProduct(index, row.product_id, $event.target.closest('.border'))"
                                                                        class="block w-full border-indigo-200 bg-indigo-50 text-sm py-1.5 text-indigo-800 focus:ring-indigo-500">
                                                                        <option value="">-- Select from product list (optional) --</option>
                                                                        <template x-for="p in boqProducts" :key="p.id">
                                                                            <option :value="p.id"
                                                                                x-text="(p.category_name ? p.category_name + ' / ' : '') + p.name + '  (₹' + p.price + ' / ' + p.unit + ')'">
                                                                            </option>
                                                                        </template>
                                                                    </select>
                                                                </div>
                                                            </template>
                                                            <div class="grid grid-cols-1 gap-2 lg:grid-cols-12 lg:items-start">
                                                                <input type="hidden" x-model="row.product_id" :name="`materials[${index}][product_id]`">
                                                                <div class="lg:col-span-2 space-y-1 min-h-[78px]">
                                                                    <input type="text" x-model="row.material_name" :name="`materials[${index}][material_name]`"
                                                                        maxlength="191"
                                                                        :class="rowErrors[index]?.material_name ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-indigo-500'"
                                                                        class="block w-full text-sm py-1.5"
                                                                        placeholder="Material Name">
                                                                    <span class="material-name-error block min-h-[18px] text-xs text-red-600" :class="rowErrors[index]?.material_name ? 'opacity-100' : 'opacity-0'" x-text="rowErrors[index]?.material_name || '\u00A0'"></span>
                                                                </div>

                                                                <div class="lg:col-span-1 space-y-1 min-h-[78px]">
                                                                    <input type="number" min="0.01" step="any" x-model="row.qty" :name="`materials[${index}][qty]`"
                                                                        :class="rowErrors[index]?.qty ? 'border-red-500 focus:border-red-500 focus:ring-red-500' : 'border-gray-300 focus:ring-indigo-500'"
                                                                        class="block w-full text-sm py-1.5"
                                                                        placeholder="Qty">
                                                                    <span class="material-qty-error block min-h-[18px] text-xs text-red-600" :class="rowErrors[index]?.qty ? 'opacity-100' : 'opacity-0'" x-text="rowErrors[index]?.qty || '\u00A0'"></span>
                                                                </div>

                                                                <div class="lg:col-span-1 min-h-[78px]">
                                                                    <select x-model="row.unit" :name="`materials[${index}][unit]`"
                                                                        x-ref="unitSelect"
                                                                        class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                        <option value="">Select unit</option>
                                                                        <template x-for="unitOption in unitOptionsFor(row.unit)" :key="unitOption">
                                                                            <option :value="unitOption" :selected="String(unitOption) === String(row.unit)" x-text="unitOption"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>

                                                                <div class="lg:col-span-2 min-h-[78px]">
                                                                    <input type="number" step="0.01" x-model="row.rate" :name="`materials[${index}][rate]`"
                                                                        class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500"
                                                                        placeholder="Price">
                                                                </div>

                                                                <div class="lg:col-span-1 min-h-[78px]">
                                                                    <select x-model.number="row.gst" :name="`materials[${index}][gst]`"
                                                                        x-ref="gstSelect"
                                                                        class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                        <template x-for="gstRate in gstOptionsFor(row.gst)" :key="gstRate">
                                                                            <option :value="Number(gstRate)" :selected="Math.abs(Number(gstRate) - Number(row.gst)) < 0.001" x-text="Number(gstRate).toFixed(2) + '%'"></option>
                                                                        </template>
                                                                    </select>
                                                                </div>

                                                                <div class="lg:col-span-2 min-h-[78px]">
                                                                    <input type="text" x-model="row.note" :name="`materials[${index}][note]`"
                                                                        maxlength="2000"
                                                                        class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500"
                                                                        placeholder="Note">
                                                                </div>

                                                                <div class="lg:col-span-2 min-h-[78px]">
                                                                    <div class="text-right text-sm font-semibold text-gray-700 bg-gray-50 p-1.5 border border-dashed border-gray-200"
                                                                        x-text="'₹' + materialRowTotal(row)">
                                                                    </div>
                                                                </div>

                                                                <div class="lg:col-span-1 flex items-center justify-end gap-1">
                                                                    <button type="button" @click="addMaterialRow()" class="inline-flex h-8 w-8 items-center justify-center bg-indigo-50 text-indigo-700 hover:bg-indigo-100">+</button>
                                                                    <button type="button" @click="removeMaterialRow(index)" class="inline-flex h-8 w-8 items-center justify-center bg-rose-50 text-rose-700 hover:bg-rose-100">-</button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>

                                                    <div class="flex items-center justify-between pt-2 border-t border-gray-200">
                                                        <div class="text-sm font-medium text-gray-700">
                                                            Grand Total: <span class="text-lg font-bold text-gray-900" x-text="'₹' + materialGrandTotal()"></span>
                                                        </div>
                                                        <button type="submit" class="inline-flex justify-center border border-transparent bg-indigo-600 py-1.5 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">
                                                            Save Materials
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>

                                            <div class="bg-white shadow sm border border-gray-200 p-2">
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full divide-y divide-gray-200">
                                                        <thead class="bg-gray-50">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 text-white">Material</th>
                                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 text-white">Qty</th>
                                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 text-white">Unit</th>
                                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 text-white">GST %</th>
                                                                <th class="px-3 py-2 text-left text-xs font-semibold uppercase text-gray-500 text-white">Note</th>
                                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500 text-white">Total</th>
                                                                <th class="px-3 py-2 text-right text-xs font-semibold uppercase text-gray-500 text-white">Action</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 bg-white">
                                                            @forelse ($materialRows as $material)
                                                                <tr class="hover:bg-gray-50">
                                                                    <td class="px-3 py-2 text-sm font-medium text-gray-900">{{ $material->material_name }}</td>
                                                                    <td class="px-3 py-2 text-sm text-gray-700">{{ rtrim(rtrim(number_format((float) $material->qty, 2, '.', ''), '0'), '.') }}</td>
                                                                    <td class="px-3 py-2 text-sm text-gray-700">{{ $material->unit ?? '-' }}</td>
                                                                    <td class="px-3 py-2 text-sm text-gray-700">{{ number_format($material->gst, 2) }}%</td>
                                                                    <td class="px-3 py-2 text-sm text-gray-400 italic">{{ Str::limit($material->note, 30) }}</td>
                                                                    <td class="px-3 py-2 text-right text-sm font-bold text-gray-900">₹{{ number_format($material->total, 2) }}</td>
                                                                    <td class="px-3 py-2 text-right whitespace-nowrap">
                                                                        <button type="button" @click="editingMaterialId = editingMaterialId === {{ $material->id }} ? null : {{ $material->id }}" class="text-indigo-600 hover:text-indigo-900 p-1" title="Edit">
                                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"></path></svg>
                                                                        </button>
                                                                        <button type="button" @click="confirmDelete($event)" data-url="{{ route('leads.materials.destroy', [$lead, $material]) }}" class="text-rose-600 hover:text-rose-900 p-1" title="Delete">
                                                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z"></path></svg>
                                                                        </button>
                                                                    </td>
                                                                </tr>
                                                                @php
                                                                    $editMaterialState = [
                                                                        'product_id' => $material->product_id ? (string) $material->product_id : '',
                                                                        'material_name' => $material->material_name,
                                                                        'qty' => (float) $material->qty,
                                                                        'unit' => $material->unit ?? '',
                                                                        'rate' => (float) $material->rate,
                                                                        'gst' => (float) $material->gst,
                                                                        'note' => $material->note ?? '',
                                                                    ];
                                                                @endphp
                                                                <tr x-show="editingMaterialId === {{ $material->id }}" x-cloak class="bg-indigo-50/60"
                                                                    x-data="{ original: @js($editMaterialState), edit: @js($editMaterialState) }">
                                                                    <td colspan="7" class="px-3 py-3">
                                                                        <form action="{{ route('leads.materials.update', [$lead, $material]) }}" method="POST" class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                                                                            @csrf
                                                                            @method('PATCH')
                                                                            <input type="hidden" name="tab" value="material">
                                                                            <template x-if="boqProducts.length > 0">
                                                                                <div class="sm:col-span-2 lg:col-span-12">
                                                                                    <label class="text-[10px] font-bold uppercase text-gray-500">Product</label>
                                                                                    <select name="product_id" x-model="edit.product_id"
                                                                                        @change="applyProductToRow(edit, edit.product_id, $event.target.closest('form'))"
                                                                                        class="block w-full border-indigo-200 bg-indigo-50 text-sm py-1.5 text-indigo-800 focus:ring-indigo-500">
                                                                                        <option value="">-- Select from product list (optional) --</option>
                                                                                        <template x-for="p in boqProducts" :key="p.id">
                                                                                            <option :value="String(p.id)" :selected="String(p.id) === String(edit.product_id)"
                                                                                                x-text="(p.category_name ? p.category_name + ' / ' : '') + p.name + '  (₹' + p.price + ' / ' + p.unit + ')'">
                                                                                            </option>
                                                                                        </template>
                                                                                    </select>
                                                                                </div>
                                                                            </template>
                                                                            <div class="lg:col-span-3">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">Material Name</label>
                                                                                <input type="text" name="material_name" x-model="edit.material_name" required maxlength="191" class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                            </div>
                                                                            <div class="lg:col-span-1">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">Qty</label>
                                                                                <input type="number" name="qty" x-model="edit.qty" required min="0.01" step="any" class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                            </div>
                                                                            <div class="lg:col-span-1">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">Unit</label>
                                                                                <select name="unit" x-model="edit.unit"
                                                                                    x-effect="$el.value = String(edit.unit || '')"
                                                                                    class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                                    <option value="">Select unit</option>
                                                                                    <template x-for="unitOption in unitOptionsFor(edit.unit)" :key="unitOption">
                                                                                        <option :value="unitOption" :selected="String(unitOption) === String(edit.unit)" x-text="unitOption"></option>
                                                                                    </template>
                                                                                </select>
                                                                            </div>
                                                                            <div class="lg:col-span-2">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">Price</label>
                                                                                <input type="number" name="rate" x-model="edit.rate" required min="0" step="0.01" class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                            </div>
                                                                            <div class="lg:col-span-1">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">GST %</label>
                                                                                <select name="gst" x-model.number="edit.gst"
                                                                                    x-effect="$el.value = String(edit.gst ?? 0)"
                                                                                    class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                                    <template x-for="gstRate in gstOptionsFor(edit.gst)" :key="gstRate">
                                                                                        <option :value="gstRate" :selected="Math.abs(Number(gstRate) - Number(edit.gst)) < 0.001" x-text="Number(gstRate).toFixed(2) + '%'"></option>
                                                                                    </template>
                                                                                </select>
                                                                            </div>
                                                                            <div class="lg:col-span-2">
                                                                                <label class="text-[10px] font-bold uppercase text-gray-500">Note</label>
                                                                                <input type="text" name="note" x-model="edit.note" maxlength="2000" class="block w-full border-gray-300 text-sm py-1.5 focus:ring-indigo-500">
                                                                            </div>
                                                                            <div class="lg:col-span-2 flex justify-end gap-2">
                                                                                <button type="button" @click="edit = { ...original }; editingMaterialId = null" class="border border-gray-300 bg-white py-1.5 px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                                                                                <button type="submit" class="border border-transparent bg-indigo-600 py-1.5 px-3 text-sm font-medium text-white shadow-sm hover:bg-indigo-700">Update</button>
                                                                            </div>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            @empty
                                                                <tr><td colspan="7" class="px-4 py-4 text-center text-xs text-gray-400">Empty list.</td></tr>
                                                            @endforelse
                                                        </tbody>
                                                        <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                                                            <tr>
                                                                <td colspan="5" class="px-3 py-3 text-right text-sm font-bold text-gray-600 uppercase">
                                                                    Grand Total:
                                                                </td>
                                                                <td class="px-3 py-3 text-right text-base font-black text-indigo-700">
                                                                    ₹{{ number_format($materialGrandTotal, 2) }}
                                                                </td>
                                                                <td></td>
                                                            </tr>
                                                        </tfoot>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>

                                        <script>
                                            function materialComponent() {
                                                return {
                                                    boqProducts: @js(($boqProducts ?? collect())->map(fn($p) => [
                                                        'id'            => $p->id,
                                                        'name'          => $p->name,
                                                        'brand'         => $p->brand,
                                                        'model'         => $p->model,
                                                        'description'   => $p->description,
                                                        'unit'          => $p->unit,
                                                        'price'         => (float) $p->price,
                                                        'gst'           => (float) $p->gst,
                                                        'category_name' => $p->category?->name ?? '',
                                                    ])->values()->all()),
                                                    gstRates: @js(collect($gstRates ?? [])->pluck('rate')->merge(($boqProducts ?? collect())->pluck('gst'))->merge([0])->map(fn($rate) => (float) $rate)->unique()->sort()->values()->all()),
                                                    unitOptions: @js(($units ?? collect())->pluck('name')->merge(($boqProducts ?? collect())->pluck('unit'))->merge(($lead->materials ?? collect())->pluck('unit'))->map(fn($u) => trim((string)$u))->filter()->unique()->values()->all()),
                                                    materialRows: [{ product_id: '', material_name: '', qty: 1, unit: '', rate: 0, gst: 0, note: '' }],
                                                    rowErrors: [{ material_name: '', qty: '' }],
                                                    productModalOpen: @js($errors->hasAny(['category_id', 'name', 'brand', 'model', 'unit', 'price', 'gst', 'product_image', 'description', 'note'])),
                                                    get productFormOpen() {
                                                        return this.productModalOpen;
                                                    },
                                                    set productFormOpen(val) {
                                                        this.productModalOpen = val;
                                                    },
                                                    errorMessage: '',
                                                    editingMaterialId: null,

                                                    openProductModal() {
                                                        this.productModalOpen = true;
                                                        this.$nextTick(() => {
                                                            const firstField = document.getElementById('lead_product_category_id');
                                                            if (firstField) firstField.focus();
                                                        });
                                                    },

                                                    closeProductModal() {
                                                        this.productModalOpen = false;
                                                        if (typeof window.clearInlineProductErrors === 'function') {
                                                            window.clearInlineProductErrors();
                                                        }
                                                    },

                                                    ensureUnitOption(value = '') {
                                                        const unit = String(value || '').trim();
                                                        if (unit && !this.unitOptions.includes(unit)) {
                                                            this.unitOptions.push(unit);
                                                        }
                                                        return unit;
                                                    },

                                                    unitOptionsFor(selectedUnit = '') {
                                                        const selected = String(selectedUnit || '').trim();
                                                        if (selected && !this.unitOptions.includes(selected)) {
                                                            this.unitOptions.push(selected);
                                                        }
                                                        return this.unitOptions;
                                                    },

                                                    ensureGstOption(value = 0) {
                                                        const rate = Number(value || 0);
                                                        if (!this.gstRates.some(existing => Math.abs(Number(existing) - rate) < 0.001)) {
                                                            this.gstRates.push(rate);
                                                            this.gstRates.sort((a, b) => Number(a) - Number(b));
                                                        }
                                                        return rate;
                                                    },

                                                    gstOptionsFor(selectedGst = 0) {
                                                        const rates = Array.isArray(this.gstRates) ? [...this.gstRates] : [0];
                                                        const selected = Number(selectedGst || 0);
                                                        if (!rates.some(rate => Math.abs(Number(rate) - selected) < 0.001)) {
                                                            rates.push(selected);
                                                        }
                                                        if (!rates.some(rate => Math.abs(Number(rate) - 0) < 0.001)) {
                                                            rates.push(0);
                                                        }

                                                        return [...new Set(rates.map(r => Number(r)))]
                                                            .filter(rate => Number.isFinite(rate))
                                                            .sort((a, b) => a - b);
                                                    },

                                                    selectProduct(index, productId, rowElement = null) {
                                                        this.applyProductToRow(this.materialRows[index], productId, rowElement);
                                                    },

                                                    applyProductToRow(row, productId, rowElement = null) {
                                                        if (!productId) return;
                                                        const product = this.boqProducts.find(p => String(p.id) === String(productId));
                                                        if (product) {
                                                            row.product_id = product.id;
                                                            row.material_name = product.name;
                                                            const unit = String(product.unit || '').trim();
                                                            if (unit) {
                                                                this.ensureUnitOption(unit);
                                                                row.unit = unit;
                                                            }
                                                            row.rate = Number(product.price || 0);
                                                            const gst = Number(product.gst || 0);
                                                            this.ensureGstOption(gst);
                                                            row.gst = gst;
                                                            if (!String(row.note || '').trim()) {
                                                                const details = [product.brand, product.model].filter(Boolean).join(' ');
                                                                row.note = product.description || details || '';
                                                            }
                                                            this.$nextTick(() => {
                                                                if (unit) row.unit = unit;
                                                                row.gst = gst;

                                                                const container = rowElement || document;
                                                                const selects = container.querySelectorAll('select');
                                                                selects.forEach(select => {
                                                                    if (typeof select._syncCustomSelect === 'function') {
                                                                        select._syncCustomSelect();
                                                                    } else {
                                                                        select.dispatchEvent(new Event('change', { bubbles: true }));
                                                                    }
                                                                });
                                                            });
                                                        }
                                                    },

                                                    addMaterialRow() {
                                                        this.materialRows.push({ product_id: '', material_name: '', qty: 1, unit: '', rate: 0, gst: 0, note: '' });
                                                        this.rowErrors.push({ material_name: '', qty: '' });
                                                    },

                                                    removeMaterialRow(index) {
                                                        if (this.materialRows.length > 1) {
                                                            this.materialRows.splice(index, 1);
                                                            this.rowErrors.splice(index, 1);
                                                        }
                                                    },

                                                    materialRowTotal(row) {
                                                        return (parseFloat(row.qty || 0) * parseFloat(row.rate || 0)).toFixed(2);
                                                    },

                                                    materialGrandTotal() {
                                                        return this.materialRows.reduce((sum, row) => sum + parseFloat(this.materialRowTotal(row)), 0).toFixed(2);
                                                    },

                                                    submitForm(formElement) {
                                                        this.errorMessage = '';
                                                        this.rowErrors = this.materialRows.map(() => ({ material_name: '', qty: '' }));

                                                        for (let i = 0; i < this.materialRows.length; i++) {
                                                            let row = this.materialRows[i];
                                                            if (!row.material_name.trim()) {
                                                                this.errorMessage = `Row ${i + 1}: Material Name is required.`;
                                                                this.rowErrors[i].material_name = 'Material Name is required.';
                                                                return false;
                                                            }
                                                            if (parseFloat(row.qty) <= 0) {
                                                                this.errorMessage = `Row ${i + 1}: Quantity must be greater than 0.`;
                                                                this.rowErrors[i].qty = 'Quantity must be greater than 0.';
                                                                return false;
                                                            }
                                                            if (!Number.isFinite(parseFloat(row.rate)) || parseFloat(row.rate) < 0) {
                                                                this.errorMessage = `Row ${i + 1}: Price cannot be negative.`;
                                                                return false;
                                                            }
                                                        }

                                                        formElement.submit();
                                                        return true;
                                                    },

                                                    confirmDelete(event) {
                                                        const url = event.currentTarget.getAttribute('data-url');

                                                        Swal.fire({
                                                            title: 'Are you sure?',
                                                            text: "You won't be able to revert this!",
                                                            icon: 'warning',
                                                            showCancelButton: true,
                                                            confirmButtonColor: '#4f46e5',
                                                            cancelButtonColor: '#f43f5e',
                                                            confirmButtonText: 'Yes, delete it!'
                                                        }).then((result) => {
                                                            if (result.isConfirmed) {
                                                                let form = document.createElement('form');
                                                                form.action = url;
                                                                form.method = 'POST';
                                                                form.innerHTML = `@csrf @method('DELETE')`;
                                                                document.body.appendChild(form);
                                                                form.submit();
                                                            }
                                                        });
                                                    }
                                                }
                                            }
                                        </script>
                                        <div x-show="tab === 'quotation'">
                                            @php
                                                $quotationEntries = $lead->quotations->filter(function ($q) {
                                                    return strtolower((string) $q->source) !== 'boq';
                                                })->sortBy('created_at')->values();
                                                $boqEntries = $lead->quotations->filter(function ($quote) {
                                                    return strtolower((string) $quote->source) === 'boq';
                                                })->sortBy('created_at')->values();
                                                $latestQuotationId = optional($quotationEntries->sortByDesc(fn ($q) => optional($q->created_at)->timestamp ?? 0)->first())->id;
                                                $latestBoqId = optional($boqEntries->sortByDesc(fn ($q) => optional($q->created_at)->timestamp ?? 0)->first())->id;
                                                $hasApprovedQuotation = $quotationEntries->contains(function ($q) {
                                                    return strtolower((string) $q->status) === 'accepted';
                                                });
                                                $isConvertedToProject = (bool) $lead->project;
                                                $showQuotationVersion = (bool) ($lead->company?->usesQuotationNumbering() ?? false);
                                                $nextQuotationVersion = old('version', $lead->company?->nextAvailableQuotationVersion() ?? (($quotationEntries->count() + 1) . '.0'));
                                                $enableQuotationGenerator = (bool) ($lead->company?->enable_quotation_generator ?? false);
                                            @endphp

                                            @if ($canManageQuotation)
                                                @if ($enableQuotationGenerator)
                                                <div class="mb-6 flex flex-col gap-4 border border-indigo-200 bg-indigo-50 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                                    <div class="min-w-0 flex-1">
                                                        <h4 class="text-base font-bold text-indigo-900 sm:text-lg">Dynamic Quotation Generator</h4>
                                                        <p class="mt-1 text-sm text-indigo-700">Generate professional quotations for this lead.</p>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                            @if ($hasApprovedQuotation)
                                                                <button type="button" disabled
                                                                    class="inline-flex w-full items-center justify-center border border-transparent bg-gray-300 px-5 py-3 text-xs font-bold uppercase tracking-widest text-gray-600 cursor-not-allowed sm:w-auto">
                                                                    Generator Locked
                                                                </button>
                                                            @else
                                                                <a href="{{ route('leads.quotation.generate', $lead) }}"
                                                                    class="inline-flex w-full items-center justify-center border border-transparent bg-indigo-600 px-5 py-3 text-xs font-bold uppercase tracking-widest text-white shadow-lg shadow-indigo-200 transition-all hover:-translate-y-0.5 hover:bg-indigo-700 sm:w-auto">
                                                                    Generate Quotation
                                                                </a>
                                                            @endif
                                                    </div>
                                                </div>
                                            @else
                                                    <form id="quotation-upload-form" action="{{ route('leads.quotation', $lead) }}"
                                                        method="POST" enctype="multipart/form-data"
                                                        class="mb-6 border border-gray-200 bg-gray-50 p-4 shadow-sm sm:p-5 swal-form">
                                                        @csrf

                                                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3 md:items-end">

                                                            <div>
                                                                <x-input-label for="quotation_file"
                                                                    value="Quotation File (PDF/Word)" />

                                                                <label for="quotation_file"
                                                                    class="mt-1 flex h-[42px] w-full cursor-pointer items-center justify-between border border-gray-300 bg-white px-3 hover:border-gray-400 {{ $hasApprovedQuotation ? 'cursor-not-allowed opacity-60' : '' }}">

                                                                    <span id="quotation-file-name"
                                                                        class="text-sm text-gray-700 truncate">
                                                                        Choose file
                                                                    </span>

                                                                    <span class="text-xs text-indigo-600 font-medium">
                                                                        Browse
                                                                    </span>
                                                                </label>

                                                                <input id="quotation_file" type="file" name="quotation_file"
                                                                    class="hidden" accept=".pdf,.doc,.docx" required
                                                                    {{ $hasApprovedQuotation ? 'disabled' : '' }}>
                                                            </div>



                                                            @if ($showQuotationVersion)
                                                                <div>
                                                                    <x-input-label for="quote_version" value="Version" />
                                                                    <x-text-input id="quote_version" name="version" type="text"
                                                                        class="mt-1 block w-full h-[42px]"
                                                                        value="{{ $nextQuotationVersion }}"
                                                                        readonly required />
                                                                </div>
                                                            @endif

                                                            <div class="mt-2 flex flex-col gap-3 sm:mt-6 sm:flex-row sm:items-center">
                                                                @if ($canDownloadSummary)
                                                                    <a href="{{ route('leads.print', $lead) }}" target="_blank"
                                                                        class="inline-flex h-[42px] w-full items-center justify-center border border-transparent bg-gray-600 px-4 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 sm:w-auto">

                                                                        <svg xmlns="http://www.w3.org/2000/svg"
                                                                            class="h-5 w-5 text-white" fill="none"
                                                                            viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                                stroke-width="2"
                                                                                d="M6 9V2h12v7M6 18h12v4H6v-4zm-2-6h16a2 2 0 012 2v4H2v-4a2 2 0 012-2z" />
                                                                        </svg>
                                                                    </a>
                                                                @endif

                                                                @if ($hasApprovedQuotation)
                                                                    <button type="button" disabled
                                                                        class="inline-flex h-[42px] w-full items-center justify-center border border-transparent bg-gray-300 px-4 text-xs font-semibold uppercase tracking-widest text-gray-600 cursor-not-allowed sm:w-auto">
                                                                        Upload Disabled
                                                                    </button>
                                                                @else
                                                                    <x-primary-button class="h-[42px] w-full justify-center sm:w-auto">
                                                                        Upload Quotation
                                                                    </x-primary-button>
                                                                @endif
                                                            </div>

                                                        </div>
                                                    </form>
                                                @endif
                                            @endif

                                            @if ($hasApprovedQuotation)
                                                <p class="mb-4 text-sm text-gray-600">
                                                    Quotation actions are locked because one quotation is already approved.
                                                </p>
                                            @endif

                                            @if ($isConvertedToProject)
                                                <b class="mb-4 text-sm text-red-600">
                                                    This lead is already converted to a project. Additional conversion actions are
                                                    disabled.
                                                </b>
                                            @endif

                                            <div class="bg-white shadow sm border border-gray-200 p-4">

                                                @if ($quotationEntries->count())
                                                    <div
                                                        class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">

                                                        @foreach ($quotationEntries as $quote)
                                                            <div
                                                                class= p-4 hover:shadow-md transition
                                                                    border
                                                                    @if (strtolower($quote->status) === 'accepted') border-green-500
                                                                    @elseif(strtolower($quote->status) === 'rejected') border-red-500
                                                                    @else border-gray-200 @endif
                                                                ">
                                                                                                                <!-- Header -->
                                                                                                                <div class="flex items-center justify-between">
                                                                                                                    <p class="text-sm font-semibold text-indigo-600">
                                                                                                                        Version {{ $quote->version }}
                                                                                                                    </p>
                                                                                                                    <div class="ml-2 flex flex-shrink-0">
                                                                                                                        <p
                                                                                                                            class="inline-flex px-2 text-xs font-semibold capitalize
                                                                            @if (strtolower($quote->status) === 'accepted') bg-green-100 text-green-800
                                                                            @elseif(strtolower($quote->status) === 'rejected') bg-red-100 text-red-800
                                                                            @else bg-gray-100 text-gray-800 @endif
                                                                        ">
                                                                        @if(strtolower($quote->status) === 'accepted')  {{ $quote->status }} @endif
                                                                        </p>
                                                                    </div>
                                                                </div>

                                                                <!-- Actions -->
                                                                <div class="mt-3 flex flex-col gap-2 text-sm">
                                                                    @if ($canManageQuotation)
                                                                        @if (strtolower((string) $quote->source) === 'form')
                                                                            <a href="{{ route('leads.quotation.view-dynamic', [$lead, $quote]) }}"
                                                                                target="_blank"
                                                                                class="text-indigo-600 font-bold hover:underline">
                                                                                View Dynamic Quotation
                                                                            </a>
                                                                            <a href="{{ route('leads.quotation.edit', [$lead, $quote]) }}"
                                                                                class="text-slate-700 font-medium hover:underline">
                                                                                Edit Quotation
                                                                            </a>
                                                                        @else
                                                                            <a href="{{ route('leads.quotation.download', [$lead, $quote]) }}"
                                                                                class="text-indigo-600 font-medium hover:underline">
                                                                                Download File
                                                                            </a>
                                                                        @endif

                                                                        @if (!$isConvertedToProject)
                                                                            <form method="POST"
                                                                                action="{{ route('leads.quotation.clone', [$lead, $quote]) }}"
                                                                                class="swal-form">
                                                                                @csrf
                                                                                <button type="submit"
                                                                                    class="text-emerald-600 font-medium hover:underline">
                                                                                    Clone
                                                                                </button>
                                                                            </form>
                                                                        @endif

                                                                        @if (strtolower((string) $quote->source) === 'form')
                                                                            <button type="button"
                                                                                @click="openSaveTemplateModal({{ $quote->id }}, '{{ route('leads.quotation.save-template', [$lead, $quote]) }}')"
                                                                                class="text-amber-600 font-medium hover:underline text-left">
                                                                                Save as Template
                                                                            </button>
                                                                        @endif
                                                                    @endif

                                                                    @if (
                                                                        $canManageQuotation &&
                                                                            !$hasApprovedQuotation &&
                                                                            !$isConvertedToProject &&
                                                                            (int) $quote->id === (int) $latestQuotationId &&
                                                                            !in_array(strtolower((string) $quote->status), ['accepted', 'rejected'], true))
                                                                        <div class="flex flex-col gap-2 sm:flex-row">
                                                                            <button type="button"
                                                                                @click="openApproveModal({{ $quote->id }}, @js($quote->version))"
                                                                                class="inline-flex w-full items-center justify-center bg-indigo-600 px-3 py-1.5 text-xs text-white hover:bg-indigo-700 sm:w-1/2">
                                                                                Approve
                                                                            </button>

                                                                            <form method="POST"
                                                                                action="{{ route('leads.quotation.decision', [$lead, $quote]) }}"
                                                                                class="w-full sm:w-1/2 swal-form">
                                                                                @csrf
                                                                                @method('PATCH')
                                                                                <input type="hidden" name="decision"
                                                                                    value="rejected">

                                                                                <button type="submit"
                                                                                    class="inline-flex w-full items-center justify-center bg-red-600 px-3 py-1.5 text-xs text-white hover:bg-red-700">
                                                                                    Reject
                                                                                </button>
                                                                            </form>
                                                                        </div>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach

                                                    </div>
                                                @else
                                                    <p class="text-center py-10 text-gray-500 italic">
                                                        No quotations managed yet.
                                                    </p>
                                                @endif

                                            </div>
                                        </div>

                                        @if ($canManageBoq)
                                            <div x-show="tab === 'boq'">
                                                @php
                                                    $boqEntries = $lead->quotations->filter(function ($quote) {
                                                        return strtolower((string) $quote->source) === 'boq';
                                                    })->sortBy('created_at')->values();
                                                    $latestBoqId = optional($boqEntries->sortByDesc(fn ($q) => optional($q->created_at)->timestamp ?? 0)->first())->id;
                                                    $isConvertedToProject = (bool) $lead->project;
                                                @endphp

                                                <div class="mb-6 flex flex-col gap-4 border border-indigo-200 bg-indigo-50 p-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:p-6">
                                                    <div class="min-w-0 flex-1">
                                                        <h4 class="text-base font-bold text-indigo-900 sm:text-lg">Dynamic BOQ Generator</h4>
                                                        <p class="mt-1 text-sm text-indigo-700">
                                                            Generate BOQ sheets using saved BOQ categories and products for {{ $lead->client_name }}.
                                                        </p>
                                                    </div>
                                                    <div class="flex-shrink-0">
                                                        @if ($isConvertedToProject)
                                                            <button type="button" disabled
                                                                class="inline-flex w-full items-center justify-center border border-transparent bg-gray-300 px-5 py-3 text-xs font-bold uppercase tracking-widest text-gray-600 cursor-not-allowed sm:w-auto">
                                                                Generator Locked
                                                            </button>
                                                        @else
                                                            <a href="{{ route('leads.boq.generate', $lead) }}"
                                                                class="inline-flex w-full items-center justify-center border border-transparent bg-indigo-600 px-5 py-3 text-xs font-bold uppercase tracking-widest text-white shadow-lg shadow-indigo-200 transition-all hover:-translate-y-0.5 hover:bg-indigo-700 sm:w-auto">
                                                                Generate BOQ
                                                            </a>
                                                        @endif
                                                    </div>
                                                </div>
                                                @if ($isConvertedToProject)
                                                    <p class="mb-4 text-sm text-red-600">
                                                        This lead is already converted to a project. BOQ generation, approval, and rejection are disabled.
                                                    </p>
                                                @endif
                                                <div class="bg-white shadow sm border border-gray-200 p-4">
                                                    <div class="space-y-4">
                                                    @if ($boqEntries->count())
                                                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                                                                @foreach ($boqEntries as $boq)
                                                                    <div class= p-4 hover:shadow-md transition border
                                                                        @if (strtolower($boq->status) === 'accepted') border-green-500
                                                                        @elseif(strtolower($boq->status) === 'rejected') border-red-500
                                                                        @else border-gray-200 @endif bg-white">

                                                                        <div class="flex items-center justify-between">
                                                                            <div>
                                                                                <p class="text-sm font-semibold text-indigo-600">
                                                                                    Version {{ $boq->version }}
                                                                                </p>
                                                                                <p class="text-[10px] text-gray-500">
                                                                                    {{ optional($boq->created_at)->format('M d, Y') }}
                                                                                </p>
                                                                            </div>
                                                                            <div class="flex flex-col items-end">
                                                                                <p class="text-sm font-bold text-gray-900">
                                                                                    ₹{{ number_format((float) ($boq->amount ?? 0), 2) }}
                                                                                </p>
                                                                                <span class="inline-flex px-2 text-[10px] font-semibold capitalize
                                                                                    @if (strtolower($boq->status) === 'accepted') bg-green-100 text-green-800
                                                                                    @elseif(strtolower($boq->status) === 'rejected') bg-red-100 text-red-800
                                                                                    @else bg-gray-100 text-gray-800 @endif">
                                                                                    {{ $boq->status ?: 'draft' }}
                                                                                </span>
                                                                            </div>
                                                                        </div>

                                                                        <div class="mt-4 flex flex-col gap-2 text-sm">
                                                                            <div class="flex flex-wrap gap-3 border-b border-gray-50 pb-2">
                                                                                <a href="{{ route('leads.boq.view', [$lead, $boq]) }}"
                                                                                    class="text-indigo-600 font-bold hover:underline" target="_blank">
                                                                                    View BOQ
                                                                                </a>
                                                                                <a href="{{ route('leads.boq.edit', [$lead, $boq]) }}"
                                                                                    class="text-slate-700 font-medium hover:underline">
                                                                                    Edit
                                                                                </a>
                                                                                <a href="{{ route('leads.boq.print', [$lead, $boq]) }}"
                                                                                    class="text-gray-600 font-medium hover:underline" target="_blank">
                                                                                    Print
                                                                                </a>
                                                                            </div>

                                                                            {{-- Decision Actions --}}
                                                                            @if (
                                                                                $canManageQuotation &&
                                                                                    !$isConvertedToProject &&
                                                                                    (int) $boq->id === (int) $latestBoqId &&
                                                                                    !in_array(strtolower((string) $boq->status), ['accepted', 'rejected'], true))
                                                                                <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                                                                                    <button type="button"
                                                                                        @click="openApproveModal({{ $boq->id }}, @js($boq->version))"
                                                                                        class="inline-flex w-full items-center justify-center bg-indigo-600 px-3 py-1.5 text-xs text-white transition hover:bg-indigo-700 sm:w-1/2">
                                                                                        Approve
                                                                                    </button>

                                                                                    <form method="POST"
                                                                                        action="{{ route('leads.quotation.decision', [$lead, $boq]) }}"
                                                                                        class="w-full sm:w-1/2 swal-form">
                                                                                        @csrf
                                                                                        @method('PATCH')
                                                                                        <input type="hidden" name="decision" value="rejected">
                                                                                        <button type="submit"
                                                                                            class="inline-flex w-full items-center justify-center bg-red-600 px-3 py-1.5 text-xs text-white transition hover:bg-red-700">
                                                                                            Reject
                                                                                        </button>
                                                                                    </form>
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            </div>
                                                        @else
                                                            <p class="text-center py-10 text-gray-500 italic">
                                                                No BOQs managed yet.
                                                            </p>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        <script>
                                            document.getElementById('quotation_file')?.addEventListener('change', function(e) {
                                                const name = e.target.files[0]?.name || 'Choose file';
                                                document.getElementById('quotation-file-name').textContent = name;
                                            });
                                        </script>

                                        <div x-show="tab === 'comments'">
                                            <div class="space-y-4 mb-6">
                                                @foreach ($lead->comments as $comment)
                                                    <div class="bg-gray-50 p-4">
                                                        <div class="flex justify-between items-center mb-2">
                                                            <span
                                                                class="font-bold text-sm text-gray-900">{{ $comment->user->name }}</span>
                                                            <span
                                                                class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                                        </div>
                                                        <p class="text-sm text-gray-700">{{ $comment->comment }}</p>
                                                    </div>
                                                @endforeach
                                            </div>

                                            @if ($canCommentLead)
                                                <form action="{{ route('leads.comment', $lead) }}" method="POST" id="lead-comment-form" novalidate>
                                                    @csrf
                                                    <div>
                                                        <x-input-label for="comment" value="Add Internal Comment" />
                                                        <textarea id="comment" name="comment" rows="3"
                                                            class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                            required
                                                            data-min="5"
                                                            data-max="500"></textarea>
                                                    </div>
                                                    <div class="mt-2 flex justify-end">
                                                        <x-primary-button type="submit">Post Comment</x-primary-button>
                                                    </div>
                                                </form>
                                            @endif
                                        </div>

                                        @if($lead->company?->allow_appointment_module ?? false)
                                            <div x-show="tab === 'appointments'">
                                                <div class="mb-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <h4 class="text-base font-bold text-slate-900">Slot Appointments</h4>
                                                        <p class="text-xs text-slate-500">1-hour slots (09:00 AM - 08:00 PM, excluding 02:00 PM - 03:00 PM lunch break). Max 10 leads/slot.</p>
                                                    </div>
                                                    <a href="{{ route('appointments.index') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                                        Book In Slot View
                                                    </a>
                                                </div>

                                                <div class="space-y-3">
                                                    @forelse($lead->appointments()->with(['assignee'])->orderByDesc('appointment_date')->orderBy('slot_start_time')->get() as $apt)
                                                        <div class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs sm:flex-row sm:items-center sm:justify-between">
                                                            <div>
                                                                <div class="flex items-center gap-2">
                                                                    <span class="text-sm font-bold text-slate-900">{{ $apt->appointment_date->format('M d, Y') }}</span>
                                                                    <span class="rounded-lg bg-indigo-50 px-2.5 py-0.5 text-xs font-bold text-indigo-700 border border-indigo-200">
                                                                        {{ $apt->slot_label }}
                                                                    </span>
                                                                    @php
                                                                        $statusClasses = [
                                                                            'scheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                                            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                                            'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                                            'rescheduled' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                                        ];
                                                                    @endphp
                                                                    <span class="rounded-md border px-2 py-0.5 text-[11px] font-semibold {{ $statusClasses[$apt->status] ?? 'bg-slate-100 text-slate-700' }}">
                                                                        {{ ucfirst($apt->status) }}
                                                                    </span>
                                                                </div>
                                                                <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                                                    @if($apt->assignee)
                                                                        <span>Assigned to: <strong>{{ $apt->assignee->name }}</strong></span>
                                                                    @endif
                                                                    @if($apt->notes)
                                                                        <span class="italic text-slate-400">"{{ $apt->notes }}"</span>
                                                                    @endif
                                                                </div>
                                                            </div>

                                                            <div class="flex items-center gap-2">
                                                                @if($apt->status === 'scheduled')
                                                                    <form method="POST" action="{{ route('appointments.status.update', $apt) }}">
                                                                        @csrf
                                                                        @method('PATCH')
                                                                        <input type="hidden" name="status" value="completed">
                                                                        <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100">
                                                                            Mark Done
                                                                        </button>
                                                                    </form>
                                                                @endif
                                                                <a href="{{ route('appointments.index', ['date' => optional($apt->appointment_date)->toDateString() ?? now()->toDateString()]) }}" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                                                    View Slot
                                                                </a>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="rounded-2xl border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400">
                                                            No slot appointments scheduled for this lead yet.
                                                        </div>
                                                    @endforelse
                                                </div>
                                            </div>
                                        @endif
                                        <script>
                                            document.addEventListener('DOMContentLoaded', function () {
                                                const commentForm = document.getElementById('lead-comment-form');

                                                if (commentForm) {
                                                    commentForm.addEventListener('submit', function (e) {
                                                        e.preventDefault(); // Stop form from submitting immediately

                                                        const textarea = document.getElementById('comment');
                                                        const commentValue = textarea.value.trim();
                                                        const minLength = parseInt(textarea.getAttribute('data-min'));
                                                        const maxLength = parseInt(textarea.getAttribute('data-max'));

                                                        // 1. Required Validation
                                                        if (!commentValue) {
                                                            Swal.fire({
                                                                icon: 'error',
                                                                title: 'Required Field',
                                                                text: 'Please fill out the comment field before posting.',
                                                                confirmButtonColor: '#4f46e5',
                                                            });
                                                            return;
                                                        }

                                                        // 2. Min-Length Validation
                                                        if (commentValue.length < minLength) {
                                                            Swal.fire({
                                                                icon: 'warning',
                                                                title: 'Comment Too Short',
                                                                text: `Your comment must be at least ${minLength} characters long.`,
                                                                confirmButtonColor: '#4f46e5',
                                                            });
                                                            return;
                                                        }

                                                        // 3. Max-Length Validation
                                                        if (commentValue.length > maxLength) {
                                                            Swal.fire({
                                                                icon: 'warning',
                                                                title: 'Comment Too Long',
                                                                text: `Your comment exceeds the limit of ${maxLength} characters.`,
                                                                confirmButtonColor: '#4f46e5',
                                                            });
                                                            return;
                                                        }

                                                        // If all validations pass, submit the form
                                                        this.submit();
                                                    });
                                                }
                                            });
                                            </script>

                                        <div x-show="tab === 'summary'">
                                            <div class="grid grid-cols-1 gap-4">
                                                @php
                                                $acceptedQuotation = $quotationEntries->first(function ($quote) {
                                                    return strtolower((string) $quote->status) === 'accepted';
                                                });
                                                    $effectiveValue = $acceptedQuotation?->amount ?? $lead->estimated_value;
                                                @endphp

                                                <div class="border border-indigo-200 bg-indigo-50 p-4 shadow-sm">
                                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold text-indigo-900">Lead Summary</p>
                                                            <div class="mt-2 grid gap-1 text-[13px] leading-5 text-indigo-800 break-words">
                                                                <p><span class="font-semibold">Client:</span> {{ $lead->client_name }}</p>
                                                                <p><span class="font-semibold">Status:</span> {{ $lead->status->name }}</p>
                                                                @if (filled($lead->loss_reason))
                                                                    <p class="text-red-700"><span class="font-semibold">Loss Reason:</span> {{ $lead->loss_reason }}</p>
                                                                @endif
                                                                <p><span class="font-semibold">Effective Value:</span> ₹{{ number_format((float) $effectiveValue, 2) }}</p>
                                                            </div>
                                                        </div>
                                                        @if ($canDownloadSummary)
                                                            <div class="grid grid-cols-1 gap-2 sm:flex sm:flex-row sm:items-center sm:gap-2 sm:shrink-0">
                                                                <a href="{{ route('leads.summary.download', ['lead' => $lead, 'format' => 'doc']) }}"
                                                                    class="inline-flex w-full items-center justify-center border border-transparent bg-indigo-600 px-3 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700 sm:w-auto">
                                                                    Download DOC
                                                                </a>
                                                                <a href="{{ route('leads.summary.download', ['lead' => $lead, 'format' => 'txt']) }}"
                                                                    class="inline-flex w-full items-center justify-center border border-indigo-200 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 hover:bg-indigo-50 sm:w-auto">
                                                                    TXT
                                                                </a>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div x-show="tab === 'documents'">
                                            @if ($canUploadDocuments)
                                                <form id="document-upload-form"
                                                    action="{{ route('leads.documents.store', $lead) }}" method="POST"
                                                    enctype="multipart/form-data"
                                                    class="mb-6 bg-gray-50 p-4 border border-gray-200 swal-form">
                                                    @csrf

                                                    <div class="grid grid-cols-12 gap-3">
                                                        <!-- Labels row -->
                                                        <div class="col-span-12 md:col-span-5">
                                                            <x-input-label for="document_file" value="Upload Document / Media File" />
                                                        </div>
                                                        <div class="col-span-12 md:col-span-5">
                                                            <x-input-label for="document_name" value="Document Name (Optional)" />
                                                        </div>
                                                        <div class="col-span-12 md:col-span-2"></div>

                                                        <!-- Inputs row -->
                                                        <div class="col-span-12 md:col-span-5">
                                                            <label for="document_file"
                                                                class="flex items-center justify-between gap-2 border-2 border-dashed border-slate-300 bg-white px-3 py-2 cursor-pointer hover:border-slate-400 hover:bg-slate-50 transition h-[42px]">
                                                                <span class="text-sm font-medium text-slate-700">Choose File</span>
                                                                <span class="text-[10px] text-slate-500 truncate max-w-[150px]">
                                                                    Images, Docs, PDF...
                                                                </span>
                                                            </label>
                                                            <input id="document_file" type="file" name="document_file"
                                                                class="sr-only"
                                                                accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip,.rar,.mp4,.mov,.avi,.mkv,.mp3,.wav"
                                                                data-media-type="document-any" data-max-size="10485760" required>
                                                        </div>

                                                        <div class="col-span-12 md:col-span-5 flex items-center">
                                                            <x-text-input id="document_name" name="document_name" type="text"
                                                                class="block w-full h-[42px]" value="{{ old('document_name') }}" maxlength="191"
                                                                placeholder="Custom display name" />
                                                        </div>

                                                        <div class="col-span-12 md:col-span-2 flex items-center md:items-end">
                                                            <x-primary-button
                                                                class="w-full justify-center h-[42px] text-xs font-bold uppercase tracking-widest">
                                                                Upload
                                                            </x-primary-button>
                                                        </div>

                                                        <!-- Helper / error row -->
                                                        <div class="col-span-12 md:col-span-5 space-y-1">
                                                            <p id="document-file-name"
                                                                class="text-[11px] text-gray-500 truncate max-w-[220px] m-0">
                                                                No file selected.
                                                            </p>
                                                            <span id="document-file-error"
                                                                class="hidden text-red-600 text-xs block"></span>
                                                            <x-input-error :messages="$errors->get('document_file')" class="mt-1" />
                                                        </div>
                                                        <div class="col-span-12 md:col-span-5">
                                                            <span id="document-name-error"
                                                                class="hidden text-red-600 text-xs block"></span>
                                                            <x-input-error :messages="$errors->get('document_name')" class="mt-1" />
                                                        </div>
                                                    </div>
                                                    <div class="h-4"></div>
                                                </form>
                                            @endif

                                            <div class="space-y-2">
                                                @forelse($lead->documents as $doc)
                                                    <div
                                                        class="flex items-center justify-between p-4 bg-white border border-gray-200 shadow-sm">
                                                        <div class="flex items-center">
                                                            <svg class="w-6 h-6 text-gray-400" fill="currentColor"
                                                                viewBox="0 0 20 20">
                                                                <path
                                                                    d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 6a1 1 0 011-1h6a1 1 0 110 2H7a1 1 0 01-1-1zm1 3a1 1 0 100 2h6a1 1 0 100-2H7z" />
                                                            </svg>
                                                            <span
                                                                class="ml-3 text-sm font-medium text-gray-900">{{ $doc->name }}</span>
                                                        </div>
                                                        <a href="{{ $doc->display_url ?? '#' }}"
                                                            target="_blank"
                                                            class="text-sm font-bold text-indigo-600 hover:text-indigo-900">View</a>
                                                    </div>
                                                @empty
                                                    <p class="text-center py-10 text-gray-500 italic">No general documents stored
                                                        yet.</p>
                                                @endforelse
                                            </div>
                                        </div>

                                        @if ($canUpdateLeadStatus)
                                            <div x-show="statusWonModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
                                                aria-labelledby="won-status-project-modal-title" role="dialog"
                                                aria-modal="true">
                                                <div class="relative min-h-full flex items-center justify-center p-4">
                                                    <div x-show="statusWonModalOpen" x-transition.opacity
                                                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                                                    <div x-show="statusWonModalOpen" x-transition
                                                        class="relative w-full max-w-lg sm:w-[32rem] sm:h-[32rem] overflow-hidden bg-white text-left shadow-xl border border-gray-200 flex flex-col">
                                                        <form method="POST" action="{{ route('leads.status.update', $lead) }}"
                                                            class="flex flex-col h-full swal-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="lead_status_id" :value="wonStatusId">

                                                            <div class="px-4 py-3 border-b border-gray-100">
                                                                <h3 id="won-status-project-modal-title"
                                                                    class="text-base font-semibold text-gray-900">
                                                                    Convert Lead To Project
                                                                </h3>
                                                                <p class="mt-1 text-sm text-gray-600">
                                                                    Status will be changed to <span
                                                                        class="font-semibold">Won</span> and lead will be converted
                                                                    to project.
                                                                </p>
                                                            </div>

                                                            <div class="p-4 space-y-3 overflow-y-auto flex-1">
                                                                <div>
                                                                    <x-input-label for="status_project_name"
                                                                        value="Project Name" />
                                                                    <x-text-input id="status_project_name" name="project_name"
                                                                        class="mt-1 block w-full" x-model="projectDetail.name" maxlength="191" autocomplete="off"
                                                                        required />
                                                                </div>
                                                                <div>
                                                                    <div class="flex items-center justify-between">
                                                                        <x-input-label for="status_project_description"
                                                                            value="Description" />
                                                                        <x-speech-to-text target="status_project_description" />
                                                                    </div>
                                                                    <textarea id="status_project_description" name="project_description" rows="2"
                                                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" maxlength="1000"
                                                                        x-model="projectDetail.description"></textarea>
                                                                </div>
                                                                <div class="grid grid-cols-2 gap-3">
                                                                    <div>
                                                                        <x-input-label for="status_project_start_date"
                                                                            value="Start Date" />
                                                                        <x-text-input id="status_project_start_date"
                                                                            name="project_start_date" type="date"
                                                                            class="mt-1 block w-full" ::min="today"
                                                                            x-model="projectDetail.start_date"
                                                                            @change="syncEndDateMin()" />
                                                                    </div>
                                                                    <div>
                                                                        <x-input-label for="status_project_end_date"
                                                                            value="End Date" />
                                                                        <x-text-input id="status_project_end_date"
                                                                            name="project_end_date" type="date"
                                                                            class="mt-1 block w-full" ::min="projectDetail.start_date || today"
                                                                            x-model="projectDetail.end_date" />
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <x-input-label for="status_project_status"
                                                                        value="Project Status" />
                                                                    <select id="status_project_status" name="project_status"
                                                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                                        x-model="projectDetail.status">
                                                                        <option value="pending">Pending</option>
                                                                        <option value="in_progress">In Progress</option>
                                                                        <option value="completed">Completed</option>
                                                                        <option value="cancelled">Cancelled</option>
                                                                    </select>
                                                                </div>
                                                            </div>

                                                            <div class="px-4 py-3 border-t border-gray-100 flex justify-end gap-2">
                                                                <button type="button" @click="closeStatusWonModal()"
                                                                    class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit"
                                                                    class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-700">
                                                                    Save
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif

                                        @if ($canManageQuotation)
                                            <div x-show="approveModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
                                                aria-labelledby="approve-quotation-modal-title" role="dialog" aria-modal="true">
                                                <div class="relative min-h-full flex items-center justify-center p-4">
                                                    <div x-show="approveModalOpen" x-transition.opacity
                                                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

                                                    <div x-show="approveModalOpen" x-transition
                                                        class="relative max-w-lg sm:w-[32rem] sm:h-[32rem] overflow-hidden bg-white text-left shadow-xl border border-gray-200 flex flex-col">
                                                        <form id="approve-quotation-form" method="POST" :action="approveFormAction || '#'"
                                                            class="flex flex-col h-full swal-form">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="decision" value="accepted">

                                                            <div class="px-4 py-3 border-b border-gray-100">
                                                                <h3 id="approve-quotation-modal-title"
                                                                    class="text-base font-semibold text-gray-900">
                                                                    Approve Quotation
                                                                </h3>
                                                                <p class="mt-1 text-sm text-gray-600">
                                                                    Version <span class="font-semibold"
                                                                        x-text="approveQuoteVersion"></span> will be approved and
                                                                    converted to project.
                                                                </p>
                                                            </div>

                                                            <div class="p-4 space-y-3 overflow-y-auto flex-1">
                                                                <div>
                                                                    <x-input-label for="project_name" value="Project Name" />
                                                                    <x-text-input id="project_name" name="project_name"
                                                                        class="mt-1 block w-full" x-model="projectDetail.name" minlength="3" maxlength="191" autocomplete="off"
                                                                        required />
                                                                    <x-input-error :messages="$errors->get('project_name')" class="mt-2" />
                                                                </div>
                                                                <div>
                                                                    <div class="flex items-center justify-between">
                                                                        <x-input-label for="project_description"
                                                                            value="Description" />
                                                                        <x-speech-to-text target="project_description" />
                                                                    </div>
                                                                    <textarea id="project_description" name="project_description" rows="2"
                                                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" maxlength="1000"
                                                                        x-model="projectDetail.description"></textarea>
                                                                    <x-input-error :messages="$errors->get('project_description')" class="mt-2" />
                                                                </div>
                                                                <div class="grid grid-cols-2 gap-3">
                                                                    <div>
                                                                        <x-input-label for="project_start_date"
                                                                            value="Start Date" />
                                                                        <x-text-input id="project_start_date"
                                                                            name="project_start_date" type="date"
                                                                            class="mt-1 block w-full" ::min="today"
                                                                            x-model="projectDetail.start_date"
                                                                            @change="syncEndDateMin()" />
                                                                        <x-input-error :messages="$errors->get('project_start_date')" class="mt-2" />
                                                                    </div>
                                                                    <div>
                                                                        <x-input-label for="project_end_date" value="End Date" />
                                                                        <x-text-input id="project_end_date"
                                                                            name="project_end_date" type="date"
                                                                            class="mt-1 block w-full" ::min="projectDetail.start_date || today"
                                                                            x-model="projectDetail.end_date" />
                                                                        <x-input-error :messages="$errors->get('project_end_date')" class="mt-2" />
                                                                    </div>
                                                                </div>
                                                                <div>
                                                                    <x-input-label for="project_status" value="Project Status" />
                                                                    <select id="project_status" name="project_status"
                                                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                                        x-model="projectDetail.status">
                                                                        <option value="pending">Pending</option>
                                                                        <option value="in_progress">In Progress</option>
                                                                        <option value="completed">Completed</option>
                                                                        <option value="cancelled">Cancelled</option>
                                                                    </select>
                                                                    <x-input-error :messages="$errors->get('project_status')" class="mt-2" />
                                                                </div>
                                                            </div>

                                                            <div class="px-4 py-3 border-t border-gray-100 flex justify-end gap-2">
                                                                <button type="button" @click="closeApproveModal()"
                                                                    class="inline-flex items-center px-3 py-2 bg-white border border-gray-300 text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit"
                                                                    class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-700">
                                                                    Approve
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>

                                            <!-- Save as Template Modal -->
                                            <div x-show="saveTemplateModalOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto"
                                                role="dialog" aria-modal="true">
                                                <div class="relative min-h-full flex items-center justify-center p-4">
                                                    <div x-show="saveTemplateModalOpen" x-transition.opacity
                                                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="closeSaveTemplateModal()"></div>

                                                    <div x-show="saveTemplateModalOpen" x-transition
                                                        class="relative max-w-lg w-full overflow-hidden bg-white text-left shadow-xl border border-gray-200 rounded-2xl p-6">
                                                        <h3 class="text-base font-bold text-gray-900 border-b pb-2">
                                                            Save Quotation as Template
                                                        </h3>
                                                        <p class="mt-1 text-xs text-gray-500">
                                                            Only items, materials, quantities, rates and GST details will be saved into the template (client information is excluded).
                                                        </p>
                                                        <form method="POST" :action="templateFormAction" class="mt-4 space-y-4">
                                                            @csrf
                                                            <div>
                                                                <x-input-label for="lead_modal_tpl_name" value="Template Name" />
                                                                <x-text-input id="lead_modal_tpl_name" name="name" x-model="templateName" class="mt-1 block w-full rounded-xl" required placeholder="e.g. 4 Camera Setup Template" />
                                                            </div>
                                                            <div>
                                                                <x-input-label for="lead_modal_tpl_desc" value="Description (Optional)" />
                                                                <textarea id="lead_modal_tpl_desc" name="description" x-model="templateDescription" rows="3" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Brief note about what this package/template covers..."></textarea>
                                                            </div>
                                                            <div class="pt-3 border-t flex justify-end gap-2">
                                                                <button type="button" @click="closeSaveTemplateModal()"
                                                                    class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 text-xs font-semibold text-gray-700 uppercase tracking-widest hover:bg-gray-50 rounded-xl">
                                                                    Cancel
                                                                </button>
                                                                <button type="submit"
                                                                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-indigo-700 rounded-xl">
                                                                    Save Template
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
            </div>

            @if (session('success'))
                <script>
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: @js(session('success')),
                        timer: 3000,
                        showConfirmButton: false
                    });
                </script>
            @endif

            @if (session('error'))
                <script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: @js(session('error'))
                    });
                </script>
            @endif

            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const surveyInput = document.getElementById('survey_photo');
                    const quoteInput = document.getElementById('quotation_file');
                    const documentInput = document.getElementById('document_file');
                    const approveForm = document.getElementById('approve-quotation-form');
                    const surveyForm = document.getElementById('survey-upload-form');
                    const quoteForm = document.getElementById('quotation-upload-form');
                    const documentForm = document.getElementById('document-upload-form');
                    const productForm = document.getElementById('lead-material-product-form');
                    const materialForm = document.getElementById('lead-material-form');
                    const surveyName = document.getElementById('survey-photo-file-name');
                    const quoteName = document.getElementById('quotation-file-name');
                    const documentName = document.getElementById('document-file-name');
                    const surveyError = document.getElementById('survey-photo-error');
                    const quoteError = document.getElementById('quotation-file-error');
                    const documentError = document.getElementById('document-file-error');
                    const activityTimes = document.querySelectorAll('.activity-time');

                    function trimToMaxLength(field) {
                        if (!field) return;
                        field.addEventListener('input', function () {
                            const maxLength = Number(this.getAttribute('maxlength') || 0);
                            if (maxLength > 0 && this.value.length > maxLength) {
                                this.value = this.value.slice(0, maxLength);
                            }
                        });
                    }

                    function updateName(input, nameEl) {
                        if (!input || !nameEl) return;
                        nameEl.textContent = input.files?.length ? input.files[0].name : 'No file selected.';
                    }

                    function setError(errorEl, message) {
                        if (!errorEl) return;
                        errorEl.textContent = message || '';
                        errorEl.classList.toggle('hidden', !message);
                    }

                    function showUploadError(title, message) {
                        if (typeof Swal === 'undefined') {
                            alert(message);
                            return;
                        }

                        Swal.fire({
                            title,
                            text: message,
                            icon: 'error',
                            confirmButtonColor: '#4f46e5',
                        });
                    }

                    function placeFieldError(error, element) {
                        error.addClass('text-xs text-red-600 mt-2 block');

                        if (element.attr('type') === 'file' && element.attr('id') === 'document_file') {
                            const fileErrorBlock = document.getElementById('document-file-error');
                            if (fileErrorBlock) {
                                error.insertAfter(fileErrorBlock);
                                return;
                            }
                        }

                        error.insertAfter(element);
                    }

                    function setMaterialFieldError(input, message) {
                        if (!input) return;
                        const wrapper = input.closest('.space-y-1');
                        const errorSlot = wrapper ? wrapper.querySelector('.material-field-error') : null;
                        if (errorSlot) {
                            errorSlot.textContent = message || '';
                            errorSlot.classList.toggle('hidden', !message);
                        }
                        input.classList.toggle('border-red-500', !!message);
                        input.classList.toggle('focus:border-red-500', !!message);
                        input.classList.toggle('focus:ring-red-500', !!message);
                    }

                    function setInlineProductFieldError(fieldName, message) {
                        const errorIdMap = {
                            category_id: 'lead_product_category_id_error',
                            name: 'lead_product_name_error',
                            brand: 'lead_product_brand_error',
                            model: 'lead_product_model_error',
                            unit: 'lead_product_unit_error',
                            price: 'lead_product_price_error',
                            gst: 'lead_product_gst_error',
                            product_image: 'lead_product_image_error',
                            description: 'lead_product_description_error',
                            note: 'lead_product_note_error',
                        };
                        const errorId = errorIdMap[fieldName] || `lead_product_${fieldName}_error`;
                        const input = productForm ? productForm.querySelector(`[name="${fieldName}"]`) : null;
                        const errorSlot = document.getElementById(errorId);
                        if (errorSlot) {
                            errorSlot.textContent = message || '';
                            errorSlot.classList.toggle('opacity-0', !message);
                            errorSlot.classList.toggle('opacity-100', !!message);
                        }
                        if (input) {
                            input.classList.toggle('border-red-500', !!message);
                            input.classList.toggle('ring-1', !!message);
                            input.classList.toggle('ring-red-300', !!message);
                            input.classList.toggle('focus:border-red-500', !!message);
                            input.classList.toggle('focus:ring-red-500', !!message);
                        }
                    }

                    function clearInlineProductErrors() {
                        if (!productForm) return;
                        ['category_id', 'name', 'brand', 'model', 'unit', 'price', 'gst', 'product_image', 'description', 'note'].forEach(function (field) {
                            setInlineProductFieldError(field, '');
                        });
                    }
                    window.clearInlineProductErrors = clearInlineProductErrors;

                    function validateMediaFile(input, errorEl) {
                        if (!input || input.disabled) return true;
                        const file = input.files?.[0];
                        if (!file) {
                            setError(errorEl, '');
                            return true;
                        }

                        const maxSize = Number(input.dataset.maxSize || 0);
                        const mediaType = input.dataset.mediaType || '';
                        const extension = (file.name.split('.').pop() || '').toLowerCase();
                        const mime = (file.type || '').toLowerCase();

                        const isImageValid = ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(extension) || ['image/jpeg',
                            'image/png', 'image/webp', 'image/gif'
                        ].includes(mime);
                        const isDocValid = ['pdf', 'doc', 'docx'].includes(extension) || ['application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                        ].includes(mime);
                        const isGenericMediaValid = [
                            'jpg', 'jpeg', 'png', 'webp', 'gif',
                            'pdf', 'doc', 'docx',
                            'xls', 'xlsx', 'ppt', 'pptx',
                            'txt', 'csv', 'zip', 'rar',
                            'mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav'
                        ].includes(extension);

                        if (mediaType === 'image' && !isImageValid) {
                            const message = 'Please select JPG, JPEG, PNG, WEBP, or GIF image.';
                            setError(errorEl, message);
                            showUploadError('Invalid file', message);
                            input.value = '';
                            return false;
                        }

                        if (mediaType === 'document' && !isDocValid) {
                            const message = 'Please select PDF, DOC, or DOCX file.';
                            setError(errorEl, message);
                            showUploadError('Invalid file', message);
                            input.value = '';
                            return false;
                        }

                        if (mediaType === 'document-any' && !isGenericMediaValid) {
                            const message = 'Unsupported file type. Please upload image, document, archive, audio, or video file.';
                            setError(errorEl, message);
                            showUploadError('Invalid file', message);
                            input.value = '';
                            return false;
                        }

                        if (maxSize > 0 && file.size > maxSize) {
                            const message = mediaType === 'image'
                                ? 'Image size must be 2 MB or smaller.'
                                : (mediaType === 'document-any'
                                    ? 'File size must be 10 MB or smaller.'
                                    : 'File size must be 3 MB or smaller.');
                            setError(errorEl, message);
                            showUploadError('File too large', message);
                            input.value = '';
                            return false;
                        }

                        setError(errorEl, '');
                        return true;
                    }

                    if (window.jQuery && jQuery.fn && typeof jQuery.fn.validate === 'function') {
                        const $ = window.jQuery;

                        if (productForm) {
                            $(productForm).validate({
                                ignore: [],
                                errorElement: 'span',
                                errorClass: 'text-xs text-red-600 block mt-1',
                                focusInvalid: false,
                                onkeyup: false,
                                onclick: false,
                                onfocusout: function (element) {
                                    this.element(element);
                                },
                                invalidHandler: function () {
                                    clearInlineProductErrors();
                                },
                                highlight: function (element) {
                                    $(element).addClass('border-red-500 ring-1 ring-red-300');
                                },
                                unhighlight: function (element) {
                                    $(element).removeClass('border-red-500 ring-1 ring-red-300');
                                },
                                errorPlacement: function (error, element) {
                                    const fieldName = element.attr('name');
                                    if (fieldName) {
                                        setInlineProductFieldError(fieldName, error.text());
                                        return;
                                    }
                                    error.insertAfter(element);
                                },
                                rules: {
                                    category_id: { required: true },
                                    name: { required: true, minlength: 3, maxlength: 191 },
                                    unit: { required: true },
                                    price: { required: true, number: true, min: 0 },
                                    gst: { required: true },
                                    product_image: { extension: 'jpg|jpeg|png|webp' },
                                    description: { maxlength: 1000 },
                                    note: { maxlength: 2000 },
                                },
                                messages: {
                                    category_id: { required: 'Category is required.' },
                                    name: {
                                        required: 'Product name is required.',
                                        minlength: 'Product name must be at least 3 characters.',
                                        maxlength: 'Product name must not exceed 191 characters.'
                                    },
                                    unit: { required: 'Unit is required.' },
                                    price: {
                                        required: 'Unit price is required.',
                                        number: 'Unit price must be a number.',
                                        min: 'Unit price cannot be negative.'
                                    },
                                    gst: { required: 'GST is required.' },
                                    product_image: { extension: 'Only JPG, JPEG, PNG, or WEBP images are allowed.' },
                                    description: { maxlength: 'Description must not exceed 1000 characters.' },
                                    note: { maxlength: 'Note must not exceed 2000 characters.' },
                                },
                                submitHandler: function (form) {
                                    clearInlineProductErrors();
                                    form.submit();
                                }
                            });

                            productForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                            productForm.querySelectorAll('select, input, textarea').forEach(function (field) {
                                field.addEventListener('input', function () {
                                    setInlineProductFieldError(field.name, '');
                                });
                                field.addEventListener('change', function () {
                                    setInlineProductFieldError(field.name, '');
                                });
                            });
                        }

                        if (approveForm) {
                            $(approveForm).validate({
                                ignore: [],
                                errorClass: 'text-xs text-red-600 mt-2 block',
                                errorPlacement: placeFieldError,
                                highlight: function (element) {
                                    $(element).addClass('border-red-500 ring-1 ring-red-300');
                                },
                                unhighlight: function (element) {
                                    $(element).removeClass('border-red-500 ring-1 ring-red-300');
                                },
                                rules: {
                                    project_name: { required: true, minlength: 3, maxlength: 191 },
                                    project_description: { maxlength: 1000 },
                                    project_start_date: { required: false, dateISO: true },
                                    project_end_date: { required: false, dateISO: true },
                                    project_status: { required: true },
                                },
                                messages: {
                                    project_name: {
                                        required: 'Project name is required.',
                                        minlength: 'Project name must be at least 3 characters.',
                                        maxlength: 'Project name must not exceed 191 characters.',
                                    },
                                    project_description: {
                                        maxlength: 'Description must not exceed 1000 characters.',
                                    },
                                    project_start_date: {
                                        dateISO: 'Please enter a valid start date.',
                                    },
                                    project_end_date: {
                                        dateISO: 'Please enter a valid end date.',
                                    },
                                    project_status: {
                                        required: 'Project status is required.',
                                    },
                                },
                                invalidHandler: function (event, validator) {
                                    showUploadError('Validation Error', validator?.errorList?.[0]?.message || 'Please check the approval details.');
                                }
                            });

                            approveForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                        }

                        // if (documentForm) {
                        //     $(documentForm).validate({
                        //         ignore: [],
                        //         errorClass: 'text-xs text-red-600 mt-2 block',
                        //         errorPlacement: placeFieldError,
                        //         highlight: function (element) {
                        //             $(element).addClass('border-red-500 ring-1 ring-red-300');
                        //         },
                        //         unhighlight: function (element) {
                        //             $(element).removeClass('border-red-500 ring-1 ring-red-300');
                        //         },
                        //         rules: {
                        //             document_file: { required: true },
                        //             document_name: { maxlength: 255 },
                        //         },
                        //         messages: {
                        //             document_file: {
                        //                 required: 'Please select a file to upload.',
                        //             },
                        //             document_name: {
                        //                 maxlength: 'Document name must not exceed 255 characters.',
                        //             },
                        //         },
                        //         invalidHandler: function (event, validator) {
                        //             showUploadError('Validation Error', validator?.errorList?.[0]?.message || 'Please check the document details.');
                        //         }
                        //     });

                        //     documentForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                        // }
                    }
                    if (materialForm) {
                        materialForm.addEventListener('submit', function (event) {
                            const component = materialForm.closest('[x-data]');
                            const state = component && component.__x ? component.__x.$data : null;
                            if (!state) {
                                return;
                            }

                            const isValid = state.submitForm(materialForm);
                            if (isValid === false || state.errorMessage) {
                                event.preventDefault();
                                return;
                            }
                        });
                    }
                    if (window.jQuery && documentForm) {
                        const $documentForm = window.jQuery(documentForm);

                        $documentForm.validate({
                            ignore: [],
                            errorElement: 'span',
                            errorClass: 'text-red-600 text-xs block mt-1',
                            rules: {
                                document_file: {
                                    required: true
                                },
                                document_name: {
                                    maxlength: 191,
                                    normalizer: function(value) {
                                        return window.jQuery.trim(value);
                                    }
                                }
                            },
                            messages: {
                                document_file: {
                                    required: 'Please select a file to upload.'
                                },
                                document_name: {
                                    maxlength: 'Document name must not exceed 191 characters.'
                                }
                            },
                            errorPlacement: function(error, element) {
                                if (element.attr('name') === 'document_file') {
                                    error.insertAfter(window.jQuery('#document-file-error'));
                                    return;
                                }

                                if (element.attr('name') === 'document_name') {
                                    error.insertAfter(window.jQuery('#document-name-error'));
                                    return;
                                }

                                error.insertAfter(element);
                            },
                            submitHandler: function(form) {
                                if (!validateMediaFile(documentInput, documentError)) {
                                    return false;
                                }

                                form.submit();
                            }
                        });
                    }
                    if (surveyInput) {
                        surveyInput.addEventListener('change', function() {
                            validateMediaFile(surveyInput, surveyError);
                            updateName(surveyInput, surveyName);
                        });
                    }

                    if (quoteInput) {
                        quoteInput.addEventListener('change', function() {
                            validateMediaFile(quoteInput, quoteError);
                            updateName(quoteInput, quoteName);
                        });
                    }

                    if (documentInput) {
                        documentInput.addEventListener('change', function() {
                            validateMediaFile(documentInput, documentError);
                            updateName(documentInput, documentName);
                        });
                    }

                    document.querySelectorAll('form.swal-form').forEach((form) => {
                        form.addEventListener('submit', function (event) {
                            if (form.id === 'survey-upload-form' && !validateMediaFile(surveyInput, surveyError)) {
                                event.preventDefault();
                                return;
                            }

                            if (form.id === 'quotation-upload-form' && !validateMediaFile(quoteInput, quoteError)) {
                                event.preventDefault();
                                return;
                            }

                            if (form.id === 'document-upload-form' && !validateMediaFile(documentInput, documentError)) {
                                event.preventDefault();
                                return;
                            }
                        });
                    });

                    // Convert activity timestamps to user's local time in-browser
                    activityTimes.forEach((el) => {
                        const iso = el.getAttribute('datetime');
                        if (!iso) return;
                        const date = new Date(iso);
                        if (isNaN(date.getTime())) return;
                        el.textContent = date.toLocaleString(undefined, {
                            month: 'short',
                            day: '2-digit',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                    });
                });
            </script>

    {{-- Log Contact Modal --}}
    <div id="log-contact-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-[1px]" onclick="closeLogContactModal()"></div>
        <div class="relative min-h-full flex items-center justify-center p-4">
            <div class="w-full max-w-lg bg-white shadow-2xl border border-slate-200 flex flex-col overflow-hidden">
                <div class="flex items-start justify-between p-5 border-b border-slate-100 bg-gradient-to-r from-violet-50 to-white">
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Log Contact</h3>
                        <p class="mt-0.5 text-sm text-slate-500">Record an interaction with <span class="font-medium">{{ $lead->client_name }}</span></p>
                    </div>
                    <button type="button" onclick="closeLogContactModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>

                <form action="{{ route('leads.contact.log', $lead) }}" method="POST">
                    @csrf
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="lc-type" value="Contact Type" />
                                <select id="lc-type" name="type" required class="mt-1 block w-full border-gray-300 focus:border-violet-500 focus:ring-violet-500 shadow-sm text-sm">
                                    <option value="call">📞 Phone Call</option>
                                    <option value="email">✉️ Email</option>
                                    <option value="meeting">🤝 Meeting</option>
                                    <option value="site_visit">📍 Site Visit</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="lc-occurred-at" value="Date & Time" />
                                <x-text-input id="lc-occurred-at" name="occurred_at" type="datetime-local" required class="mt-1 block w-full text-sm" />
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="lc-description" value="Notes" />
                                <x-speech-to-text target="lc-description" />
                            </div>
                            <textarea id="lc-description" name="description" rows="3" placeholder="What was discussed or agreed upon..." class="mt-1 block w-full border-gray-300 focus:border-violet-500 focus:ring-violet-500 shadow-sm text-sm resize-none"></textarea>
                        </div>

                    </div>

                    <div class="px-5 py-4 border-t border-slate-100 bg-white flex justify-end gap-2">
                        <button type="button" onclick="closeLogContactModal()" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-xs font-semibold text-slate-700 uppercase tracking-widest hover:bg-slate-50 transition">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-violet-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-violet-700 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                            Save Log
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        (function () {
            function closeLogContactModal() {
                document.getElementById('log-contact-modal').classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
            window.closeLogContactModal = closeLogContactModal;

            // Pre-fill current datetime on open
            document.querySelector('[onclick*="log-contact-modal"]')?.addEventListener('click', function () {
                const input = document.getElementById('lc-occurred-at');
                if (input && !input.value) {
                    const now = new Date();
                    now.setSeconds(0, 0);
                    input.value = now.getFullYear() + '-'
                        + String(now.getMonth() + 1).padStart(2, '0') + '-'
                        + String(now.getDate()).padStart(2, '0') + 'T'
                        + String(now.getHours()).padStart(2, '0') + ':'
                        + String(now.getMinutes()).padStart(2, '0');
                }
            });

            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('log-contact-modal');
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeLogContactModal();
                }
            });
        })();
    </script>

    @if ($canCreateCompanyFromLead && ! $lead->createdCompany)
        <div id="convert-to-company-modal" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
            <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity" onclick="closeConvertToCompanyModal()"></div>

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
                        <button type="button" onclick="closeConvertToCompanyModal()" class="text-slate-400 hover:text-white transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <form id="show-convert-to-company-form" method="POST" action="{{ route('leads.convert-company', $lead) }}" novalidate>
                        @csrf
                        <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                            <div class="rounded-xl border border-blue-100 bg-blue-50/80 p-3 text-xs text-blue-900">
                                <p class="font-semibold">Onboarding Integration</p>
                                <p class="mt-0.5 text-blue-700">A new organization will be created for <strong>{{ $lead->client_name }}</strong>. Its onboarding flow will start on Step 4 (subscription & license selection) upon first login.</p>
                            </div>

                            <div>
                                <x-input-label for="conv_company_name" value="Company Name *" />
                                <x-text-input id="conv_company_name" name="company_name" type="text" class="mt-1 block w-full text-sm" value="{{ old('company_name', $lead->client_name) }}" required />
                                <p id="conv_company_name_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="conv_email" value="Company Admin Email *" />
                                <x-text-input id="conv_email" name="email" type="email" class="mt-1 block w-full text-sm" value="{{ old('email', $lead->email) }}" required placeholder="admin@example.com" />
                                <p class="mt-1 text-[11px] text-slate-500">Provide the email address for this company's administrator account.</p>
                                <p id="conv_email_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="conv_phone_number" value="Mobile / Contact Number (10 digits) *" />
                                <x-text-input id="conv_phone_number" name="phone_number" type="tel" maxlength="10" class="mt-1 block w-full text-sm" value="{{ old('phone_number', preg_replace('/\D+/', '', (string) ($lead->mobile ?: $lead->phone))) }}" required placeholder="e.g. 9876543210" />
                                <p id="conv_phone_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                            </div>

                            <div>
                                <x-input-label for="conv_admin_name" value="Admin Contact Person (Optional)" />
                                <x-text-input id="conv_admin_name" name="admin_name" type="text" class="mt-1 block w-full text-sm" value="{{ old('admin_name', $lead->client_name . ' Admin') }}" />
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <x-input-label for="conv_password" value="Initial Password *" />
                                    <div class="relative mt-1">
                                        <x-text-input id="conv_password" name="password" type="password" class="block w-full pr-10 text-sm" placeholder="At least 8 characters" required />
                                        <button type="button" onclick="toggleShowPasswordVisibility('conv_password', 'conv_password_eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none" title="Show/Hide password">
                                            <svg id="conv_password_eye" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="conv_password_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                                </div>
                                <div>
                                    <x-input-label for="conv_password_confirmation" value="Confirm Password *" />
                                    <div class="relative mt-1">
                                        <x-text-input id="conv_password_confirmation" name="password_confirmation" type="password" class="block w-full pr-10 text-sm" placeholder="Repeat password" required />
                                        <button type="button" onclick="toggleShowPasswordVisibility('conv_password_confirmation', 'conv_password_confirm_eye')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 focus:outline-none" title="Show/Hide password">
                                            <svg id="conv_password_confirm_eye" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </button>
                                    </div>
                                    <p id="conv_password_confirm_error" class="hidden mt-1 text-xs text-red-600 font-medium"></p>
                                </div>
                            </div>
                        </div>

                        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50 flex justify-end gap-2">
                            <button type="button" onclick="closeConvertToCompanyModal()" class="inline-flex items-center px-4 py-2 bg-white border border-slate-300 text-xs font-semibold text-slate-700 uppercase tracking-widest hover:bg-slate-50 transition">
                                Cancel
                            </button>
                            <button type="submit" id="show-conv-submit-btn" class="inline-flex items-center gap-1.5 px-4 py-2 bg-emerald-600 border border-transparent text-xs font-semibold text-white uppercase tracking-widest hover:bg-emerald-700 transition">
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
            function toggleShowPasswordVisibility(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                if (!input) return;
                const isPassword = input.type === 'password';
                input.type = isPassword ? 'text' : 'password';
                if (icon) {
                    if (isPassword) {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />';
                    } else {
                        icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
                    }
                }
            }
            window.toggleShowPasswordVisibility = toggleShowPasswordVisibility;

            function setShowFieldError(fieldId, errorId, message) {
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

            function resetShowConversionErrors() {
                setShowFieldError('conv_company_name', 'conv_company_name_error', '');
                setShowFieldError('conv_email', 'conv_email_error', '');
                setShowFieldError('conv_phone_number', 'conv_phone_error', '');
                setShowFieldError('conv_password', 'conv_password_error', '');
                setShowFieldError('conv_password_confirmation', 'conv_password_confirm_error', '');
            }

            function closeConvertToCompanyModal() {
                const modal = document.getElementById('convert-to-company-modal');
                if (modal) {
                    modal.classList.add('hidden');
                    document.body.classList.remove('overflow-hidden');
                    resetShowConversionErrors();
                }
            }
            window.closeConvertToCompanyModal = closeConvertToCompanyModal;

            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('show-convert-to-company-form');
                if (!form) return;

                form.addEventListener('submit', async function (e) {
                    e.preventDefault();
                    resetShowConversionErrors();
                    let isValid = true;

                    const nameInput = document.getElementById('conv_company_name');
                    const companyName = nameInput ? nameInput.value.trim() : '';
                    if (!companyName || companyName.length < 2) {
                        setShowFieldError('conv_company_name', 'conv_company_name_error', 'Company name is required (at least 2 characters).');
                        isValid = false;
                    }

                    const emailInput = document.getElementById('conv_email');
                    const email = emailInput ? emailInput.value.trim() : '';
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!email) {
                        setShowFieldError('conv_email', 'conv_email_error', 'Company admin email is required.');
                        isValid = false;
                    } else if (!emailRegex.test(email)) {
                        setShowFieldError('conv_email', 'conv_email_error', 'Please enter a valid email address.');
                        isValid = false;
                    }

                    const phoneInput = document.getElementById('conv_phone_number');
                    const rawPhone = phoneInput ? phoneInput.value.replace(/\D+/g, '') : '';
                    if (!rawPhone) {
                        setShowFieldError('conv_phone_number', 'conv_phone_error', 'A 10-digit mobile number is required.');
                        isValid = false;
                    } else if (rawPhone.length !== 10) {
                        setShowFieldError('conv_phone_number', 'conv_phone_error', 'Please enter a valid 10-digit mobile number.');
                        isValid = false;
                    }

                    const passInput = document.getElementById('conv_password');
                    const confirmInput = document.getElementById('conv_password_confirmation');
                    const pass = passInput ? passInput.value : '';
                    const confirmPass = confirmInput ? confirmInput.value : '';
                    if (!pass) {
                        setShowFieldError('conv_password', 'conv_password_error', 'Password is required.');
                        isValid = false;
                    } else if (pass.length < 8) {
                        setShowFieldError('conv_password', 'conv_password_error', 'Password must be at least 8 characters.');
                        isValid = false;
                    }
                    if (!confirmPass) {
                        setShowFieldError('conv_password_confirmation', 'conv_password_confirm_error', 'Please confirm your password.');
                        isValid = false;
                    } else if (pass && pass !== confirmPass) {
                        setShowFieldError('conv_password_confirmation', 'conv_password_confirm_error', 'Passwords do not match.');
                        isValid = false;
                    }

                    if (!isValid) {
                        return false;
                    }

                    const submitBtn = document.getElementById('show-conv-submit-btn');
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
                            closeConvertToCompanyModal();
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

                        // Handle errors
                        const errors = (result && result.errors) || {};
                        let alertMessage = result ? (result.message || 'Company creation failed.') : 'An error occurred while creating company.';

                        if (errors.email && errors.email[0]) {
                            setShowFieldError('conv_email', 'conv_email_error', errors.email[0]);
                        }
                        if (errors.phone_number && errors.phone_number[0]) {
                            setShowFieldError('conv_phone_number', 'conv_phone_error', errors.phone_number[0]);
                        }
                        if (errors.company_name && errors.company_name[0]) {
                            setShowFieldError('conv_company_name', 'conv_company_name_error', errors.company_name[0]);
                        }
                        if (errors.password && errors.password[0]) {
                            setShowFieldError('conv_password', 'conv_password_error', errors.password[0]);
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

            document.addEventListener('keydown', function (e) {
                const modal = document.getElementById('convert-to-company-modal');
                if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                    closeConvertToCompanyModal();
                }
            });
        </script>
    @endif

</x-app-layout>
@if ($errors->any())
<script>
document.addEventListener('DOMContentLoaded', function () {
    const productErrors = @js($errors->getMessages());
    const productFieldMap = {
        category_id: 'lead_product_category_id_error',
        name: 'lead_product_name_error',
        brand: 'lead_product_brand_error',
        model: 'lead_product_model_error',
        unit: 'lead_product_unit_error',
        price: 'lead_product_price_error',
        gst: 'lead_product_gst_error',
        product_image: 'lead_product_image_error',
        description: 'lead_product_description_error',
        note: 'lead_product_note_error',
    };

    let renderedProductInlineErrors = false;

    Object.entries(productFieldMap).forEach(([field, errorId]) => {
        const messages = productErrors[field];
        if (!messages || !messages.length) {
            return;
        }

        const errorEl = document.getElementById(errorId);
        const input = document.querySelector(`#lead-material-product-form [name="${field}"]`);
        if (errorEl) {
            errorEl.textContent = messages[0];
            errorEl.classList.remove('opacity-0');
            errorEl.classList.add('opacity-100');
            renderedProductInlineErrors = true;
        }
        if (input) {
            input.classList.add('border-red-500', 'ring-1', 'ring-red-300', 'focus:border-red-500', 'focus:ring-red-500');
        }
    });

    if (!renderedProductInlineErrors) {
        const hasDocFileError = @js($errors->has('document_file'));
        const message = @js($errors->first('document_file') ?: $errors->first());
        Swal.fire({
            icon: 'error',
            title: hasDocFileError ? 'Upload Failed' : 'Action Failed',
            text: message,
        });
    }
});
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {

    const form = document.getElementById('quotation-upload-form');
    const fileInput = document.getElementById('quotation_file');

    const allowedTypes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ];

    const maxSize = 5 * 1024 * 1024; // 5MB

    form.addEventListener('submit', function (e) {

        const file = fileInput.files[0];

        // ❌ STOP everything first
        let hasError = false;

        // ❌ No file
        if (!file) {
            hasError = true;
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Please select a file'
            });
        }

        // ❌ Invalid type
        else if (!allowedTypes.includes(file.type)) {
            hasError = true;
            Swal.fire({
                icon: 'error',
                title: 'Invalid Format',
                text: 'Only PDF, DOC, DOCX files are allowed'
            });
            fileInput.value = '';
        }

        // ❌ File too large
        else if (file.size > maxSize) {
            hasError = true;
            Swal.fire({
                icon: 'error',
                title: 'File Too Large',
                text: 'Maximum file size is 5MB'
            });
            fileInput.value = '';
        }

        // 🚫 IF ERROR → STOP ALL SUBMIT EVENTS (KEY FIX)
        if (hasError) {
            e.preventDefault();
            e.stopImmediatePropagation(); // ✅ THIS LINE FIXES YOUR ISSUE
            return false;
        }

        // ✅ VALID → show loader
        // Global loader already handles submit state.

    }, true); // ✅ use capture phase

});
</script>
