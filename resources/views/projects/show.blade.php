<x-app-layout>
    <div id="project-show-page">
    @php
        $statusClasses = [
            'on_track' => 'bg-emerald-100 text-emerald-800',
            'at_risk' => 'bg-amber-100 text-amber-800',
            'delayed' => 'bg-rose-100 text-rose-800',
            'completed' => 'bg-sky-100 text-sky-800',
        ];
        $statusColors = [
            'on_track' => '#16a34a',
            'at_risk' => '#d97706',
            'delayed' => '#e11d48',
            'completed' => '#0284c7',
        ];
        $tabLabels = [
            'overview' => 'Overview',
            'drawing' => 'Drawing',
            'material' => 'Material',
            'ready_to_dispatch' => 'Ready to Dispatch',
            'tasks' => 'Tasks',
            // 'installation' => 'Installation',
            // 'completed' => 'Completed',
            'activity' => 'Activity',
            'comments' => 'Comments',


        ];




        $daysRemaining = $project->end_date ? now()->startOfDay()->diffInDays($project->end_date, false) : null;
        $showCompletedAmcPrompt = session('show_completed_amc_prompt') === true;
        $authUser = auth()->user();
        $canEditProject = $authUser && $authUser->hasPermission('projects.edit');
        $financialToneClasses = match ($financialSummary['status'] ?? 'neutral') {
            'positive' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
            'negative' => 'border-rose-200 bg-rose-50 text-rose-700',
            default => 'border-amber-200 bg-amber-50 text-amber-700',
        };
        $financialBadgeClasses = match ($financialSummary['status'] ?? 'neutral') {
            'positive' => 'bg-emerald-100 text-emerald-700',
            'negative' => 'bg-rose-100 text-rose-700',
            default => 'bg-amber-100 text-amber-700',
        };
        $financialLabel = match ($financialSummary['status'] ?? 'neutral') {
            'positive' => 'Profit',
            'negative' => 'Loss',
            default => 'Neutral',
        };


    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <h2 class="min-w-0 whitespace-nowrap overflow-hidden text-ellipsis text-sm font-semibold leading-tight text-gray-800 sm:text-xl">
                {{ __('Project Details') }}: {{ $project->name }}
            </h2>
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                @if ($canEditProject)
                    <a href="{{ route('projects.edit', $project) }}"
                        class="inline-flex items-center gap-1.5 border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 shadow-sm transition hover:bg-indigo-100">
                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                    </a>
                @endif
                <span class="whitespace-nowrap px-2.5 py-1 text-[11px] font-bold sm:px-3 sm:text-sm" style="background-color: {{ ($statusColors[$project->status] ?? '#64748b') }}22; color: {{ $statusColors[$project->status] ?? '#64748b' }}">
                    {{ $statusOptions[$project->status] ?? str($project->status)->headline() }}
                </span>
                <span class="whitespace-nowrap bg-indigo-100 px-2.5 py-1 text-[11px] font-bold text-indigo-700 sm:px-3 sm:text-sm">Stage {{ $project->stage_label }}</span>
                <span
                x-data="{ tab: '{{ $activeTab }}' }"
                x-show="tab === 'drawing' && {{ $acceptedDrawing ? 'true' : 'false' }}"
                class="whitespace-nowrap px-2.5 py-1 text-[11px] font-bold sm:px-3 sm:text-sm {{ $statusClasses['completed'] }}"
            >
                Accepted
            </span>
            </div>
        </div>
    </x-slot>

    <div id="project-show-page" class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm">
                <div class="p-6 text-gray-900" x-data="{
                    tab: '{{ $activeTab }}',
                    projectUnits: @js(($projectUnits ?? collect())->values()->all()),
                    projectGstRates: @js(($projectGstRates ?? collect())->values()->all()),
                    init() {
                        this.syncBodyScrollLock();
                    },
                    isChallanModalVisible() {
                        return this.tab === 'ready_to_dispatch'
                            && this.challanModalOpen
                            && this.challanModalId !== null;
                    },
                    hasOpenOverlay() {
                        return this.attachmentPreviewOpen
                            || this.materialEditOpen
                            || this.productionEditOpen
                            || this.dispatchChallanModalOpen
                            || this.isChallanModalVisible();
                    },
                    syncBodyScrollLock() {
                        document.body.classList.toggle('overflow-hidden', this.hasOpenOverlay());
                    },
                    clearChallanQueryParam() {
                        const url = new URL(window.location.href);
                        if (!url.searchParams.has('challan')) {
                            return;
                        }

                        url.searchParams.delete('challan');
                        window.history.replaceState({}, '', url.toString());
                    },
                    setTab(nextTab) {
                        this.tab = nextTab;

                        if (nextTab !== 'ready_to_dispatch') {
                            if (this.challanModalOpen) this.closeChallanModal();
                            if (this.dispatchChallanModalOpen) this.closeDispatchChallanModal();
                        }
                    },
                    attachmentPreviewOpen: false,
                    attachmentPreviewUrl: '',
                    attachmentPreviewName: '',
                    isImagePreview() {
                        // Some preview routes don't expose the filename in the URL, so also
                        // inspect the displayed name to decide whether to render an <img>.
                        const url = (this.attachmentPreviewUrl || '').split('?')[0];
                        const name = (this.attachmentPreviewName || '').split('?')[0];
                        const imageRegex = /\.(png|jpe?g|gif|webp|bmp|svg)$/i;
                        return imageRegex.test(url) || imageRegex.test(name);
                    },
                    openAttachmentPreview(url, name) {
                        this.attachmentPreviewUrl = url;
                        this.attachmentPreviewName = name;
                        this.attachmentPreviewOpen = true;
                        this.syncBodyScrollLock();
                    },
                    closeAttachmentPreview() {
                        this.attachmentPreviewOpen = false;
                        this.attachmentPreviewUrl = '';
                        this.attachmentPreviewName = '';
                        this.syncBodyScrollLock();
                    },
                    materialEditOpen: false,
                    materialEdit: { id: null, product_id: '', material_name: '', qty: '', unit: '', rate: '', contractor_price: '', gst: '', note: '', updateUrl: '' },
                    unitOptionsFor(value) {
                        const current = String(value || '').trim();
                        const options = Array.isArray(this.projectUnits) ? [...this.projectUnits] : [];
                        if (current !== '' && !options.includes(current)) {
                            options.push(current);
                        }
                        return options;
                    },
                    gstOptionsFor(value) {
                        const current = Number(value || 0);
                        const options = Array.isArray(this.projectGstRates) ? [...this.projectGstRates] : [];
                        if (!options.some((rate) => Number(rate) === current)) {
                            options.push(current);
                        }
                        if (!options.some((rate) => Number(rate) === 0)) {
                            options.push(0);
                        }
                        return options
                            .filter((rate) => Number.isFinite(Number(rate)))
                            .sort((a, b) => Number(a) - Number(b));
                    },
                    openMaterialEdit(material) {
                        const qtyValue = Number(material?.qty ?? '');
                        const normalizedQty = Number.isFinite(qtyValue) && qtyValue > 0
                            ? String(Number(qtyValue.toFixed(2)))
                            : '';

                        this.materialEdit = {
                            id: material?.id ?? null,
                            product_id: String(material?.product_id ?? ''),
                            material_name: String(material?.material_name ?? ''),
                            qty: normalizedQty,
                            unit: String(material?.unit ?? ''),
                            rate: material?.rate ?? '',
                            contractor_price: material?.contractor_price ?? '',
                            gst: Number(material?.gst ?? 0),
                            note: String(material?.note ?? ''),
                            updateUrl: String(material?.updateUrl ?? ''),
                        };
                        this.materialEditOpen = true;
                        this.syncBodyScrollLock();
                        this.$nextTick(() => {
                            const modal = document.getElementById('edit-material-form');
                            if (modal) {
                                modal.querySelectorAll('select').forEach(select => {
                                    if (typeof select._syncCustomSelect === 'function') {
                                        select._syncCustomSelect();
                                    } else {
                                        select.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                });
                            }
                        });
                    },
                    closeMaterialEdit() {
                        this.materialEditOpen = false;
                        this.materialEdit = { id: null, product_id: '', material_name: '', qty: '', unit: '', rate: '', contractor_price: '', gst: '', note: '', updateUrl: '' };
                        this.syncBodyScrollLock();
                    },
                    productionEditOpen: false,
                    productionEdit: { id: null, title: '', note: '', updateUrl: '' },
                    openProductionEdit(production) {
                        this.productionEdit = production;
                        this.productionEditOpen = true;
                        this.syncBodyScrollLock();
                    },
                    closeProductionEdit() {
                        this.productionEditOpen = false;
                        this.productionEdit = { id: null, title: '', note: '', updateUrl: '' };
                        this.syncBodyScrollLock();
                    },
                    challanModalOpen: {{ $selectedChallanId !== null ? 'true' : 'false' }},
                    challanModalId: {{ $selectedChallanId !== null ? $selectedChallanId : 'null' }},
                    openChallanModal(id) {
                        this.challanModalId = id;
                        this.challanModalOpen = true;
                        this.syncBodyScrollLock();
                    },
                    closeChallanModal() {
                        this.challanModalOpen = false;
                        this.challanModalId = null;
                        this.clearChallanQueryParam();
                        this.syncBodyScrollLock();
                    },
                    dispatchChallanModalOpen: false,
                    openDispatchChallanModal() {
                        const anyChecked = Array.from(document.querySelectorAll('.rtd-item-checkbox')).some(cb => cb.checked);
                        if (!anyChecked) {
                            if (window.Swal) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'No items selected',
                                    text: 'Please select at least one material item to dispatch.',
                                    confirmButtonColor: '#4f46e5',
                                });
                            } else {
                                alert('Please select at least one material item to dispatch.');
                            }
                            return;
                        }
                        this.dispatchChallanModalOpen = true;
                        this.syncBodyScrollLock();
                    },
                    closeDispatchChallanModal() {
                        this.dispatchChallanModalOpen = false;
                        this.syncBodyScrollLock();
                    },
                    completedDetailsOpen: false,
                }" x-init="window.projectShowPage = $data"
                data-project-name="{{ $project->name }}"
                data-completed-amc-url="{{ route('projects.completed-amc.store', $project) }}"
                data-amc-count="{{ $project->amcs_count ?? 0 }}">
                    <div class="border-b border-gray-200 mb-6">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto">
                            @foreach($tabLabels as $tabKey => $tabLabel)
                                <button @click="setTab('{{ $tabKey }}')" :class="tab === '{{ $tabKey }}' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">{{ $tabLabel }}</button>
                            @endforeach
                        </nav>
                    </div>

                    <div x-show="tab === 'overview'" x-cloak>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h3 class="text-lg font-bold mb-4">Project Overview</h3>
                                <div class="space-y-3 text-sm">
                                    <p><span class="font-semibold">Project Name:</span> {{ $project->name }}</p>
                                    <p><span class="font-semibold">Client:</span> {{ $project->client_name }}</p>
                                    <p><span class="font-semibold">Reference Number:</span> {{ $project->reference_no }}</p>
                                    <p><span class="font-semibold">Linked Lead:</span> {{ $project->lead?->client_name ?? 'Not linked' }}</p>
                                    <p><span class="font-semibold">Assigned Contractor:</span> {{ $project->contractor?->name ?? 'Not assigned' }}</p>
                                    <p><span class="font-semibold">Current Stage:</span> {{ $project->stage_label }}</p>
                                    <p><span class="font-semibold">Material Total:</span> ₹{{ number_format((float) ($financialSummary['material_total'] ?? 0), 2) }}</p>
                                    <p><span class="font-semibold">Material Count:</span> {{ (int) ($financialSummary['material_count'] ?? 0) }}</p>
                                    <p><span class="font-semibold">Project Value:</span> {{ $projectValue !== null ? '₹'.number_format($projectValue, 2) : 'Not available' }}</p>
                                    @foreach(collect($project->company?->normalizedProjectCustomFields() ?? []) as $field)
                                        @php
                                            $value = data_get($project->custom_fields ?? [], $field['key']);
                                            if ($field['type'] === 'checkbox') {
                                                $displayValue = filter_var($value, FILTER_VALIDATE_BOOL) ? 'Yes' : 'No';
                                            } elseif (is_array($value)) {
                                                $displayValue = implode(', ', array_filter(array_map('trim', $value)));
                                            } elseif ($value === null || $value === '') {
                                                $displayValue = 'N/A';
                                            } else {
                                                $displayValue = (string) $value;
                                            }
                                        @endphp
                                        <p><span class="font-semibold">{{ $field['label'] }}:</span> {{ $displayValue }}</p>
                                    @endforeach
                                </div>

                                <div class="mt-6 border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <h4 class="text-base font-semibold text-slate-900">Contractor</h4>
                                            <p class="text-xs text-slate-500">Select a saved contractor and set the commission on this project.</p>
                                        </div>
                                    </div>

                                    @if(auth()->user()?->hasPermission('projects.edit'))
                                        <form method="POST" action="{{ route('projects.contractor.update', $project) }}" class="mt-4 space-y-3">
                                            @csrf
                                            @method('PATCH')
                                            <div class="grid gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)]">
                                                <div>
                                                    <x-input-label for="contractor_id" value="Contractor" />
                                                    <select name="contractor_id" id="contractor_id" class="mt-1 block w-full border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">Select contractor</option>
                                                        @foreach($contractors as $contractor)
                                                            <option value="{{ $contractor->id }}" @selected((int) old('contractor_id', $project->contractor_id) === (int) $contractor->id)>
                                                                {{ $contractor->name }}{{ $contractor->phone_number ? ' - '.$contractor->phone_number : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                    <x-input-error :messages="$errors->get('contractor_id')" class="mt-2" />
                                                    <p id="contractor_id_error" class="hidden mt-1 text-xs text-rose-600"></p>
                                                </div>
                                                <div>
                                                    <x-input-label for="commission_type" value="Commission Type" />
                                                    <select name="commission_type" id="commission_type" class="mt-1 block w-full border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        <option value="">Select type</option>
                                                        <option value="fixed" @selected(old('commission_type', $project->commission_type) === 'fixed')>Fixed</option>
                                                        <option value="percentage" @selected(old('commission_type', $project->commission_type) === 'percentage')>Percentage</option>
                                                    </select>
                                                    <x-input-error :messages="$errors->get('commission_type')" class="mt-2" />
                                                    <p id="commission_type_error" class="hidden mt-1 text-xs text-rose-600"></p>
                                                </div>
                                                <div>
                                                    <x-input-label for="commission_value" value="Commission Value" />
                                                    <x-text-input
                                                        id="commission_value"
                                                        name="commission_value"
                                                        type="number"
                                                        min="0.01"
                                                        step="0.01"
                                                        class="mt-1 block w-full"
                                                        value="{{ old('commission_value', $project->commission_value) }}"
                                                        placeholder="0.00"
                                                    />
                                                    <x-input-error :messages="$errors->get('commission_value')" class="mt-2" />
                                                    <p id="commission_value_error" class="hidden mt-1 text-xs text-rose-600"></p>
                                                </div>
                                            </div>
                                            <p class="text-xs text-slate-500">Commission is stored on this project, so it can change from one project to another even for the same contractor.</p>
                                            <div class="flex justify-end">
                                                <x-primary-button type="submit" class="justify-center">Save Contractor & Commission</x-primary-button>
                                            </div>
                                        </form>
                                    @endif

                                    @if($project->contractor)
                                        <div class="mt-4 grid gap-2 text-xs text-slate-600 sm:grid-cols-2">
                                            <p><span class="font-semibold text-slate-800">Phone:</span> {{ $project->contractor->phone_number ?: 'N/A' }}</p>
                                            <p><span class="font-semibold text-slate-800">Email:</span> {{ $project->contractor->email ?: 'N/A' }}</p>
                                            <p><span class="font-semibold text-slate-800">Referral:</span> {{ $project->contractor->referral_name ?: 'N/A' }}</p>
                                            <p><span class="font-semibold text-slate-800">Project Commission:</span>
                                                @if($contractorCommissionSummary['has_commission'])
                                                    {{ number_format((float) $contractorCommissionSummary['value'], 2) }}{{ $contractorCommissionSummary['type'] === 'percentage' ? '%' : '' }} ({{ ucfirst($contractorCommissionSummary['type']) }})
                                                @else
                                                    Not set
                                                @endif
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold mb-4">Team & Timeline</h3>
                                <div class="space-y-3 text-sm">
                                    <p><span class="font-semibold">Project Manager:</span> {{ $project->manager?->name ?? 'Unassigned' }}</p>
                                    <p><span class="font-semibold">Team Members:</span> {{ $teamMembers->pluck('name')->join(', ') ?: 'No team members assigned' }}</p>
                                    <p><span class="font-semibold">Start Date:</span> {{ $project->start_date?->format('d M Y') ?? 'N/A' }}</p>
                                    <p><span class="font-semibold">Deadline:</span> {{ $project->end_date?->format('d M Y') ?? 'N/A' }}</p>
                                    <p><span class="font-semibold">Days Remaining / Overdue:</span>
                                        @if($daysRemaining === null)
                                            N/A
                                        @elseif($daysRemaining < 0)
                                            <span class="text-rose-600 font-semibold">{{ abs($daysRemaining) }} days overdue</span>
                                        @else
                                            {{ $daysRemaining }} days remaining
                                        @endif
                                    </p>
                                    <p><span class="font-semibold">Completion Percentage:</span> {{ $project->completion_percentage }}%</p>
                                </div>

                                <div class="mt-6 overflow-hidden border shadow-sm {{ $financialToneClasses }}">
                                    <div class="bg-white/50 p-5 backdrop-blur-sm">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-xs font-bold uppercase tracking-[0.25em]">Financial Overview</p>
                                                <h4 class="mt-2 text-2xl font-extrabold leading-tight">
                                                    @if(($financialSummary['net_value'] ?? null) !== null)
                                                        ₹{{ number_format((float) $financialSummary['net_value'], 2) }}
                                                    @else
                                                        Not available
                                                    @endif
                                                </h4>
                                                <p class="mt-2 text-sm font-medium">
                                                    {{ $financialLabel }}
                                                    @if(($financialSummary['net_value'] ?? null) !== null)
                                                        after subtracting material total from project value.
                                                    @else
                                                        Add an accepted quotation to compare against material cost.
                                                    @endif
                                                </p>
                                            </div>
                                            <span class="inline-flex px-3 py-1 text-xs font-bold uppercase tracking-wider {{ $financialBadgeClasses }}">
                                                {{ $financialLabel }}
                                            </span>
                                        </div>

                                        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                            <div class="bg-white/80 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Project Value</p>
                                                <p class="mt-2 text-lg font-bold text-slate-900">
                                                    {{ $projectValue !== null ? '₹'.number_format((float) $projectValue, 2) : 'Not available' }}
                                                </p>
                                            </div>
                                            <div class="bg-white/80 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Material Total</p>
                                                <p class="mt-2 text-lg font-bold text-slate-900">₹{{ number_format((float) ($financialSummary['material_total'] ?? 0), 2) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">{{ (int) ($financialSummary['material_count'] ?? 0) }} item(s)</p>
                                            </div>
                                            <div class="bg-white/80 p-4">
                                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contractor Basis</p>
                                                <p class="mt-2 text-lg font-bold text-slate-900">₹{{ number_format((float) ($financialSummary['contractor_total'] ?? 0), 2) }}</p>
                                                <p class="mt-1 text-xs text-slate-500">Uses contractor price where available</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'drawing'" x-cloak>

    <!-- Upload Section -->
    <form id="drawing-upload-form" action="{{ route('projects.drawings.store', $project) }}" method="POST" enctype="multipart/form-data" class="mb-6 p-3 border border-gray-200 bg-white">
            @csrf
            <input type="hidden" name="tab" value="drawing">

            <div class="grid grid-cols-12 gap-4">
                <!-- Labels row -->
                <div class="col-span-12 md:col-span-7 text-xs font-medium text-gray-600">Drawing Files</div>
                <div class="col-span-12 md:col-span-3 text-xs font-medium text-gray-600">Version</div>
                <div class="col-span-12 md:col-span-2 text-xs font-medium text-gray-600">Actions</div>

                <!-- Inputs row -->
                <div class="col-span-12 md:col-span-7 flex items-start">
                    <div class="w-full">
                        <label for="drawing_files"
                            class="h-10 flex flex-1 items-center justify-between px-3 border border-dashed border-indigo-300 bg-indigo-50 text-sm text-indigo-700 cursor-pointer hover:bg-indigo-100 transition">
                            <span class="truncate">Choose files</span>
                            <span class="text-xs font-bold text-indigo-600 ml-2">Browse</span>
                        </label>
                        <input id="drawing_files" type="file" name="drawing_files[]" multiple
                            class="hidden" data-max-size="52428800"
                            accept=".jpg,.jpeg,.png,.pdf,.dwg,.dxf,image/jpeg,image/png,application/pdf">
                        <div class="mt-1">
                            <p id="drawing-files-error" class="hidden text-xs leading-4 text-rose-600 m-0"></p>
                        </div>
                        <div class="mt-1 space-y-1">
                            <p id="drawing-files-file-name" class="hidden text-xs leading-4 text-gray-600 m-0">No files selected.</p>
                            <p class="text-xs leading-4 text-gray-500 m-0">Allowed: JPG, JPEG, PNG, PDF, DWG, DXF. Max 50 MB total per file.</p>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-3 flex items-start">
                    <input type="text"
                        value="{{ $nextDrawingVersion }}"
                        readonly
                        class="h-10 w-full border border-gray-300 bg-gray-100 text-center text-sm font-medium">
                </div>

                <div class="col-span-12 md:col-span-2 flex items-start gap-2">
                    <a href="{{ route('projects.drawings.download', $project) }}"
                        data-has-drawings="{{ $project->drawings->isNotEmpty() ? '1' : '0' }}"
                        class="h-10 w-10 flex items-center justify-center border border-gray-300 bg-gray-100 hover:bg-gray-200 transition"
                        title="Download">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v10m0 0l-3-3m3 3l3-3M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                        </svg>
                    </a>
                    <x-primary-button class="h-10 w-full flex justify-center text-xs font-bold uppercase tracking-widest">
                        Upload
                    </x-primary-button>
                </div>
            </div>

            @php
                $drawingUploadError = $errors->first('drawing_files')
                    ?: $errors->first('drawing_files.*');
            @endphp
            <p class="mt-3 text-xs text-rose-600 {{ $drawingUploadError ? '' : 'hidden' }}">{{ $drawingUploadError }}</p>
    </form>

    <!-- Drawings List -->
    @php
        $drawingsToShow = $project->drawings->sortByDesc('created_at');
    @endphp

    @if($drawingsToShow->isEmpty())
        <p class="text-center py-10 text-gray-500 italic">No drawings uploaded yet.</p>
    @else
        <div class="overflow-hidden bg-white shadow sm:border border-gray-200">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">

                    <!-- Header -->
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white">File Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white">Version</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white">Added</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase text-white">Action</th>
                        </tr>
                    </thead>

                    <!-- Body -->
                    <tbody class="bg-white divide-y divide-gray-100">
                        @foreach($drawingsToShow as $drawing)
                            <tr class="hover:bg-gray-50">

                                <!-- File Name -->
                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                    {{ $drawing->file_name }}
                                </td>

                                <!-- Version -->
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    {{ $drawing->version }}
                                </td>
                                <!-- Date -->
                                <td class="px-4 py-3 text-sm text-gray-700">
                                    <time class="local-time" datetime="{{ $drawing->created_at?->toIso8601String() }}">
                                        {{ $drawing->created_at->format('d M Y, h:i A') }}
                                    </time>
                                </td>

                                <!-- View -->
                                <td class="px-4 py-3 text-right">
                                    <button type="button"
                                        @click="openAttachmentPreview(@js(route('projects.drawings.view', [$project, $drawing])), @js($drawing->file_name))"
                                        class="inline-flex items-center justify-center w-8 h-8 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                        title="View">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">
                                            <path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                        </svg>
                                    </button>
                                </td>

                            </tr>
                        @endforeach
                    </tbody>

                </table>
            </div>
        </div>
    @endif
</div>

                    {{-- <div x-show="tab === 'drawing'" x-cloak>
                        <div class="mb-6 bg-gray-50 p-4 border border-gray-200">
                            <form id="drawing-upload-form" action="{{ route('projects.drawings.store', $project) }}" method="POST" enctype="multipart/form-data" class="border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm">
                                @csrf
                                <input type="hidden" name="tab" value="drawing">
                                <input type="hidden" name="drawing_status" value="pending">

                                <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                                    <div class="w-full lg:flex-[1.8]">
                                        <label for="drawing_files" class="block text-sm font-semibold text-slate-700 mb-2">
                                            Drawing Files
                                        </label>
                                        <label for="drawing_files"
                                            class="group flex min-h-14 cursor-pointer items-center justify-between gap-4 border border-dashed border-indigo-300 bg-indigo-50 px-4 py-3 text-sm text-indigo-700 transition hover:border-indigo-400 hover:bg-indigo-100 {{ $acceptedDrawing ? 'cursor-not-allowed opacity-60' : '' }}">
                                            <span class="min-w-0 truncate font-medium text-slate-700">
                                                Choose one or more files
                                            </span>
                                            <span class="shrink-0 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-wider text-indigo-600 shadow-sm">
                                                Browse
                                            </span>
                                        </label>
                                        <input id="drawing_files" type="file" name="drawing_files[]" multiple class="hidden"
                                            data-max-size="10485760"
                                            {{ $acceptedDrawing ? 'disabled' : '' }}>

                                        <p id="drawing-files-file-name" class="mt-1 text-xs text-gray-600 absolute hidden"></p>
                                    </div>

                                    <div class="w-full lg:flex-1">
                                        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                                            <div class="sm:w-40 shrink-0">
                                                <label class="block text-sm font-semibold text-slate-700 mb-2 text-center sm:text-left">
                                                    Version
                                                </label>
                                                <input type="text"
                                                    value="{{ $nextDrawingVersion }}"
                                                    readonly
                                                    class="h-12 w-full border border-slate-200 bg-slate-100 px-3 text-center text-sm font-semibold text-slate-700 focus:border-slate-200 focus:ring-0">
                                            </div>

                                            <a href="{{ route('projects.drawings.download', $project) }}"
                                                class="flex h-12 w-12 shrink-0 items-center justify-center border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:bg-slate-50 hover:text-slate-900"
                                                title="Download">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v10m0 0l-3-3m3 3l3-3M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/>
                                                </svg>
                                            </a>

                                            <div class="flex min-w-0 flex-1 gap-3">
                                                @if($acceptedDrawing)
                                                    <span class="flex h-12 min-w-0 flex-1 items-center justify-center border border-emerald-200 bg-emerald-50 px-4 text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">
                                                        Accepted
                                                    </span>
                                                @elseif($pendingDrawingForAccept)
                                                    <span class="flex h-12 min-w-0 flex-1 items-center justify-center border border-amber-200 bg-amber-50 px-4 text-center text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700">
                                                        Select a drawing below to accept
                                                    </span>
                                                @else
                                                    <span class="flex h-12 min-w-0 flex-1 items-center justify-center border border-slate-200 bg-white px-4 text-xs font-medium text-slate-500">
                                                        Ready to upload
                                                    </span>
                                                @endif

                                                @if(!$acceptedDrawing)
                                                    <x-primary-button class="h-12 min-w-36 justify-center text-xs font-bold uppercase tracking-[0.18em]">
                                                        Upload
                                                    </x-primary-button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                @php
                                    $drawingUploadError = $errors->first('drawing_files')
                                        ?: $errors->first('drawing_files.*')
                                        ?: $errors->first('drawing_status');
                                @endphp
                                <p id="drawing-files-error" class="mt-5 text-xs text-rose-600 {{ $drawingUploadError ? '' : 'hidden' }}">{{ $drawingUploadError }}</p>
                            </form>
                        </div>

                        @php
                            $drawingsToShow = $project->drawings
                                ->sortByDesc('created_at')
                                ->reject(fn ($drawing) => $drawing->accepted_at !== null);

                            $acceptedVersion = $acceptedDrawing?->version;
                            $canAcceptMoreDrawings = !$acceptedDrawing
                                || ($drawingsToShow->isNotEmpty() && $drawingsToShow->every(fn ($d) => (string) $d->version === (string) $acceptedVersion));
                        @endphp

                        @if($drawingsToShow->isEmpty())
                            <p class="text-center py-10 text-gray-500 italic">{{ $acceptedDrawing ? 'No pending drawings. Accepted drawing is available above.' : 'No drawings uploaded yet.' }}</p>
                        @else
                            <div class="overflow-hidden bg-white shadow sm:border border-gray-200">
                                <div class="overflow-x-auto">
                                    <form id="drawing-accept-selected-form" method="POST" action="{{ route('projects.drawings.acceptMany', $project) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="tab" value="drawing">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">File Name</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Version</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Added</th>
                                                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-100">
                                                @foreach($drawingsToShow as $drawing)
                                                    <tr class="hover:bg-gray-50">
                                                        {{-- <td class="px-4 py-3 text-sm text-gray-700">
                                                            @if($canAcceptMoreDrawings)
                                                                <input
                                                                    type="checkbox"
                                                                    name="drawing_ids[]"
                                                                    value="{{ $drawing->id }}"
                                                                    class="drawing-select-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                                    data-version="{{ $drawing->version }}"
                                                                >
                                                            @endif
                                                        </td> --}}
                                                        {{-- <td class="px-4 py-3 text-sm text-gray-900">
                                                            <div class="font-semibold">{{ $drawing->file_name }}</div>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $drawing->version }}</td>
                                                        <td class="px-4 py-3 text-sm text-gray-700">
                                                            <span class="inline-flex items-center px-2 py-1 text-xs font-semibold bg-indigo-100 text-indigo-800">
                                                                {{ str($drawing->drawing_status)->headline() }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $drawing->created_at->format('d M Y, h:i A') }}</td>
                                                        <td class="px-4 py-3 text-right">
                                                            <button type="button" @click="openAttachmentPreview(@js(route('projects.drawings.view', [$project, $drawing])), @js($drawing->file_name))" class="inline-flex items-center justify-center w-8 h-8 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="View">
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/></svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        <div class="p-4 flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 bg-gray-50">
                                            <div class="text-xs text-gray-600">
                                                <span id="drawing-accept-selected-count">0</span> selected
                                                <span id="drawing-accept-version-error" class="ml-2 text-rose-600 hidden">Select drawings from only one version.</span>
                                                @if(!$canAcceptMoreDrawings && $acceptedDrawing)
                                                    <span class="ml-2 text-amber-700">Only version {{ $acceptedVersion }} can be accepted.</span>
                                                @endif
                                            </div>
                                            @if($canAcceptMoreDrawings)
                                                <button id="drawing-accept-selected-btn" type="submit" class="h-9 px-4 text-xs font-medium bg-emerald-600 text-white hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                                                    Accept selected
                                                </button>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </div> --}}

                    <div x-show="tab === 'material'" x-cloak>
                        @php
                            $materialLoadRequested = in_array(strtolower((string) $requestedMaterialSource), ['quotation', 'form', 'boq'], true);
                            $shouldPrefillLinkedLeadMaterials = !empty($linkedLeadMaterialRows) && $materialLoadRequested;
                            $initialProjectMaterialRows = old('materials', $shouldPrefillLinkedLeadMaterials
                                ? $linkedLeadMaterialRows
                                : [[
                                    'product_id' => '',
                                    'material_name' => '',
                                    'qty' => 1,
                                    'unit' => '',
                                    'rate' => 0,
                                    'contractor_price' => '',
                                    'gst' => 0,
                                    'note' => '',
                                    'source_type' => 'manual',
                                    'source_label' => '',
                                ]]);
                        @endphp
                        <form id="material-upload-form" action="{{ route('projects.materials.store', $project) }}" method="POST" enctype="multipart/form-data" target="_self" class="mb-6">
                            @csrf
                            <input type="hidden" name="tab" value="material">
                            <div x-data="{
                                rows: @js($initialProjectMaterialRows),
                                products: @js($catalogProducts->map(fn ($product) => [
                                    'id' => (string) $product->id,
                                    'name' => (string) $product->name,
                                    'description' => (string) ($product->description ?? ''),
                                    'unit' => (string) ($product->unit ?? ''),
                                    'price' => (float) ($product->price ?? 0),
                                    'gst' => (float) ($product->gst ?? 0),
                                    'category_name' => (string) ($product->category?->name ?? ''),
                                ])->values()),
                                units: @js($projectUnits),
                                gstRates: @js($projectGstRates),
                                selectedProductId: '',
                                newRow() {
                                    return { product_id: '', material_name: '', qty: 1, unit: '', rate: 0, contractor_price: '', gst: 0, note: '', source_type: 'manual', source_label: '' };
                                },
                                addRow() {
                                    this.rows.push(this.newRow());
                                    this.$nextTick(() => {
                                        const idx = this.rows.length - 1;
                                        const el = this.$refs[`material_name_${idx}`];
                                        if (el) el.focus();
                                    });
                                },
                                removeRow(index) {
                                    if (this.rows.length <= 1) return;
                                    this.rows.splice(index, 1);
                                },
                                rowHasUserInput(row) {
                                    return Boolean(
                                        String(row.product_id || '').trim()
                                        || String(row.material_name || '').trim()
                                        || String(row.unit || '').trim()
                                        || String(row.note || '').trim()
                                        || Number(row.rate || 0) > 0
                                        || String(row.contractor_price ?? '').trim() !== ''
                                    );
                                },
                                rowTotal(row) {
                                    const qty = Number(row.qty || 0);
                                    const hasContractorPrice = row.contractor_price !== null
                                        && row.contractor_price !== undefined
                                        && String(row.contractor_price).trim() !== '';
                                    const basisRate = hasContractorPrice
                                        ? Number(row.contractor_price || 0)
                                        : Number(row.rate || 0);

                                    return (qty * basisRate).toFixed(2);
                                },
                                unitEnsureOption(value = '') {
                                    const unit = String(value || '').trim();
                                    if (unit && !this.units.includes(unit)) {
                                        this.units.push(unit);
                                    }
                                    return unit;
                                },
                                ensureGstOption(value = 0) {
                                    const rate = Number(value || 0);
                                    if (!this.gstRates.some((r) => Math.abs(Number(r) - rate) < 0.001)) {
                                        this.gstRates.push(rate);
                                        this.gstRates.sort((a, b) => Number(a) - Number(b));
                                    }
                                    return rate;
                                },
                                unitOptionsFor(value) {
                                    const current = String(value || '').trim();
                                    if (current !== '' && !this.units.includes(current)) {
                                        this.units.push(current);
                                    }
                                    return this.units;
                                },
                                gstOptionsFor(value) {
                                    const current = Number(value || 0);
                                    if (!this.gstRates.some((rate) => Math.abs(Number(rate) - current) < 0.001)) {
                                        this.gstRates.push(current);
                                    }
                                    if (!this.gstRates.some((rate) => Math.abs(Number(rate) - 0) < 0.001)) {
                                        this.gstRates.push(0);
                                    }
                                    return this.gstRates.sort((a, b) => Number(a) - Number(b));
                                },
                                selectProduct(index, productId, rowElement = null) {
                                    const row = this.rows[index];
                                    if (!row) return;
                                    const product = this.products.find((item) => String(item.id) === String(productId));
                                    row.product_id = product ? String(product.id) : '';
                                    if (!product) {
                                        return;
                                    }
                                    row.material_name = product.name || row.material_name;
                                    const unit = String(product.unit || '').trim();
                                    if (unit) {
                                        this.unitEnsureOption(unit);
                                        row.unit = unit;
                                    }
                                    row.rate = Number(product.price || 0);
                                    const gst = Number(product.gst || 0);
                                    this.ensureGstOption(gst);
                                    row.gst = gst;
                                    if (!String(row.note || '').trim()) {
                                        row.note = product.description || '';
                                    }
                                    this.$nextTick(() => {
                                        if (unit) row.unit = unit;
                                        row.gst = gst;

                                        const container = rowElement || document.querySelector(`.material-row:nth-child(${index + 1})`) || document;
                                        const selects = container.querySelectorAll('select');
                                        selects.forEach(select => {
                                            if (typeof select._syncCustomSelect === 'function') {
                                                select._syncCustomSelect();
                                            } else {
                                                select.dispatchEvent(new Event('change', { bubbles: true }));
                                            }
                                        });
                                    });
                                },
                                selectMainProduct(productId) {
                                    if (!productId) {
                                        return;
                                    }

                                    let targetIndex = this.rows.length - 1;
                                    if (targetIndex < 0) {
                                        this.rows.push(this.newRow());
                                        targetIndex = 0;
                                    } else if (this.rowHasUserInput(this.rows[targetIndex])) {
                                        this.rows.push(this.newRow());
                                        targetIndex = this.rows.length - 1;
                                    }

                                    this.$nextTick(() => {
                                        const targetRowEl = document.querySelectorAll('.material-row')[targetIndex] || null;
                                        this.selectProduct(targetIndex, productId, targetRowEl);
                                        this.selectedProductId = '';

                                        const el = this.$refs[`material_name_${targetIndex}`];
                                        if (el) el.focus();
                                    });
                                }
                            }">
                                @if($project->lead_id)
                                    <div class="mb-4 flex flex-col gap-3 border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 lg:flex-row lg:items-center lg:justify-between">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="font-semibold">Load material from linked lead:</span>
                                            <a
                                                href="{{ route('projects.show', ['project' => $project, 'tab' => 'material', 'load_material_from' => 'quotation']) }}"
                                                class="inline-flex items-center border px-3 py-1.5 text-xs font-semibold transition {{ in_array(strtolower((string) $requestedMaterialSource), ['quotation', 'form'], true) ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-indigo-200 bg-white text-indigo-700 hover:bg-indigo-50' }}"
                                            >
                                                Load from Quotation
                                            </a>
                                            <a
                                                href="{{ route('projects.show', ['project' => $project, 'tab' => 'material', 'load_material_from' => 'boq']) }}"
                                                class="inline-flex items-center border px-3 py-1.5 text-xs font-semibold transition {{ strtolower((string) $requestedMaterialSource) === 'boq' ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-emerald-200 bg-white text-emerald-700 hover:bg-emerald-50' }}"
                                            >
                                                Load from BOQ
                                            </a>
                                        </div>
                                        @if($contractorCommissionSummary['has_commission'])
                                            <div class="border border-orange-200 bg-white px-4 py-3 text-sm text-orange-900 lg:min-w-[320px]">
                                                <p class="font-semibold">Project Contractor Commission</p>
                                                <p class="mt-1 text-lg font-bold">₹{{ number_format((float) $contractorCommissionSummary['amount'], 2) }}</p>
                                                @if($contractorCommissionSummary['type'] === 'percentage')
                                                    <p class="mt-1 text-xs text-orange-700">
                                                        {{ number_format((float) $contractorCommissionSummary['value'], 2) }}% of ₹{{ number_format((float) $contractorCommissionSummary['base_total'], 2) }}
                                                        from {{ $contractorCommissionSummary['materials_count'] }} material item(s), using contractor price when available, otherwise rate.
                                                    </p>
                                                @else
                                                    <p class="mt-1 text-xs text-orange-700">
                                                        Fixed commission for this project: ₹{{ number_format((float) $contractorCommissionSummary['value'], 2) }}.
                                                    </p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @elseif($contractorCommissionSummary['has_contractor'])
                                    <div class="mb-4 border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-900">
                                        <p class="font-semibold">Contractor assigned, but commission is not set for this project yet.</p>
                                        <p class="mt-1 text-xs text-orange-700">Add the commission in the project contractor card above to calculate the project commission amount.</p>
                                    </div>
                                @endif

                                @if($project->lead_id && $linkedLeadMaterialSource && $shouldPrefillLinkedLeadMaterials)
                                    <div class="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                        <p class="font-semibold">Loaded material rows from linked lead {{ $linkedLeadMaterialSource }}.</p>
                                        <p class="mt-1 text-xs text-emerald-700">Review the imported rows below, then save them to this project. You can still edit any field before saving.</p>
                                    </div>
                                @elseif($project->lead_id && $materialLoadRequested)
                                    @php
                                        $requestedSourceLabel = strtolower((string) $requestedMaterialSource) === 'boq'
                                            ? 'BOQ'
                                            : 'quotation';
                                    @endphp
                                    <div class="mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                        <p class="font-semibold">This project is linked to a lead, but no {{ $requestedSourceLabel }} items were found to import.</p>
                                        <p class="mt-1 text-xs text-amber-700">You can still add project materials manually below.</p>
                                    </div>
                                @elseif($project->lead_id && $catalogProducts->isNotEmpty())
                                    <div class="mb-4 border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                                        <p class="font-semibold">This project is linked to a lead.</p>
                                        <p class="mt-1 text-xs text-indigo-700">Start with an empty material row, choose a product from the list, or load rows from quotation/BOQ.</p>
                                    </div>
                                @elseif($catalogProducts->isNotEmpty())
                                    <div class="mb-4 border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                                        <p class="font-semibold">This project is not linked to a lead.</p>
                                        <p class="mt-1 text-xs text-indigo-700">Select a saved product to auto-fill material details, similar to the lead material tab.</p>
                                    </div>
                                @endif

                                <div class="mt-4 space-y-3">
                                    <template x-if="products.length > 0">
                                        <div class="border border-indigo-200 bg-indigo-50 p-3">
                                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Product</label>
                                            <select
                                                class="mt-1 block h-10 w-full border-indigo-200 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                x-model="selectedProductId"
                                                @change="selectMainProduct(selectedProductId)"
                                            >
                                                <option value="">Select from product list</option>
                                                @foreach($catalogProducts as $catalogProduct)
                                                    <option value="{{ (string) $catalogProduct->id }}">
                                                        {{ ($catalogProduct->category?->name ? $catalogProduct->category->name . ' / ' : '') . $catalogProduct->name . ' (₹' . number_format((float) ($catalogProduct->price ?? 0), 2, '.', '') . ')' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </template>
                                    <template x-for="(row, index) in rows" :key="index">
                                        <div class="material-row border border-gray-200 bg-white p-3 shadow-sm">
                                            <template x-if="products.length > 0">
                                                <div class="mb-3">
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Product</label>
                                                    <select
                                                        class="mt-1 block h-10 w-full border-indigo-200 bg-indigo-50 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        x-model="row.product_id"
                                                        :name="`materials[${index}][product_id]`"
                                                        @change="selectProduct(index, row.product_id, $event.target.closest('.material-row'))"
                                                    >
                                                        <option value="">Select from product list</option>
                                                        @foreach($catalogProducts as $catalogProduct)
                                                            <option value="{{ (string) $catalogProduct->id }}">
                                                                {{ ($catalogProduct->category?->name ? $catalogProduct->category->name . ' / ' : '') . $catalogProduct->name . ' (₹' . number_format((float) ($catalogProduct->price ?? 0), 2, '.', '') . ')' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </template>
                                            <input type="hidden" x-model="row.product_id" :name="`materials[${index}][product_id]`">
                                            <input type="hidden" x-model="row.source_type" :name="`materials[${index}][source_type]`">
                                            <input type="hidden" x-model="row.source_label" :name="`materials[${index}][source_label]`">
                                            <div class="grid grid-cols-1 gap-3 lg:grid-cols-12 lg:items-start">
                                            <div class="flex flex-col lg:col-span-4">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Material Name</label>
                                                <input
                                                    type="text"
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                    minlength="2"
                                                    maxlength="191"
                                                    x-model="row.material_name"
                                                    :name="`materials[${index}][material_name]`"
                                                    :x-ref="`material_name_${index}`"
                                                >
                                                <p class="material-name-error-slot hidden mt-1 min-h-[1rem] text-xs leading-4 text-rose-600"></p>
                                            </div>
                                            <div class="flex flex-col lg:col-span-2">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Qty</label>
                                                <input
                                                    type="number"
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                    min="0.01"
                                                    step="any"
                                                    x-model="row.qty"
                                                    :name="`materials[${index}][qty]`"
                                                >
                                                <p class="qty-error-slot hidden mt-1 min-h-[1rem] text-xs leading-4 text-rose-600"></p>
                                            </div>
                                            <div class="flex flex-col lg:col-span-2">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Unit</label>
                                                <select
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    x-model="row.unit"
                                                    :name="`materials[${index}][unit]`"
                                                >
                                                    <option value="">Select unit</option>
                                                    <template x-for="unitOption in unitOptionsFor(row.unit)" :key="unitOption">
                                                        <option :value="unitOption" :selected="String(unitOption) === String(row.unit)" x-text="unitOption"></option>
                                                    </template>
                                                </select>
                                            </div>
                                            <div class="flex flex-col lg:col-span-2">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Rate</label>
                                                <input
                                                    type="number"
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    min="0"
                                                    step="0.01"
                                                    x-model="row.rate"
                                                    :name="`materials[${index}][rate]`"
                                                >
                                            </div>
                                            @if($project->contractor_id)
                                                <div class="lg:col-span-2">
                                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Contractor Price</label>
                                                    <input
                                                        type="number"
                                                        class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        min="0"
                                                        step="0.01"
                                                        x-model="row.contractor_price"
                                                        :name="`materials[${index}][contractor_price]`"
                                                        placeholder="Optional"
                                                    >
                                                </div>
                                            @endif
                                            <div class="lg:col-span-2">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">GST %</label>
                                                <select
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    x-model.number="row.gst"
                                                    :name="`materials[${index}][gst]`"
                                                >
                                                    <template x-for="gstOption in gstOptionsFor(row.gst)" :key="gstOption">
                                                        <option :value="Number(gstOption)" :selected="Math.abs(Number(gstOption) - Number(row.gst)) < 0.001" x-text="Number(gstOption).toFixed(2) + '%'"></option>
                                                    </template>
                                                </select>
                                            </div>
                                                <div class="flex flex-col {{ $project->contractor_id ? 'lg:col-span-4' : 'lg:col-span-6' }}">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Note</label>
                                                <input
                                                    type="text"
                                                    class="mt-1 block h-10 w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    maxlength="2000"
                                                    x-model="row.note"
                                                    :name="`materials[${index}][note]`"
                                                    @keydown.enter.prevent="addRow()"
                                                >
                                            </div>
                                            <div class="flex flex-col lg:col-span-3">
                                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-600">Total</label>
                                                <div class="mt-1 flex h-10 items-center border border-dashed border-gray-300 bg-gray-50 px-3 text-sm font-semibold text-gray-800" x-text="'₹' + rowTotal(row)"></div>
                                            </div>
                                            <div class="flex w-full flex-wrap items-start gap-2 lg:col-span-3 lg:justify-end">
                                                <button type="button"
                                                    @click="addRow()"
                                                    x-show="index === rows.length - 1"
                                                    class="inline-flex h-10 flex-1 items-center justify-center rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100 sm:flex-none sm:w-10 lg:h-11 lg:w-14 lg:flex-none"
                                                    title="Add row"
                                                >
                                                    +
                                                </button>
                                                <button type="button"
                                                    @click="removeRow(index)"
                                                    class="inline-flex h-10 flex-1 items-center justify-center rounded bg-red-50 text-red-700 hover:bg-red-100 sm:flex-none sm:w-10 lg:h-11 lg:w-14 lg:flex-none"
                                                    title="Remove row"
                                                >
                                                    <svg class="w-4 h-4" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                        <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208z"/>
                                                    </svg>
                                                </button>
                                                <button
                                                    type="submit"
                                                    x-show="index === rows.length - 1"
                                                    class="inline-flex h-10 flex-1 items-center justify-center rounded bg-emerald-600 text-white hover:bg-emerald-700 sm:flex-none sm:w-10 lg:h-11 lg:w-14 lg:flex-none"
                                                    title="Add materials"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                        <path d="M9 16.2l-3.5-3.5 1.4-1.4L9 13.4l8.1-8.1 1.4 1.4L9 16.2z"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>


                            </div>
                        </form>

                        @php
                            $materialsToShow = $materialsPaginator;
                            $materialSourceBadgeClasses = [
                                'quotation' => 'bg-sky-100 text-sky-700',
                                'boq' => 'bg-violet-100 text-violet-700',
                                'manual' => 'bg-slate-100 text-slate-700',
                            ];
                        @endphp

                        @if($materialsToShow->isEmpty())
                            <p class="text-center py-10 text-gray-500 italic">No material items added yet.</p>
                        @else
                            <div class="sm:hidden space-y-3">
                                @foreach($materialsToShow as $material)
                                    <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                @php
                                                    $sourceType = in_array($material->source_type, ['quotation', 'boq', 'manual'], true)
                                                        ? $material->source_type
                                                        : 'manual';
                                                    $sourceLabel = $material->source_label ?: ucfirst($sourceType);
                                                @endphp
                                                <p class="text-sm font-semibold text-gray-900 break-words">{{ $material->material_name }}</p>
                                                <span class="mt-2 inline-flex items-center px-2.5 py-1 text-[11px] font-semibold {{ $materialSourceBadgeClasses[$sourceType] }}">
                                                    {{ $sourceLabel }}
                                                </span>
                                                <p class="mt-1 text-xs text-gray-500">{{ $material->unit ?: 'No unit' }} @if($material->rate !== null) â€¢ ₹{{ number_format((float) $material->rate, 2) }} @endif @if($material->contractor_price !== null) â€¢ Contractor ₹{{ number_format((float) $material->contractor_price, 2) }} @endif @if($material->gst !== null) â€¢ GST {{ number_format((float) $material->gst, 2) }}% @endif</p>
                                                <p class="mt-1 text-xs text-gray-500">Added {{ $material->created_at->format('d M Y, h:i A') }}</p>
                                                <span class="mt-2 inline-flex items-center px-2.5 py-1 text-xs font-semibold {{ $material->qa_done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ $material->qa_done ? 'Ready to Dispatch' : 'Dispatch Pending' }}
                                                </span>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                <button type="button"
                                                    @click="openMaterialEdit({
                                                        id: {{ $material->id }},
                                                        product_id: @js((string) ($material->product_id ?? '')),
                                                        material_name: @js($material->material_name),
                                                        qty: @js($material->qty),
                                                        unit: @js($material->unit ?? ''),
                                                        rate: @js((float) ($material->rate ?? 0)),
                                                        contractor_price: @js($material->contractor_price !== null ? (float) $material->contractor_price : ''),
                                                        gst: @js((float) ($material->gst ?? 0)),
                                                        note: @js($material->note ?? ''),
                                                        updateUrl: @js(route('projects.materials.update', [$project, $material]))
                                                    })"
                                                    class="inline-flex h-8 w-8 items-center justify-center rounded bg-indigo-50 text-indigo-700"
                                                    title="Edit"
                                                >
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                        <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                    </svg>
                                                </button>
                                                <form method="POST" action="{{ route('projects.materials.destroy', [$project, $material]) }}" class="js-confirm-delete" data-confirm-title="Delete material?" data-confirm-text="This will permanently delete the material item.">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="tab" value="material">
                                                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded bg-red-50 text-red-700" title="Delete">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                            <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                        </svg>
                                                    </button>
                                                </form>
                                                @if($material->attachment_path)
                                                    <button type="button" @click="openAttachmentPreview(@js(route('projects.materials.view', [$project, $material])), @js($material->material_name))" class="inline-flex h-8 w-8 items-center justify-center rounded bg-indigo-50 text-indigo-700" title="View">
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                            <path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                                        </svg>
                                                    </button>
                                                @endif
                                                @if(!$material->qa_done)
                                                    <form method="POST" action="{{ route('projects.materials.qa.update', [$project, $material]) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="qa_done" value="1">
                                                        <input type="hidden" name="tab" value="material">
                                                        <button type="submit" class="inline-flex rounded bg-white px-3 py-2 text-xs font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50" title="Send To Dispatch">
                                                            Dispatch
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                                            <div class="bg-slate-50 p-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Qty</p>
                                                <p class="mt-1 font-semibold text-gray-900">{{ rtrim(rtrim(number_format((float) $material->qty, 2, '.', ''), '0'), '.') }}</p>
                                            </div>
                                            <div class="bg-slate-50 p-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Total</p>
                                                <p class="mt-1 font-semibold text-gray-900">₹{{ number_format((float) $material->qty * (float) ($material->contractor_price ?? $material->rate ?? 0), 2) }}</p>
                                            </div>
                                            <div class="bg-slate-50 p-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Unit</p>
                                                <p class="mt-1 font-semibold text-gray-900">{{ $material->unit ?: 'N/A' }}</p>
                                            </div>
                                            @if($project->contractor_id)
                                                <div class="bg-slate-50 p-3">
                                                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Contractor Price</p>
                                                    <p class="mt-1 font-semibold text-gray-900">{{ $material->contractor_price !== null ? '₹'.number_format((float) $material->contractor_price, 2) : 'N/A' }}</p>
                                                </div>
                                            @endif
                                            <div class="col-span-2 bg-slate-50 p-3">
                                                <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Note</p>
                                                <p class="mt-1 text-sm text-gray-700">{{ $material->note ?: 'No note' }}</p>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="hidden overflow-hidden bg-white shadow sm:block sm:border border-gray-200">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Material</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Source</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Qty</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Unit</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Rate</th>
                                                @if($project->contractor_id)
                                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Contractor Price</th>
                                                @endif
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">GST</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Total</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Note</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Added</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            @foreach($materialsPaginator as $material)
                                                @php
                                                    $sourceType = in_array($material->source_type, ['quotation', 'boq', 'manual'], true)
                                                        ? $material->source_type
                                                        : 'manual';
                                                    $sourceLabel = $material->source_label ?: ucfirst($sourceType);
                                                @endphp
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ $material->material_name }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold {{ $materialSourceBadgeClasses[$sourceType] }}">
                                                            {{ $sourceLabel }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ rtrim(rtrim(number_format((float) $material->qty, 2, '.', ''), '0'), '.') }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $material->unit ?: 'N/A' }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format((float) ($material->rate ?? 0), 2) }}</td>
                                                    @if($project->contractor_id)
                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $material->contractor_price !== null ? '₹'.number_format((float) $material->contractor_price, 2) : 'N/A' }}</td>
                                                    @endif
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ number_format((float) ($material->gst ?? 0), 2) }}%</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">₹{{ number_format((float) $material->qty * (float) ($material->contractor_price ?? $material->rate ?? 0), 2) }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <div class="max-w-xs truncate" title="{{ $material->note ?: 'No note' }}">{{ $material->note ?: 'No note' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">{{ $material->created_at->format('d M Y, h:i A') }}</td>
                                                    <td class="px-4 py-3 text-right">
                                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold {{ $material->qa_done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                                {{ $material->qa_done ? 'Ready to Dispatch' : 'Dispatch Pending' }}
                                                            </span>
                                                            <button type="button"
                                                                @click="openMaterialEdit({
                                                                    id: {{ $material->id }},
                                                                    product_id: @js((string) ($material->product_id ?? '')),
                                                                    material_name: @js($material->material_name),
                                                                    qty: @js($material->qty),
                                                                    unit: @js($material->unit ?? ''),
                                                                    rate: @js((float) ($material->rate ?? 0)),
                                                                    contractor_price: @js($material->contractor_price !== null ? (float) $material->contractor_price : ''),
                                                                    gst: @js((float) ($material->gst ?? 0)),
                                                                    note: @js($material->note ?? ''),
                                                                    updateUrl: @js(route('projects.materials.update', [$project, $material]))
                                                                })"
                                                                class="inline-flex items-center justify-center w-7 h-7 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                                                title="Edit"
                                                            >
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                                    <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                                </svg>
                                                            </button>

                                                            <form method="POST" action="{{ route('projects.materials.destroy', [$project, $material]) }}" class="inline js-confirm-delete" data-confirm-title="Delete material?" data-confirm-text="This will permanently delete the material item.">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="tab" value="material">
                                                                <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded bg-red-50 text-red-700 hover:bg-red-100" title="Delete">
                                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                                        <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                                    </svg>
                                                                </button>
                                                            </form>

                                                            @if($material->attachment_path)
                                                                <button type="button" @click="openAttachmentPreview(@js(route('projects.materials.view', [$project, $material])), @js($material->material_name))" class="inline-flex items-center justify-center w-8 h-8 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="View">
                                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                                        <path d="M12 5c-7.633 0-11 7-11 7s3.367 7 11 7 11-7 11-7-3.367-7-11-7zm0 12a5 5 0 110-10 5 5 0 010 10zm0-2.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z"/>
                                                                    </svg>
                                                                </button>
                                                            @endif
                                                            @if(!$material->qa_done)
                                                                <form method="POST" action="{{ route('projects.materials.qa.update', [$project, $material]) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="qa_done" value="1">
                                                                    <input type="hidden" name="tab" value="material">
                                                                    <button type="submit" class="inline-flex items-center bg-white border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                                        Dispatch
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="px-4 py-3 border-t border-gray-200">
                                    {{ $materialsPaginator->links() }}
                                </div>
                            </div>
                        @endif
                    </div>

                    <div x-show="tab === 'production'" x-cloak>
                           <form id="production-upload-form" action="{{ route('projects.productions.store', $project) }}" method="POST" enctype="multipart/form-data" class="mb-6 border border-slate-200 bg-white p-4 shadow-sm lg:p-5">
                            @csrf
                            <input type="hidden" name="tab" value="production">
                            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-12 xl:items-start">
                                <div class="space-y-1 xl:col-span-3">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Title</div>
                                    <x-text-input id="production_title" name="title" class="block h-11 w-full" :value="old('title')" required minlength="2" maxlength="191" />
                                    <x-input-error :messages="$errors->get('title')" class="mt-1 text-xs" />
                                    <div class="error-message"></div>
                                </div>
                                <div class="space-y-1 xl:col-span-3">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Note</div>
                                    <x-text-input id="production_note" name="note" class="block h-11 w-full" :value="old('note')" maxlength="1000" />
                                    <x-input-error :messages="$errors->get('note')" class="mt-1 text-xs" />
                                    <div class="error-message"></div>
                                </div>
                                <div class="space-y-1 xl:col-span-4">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Attachment</div>
                                    <label for="production_attachment" class="flex h-11 w-full items-center justify-between gap-3 border border-dashed border-indigo-200 bg-indigo-50/60 px-3 cursor-pointer transition hover:border-indigo-300 hover:bg-indigo-50">
                                        <span class="min-w-0 truncate text-sm font-semibold text-indigo-800">Choose file</span>
                                        <span class="shrink-0 bg-white px-2.5 py-1 text-[11px] font-semibold text-indigo-700 shadow-sm">Browse</span>
                                    </label>
                                    <input id="production_attachment" type="file" name="attachment" class="hidden"
                                        data-max-size="20971520"
                                        accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.mp4,.mov,.avi,.mkv,.mp3,.wav">
                                    <p id="production-attachment-file-name" class="text-xs text-gray-600 break-words">No file selected.</p>
                                    <p class="text-xs leading-5 text-gray-500">Allowed: JPG, JPEG, PNG, PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, ZIP, RAR, MP4, MOV, AVI, MKV, MP3, WAV. Max 20 MB.</p>
                                    <p id="production-attachment-error" class="hidden text-xs text-rose-600 mt-1"></p>
                                    <img id="production-attachment-preview" class="mt-2 h-24 max-w-full rounded border border-gray-200 object-contain hidden" alt="Attachment preview">
                                    <x-input-error :messages="$errors->get('attachment')" class="mt-1 text-xs" />
                                    <div class="error-message"></div>
                                </div>
                                <div class="space-y-1 xl:col-span-2">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 xl:opacity-0">Actions</div>
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('projects.productions.print', $project) }}" target="_blank"
                                            class="inline-flex h-11 w-11 shrink-0 items-center justify-center border border-slate-200 bg-slate-100 transition hover:bg-slate-200"
                                            title="Print">
                                            <svg xmlns="http://www.w3.org/2000/svg"
                                                class="h-5 w-5 text-gray-700"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 9V2h12v7M6 18h12v4H6v-4zm-2-6h16a2 2 0 012 2v4H2v-4a2 2 0 012-2z"/>
                                            </svg>
                                        </a>
                                        <x-primary-button class="h-11 min-w-[110px] justify-center px-5 text-xs font-bold uppercase tracking-widest">Add</x-primary-button>
                                    </div>
                                </div>
                            </div>
                        </form>

                        @php
                            $productionsToShow = $project->productions->sortByDesc('created_at');
                        @endphp

                        @if($productionsToShow->isEmpty())
                            <p class="text-center py-10 text-gray-500 italic">No production updates yet.</p>
                        @else
                            <div class="sm:hidden space-y-3">
                                @foreach($productionsToShow as $production)
                                    <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="break-words text-sm font-semibold text-gray-900">{{ $production->title }}</p>
                                                <p class="mt-1 text-xs text-gray-500">
                                                    <time class="local-time" datetime="{{ $production->created_at?->toIso8601String() }}">
                                                        {{ $production->created_at->format('d M Y, h:i A') }}
                                                    </time>
                                                </p>
                                            </div>
                                            <span class="inline-flex shrink-0 items-center px-2.5 py-1 text-xs font-semibold {{ $production->qa_done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                QA: {{ $production->qa_done ? 'Done' : 'Pending' }}
                                            </span>
                                        </div>

                                        <div class="mt-3 bg-slate-50 p-3">
                                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">Note</p>
                                            <p class="mt-1 text-sm text-gray-700">{{ $production->note ?: 'No note' }}</p>
                                        </div>

                                        <div class="mt-4 grid grid-cols-2 gap-2">
                                            <button type="button"
                                                @click="openProductionEdit({
                                                    id: {{ $production->id }},
                                                    title: @js($production->title),
                                                    note: @js($production->note ?? ''),
                                                    updateUrl: @js(route('projects.productions.update', [$project, $production]))
                                                })"
                                                class="inline-flex items-center justify-center gap-1 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700"
                                                title="Edit"
                                            >
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                    <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                </svg>
                                                Edit
                                            </button>

                                            <form method="POST" action="{{ route('projects.productions.destroy', [$project, $production]) }}" class="js-confirm-delete" data-confirm-title="Delete production update?" data-confirm-text="This will permanently delete the production update.">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="tab" value="production">
                                                <button type="submit" class="inline-flex w-full items-center justify-center gap-1 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700" title="Delete">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                        <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                    </svg>
                                                    Delete
                                                </button>
                                            </form>

                                            @if(!$production->qa_done)
                                                <form method="POST" action="{{ route('projects.productions.qa.update', [$project, $production]) }}" class="col-span-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="qa_done" value="1">
                                                    <input type="hidden" name="tab" value="production">
                                                    <button type="submit" class="inline-flex w-full items-center justify-center border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700" title="Mark Done">
                                                        Mark Done
                                                    </button>
                                                </form>
                                            @endif

                                            @if($production->attachment_path)
                                                <button type="button"
                                                    @click="openAttachmentPreview(@js(route('projects.productions.view', [$project, $production])), @js($production->title))"
                                                    class="col-span-2 inline-flex items-center justify-center gap-1 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700"
                                                    title="View Image">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                        <path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zM5 5h14v10l-3.5-3.5a1 1 0 0 0-1.4 0L9 17l-2-2-2 2V5zm4 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                                                    </svg>
                                                    View
                                                </button>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="hidden overflow-hidden bg-white shadow sm:block sm:border border-gray-200">
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Title</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Note</th>
                                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Added</th>
                                                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-100">
                                            @foreach($productionsToShow as $production)
                                                <tr class="hover:bg-gray-50">
                                                    <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ $production->title }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <div class="max-w-xs truncate" title="{{ $production->note ?: 'No note' }}">{{ $production->note ?: 'No note' }}</div>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-700">
                                                        <time class="local-time" datetime="{{ $production->created_at?->toIso8601String() }}">
                                                            {{ $production->created_at->format('d M Y, h:i A') }}
                                                        </time>
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                                            <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold {{ $production->qa_done ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                                QA: {{ $production->qa_done ? 'Done' : 'Pending' }}
                                                            </span>
                                                            <button type="button"
                                                                @click="openProductionEdit({
                                                                    id: {{ $production->id }},
                                                                    title: @js($production->title),
                                                                    note: @js($production->note ?? ''),
                                                                    updateUrl: @js(route('projects.productions.update', [$project, $production]))
                                                                })"
                                                                class="inline-flex items-center justify-center w-7 h-7 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                                                title="Edit"
                                                            >
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                                    <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                                </svg>
                                                            </button>
                                                                <form method="POST" action="{{ route('projects.productions.destroy', [$project, $production]) }}" class="inline js-confirm-delete" data-confirm-title="Delete production update?" data-confirm-text="This will permanently delete the production update.">
                                                                @csrf
                                                                @method('DELETE')
                                                                <input type="hidden" name="tab" value="production">
                                                                <button type="submit" class="inline-flex items-center justify-center w-7 h-7 rounded bg-red-50 text-red-700 hover:bg-red-100" title="Delete">
                                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                                                                        <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                                    </svg>
                                                                </button>
                                                            </form>
                                                            @if(!$production->qa_done)
                                                                <form method="POST" action="{{ route('projects.productions.qa.update', [$project, $production]) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="qa_done" value="1">
                                                                    <input type="hidden" name="tab" value="production">
                                                                    <button type="submit" class="inline-flex items-center bg-white border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                                        Mark Done
                                                                    </button>
                                                                </form>
                                                            @endif
                                                          @if($production->attachment_path)
                                                            <button type="button"
                                                                @click="openAttachmentPreview(@js(route('projects.productions.view', [$project, $production])), @js($production->title))"
                                                                class="inline-flex items-center justify-center w-8 h-8 rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100"
                                                                title="View Image">

                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                                                    <path d="M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zM5 5h14v10l-3.5-3.5a1 1 0 0 0-1.4 0L9 17l-2-2-2 2V5zm4 4a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3z"/>
                                                                </svg>

                                                            </button>
                                                        @endif
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            @if($materialsToShow->hasPages())
                                <div class="mt-4">
                                    {{ $materialsToShow->links() }}
                                </div>
                            @endif
                        @endif
                    </div>

                    <div x-show="tab === 'ready_to_dispatch'" x-cloak>
                        @php
                            $formatMaterialQty = fn ($qty) => rtrim(rtrim(number_format((float) $qty, 2, '.', ''), '0'), '.');

                            $readyToDispatchPendingItems = $project->materials
                                ->reject(fn ($material) => $material->source_drawing_id !== null)
                                ->filter(fn ($material) => (bool) $material->qa_done && empty($material->dispatched_at))
                                ->sortByDesc('created_at')
                                ->values();

                        @endphp

                        <div class="space-y-6">
                            <div class="bg-white border border-gray-200 p-4 sm:p-6">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <h3 class="text-lg font-bold text-gray-900">{{ $tabLabels['ready_to_dispatch'] }}</h3>
                                        <p class="mt-1 text-sm text-gray-600">Dispatch QA approved items and create challan in the same step.</p>
                                    </div>
                                    <div class="text-xs font-semibold text-gray-500 whitespace-nowrap sm:text-right">
                                        Pending: {{ $readyToDispatchPendingItems->count() }} | Dispatched: {{ $readyToDispatchDispatchedItems->total() }} | Challans: {{ $challans->total() }}
                                    </div>
                                </div>

                                @if($readyToDispatchPendingItems->isNotEmpty())
                                    <form id="ready-to-dispatch-form" method="POST" action="{{ route('projects.dispatch', $project) }}" target="_self" class="mt-5">
                                        @csrf
                                        <input type="hidden" name="tab" value="ready_to_dispatch">
                                        <input type="hidden" name="dispatch_action" value="dispatch_and_challan">

                                        <div class="sm:hidden space-y-3">
                                            @foreach($readyToDispatchPendingItems as $material)
                                                <label class="block border border-gray-200 bg-white p-4 shadow-sm">
                                                    <div class="flex items-start gap-3">
                                                        <input type="checkbox" class="rtd-item-checkbox mt-1 h-4 w-4 shrink-0 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" name="material_ids[]" value="{{ $material->id }}">
                                                        <div class="min-w-0 flex-1">
                                                            <p class="break-words text-sm font-semibold text-gray-900">{{ $material->material_name }}</p>
                                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                                                                <span>Qty: {{ $formatMaterialQty($material->qty) }}</span>
                                                                <span>Unit: {{ $material->unit ?: '-' }}</span>
                                                            </div>
                                                            <p class="mt-1 text-xs text-gray-500">{{ $material->note ?: 'No note' }}</p>
                                                            <p class="mt-1 text-xs text-gray-500">Added {{ $material->created_at->format('d M Y, h:i A') }}</p>
                                                        </div>
                                                    </div>
                                                </label>
                                            @endforeach
                                        </div>

                                        <div class="hidden overflow-hidden bg-white shadow sm:block sm:border border-gray-200">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Done</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Material</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Qty</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Unit</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Note</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-600 uppercase tracking-wider">Added</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-100">
                                                        @foreach($readyToDispatchPendingItems as $material)
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-3">
                                                                    <input type="checkbox" class="rtd-item-checkbox h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" name="material_ids[]" value="{{ $material->id }}">
                                                                </td>
                                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $material->material_name }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $formatMaterialQty($material->qty) }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $material->unit ?: '-' }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                                    <div class="max-w-xs truncate" title="{{ $material->note ?: 'No note' }}">{{ $material->note ?: 'No note' }}</div>
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $material->created_at->format('d M Y, h:i A') }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <div class="mt-5 flex flex-col gap-3 sm:flex-row sm:justify-end">
                                            <button id="ready-to-dispatch-submit-btn" type="button" @click="openDispatchChallanModal()" disabled class="inline-flex w-full items-center justify-center border border-transparent bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">
                                                Dispatch and Create Challan
                                            </button>
                                        </div>

                                        {{-- Modal Popup for Challan Generation Details --}}
                                        <div
                                            x-show="dispatchChallanModalOpen"
                                            x-cloak
                                            class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                            aria-modal="true"
                                            role="dialog"
                                        >
                                            <div class="absolute inset-0 bg-black/50" @click="closeDispatchChallanModal()"></div>

                                            <div class="relative w-full max-w-lg bg-white shadow-xl border border-gray-200 overflow-hidden flex flex-col max-h-[90vh]">
                                                <div class="flex-shrink-0 flex items-center justify-between gap-4 p-4 border-b border-gray-200 bg-gray-50">
                                                    <div>
                                                        <h3 class="text-base font-bold text-gray-900">Dispatch & Generate Challan</h3>
                                                        <p class="text-xs text-gray-500">Provide recipient and delivery details for this challan.</p>
                                                    </div>
                                                    <button type="button" @click="closeDispatchChallanModal()" class="inline-flex items-center justify-center h-8 w-8 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                                        <span class="sr-only">Close</span>
                                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                            <path d="M6.225 4.811a1 1 0 011.414 0L12 9.172l4.361-4.361a1 1 0 111.414 1.414L13.414 10.586l4.361 4.361a1 1 0 01-1.414 1.414L12 12l-4.361 4.361a1 1 0 01-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 010-1.414z"/>
                                                        </svg>
                                                    </button>
                                                </div>

                                                <div class="p-5 space-y-4 overflow-y-auto">
                                                    <div>
                                                        <div class="flex items-center justify-between">
                                                            <x-input-label for="rtd_name_of_person" value="Name of Person" />
                                                            <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                        </div>
                                                        <x-text-input id="rtd_name_of_person" name="name_of_person" type="text" class="mt-1 block w-full text-sm" placeholder="Enter name of person receiving materials" required />
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center justify-between">
                                                            <x-input-label for="rtd_needed_for" value="Needed For / Needed Forheads" />
                                                            <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                        </div>
                                                        <x-text-input id="rtd_needed_for" name="needed_for" type="text" class="mt-1 block w-full text-sm" placeholder="Enter purpose or reason for delivery" required />
                                                    </div>
                                                    <div>
                                                        <div class="flex items-center justify-between">
                                                            <x-input-label for="rtd_delivery_address" value="Delivery Address" />
                                                            <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                        </div>
                                                        <textarea id="rtd_delivery_address" name="delivery_address" rows="3" class="js-delivery-address-autocomplete mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm text-sm" placeholder="Enter complete delivery address" required>{{ old('delivery_address', $project->lead?->address) }}</textarea>
                                                    </div>
                                                </div>

                                                <div class="flex-shrink-0 flex items-center justify-end gap-3 p-4 border-t border-gray-200 bg-gray-50">
                                                    <button type="button" @click="closeDispatchChallanModal()" class="inline-flex items-center px-4 py-2 border border-gray-300 bg-white font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                                                        Cancel
                                                    </button>
                                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-slate-900 border border-transparent font-semibold text-xs text-white uppercase tracking-widest hover:bg-slate-800">
                                                        Confirm & Generate Challan
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                @endif

                                <div class="{{ $readyToDispatchPendingItems->isNotEmpty() ? 'mt-8' : 'mt-2' }}">
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                        <h4 class="text-sm font-bold text-gray-900">Dispatched Items</h4>
                                        <div class="text-xs text-gray-500">Shows all QA approved items already dispatched from this project.</div>
                                    </div>

                                    @if($readyToDispatchDispatchedItems->count() === 0)
                                        <p class="text-center py-6 text-gray-500 italic">No dispatched items yet.</p>
                                    @else
                                        <div class="mt-3 sm:hidden space-y-3">
                                            @foreach($readyToDispatchDispatchedItems as $material)
                                                <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <span class="inline-flex items-center bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">Dispatched</span>
                                                            <p class="mt-2 break-words text-sm font-semibold text-gray-900">{{ $material->material_name }}</p>
                                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500">
                                                                <span>Qty: {{ $formatMaterialQty($material->qty) }}</span>
                                                                <span>Unit: {{ $material->unit ?: '-' }}</span>
                                                            </div>
                                                            <p class="mt-1 text-xs text-gray-500">{{ $material->note ?: 'No note' }}</p>
                                                            <p class="mt-1 text-xs text-gray-500">Dispatched {{ $material->dispatched_at?->format('d M Y, h:i A') ?? '-' }}</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        <div class="hidden mt-3 overflow-hidden bg-white shadow sm:block sm:border border-gray-200">
                                            <div class="overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Status</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Material</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Qty</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Unit</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Note</th>
                                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Dispatched At</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-100">
                                                        @foreach($readyToDispatchDispatchedItems as $material)
                                                            <tr class="hover:bg-gray-50">
                                                                <td class="px-4 py-3">
                                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-semibold bg-emerald-100 text-emerald-700">Dispatched</span>
                                                                </td>
                                                                <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $material->material_name }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $formatMaterialQty($material->qty) }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $material->unit ?: '-' }}</td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">
                                                                    <div class="max-w-xs truncate" title="{{ $material->note ?: 'No note' }}">{{ $material->note ?: 'No note' }}</div>
                                                                </td>
                                                                <td class="px-4 py-3 text-sm text-gray-700">{{ $material->dispatched_at?->format('d M Y, h:i A') ?? '-' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        @if($readyToDispatchDispatchedItems->hasPages())
                                            <div class="mt-4">
                                                {{ $readyToDispatchDispatchedItems->links() }}
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            </div>

                            <div class="bg-white border border-gray-200 p-4 sm:p-6">
                                <h3 class="text-lg font-bold text-gray-900">Challan History</h3>
                                <p class="mt-1 text-sm text-gray-600">View challans created from dispatched items and update their delivery status.</p>

                                @if($challans->count() === 0)
                                    <p class="py-8 text-center text-gray-500 italic">No challans created yet.</p>
                                @else
                                    <div class="mt-4 sm:hidden space-y-3">
                                        @foreach($challans as $challan)
                                            @php
                                                $challanMaterialIds = collect($challan->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
                                                $challanItems = $project->materials->whereIn('id', $challanMaterialIds)->whereNull('source_drawing_id')->values();
                                                $challanUnits = $challanItems->pluck('unit')->filter()->unique()->values()->join(', ');
                                            @endphp
                                            <div class="border border-gray-200 bg-white p-4 shadow-sm">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Challan No</p>
                                                        <p class="mt-1 break-words text-sm font-semibold text-gray-900">{{ $challan->challan_no }}</p>
                                                        <p class="mt-2 text-xs text-gray-500">Created {{ $challan->created_at->format('d M Y, h:i A') }}</p>
                                                        <div class="mt-2 space-y-1 text-xs text-gray-500">
                                                            <p>Items: {{ $challanItems->count() }}</p>
                                                            <p>Total Qty: {{ $formatMaterialQty($challanItems->sum(fn ($material) => (float) $material->qty)) }}</p>
                                                            <p>Units: {{ $challanUnits !== '' ? $challanUnits : '-' }}</p>
                                                        </div>
                                                    </div>
                                                    <span class="inline-flex shrink-0 items-center px-2.5 py-1 text-xs font-semibold bg-slate-100 text-slate-700">{{ str($challan->status)->headline() }}</span>
                                                </div>
                                                <div class="mt-4 grid grid-cols-2 gap-2">
                                                    <button type="button" @click="openChallanModal({{ (int) $challan->id }})" class="inline-flex items-center justify-center border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                        View
                                                    </button>
                                                    <a href="{{ route('projects.challans.print', [$project, $challan]) }}" target="_blank" class="inline-flex items-center justify-center bg-gray-700 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-800">
                                                        Print
                                                    </a>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <div class="hidden mt-4 overflow-hidden bg-white shadow sm:block sm:border border-gray-200">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Challan No</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Status</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Items</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Total Qty</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Units</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Created</th>
                                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase tracking-wider text-white">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    @foreach($challans as $challan)
                                                        @php
                                                            $challanMaterialIds = collect($challan->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
                                                            $challanItems = $project->materials->whereIn('id', $challanMaterialIds)->whereNull('source_drawing_id')->values();
                                                            $challanUnits = $challanItems->pluck('unit')->filter()->unique()->values()->join(', ');
                                                        @endphp
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $challan->challan_no }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ str($challan->status)->headline() }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $challanItems->count() }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $formatMaterialQty($challanItems->sum(fn ($material) => (float) $material->qty)) }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $challanUnits !== '' ? $challanUnits : '-' }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $challan->created_at->format('d M Y, h:i A') }}</td>
                                                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                                                <button type="button" @click="openChallanModal({{ (int) $challan->id }})" class="inline-flex items-center px-3 py-1.5 border border-gray-300 bg-white text-xs font-semibold text-gray-700 hover:bg-gray-50">
                                                                    View
                                                                </button>
                                                                <a href="{{ route('projects.challans.print', [$project, $challan]) }}" target="_blank" class="ml-2 inline-flex items-center px-3 py-1.5 bg-gray-700 text-xs font-semibold text-white hover:bg-gray-800">
                                                                    Print
                                                                </a>
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    @if($challans->hasPages())
                                        <div class="mt-4">
                                            {{ $challans->links() }}
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if($challans->count() > 0)
                                @foreach($challans as $challan)
                                    @php
                                        $challanProductionIdsForModal = collect($challan->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
                                        $challanItemsForModal = $project->materials
                                            ->whereIn('id', $challanProductionIdsForModal)
                                            ->whereNull('source_drawing_id')
                                            ->values();
                                    @endphp

                                    <div
                                        x-show="challanModalOpen && challanModalId === {{ (int) $challan->id }}"
                                        x-cloak
                                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                        aria-modal="true"
                                        role="dialog"
                                    >
                                        <div class="absolute inset-0 bg-black/50" @click="closeChallanModal()"></div>

                                        <div class="relative w-full max-w-4xl bg-white shadow-xl border border-gray-200 overflow-hidden flex flex-col max-h-[90vh]">
                                            <div class="flex-shrink-0 flex items-start justify-between gap-4 p-4 border-b border-gray-200 bg-gray-50">
                                                <div>
                                                    <div class="text-xs font-semibold text-gray-500">Challan</div>
                                                    <div class="text-lg font-extrabold text-gray-900">{{ $challan->challan_no }}</div>
                                                    <div class="mt-1 text-xs text-gray-600">Created {{ $challan->created_at->format('d M Y, h:i A') }}</div>
                                                </div>
                                                <button type="button" @click="closeChallanModal()" class="inline-flex items-center justify-center h-9 w-9 border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
                                                    <span class="sr-only">Close</span>
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                        <path d="M6.225 4.811a1 1 0 011.414 0L12 9.172l4.361-4.361a1 1 0 111.414 1.414L13.414 10.586l4.361 4.361a1 1 0 01-1.414 1.414L12 12l-4.361 4.361a1 1 0 01-1.414-1.414l4.361-4.361-4.361-4.361a1 1 0 010-1.414z"/>
                                                    </svg>
                                                </button>
                                            </div>

                                            <div class="flex-1 min-h-0 overflow-y-auto p-4 space-y-6">
                                                <div class="overflow-hidden bg-white shadow sm:border border-gray-200">
                                                    <div class="overflow-x-auto">
                                                        <table class="min-w-full divide-y divide-gray-200">
                                                            <thead class="bg-gray-50">
                                                                <tr>
                                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Material</th>
                                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Qty</th>
                                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Unit</th>
                                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Note</th>
                                                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white">Dispatched</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="bg-white divide-y divide-gray-100">
                                                                @foreach($challanItemsForModal as $material)
                                                                    <tr class="hover:bg-gray-50">
                                                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $material->material_name }}</td>
                                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $formatMaterialQty($material->qty) }}</td>
                                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $material->unit ?: '-' }}</td>
                                                                        <td class="px-4 py-3 text-sm text-gray-700">
                                                                            <div class="max-w-xs truncate" title="{{ $material->note ?: 'No note' }}">{{ $material->note ?: 'No note' }}</div>
                                                                        </td>
                                                                        <td class="px-4 py-3 text-sm text-gray-700">{{ $material->dispatched_at?->format('d M Y, h:i A') ?? '-' }}</td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <form method="POST" action="{{ route('projects.challans.update', [$project, $challan]) }}" target="_self" class="space-y-4" enctype="multipart/form-data" data-challan-update-url="{{ route('projects.challans.update', [$project, $challan]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="tab" value="ready_to_dispatch">
                                                    <input type="hidden" name="challans_page" value="{{ request('challans_page', 1) }}">

                                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div class="md:col-span-2">
                                                            <x-input-label for="challan_status_{{ (int) $challan->id }}" value="Delivery Status" />
                                                            <select
                                                                id="challan_status_{{ (int) $challan->id }}"
                                                                name="status"
                                                                class="js-challan-status mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                                                data-update-url="{{ route('projects.challans.update', [$project, $challan]) }}"
                                                            >
                                                                @foreach(['created' => 'Created', 'ongoing' => 'Ongoing', 'delivered' => 'Delivered'] as $key => $label)
                                                                    <option value="{{ $key }}" @selected(old('status', $challan->status ?? 'created') === $key)>{{ $label }}</option>
                                                                @endforeach
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <div class="flex items-center justify-between">
                                                                <x-input-label for="name_of_person_{{ (int) $challan->id }}" value="Name of Person" />
                                                                <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                            </div>
                                                            <x-text-input id="name_of_person_{{ (int) $challan->id }}" name="name_of_person" type="text" class="mt-1 block w-full text-sm" value="{{ old('name_of_person', $challan->name_of_person) }}" placeholder="Person receiving materials" required />
                                                        </div>
                                                        <div>
                                                            <div class="flex items-center justify-between">
                                                                <x-input-label for="needed_for_{{ (int) $challan->id }}" value="Needed For/Needed Heads" />
                                                                <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                            </div>
                                                            <x-text-input id="needed_for_{{ (int) $challan->id }}" name="needed_for" type="text" class="mt-1 block w-full text-sm" value="{{ old('needed_for', $challan->needed_for) }}" placeholder="Purpose / Reason for delivery" required />
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <div class="flex items-center justify-between">
                                                                <x-input-label for="delivery_address_{{ (int) $challan->id }}" value="Delivery Address" />
                                                                <span class="text-xs text-rose-500 font-medium">* Required</span>
                                                            </div>
                                                            <textarea id="delivery_address_{{ (int) $challan->id }}" name="delivery_address" rows="2" class="js-delivery-address-autocomplete mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm text-sm" placeholder="Complete delivery address" required>{{ old('delivery_address', $challan->delivery_address) }}</textarea>
                                                        </div>
                                                        <div class="md:col-span-2">
                                                            <div class="flex items-center justify-between mb-1">
                                                                <x-input-label for="remarks_{{ (int) $challan->id }}" value="Remarks" />
                                                                <x-speech-to-text target="remarks_{{ (int) $challan->id }}" />
                                                            </div>
                                                            <textarea id="remarks_{{ (int) $challan->id }}" name="remarks" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('remarks', $challan->remarks) }}</textarea>
                                                        </div>
                                                    </div>

                                                    <div class="flex flex-wrap items-center justify-end gap-3">
                                                        <a href="{{ route('projects.challans.print', [$project, $challan]) }}" target="_blank" class="inline-flex items-center px-4 py-2 bg-gray-700 border border-transparent font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-800">
                                                            Print Challan
                                                        </a>
                                                        <x-primary-button>Save Details</x-primary-button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>

                    {{-- <div x-show="tab === 'installation'" x-cloak>
                        @php
                            $installationValues = [
                                'site_preparation_status' => old('site_preparation_status', $project->site_preparation_status ?? 'not_started'),
                                'fixing_welding_status' => old('fixing_welding_status', $project->fixing_welding_status ?? 'not_started'),
                                'testing_commissioning_status' => old('testing_commissioning_status', $project->testing_commissioning_status ?? 'not_started'),
                                'final_inspection_status' => old('final_inspection_status', $project->final_inspection_status ?? 'ready'),
                                'client_handover_status' => old('client_handover_status', $project->client_handover_status ?? 'pending'),
                            ];

                            $installationComplete = $installationValues['site_preparation_status'] === 'done'
                                && $installationValues['fixing_welding_status'] === 'done'
                                && $installationValues['testing_commissioning_status'] === 'pass'
                                && $installationValues['final_inspection_status'] === 'passed'
                                && $installationValues['client_handover_status'] === 'completed';
                        @endphp

                        <div class="bg-white border border-gray-200 p-6">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900">{{ $tabLabels['installation'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-600">Update installation checklist. When all steps are positive, you can complete the order.</p>
                                   <p class="mt-2 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-3 py-1.5 inline-block"> Complete the current stage before selecting the next.</p>
                                </div>
                                @if($project->installation_completed_at)
                                    <div class="text-right">
                                        <div class="text-xs font-semibold text-gray-500">Completed At</div>
                                        <div class="text-sm font-extrabold text-gray-900">{{ $project->installation_completed_at->format('d M Y, h:i A') }}</div>
                                    </div>
                                @endif
                            </div>

                            <form id="installation-checklist-form" method="POST" action="{{ route('projects.installation.update', $project) }}" target="_self" class="mt-5">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="tab" value="installation">

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="site_preparation_status" value="Site Preparation" />
                                        <select id="site_preparation_status" name="site_preparation_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="not_started" @selected($installationValues['site_preparation_status'] === 'not_started')>Not started</option>
                                            <option value="in_progress" @selected($installationValues['site_preparation_status'] === 'in_progress')>In progress</option>
                                            <option value="done" @selected($installationValues['site_preparation_status'] === 'done')>Done</option>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label for="fixing_welding_status" value="Fixing & Welding" />
                                        <select id="fixing_welding_status" name="fixing_welding_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="not_started" @selected($installationValues['fixing_welding_status'] === 'not_started')>Not started</option>
                                            <option value="in_progress" @selected($installationValues['fixing_welding_status'] === 'in_progress')>In progress</option>
                                            <option value="done" @selected($installationValues['fixing_welding_status'] === 'done')>Done</option>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label for="testing_commissioning_status" value="Testing & Commissioning" />
                                        <select id="testing_commissioning_status" name="testing_commissioning_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="not_started" @selected($installationValues['testing_commissioning_status'] === 'not_started')>Not started</option>
                                            <option value="pass" @selected($installationValues['testing_commissioning_status'] === 'pass')>Pass</option>
                                            <option value="fail" @selected($installationValues['testing_commissioning_status'] === 'fail')>Fail</option>
                                        </select>
                                    </div>

                                    <div>
                                        <x-input-label for="final_inspection_status" value="Final Inspection" />
                                        <select id="final_inspection_status" name="final_inspection_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="ready" @selected($installationValues['final_inspection_status'] === 'ready')>Ready</option>
                                            <option value="passed" @selected($installationValues['final_inspection_status'] === 'passed')>Passed</option>
                                            <option value="failed" @selected($installationValues['final_inspection_status'] === 'failed')>Failed</option>
                                        </select>
                                    </div>

                                    <div class="md:col-span-2">
                                        <x-input-label for="client_handover_status" value="Client Handover" />
                                        <select id="client_handover_status" name="client_handover_status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                            <option value="pending" @selected($installationValues['client_handover_status'] === 'pending')>Pending</option>
                                            <option value="completed" @selected($installationValues['client_handover_status'] === 'completed')>Completed</option>
                                        </select>
                                    </div>
                                </div>

                                <p class="mt-4 text-xs text-gray-500">Changes are saved automatically.</p>
                            </form>

                            <div class="mt-6 border-t border-gray-100 pt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div class="text-sm text-gray-600">
                                    <span class="font-semibold">Complete Order</span> is enabled only when all steps are positive.
                                </div>
                                <form id="complete-installation-form" method="POST" action="{{ route('projects.installation.complete', $project) }}" target="_self">
                                    @csrf
                                    <input type="hidden" name="tab" value="installation">
                                    <button id="complete-order-btn" type="submit" {{ $installationComplete ? '' : 'disabled' }} class="inline-flex items-center px-4 py-2 bg-emerald-600 border border-transparent font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                        Order Completed
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div x-show="tab === 'completed'" x-cloak>
                        @php
                            $completedChallan = $project->challans->sortByDesc('created_at')->first();
                            $completedChallanProductionIds = collect($completedChallan?->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
                            $completedProductions = $project->materials
                                ->whereIn('id', $completedChallanProductionIds)
                                ->whereNull('source_drawing_id')
                                ->values();
                            $completedMaterials = $project->materials
                                ->sortByDesc('created_at')
                                ->reject(fn ($material) => $material->source_drawing_id !== null);
                        @endphp

                        <div class="bg-white border border-gray-200 p-6">
                            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-gray-900">{{ $tabLabels['completed'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-600">Order is completed. View details or print important summary.</p>
                                </div>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                    <button type="button" @click="completedDetailsOpen = !completedDetailsOpen" class="inline-flex w-full items-center justify-center border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 hover:bg-gray-50">
                                        <span x-text="completedDetailsOpen ? 'Hide Details' : 'View Details'"></span>
                                    </button>
                                    <a href="{{ route('projects.completed.print', $project) }}" target="_blank" class="inline-flex w-full items-center justify-center border border-transparent bg-gray-700 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-gray-800">
                                        Print Summary
                                    </a>
                                </div>
                            </div>

                            <div x-show="completedDetailsOpen" x-cloak class="mt-6">
                            <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-6">
                                <div class="border border-gray-200 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Project</div>
                                    <div class="mt-2 space-y-2 text-sm text-gray-700">
                                        <p><span class="font-semibold">Name:</span> {{ $project->name }}</p>
                                        <p><span class="font-semibold">Client:</span> {{ $project->client_name ?: 'N/A' }}</p>
                                        <p><span class="font-semibold">Reference:</span> {{ $project->reference_no }}</p>
                                        <p><span class="font-semibold">Manager:</span> {{ $project->manager?->name ?? 'Unassigned' }}</p>
                                        <p><span class="font-semibold">Status:</span> {{ str($project->status)->headline() }}</p>
                                        <p><span class="font-semibold">Stage:</span> {{ $project->stage_label }}</p>
                                        <p><span class="font-semibold">Completed At:</span> {{ $project->installation_completed_at?->format('d M Y, h:i A') ?? '-' }}</p>
                                    </div>
                                </div>

                                <div class="border border-gray-200 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Delivery / Challan</div>
                                    @if(!$completedChallan)
                                        <p class="mt-2 text-sm text-gray-600">No challan found.</p>
                                    @else
                                        <div class="mt-2 space-y-2 text-sm text-gray-700">
                                            <p><span class="font-semibold">Challan No:</span> {{ $completedChallan->challan_no }}</p>
                                            <p><span class="font-semibold">Status:</span> {{ str($completedChallan->status ?? 'created')->headline() }}</p>
                                            <p><span class="font-semibold">Dispatch Date:</span> {{ $completedChallan->dispatch_date?->format('d M Y') ?? '-' }}</p>
                                            <p><span class="font-semibold">Transport:</span> {{ $completedChallan->transport_name ?: '-' }}</p>
                                            <p><span class="font-semibold">Vehicle:</span> {{ $completedChallan->vehicle_no ?: '-' }}</p>
                                            <p><span class="font-semibold">LR No:</span> {{ $completedChallan->lr_no ?: '-' }}</p>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6 border border-gray-200 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Installation Checklist</div>
                                <div class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3 text-sm text-gray-700">
                                    <p><span class="font-semibold">Site Preparation:</span> {{ str($project->site_preparation_status ?: 'not_started')->replace('_', ' ')->headline() }}</p>
                                    <p><span class="font-semibold">Fixing & Welding:</span> {{ str($project->fixing_welding_status ?: 'not_started')->replace('_', ' ')->headline() }}</p>
                                    <p><span class="font-semibold">Testing & Commissioning:</span> {{ str($project->testing_commissioning_status ?: 'not_started')->replace('_', ' ')->headline() }}</p>
                                    <p><span class="font-semibold">Final Inspection:</span> {{ str($project->final_inspection_status ?: 'ready')->replace('_', ' ')->headline() }}</p>
                                    <p><span class="font-semibold">Client Handover:</span> {{ str($project->client_handover_status ?: 'pending')->replace('_', ' ')->headline() }}</p>
                                </div>
                            </div>

                            <div class="mt-6">
                                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Materials</div>
                                @if($completedMaterials->isEmpty())
                                    <p class="mt-2 text-sm text-gray-600">No materials.</p>
                                @else
                                    <div class="mt-3 overflow-hidden bg-white shadow sm:border border-gray-200">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Material</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Qty</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    @foreach($completedMaterials as $material)
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ $material->material_name }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ rtrim(rtrim(number_format((float) $material->qty, 2, '.', ''), '0'), '.') }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $material->note ?: '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6">
                                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Delivered Items</div>
                                @if(!$completedChallan || $completedProductions->isEmpty())
                                    <p class="mt-2 text-sm text-gray-600">No delivered items.</p>
                                @else
                                    <div class="mt-3 overflow-hidden bg-white shadow sm:border border-gray-200">
                                        <div class="overflow-x-auto">
                                            <table class="min-w-full divide-y divide-gray-200">
                                                <thead class="bg-gray-50">
                                                    <tr>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Material</th>
                                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Note</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white divide-y divide-gray-100">
                                                    @foreach($completedProductions as $production)
                                                        <tr class="hover:bg-gray-50">
                                                            <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ $production->material_name }}</td>
                                                            <td class="px-4 py-3 text-sm text-gray-700">{{ $production->note ?: '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            </div>
                        </div>
                    </div> --}}

                    <div x-show="tab === 'activity'" x-cloak>
                        <div class="flow-root">
                            <ul role="list" class="-mb-8">
                                @forelse($project->activities->sortByDesc('occurred_at') as $activity)
                                    <li>
                                        <div class="relative pb-8">
                                            @if(!$loop->last)
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                            @endif
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 bg-gray-400 flex items-center justify-center ring-8 ring-white">
                                                        @if($activity->type === 'status_change')
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
                                                        @elseif(in_array($activity->type, ['drawing', 'material', 'production'], true))
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h10M7 11h10M7 15h6M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z" /></svg>
                                                        @elseif($activity->type === 'note')
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" /></svg>
                                                        @else
                                                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                                        @endif
                                                    </span>
                                                </div>
                                                <div class="flex min-w-0 flex-1 justify-between space-x-4 pt-1.5">
                                                    <div>
                                                        <p class="text-sm text-gray-500">{{ $activity->description }} <span class="font-medium text-gray-900">by {{ $activity->user->name ?? 'System' }}</span></p>
                                                    </div>
                                                    <div class="whitespace-nowrap text-right text-sm text-gray-500">
                                                        <time datetime="{{ $activity->occurred_at }}">{{ $activity->occurred_at->format('M d, H:i') }}</time>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                @empty
                                    <p class="text-center py-10 text-gray-500 italic">No project activity yet.</p>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    <div x-show="tab === 'comments'" x-cloak>
                        <div class="space-y-4 mb-6">
                            @foreach($project->comments->sortByDesc('created_at') as $comment)
                                <div class="bg-gray-50 p-4">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="font-bold text-sm text-gray-900">{{ $comment->user->name }}</span>
                                        <span class="text-xs text-gray-500">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p class="text-sm text-gray-700">{{ $comment->comment }}</p>
                                </div>
                            @endforeach
                        </div>

                        <form id="project-comment-form" action="{{ route('projects.comment', $project) }}" method="POST">
                            @csrf
                            <input type="hidden" name="tab" value="comments">
                            <div>
                                <x-input-label for="comment" value="Add Internal Comment" />
                                <textarea id="comment" name="comment" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required minlength="2" maxlength="1000"></textarea>
                            </div>
                            <div class="mt-2 flex justify-end">
                            <x-primary-button>Post Comment</x-primary-button>
                        </div>
                    </form>
                </div>

                    <div x-show="tab === 'tasks'" x-cloak>
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Project Tasks</h3>
                                <p class="text-sm text-gray-500">Tasks created for this project.</p>
                            </div>
                            <span class="text-sm text-gray-500">{{ $projectTasks->count() }} task{{ $projectTasks->count() === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 overflow-x-auto rounded-lg border border-gray-200">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Task Title</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Assignee</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Blocker</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Reminder</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Repeat</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @forelse($projectTasks as $task)
                                        @php
                                            $blockerMeta = match ($task->blocker_level) {
                                                'high' => ['label' => 'High', 'class' => 'bg-rose-100 text-rose-800'],
                                                'medium' => ['label' => 'Medium', 'class' => 'bg-amber-100 text-amber-800'],
                                                'low' => ['label' => 'Low', 'class' => 'bg-emerald-100 text-emerald-800'],
                                                default => null,
                                            };
                                        @endphp
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 font-medium text-gray-900">{{ $task->task_name }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $task->staff?->name ?? '-' }}</td>
                                            <td class="px-4 py-3 text-gray-600">{{ $task->due_date?->format('d M, Y') ?? '-' }}</td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($task->has_blocker)
                                                    <a href="{{ route('task-management.show', $task) }}" class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $blockerMeta['class'] ?? 'bg-slate-100 text-slate-700' }}">
                                                        {{ $blockerMeta['label'] ?? 'Blocker' }}
                                                    </a>
                                                    @if($task->blocker_description)
                                                        <div class="mt-1 max-w-[18rem] text-xs leading-5 text-gray-500 whitespace-pre-line">
                                                            {{ $task->blocker_description }}
                                                        </div>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($task->has_reminder)
                                                    <span class="inline-flex rounded-full bg-cyan-50 px-2.5 py-1 text-xs font-semibold text-cyan-700">
                                                        {{ $task->reminder_type ? \Illuminate\Support\Str::headline($task->reminder_type) : 'Enabled' }}
                                                        @if($task->reminder_days_before !== null)
                                                            - {{ $task->reminder_days_before }} day{{ $task->reminder_days_before === 1 ? '' : 's' }} before
                                                        @endif
                                                    </span>
                                                    @if($task->reminder_date)
                                                        <div class="mt-1 text-xs text-gray-400">On {{ $task->reminder_date->format('d M, Y') }}</div>
                                                    @endif
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                @if($task->is_repeated)
                                                    <span class="inline-flex rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                                        {{ \App\Models\StaffTask::repeatTypeOptions()[$task->repeat_type] ?? ($task->repeat_type ? \Illuminate\Support\Str::headline($task->repeat_type) : 'Repeat') }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-gray-600">
                                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold bg-slate-100 text-slate-700">
                                                    {{ \App\Models\StaffTask::statusOptions()[$task->status] ?? ucfirst((string) $task->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500">No tasks have been created for this project yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="materialEditOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
                        <div class="relative min-h-full flex items-center justify-center p-4">
                            <div x-show="materialEditOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/70" @click="closeMaterialEdit()"></div>

                            <div x-show="materialEditOpen" x-transition class="relative w-full max-w-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <h3 class="text-base font-semibold text-gray-900">Edit Material</h3>
                                    <button type="button" @click="closeMaterialEdit()" class="text-gray-400 hover:text-gray-600">
                                        <span class="sr-only">Close</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <form id="edit-material-form" method="POST" :action="materialEdit.updateUrl" enctype="multipart/form-data" class="p-4" x-data="{ fileName: '' }">
    @csrf
    @method('PATCH')
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-start">
        <input type="hidden" name="product_id" x-model="materialEdit.product_id">
        <div>
            <x-input-label for="edit_material_name" value="Material Name" />
            <x-text-input id="edit_material_name" name="material_name" class="mt-1 block w-full" x-model="materialEdit.material_name" required minlength="2" maxlength="191" />
        </div>
        <div>
            <x-input-label for="edit_qty" value="Qty" />
            <x-text-input id="edit_qty" name="qty" type="number" step="any" class="mt-1 block w-full" min="0.01" required x-model="materialEdit.qty" @input="materialEdit.qty = materialEdit.qty === '' ? '' : (Number(materialEdit.qty) < 0 ? '0.01' : materialEdit.qty)" />
        </div>
        <div>
            <x-input-label for="edit_unit" value="Unit" />
            <select id="edit_unit" name="unit" class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model="materialEdit.unit">
                <option value="">Select unit</option>
                <template x-for="unitOption in unitOptionsFor(materialEdit.unit)" :key="unitOption">
                    <option :value="unitOption" x-text="unitOption"></option>
                </template>
            </select>
        </div>
        <div>
            <x-input-label for="edit_rate" value="Rate" />
            <x-text-input id="edit_rate" name="rate" type="number" step="0.01" class="mt-1 block w-full" min="0" x-model="materialEdit.rate" />
        </div>
        @if($project->contractor_id)
            <div>
                <x-input-label for="edit_contractor_price" value="Contractor Price" />
                <x-text-input id="edit_contractor_price" name="contractor_price" type="number" step="0.01" class="mt-1 block w-full" min="0" x-model="materialEdit.contractor_price" placeholder="Optional" />
            </div>
        @endif
        <div>
            <x-input-label for="edit_gst" value="GST %" />
            <select id="edit_gst" name="gst" class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" x-model.number="materialEdit.gst">
                <template x-for="gstRate in gstOptionsFor(materialEdit.gst)" :key="gstRate">
                    <option :value="gstRate" x-text="`${Number(gstRate).toFixed(2)}%`"></option>
                </template>
            </select>
        </div>
        <div class="md:col-span-2">
            <x-input-label for="edit_material_note" value="Note" />
            <x-text-input id="edit_material_note" name="note" class="mt-1 block w-full" x-model="materialEdit.note" maxlength="2000" />
        </div>

        <div class="md:col-span-1">
            <x-input-label for="edit_material_attachment" value="Replace Attachment (optional)" />

            <label for="edit_material_attachment"
                class="mt-1 flex items-center justify-between gap-3 border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition min-h-[42px]">

                <span class="text-sm font-semibold text-indigo-800 truncate max-w-[150px]"
                      x-text="fileName ? fileName : 'Choose file'">
                </span>

                <span class="text-xs text-indigo-700 shrink-0" x-show="!fileName">
                    Browse
                </span>
            </label>

            <input id="edit_material_attachment"
                   type="file"
                   name="attachment"
                   class="hidden"
                   data-max-size="20971520"
                   accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.mp4,.mov,.avi,.mkv,.mp3,.wav"
                   @change="fileName = $event.target.files[0].name" />
            <p id="edit-material-attachment-error" class="hidden text-xs text-rose-600 mt-1"></p>
        </div>
    </div>

    <div class="mt-5 flex justify-end gap-2">
        <button type="button" @click="closeMaterialEdit(); fileName = ''" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 transition">
            Cancel
        </button>
        <x-primary-button>Save</x-primary-button>
    </div>
</form>
                            </div>
                        </div>
                    </div>

                    <div x-show="productionEditOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
                        <div class="relative min-h-full flex items-center justify-center p-4">
                            <div x-show="productionEditOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/70" @click="closeProductionEdit()"></div>

                            <div x-show="productionEditOpen" x-transition class="relative w-full max-w-2xl bg-white shadow-xl border border-gray-200 overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <h3 class="text-base font-semibold text-gray-900">Edit Production Update</h3>
                                    <button type="button" @click="closeProductionEdit()" class="text-gray-400 hover:text-gray-600">
                                        <span class="sr-only">Close</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <form id="edit-production-form" method="POST" :action="productionEdit.updateUrl" enctype="multipart/form-data" class="p-4">
                                    @csrf
                                    @method('PATCH')

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <x-input-label for="edit_production_title" value="Title" />
                                            <x-text-input id="edit_production_title" name="title" class="mt-1 block w-full" x-model="productionEdit.title" required minlength="2" maxlength="191" />
                                        </div>
                                        <div class="md:col-span-2">
                                            <x-input-label for="edit_production_note" value="Note" />
                                            <x-text-input id="edit_production_note" name="note" class="mt-1 block w-full" x-model="productionEdit.note" maxlength="1000" />
                                        </div>
                                        <div>
                                            <x-input-label for="edit_production_attachment" value="Replace Attachment (optional)" />
                                            <input id="edit_production_attachment" type="file" name="attachment" class="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:py-2 file:px-3 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" data-max-size="20971520" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.rar,.mp4,.mov,.avi,.mkv,.mp3,.wav" />
                                            <p id="edit-production-attachment-error" class="hidden text-xs text-rose-600 mt-1"></p>
                                        </div>
                                    </div>

                                    <div class="mt-5 flex justify-end gap-2">
                                        <button type="button" @click="closeProductionEdit()" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50 active:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            Cancel
                                        </button>
                                        <x-primary-button>Save</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div x-show="attachmentPreviewOpen" x-cloak class="fixed inset-0 z-50 overflow-y-auto" aria-modal="true" role="dialog">
                        <div class="relative min-h-full flex items-center justify-center p-4">
                            <div x-show="attachmentPreviewOpen" x-transition.opacity class="fixed inset-0 bg-gray-900/70" @click="closeAttachmentPreview()"></div>

                            <div x-show="attachmentPreviewOpen" x-transition class="relative w-full max-w-5xl h-[85vh] bg-white shadow-xl border border-gray-200 flex flex-col overflow-hidden">
                                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                                    <div class="min-w-0">
                                        <h3 class="text-base font-semibold text-gray-900 truncate" x-text="attachmentPreviewName"></h3>
                                    </div>
                                    <button type="button" @click="closeAttachmentPreview()" class="text-gray-400 hover:text-gray-600">
                                        <span class="sr-only">Close</span>
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="flex-1 bg-gray-100 flex items-center justify-center p-4 overflow-hidden">
                                    <template x-if="isImagePreview()">
                                        <img :src="attachmentPreviewUrl" class="w-full h-full max-h-full max-w-full object-contain shadow-sm" />
                                    </template>
                                    <template x-if="!isImagePreview()">
                                        <iframe :src="attachmentPreviewUrl" class="w-full h-full" frameborder="0"></iframe>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('installation-checklist-form');
            const selects = form.querySelectorAll('select');

            // 1. Target your Restart button
            const restartBtn = document.querySelector('.restart-button-class');

            if (restartBtn) {
                restartBtn.addEventListener('click', function() {
                    // Clear validation messages
                    document.querySelectorAll('.validation-msg').forEach(span => {
                        span.textContent = '';
                        span.classList.add('hidden');
                    });

                    // Ensure you are clearing the correct input field name
                    // If it's a hidden input inside the form:
                    const failReasonInput = document.getElementsByName('installation_fail_reason')[0];
                    if (failReasonInput) {
                        failReasonInput.value = '';
                    }

                    // Reset the oldValue memory
                    selects.forEach(select => {
                        select.dataset.oldValue = select.value;
                    });

                    // Close SweetAlerts
                    if (typeof Swal !== 'undefined') {
                        Swal.close();
                    }

                    // Optional: If you want to refresh the state from the server
                    // window.location.reload();
                });
            }

            // Existing validation logic...
            selects.forEach(select => {
                select.addEventListener('change', function () {
                    const errorSpan = this.parentElement.querySelector('.validation-msg');

                    // If the user is moving back to a previous stage (Restarting logic)
                    // we hide errors globally to allow the new selection
                    document.querySelectorAll('.validation-msg').forEach(s => s.classList.add('hidden'));

                    // Your existing logic for sequence check here...
                });
            });
        });
        document.addEventListener('DOMContentLoaded', function () {
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
            const shouldPromptCompletedAmc = @js($showCompletedAmcPrompt);
            const projectPageRoot = document.querySelector('[data-completed-amc-url][data-project-name]');

            @if(session('success'))
            if (window.Swal) {
                Swal.fire({
                    title: 'Success',
                    text: @js(session('success')),
                    icon: 'success',
                    confirmButtonColor: '#C22032',
                    confirmButtonText: 'OK'
                });
            } else {
                alert(@js(session('success')));
            }
            @endif

            @if(session('error'))
            if (window.Swal) {
                Swal.fire({
                    title: 'Error',
                    text: @js(session('error')),
                    icon: 'error',
                    confirmButtonColor: '#C22032',
                    confirmButtonText: 'OK'
                });
            } else {
                alert(@js(session('error')));
            }
            @endif

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

            function incrementCompletedAmcCount() {
                if (!projectPageRoot) return;
                const currentCount = Number(projectPageRoot.getAttribute('data-amc-count') || 0);
                projectPageRoot.setAttribute('data-amc-count', String(currentCount + 1));
            }

            function promptCompletedStageAmcOnProjectPage() {
                if (!projectPageRoot || !window.Swal) return Promise.resolve();

                const existingAmcCount = Number(projectPageRoot.getAttribute('data-amc-count') || 0);
                if (existingAmcCount > 0 || completedStageAmcPlans.length === 0) {
                    if (completedStageAmcPlans.length === 0) {
                        return Swal.fire({
                            title: 'Project marked completed',
                            text: 'No active 1-year AMC plans are available right now.',
                            icon: 'info',
                            confirmButtonColor: '#C22032',
                            confirmButtonText: 'OK'
                        });
                    }

                    return Promise.resolve();
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
                    text: `Project "${projectPageRoot.getAttribute('data-project-name') || 'Project'}" is now completed. Choose an AMC plan.`,
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

                    return createCompletedStageAmc(projectPageRoot.getAttribute('data-completed-amc-url'), { amc_plan_id: result.value })
                        .then((data) => {
                            incrementCompletedAmcCount();

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
                        .catch((err) => {
                            return Swal.fire({
                                title: 'AMC creation failed',
                                text: err?.message || 'AMC creation failed.',
                                icon: 'error',
                                confirmButtonColor: '#C22032',
                                confirmButtonText: 'OK'
                            });
                        });
                });
            }

            const drawingInput = document.getElementById('drawing_files');
            const drawingName = document.getElementById('drawing-files-file-name');
            const drawingError = document.getElementById('drawing-files-error');
            const drawingForm = document.getElementById('drawing-upload-form');
            const localTimes = document.querySelectorAll('.local-time');

            const materialForm = document.getElementById('material-upload-form');

            const productionInput = document.getElementById('production_attachment');
            const productionName = document.getElementById('production-attachment-file-name');
            const productionError = document.getElementById('production-attachment-error');
            const productionPreview = document.getElementById('production-attachment-preview');
            const productionForm = document.getElementById('production-upload-form');
            const editMaterialForm = document.getElementById('edit-material-form');
            const editMaterialAttachmentInput = document.getElementById('edit_material_attachment');
            const editMaterialAttachmentError = document.getElementById('edit-material-attachment-error');
            const editProductionForm = document.getElementById('edit-production-form');
            const editProductionAttachmentInput = document.getElementById('edit_production_attachment');
            const editProductionAttachmentError = document.getElementById('edit-production-attachment-error');
            const commentForm = document.getElementById('project-comment-form');
            const contractorForm = document.querySelector('form[action="{{ route('projects.contractor.update', $project) }}"]');

            function showValidationPopup(title, text, icon = 'error') {
                if (window.Swal) {
                    Swal.fire({
                        title,
                        text,
                        icon,
                        confirmButtonColor: '#C22032',
                        confirmButtonText: 'OK'
                    });
                } else {
                    alert(text);
                }
            }

            function setError(errorEl, message) {
                if (!errorEl) return;
                errorEl.textContent = message || '';
                errorEl.classList.toggle('hidden', !message);
            }

            function updateName(input, nameEl) {
                if (!input || !nameEl) return;
                if (!input.files?.length) {
                    nameEl.textContent = 'No files selected.';
                    return;
                }
                if (input.multiple && input.files.length > 1) {
                    const names = Array.from(input.files).map(f => f.name).join(', ');
                    nameEl.textContent = `${input.files.length} files selected: ${names}`;
                    return;
                }
                nameEl.textContent = input.files[0].name;
            }

            function isImageFile(file) {
                if (!file) return false;
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                const mime = (file.type || '').toLowerCase();
                return ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext)
                    || ['image/jpeg', 'image/png', 'image/webp', 'image/gif'].includes(mime);
            }

            function updatePreview(input, imgEl) {
                if (!input || !imgEl) return;
                const file = input.files?.[0];
                if (!file || !isImageFile(file)) {
                    imgEl.src = '';
                    imgEl.classList.add('hidden');
                    return;
                }
                imgEl.src = URL.createObjectURL(file);
                imgEl.classList.remove('hidden');
            }

            const drawingDownloadLink = document.querySelector('[data-has-drawings]');
            if (drawingDownloadLink) {
                drawingDownloadLink.addEventListener('click', function (e) {
                    if (drawingDownloadLink.dataset.hasDrawings !== '1') {
                        e.preventDefault();
                        if (window.Swal) {
                            Swal.fire({
                                title: 'No data available',
                                text: "Data isn't available.",
                                icon: 'info',
                                confirmButtonColor: '#C22032',
                                confirmButtonText: 'OK'
                            });
                        }
                    }
                });
            }

            function validateMaxSize(input, errorEl) {
                if (!input || input.disabled) return true;
                const maxSize = Number(input.dataset.maxSize || 0);
                const files = Array.from(input.files || []);
                if (!files.length) {
                    setError(errorEl, '');
                    return true;
                }
                if (maxSize > 0 && files.some(f => f.size > maxSize)) {
                    const mb = (maxSize / (1024 * 1024)).toFixed(0);
                    const message = `File size is too large (max ${mb} MB).`;
                    setError(errorEl, message);
                    showValidationPopup('File Too Large', message);
                    input.value = '';
                    return false;
                }
                setError(errorEl, '');
                return true;
            }

            function validateDrawings(input, errorEl) {
                if (!input || input.disabled) return true;
                const files = Array.from(input.files || []);
                if (!files.length) {
                    setError(errorEl, '');
                    return true;
                }

                const allowedExt = new Set(['jpg', 'jpeg', 'png', 'pdf', 'dwg', 'dxf']);
                const isOk = files.every(file => {
                    const ext = (file.name.split('.').pop() || '').toLowerCase();
                    return allowedExt.has(ext);
                });

                if (!isOk) {
                    const message = 'Allowed files: JPG, JPEG, PNG, PDF, DWG, DXF.';
                    setError(errorEl, message);
                    showValidationPopup('Invalid File', message);
                    input.value = '';
                    return false;
                }

                return validateMaxSize(input, errorEl);
            }

            function bindFileInput(input, nameEl, errorEl, previewEl, validateFn) {
                if (!input) return;
                input.addEventListener('change', function () {
                    const isValid = (validateFn || validateMaxSize)(input, errorEl);
                    updateName(input, nameEl);
                    if (previewEl) updatePreview(input, previewEl);
                    if (isValid) {
                        setError(errorEl, '');
                    }
                });
            }

            bindFileInput(drawingInput, drawingName, drawingError, null, validateDrawings);
            bindFileInput(productionInput, productionName, productionError, productionPreview, validateMaxSize);
            bindFileInput(editMaterialAttachmentInput, null, editMaterialAttachmentError, null, validateMaxSize);
            bindFileInput(editProductionAttachmentInput, null, editProductionAttachmentError, null, validateMaxSize);

            if (drawingForm) {
                drawingForm.addEventListener('submit', function (e) {
                    if (!validateDrawings(drawingInput, drawingError)) e.preventDefault();
                });
            }
            if (materialForm) {
                materialForm.addEventListener('submit', function (e) {
                    let hasError = false;
                    materialForm.querySelectorAll('input[name^=\"materials[\"], select[name^=\"materials[\"]').forEach((field) => {
                        field.classList.remove('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                    });

                    const rows = materialForm.querySelectorAll('.material-row');
                    rows.forEach((row) => {
                        const materialNameField = row.querySelector('input[name*=\"[material_name]\"]');
                        const qtyField = row.querySelector('input[name*=\"[qty]\"]');
                        const unitField = row.querySelector('select[name*=\"[unit]\"], input[name*=\"[unit]\"]');
                        const rateField = row.querySelector('input[name*=\"[rate]\"]');
                        const gstField = row.querySelector('select[name*=\"[gst]\"], input[name*=\"[gst]\"]');
                        const noteField = row.querySelector('input[name*=\"[note]\"]');

                        [materialNameField, qtyField, unitField, rateField, gstField, noteField].forEach((field) => {
                            row.querySelectorAll(`label.error[for=\"${field?.name}\"]`).forEach((error) => error.remove());
                        });

                        if (materialNameField) {
                            const value = materialNameField.value.trim();
                            const materialNameError = row.querySelector('.material-name-error-slot');
                            if (value.length < 2 || value.length > 191) {
                                hasError = true;
                                materialNameField.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                                if (materialNameError) {
                                    materialNameError.textContent = value.length < 2 ? 'Material Name must be at least 2 characters.' : 'Material Name may not be greater than 191 characters.';
                                    materialNameError.classList.remove('hidden');
                                }
                            } else if (materialNameError) {
                                materialNameError.textContent = '';
                                materialNameError.classList.add('hidden');
                            }
                        }

                        if (qtyField) {
                            const numericValue = Number(qtyField.value);
                            const qtyError = row.querySelector('.qty-error-slot');
                            if (qtyField.value === '' || Number.isNaN(numericValue) || numericValue <= 0) {
                                hasError = true;
                                qtyField.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                                if (qtyError) {
                                    qtyError.textContent = 'Qty must be greater than 0.';
                                    qtyError.classList.remove('hidden');
                                }
                            } else if (qtyError) {
                                qtyError.textContent = '';
                                qtyError.classList.add('hidden');
                            }
                        }

                        if (rateField) {
                            const numericValue = Number(rateField.value || 0);
                            if (rateField.value !== '' && (Number.isNaN(numericValue) || numericValue < 0)) {
                                hasError = true;
                                rateField.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                                const error = document.createElement('label');
                                error.className = 'error text-xs text-rose-600 mt-1 block';
                                error.setAttribute('for', rateField.name);
                                error.textContent = 'Rate must be 0 or greater.';
                                rateField.insertAdjacentElement('afterend', error);
                            }
                        }

                        if (gstField) {
                            const numericValue = Number(gstField.value || 0);
                            if (gstField.value !== '' && (Number.isNaN(numericValue) || numericValue < 0 || numericValue > 100)) {
                                hasError = true;
                                gstField.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                                const error = document.createElement('label');
                                error.className = 'error text-xs text-rose-600 mt-1 block';
                                error.setAttribute('for', gstField.name);
                                error.textContent = 'GST must be between 0 and 100.';
                                gstField.insertAdjacentElement('afterend', error);
                            }
                        }

                        if (noteField && noteField.value.trim().length > 2000) {
                            hasError = true;
                            noteField.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
                            const error = document.createElement('label');
                            error.className = 'error text-xs text-rose-600 mt-1 block';
                            error.setAttribute('for', noteField.name);
                            error.textContent = 'Note may not be greater than 2000 characters.';
                            noteField.insertAdjacentElement('afterend', error);
                        }
                    });

                    if (hasError) {
                        e.preventDefault();
                    }
                });
            }
            if (productionForm) {
                productionForm.addEventListener('submit', function (e) {
                    if (window.jQuery && $(productionForm).data('validator')) {
                        const isValid = $(productionForm).valid();
                        const isFileValid = validateMaxSize(productionInput, productionError);
                        if (!isValid || !isFileValid) {
                            e.preventDefault();
                        }
                        return;
                    }

                    if (!validateMaxSize(productionInput, productionError)) e.preventDefault();
                });
            }
            if (editMaterialForm) {
                editMaterialForm.addEventListener('submit', function (e) {
                    if (!validateMaxSize(editMaterialAttachmentInput, editMaterialAttachmentError)) e.preventDefault();
                });
            }
            if (editProductionForm) {
                editProductionForm.addEventListener('submit', function (e) {
                    if (!validateMaxSize(editProductionAttachmentInput, editProductionAttachmentError)) e.preventDefault();
                });
            }

            if (window.jQuery && window.jQuery.fn && window.jQuery.fn.validate) {
                const $ = window.jQuery;

                $.validator.addMethod('filesize', function (value, element, param) {
                    if (!element.files || !element.files.length) {
                        return true;
                    }

                    return Array.from(element.files).every((file) => file.size <= param);
                });

                $.validator.addMethod('numberGreaterThanZero', function (value, element) {
                    if (this.optional(element)) {
                        return true;
                    }

                    const num = Number(value);
                    return Number.isFinite(num) && num > 0;
                }, 'Please enter a quantity greater than 0.');

                function placeProjectError(error, element) {
                    error.addClass('text-xs text-rose-600 mt-1');

                    if (element.attr('type') === 'file') {
                        const fileErrorTarget = element.siblings('p[id$=\"-error\"]').first();
                        if (fileErrorTarget.length) {
                            fileErrorTarget.removeClass('hidden').text(error.text());
                            return;
                        }

                        const label = element.prev('label');
                        if (label.length) {
                            error.insertAfter(label);
                            return;
                        }
                    }

                    const errorContainer = element.siblings('.error-message').first();
                    if (errorContainer.length) {
                        errorContainer.empty().append(error);
                        return;
                    }

                    error.insertAfter(element);
                }

                function placeContractorError(error, element) {
                    error.addClass('text-xs text-rose-600 mt-1');

                    const errorId = `${element.attr('id')}_error`;
                    const errorContainer = document.getElementById(errorId);
                    if (errorContainer) {
                        errorContainer.textContent = error.text();
                        errorContainer.classList.remove('hidden');
                        return;
                    }

                    error.insertAfter(element);
                }

                function clearContractorError(element) {
                    const errorId = `${element.id}_error`;
                    const errorContainer = document.getElementById(errorId);
                    if (errorContainer) {
                        errorContainer.textContent = '';
                        errorContainer.classList.add('hidden');
                    }
                }

                function highlightProjectField(element) {
                    $(element).addClass('border-rose-500 focus:border-rose-500 focus:ring-rose-500');
                }

                function unhighlightProjectField(element) {
                    $(element).removeClass('border-rose-500 focus:border-rose-500 focus:ring-rose-500');
                    const $element = $(element);
                    if ($element.attr('type') === 'file') {
                        $element.siblings('p[id$=\"-error\"]').first().addClass('hidden').text('');
                    }
                    $element.siblings('.error-message').first().empty();
                }

                $(drawingForm).validate({
                    ignore: [],
                    errorElement: 'p',
                    rules: {
                        'drawing_files[]': {
                            required: true,
                            extension: 'jpg|jpeg|png|pdf|dwg|dxf',
                            filesize: 52428800
                        }
                    },
                    messages: {
                        'drawing_files[]': {
                            required: 'Please choose at least one drawing file.',
                            extension: 'Allowed files: JPG, JPEG, PNG, PDF, DWG, DXF.',
                            filesize: 'File size is too large (max 50 MB).'
                        }
                    },
                    errorPlacement: placeProjectError,
                    highlight: highlightProjectField,
                    unhighlight: unhighlightProjectField
                });

                $(productionForm).validate({
                    ignore: [],
                    errorElement: 'p',
                    onkeyup: function (element) {
                        $(element).valid();
                    },
                    onfocusout: function (element) {
                        $(element).valid();
                    },
                    rules: {
                        title: {
                            required: true,
                            minlength: 2,
                            maxlength: 191,
                            normalizer: function (value) {
                                return $.trim(value);
                            }
                        },
                        note: {
                            maxlength: 1000,
                            normalizer: function (value) {
                                return $.trim(value);
                            }
                        },
                        attachment: {
                            extension: 'jpg|jpeg|png|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|mp4|mov|avi|mkv|mp3|wav',
                            filesize: 20971520
                        }
                    },
                    messages: {
                        title: {
                            required: 'Title is required.',
                            minlength: 'Title must be at least 2 characters.',
                            maxlength: 'Title may not be greater than 191 characters.'
                        },
                        note: {
                            maxlength: 'Note may not be greater than 1000 characters.'
                        },
                        attachment: {
                            extension: 'Please choose a valid attachment file.',
                            filesize: 'File size is too large (max 20 MB).'
                        }
                    },
                    errorPlacement: placeProjectError,
                    highlight: highlightProjectField,
                    unhighlight: unhighlightProjectField,
                    invalidHandler: function () {
                        validateMaxSize(productionInput, productionError);
                    },
                    submitHandler: function (form) {
                        if (!validateMaxSize(productionInput, productionError)) {
                            return false;
                        }

                        form.submit();
                    }
                });

                $(editMaterialForm).validate({
                    ignore: [],
                    errorElement: 'p',
                    rules: {
                        material_name: {
                            required: true,
                            minlength: 2,
                            maxlength: 191
                        },
                        qty: {
                            required: true,
                            numberGreaterThanZero: true
                        },
                        note: {
                            maxlength: 2000
                        },
                        attachment: {
                            extension: 'jpg|jpeg|png|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|mp4|mov|avi|mkv|mp3|wav',
                            filesize: 20971520
                        }
                    },
                    messages: {
                        material_name: {
                            required: 'Material Name is required.',
                            minlength: 'Material Name must be at least 2 characters.',
                            maxlength: 'Material Name may not be greater than 191 characters.'
                        },
                        qty: {
                            required: 'Qty is required.'
                        },
                        note: {
                            maxlength: 'Note may not be greater than 2000 characters.'
                        },
                        attachment: {
                            extension: 'Please choose a valid attachment file.',
                            filesize: 'File size is too large (max 20 MB).'
                        }
                    },
                    errorPlacement: placeProjectError,
                    highlight: highlightProjectField,
                    unhighlight: unhighlightProjectField
                });

                $(editProductionForm).validate({
                    ignore: [],
                    errorElement: 'p',
                    rules: {
                        title: {
                            required: true,
                            minlength: 2,
                            maxlength: 191
                        },
                        note: {
                            maxlength: 1000
                        },
                        attachment: {
                            extension: 'jpg|jpeg|png|pdf|doc|docx|xls|xlsx|ppt|pptx|zip|rar|mp4|mov|avi|mkv|mp3|wav',
                            filesize: 20971520
                        }
                    },
                    messages: {
                        title: {
                            required: 'Title is required.',
                            minlength: 'Title must be at least 2 characters.',
                            maxlength: 'Title may not be greater than 191 characters.'
                        },
                        note: {
                            maxlength: 'Note may not be greater than 1000 characters.'
                        },
                        attachment: {
                            extension: 'Please choose a valid attachment file.',
                            filesize: 'File size is too large (max 20 MB).'
                        }
                    },
                    errorPlacement: placeProjectError,
                    highlight: highlightProjectField,
                    unhighlight: unhighlightProjectField
                });

                $(commentForm).validate({
                    ignore: [],
                    errorElement: 'p',
                    rules: {
                        comment: {
                            required: true,
                            minlength: 2,
                            maxlength: 1000
                        }
                    },
                    messages: {
                        comment: {
                            required: 'Comment is required.',
                            minlength: 'Comment must be at least 2 characters.',
                            maxlength: 'Comment may not be greater than 1000 characters.'
                        }
                    },
                    errorPlacement: placeProjectError,
                    highlight: highlightProjectField,
                    unhighlight: unhighlightProjectField
                });

                if (contractorForm) {
                    $(contractorForm).validate({
                        ignore: [],
                        errorElement: 'p',
                        rules: {
                            contractor_id: {
                                required: true
                            },
                            commission_type: {
                                required: true
                            },
                            commission_value: {
                                required: true,
                                number: true,
                                min: 0.01
                            }
                        },
                        messages: {
                            contractor_id: {
                                required: 'Please select a contractor.'
                            },
                            commission_type: {
                                required: 'Please select a commission type.'
                            },
                            commission_value: {
                                required: 'Please enter a commission value.',
                                number: 'Please enter a valid commission value.',
                                min: 'Commission value must be greater than 0.'
                            }
                        },
                        errorPlacement: placeContractorError,
                        highlight: function (element) {
                            $(element).addClass('border-rose-500 focus:border-rose-500 focus:ring-rose-500');
                        },
                        unhighlight: function (element) {
                            $(element).removeClass('border-rose-500 focus:border-rose-500 focus:ring-rose-500');
                            clearContractorError(element);
                        },
                        submitHandler: function (form) {
                            form.submit();
                        }
                    });

                    ['contractor_id', 'commission_type', 'commission_value'].forEach((id) => {
                        const field = document.getElementById(id);
                        field?.addEventListener('change', function () {
                            clearContractorError(this);
                            if (window.jQuery && $(contractorForm).data('validator')) {
                                $(this).valid();
                            }
                        });
                        field?.addEventListener('input', function () {
                            clearContractorError(this);
                        });
                    });
                }
            }

            // Convert any timestamps to the user's local time
            localTimes.forEach((el) => {
                const iso = el.getAttribute('datetime');
                if (!iso) return;
                const date = new Date(iso);
                if (isNaN(date.getTime())) return;
                el.textContent = date.toLocaleString(undefined, {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: true,
                });
            });

            const drawingSelectAll = document.getElementById('drawing-select-all');
            const drawingCheckboxes = Array.from(document.querySelectorAll('.drawing-select-checkbox'));
            const drawingAcceptBtn = document.getElementById('drawing-accept-selected-btn');
            const drawingSelectedCount = document.getElementById('drawing-accept-selected-count');
            const drawingVersionError = document.getElementById('drawing-accept-version-error');

            function updateDrawingAcceptState() {
                if (!drawingSelectedCount) return;
                const selected = drawingCheckboxes.filter(cb => cb.checked);
                drawingSelectedCount.textContent = String(selected.length);

                const versions = new Set(selected.map(cb => String(cb.dataset.version || '')));
                const hasMultipleVersions = versions.size > 1;

                if (drawingVersionError) {
                    drawingVersionError.classList.toggle('hidden', !hasMultipleVersions);
                }

                if (drawingAcceptBtn) {
                    drawingAcceptBtn.disabled = selected.length === 0 || hasMultipleVersions;
                }
            }

            if (drawingSelectAll) {
                drawingSelectAll.addEventListener('change', function () {
                    const next = !!drawingSelectAll.checked;
                    drawingCheckboxes.forEach(cb => { cb.checked = next; });
                    updateDrawingAcceptState();
                });
            }

            document.getElementById('drawing_files').addEventListener('change', function() {
                const fileNameContainer = document.getElementById('drawing-files-file-name');
                const fileErrorContainer = document.getElementById('drawing-files-error');
                const files = this.files;

                if (files.length > 0) {
                    // Show the text and update content
                    fileNameContainer.classList.remove('hidden');
                    fileErrorContainer?.classList.add('hidden');
                    fileErrorContainer.textContent = '';
                    if (files.length === 1) {
                        fileNameContainer.textContent = files[0].name;
                    } else {
                        fileNameContainer.textContent = files.length + ' files selected';
                    }
                } else {
                    // Hide it if no files are selected
                    fileNameContainer.classList.add('hidden');
                    fileNameContainer.textContent = '';
                    fileErrorContainer?.classList.add('hidden');
                    fileErrorContainer.textContent = '';
                }
            });

            drawingCheckboxes.forEach(cb => cb.addEventListener('change', function () {
                if (drawingSelectAll) {
                    drawingSelectAll.checked = drawingCheckboxes.length > 0 && drawingCheckboxes.every(x => x.checked);
                }
                updateDrawingAcceptState();
            }));

            updateDrawingAcceptState();

            document.querySelectorAll('form.js-confirm-delete').forEach(form => {
                form.addEventListener('submit', function (e) {
                    if (form.dataset.confirmedDelete === '1') {
                        return;
                    }
                    e.preventDefault();

                    const title = form.dataset.confirmTitle || 'Are you sure?';
                    const text = form.dataset.confirmText || 'This action cannot be undone.';

                    if (window.Swal) {
                        Swal.fire({
                            title,
                            text,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#e11d48',
                            confirmButtonText: 'Delete',
                            cancelButtonText: 'Cancel',
                        }).then((result) => {
                            if (!result.isConfirmed) return;
                            form.dataset.confirmedDelete = '1';
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                                return;
                            }
                            form.submit();
                        });
                    } else {
                        if (confirm(`${title}\n\n${text}`)) {
                            form.dataset.confirmedDelete = '1';
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                                return;
                            }
                            form.submit();
                        }
                    }
                });
            });

            const rtdForm = document.getElementById('ready-to-dispatch-form');
            if (rtdForm) {
                const checkboxes = Array.from(document.querySelectorAll('.rtd-item-checkbox'));
                const submitBtn = document.getElementById('ready-to-dispatch-submit-btn');

                function updateDoneState() {
                    const anyChecked = checkboxes.some(cb => cb.checked);
                    if (submitBtn) submitBtn.disabled = !anyChecked;
                }

                checkboxes.forEach(cb => cb.addEventListener('change', updateDoneState));
                updateDoneState();
            }

            function bindChallanStatusSelect(select) {
                if (!select) return;
                let previous = select.value;
                const form = select.closest('form');
                const updateUrl = select.dataset.updateUrl || form?.dataset?.challanUpdateUrl || form?.getAttribute('action') || '';
                if (!updateUrl) return;

                async function patchChallan(formData) {
                    formData.append('_method', 'PATCH');
                    const resp = await fetch(updateUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) {
                        throw new Error(data?.message || 'Update failed.');
                    }
                    return data;
                }

                select.addEventListener('change', async function () {
                    const next = select.value;

                    try {
                        const fd = new FormData();
                        fd.append('status', next);
                        const res = await patchChallan(fd);
                        previous = next;
                        if (res?.refresh_url) {
                            try {
                                const url = new URL(res.refresh_url, window.location.origin);
                                window.history.replaceState({}, '', url.toString());
                            } catch (error) {
                                // Ignore URL parsing issues and keep the current address bar intact.
                            }
                        }
                        if (next === 'delivered') {
                            window.projectShowPage?.setTab?.('ready_to_dispatch');
                        }
                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Status updated',
                                showConfirmButton: false,
                                timer: 1200,
                                timerProgressBar: true,
                            });
                        }
                    } catch (e) {
                        if (window.Swal) {
                            Swal.fire({
                                title: 'Update failed',
                                text: e?.message || 'Unable to update challan.',
                                icon: 'error',
                                confirmButtonColor: '#4f46e5',
                            });
                        } else {
                            alert(e?.message || 'Unable to update challan.');
                        }
                        select.value = previous;
                    }
                });
            }

            document.querySelectorAll('select.js-challan-status').forEach(bindChallanStatusSelect);

            const completeBtn = document.getElementById('complete-order-btn');
            const installationStatusFields = [
                document.getElementById('site_preparation_status'),
                document.getElementById('fixing_welding_status'),
                document.getElementById('testing_commissioning_status'),
                document.getElementById('final_inspection_status'),
                document.getElementById('client_handover_status'),
            ].filter(Boolean);

            function updateInstallationCompleteState() {
                if (!completeBtn) return;

                const values = Object.fromEntries(installationStatusFields.map(f => [f.id, f.value]));
                const ok = values.site_preparation_status === 'done'
                    && values.fixing_welding_status === 'done'
                    && values.testing_commissioning_status === 'pass'
                    && values.final_inspection_status === 'passed'
                    && values.client_handover_status === 'completed';

                completeBtn.disabled = !ok;
            }

            const installationForm = document.getElementById('installation-checklist-form');
            if (installationForm) {
                const updateUrl = installationForm.getAttribute('action') || '';
                const fields = installationStatusFields;

                async function patchInstallation(formData) {
                    formData.append('_method', 'PATCH');
                    const resp = await fetch(updateUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: formData,
                    });
                    const data = await resp.json().catch(() => ({}));
                    if (!resp.ok) {
                        throw new Error(data?.message || 'Update failed.');
                    }
                    return data;
                }

                let installationSaving = false;
                let installationSaveTimer = null;
                let lastTestingValue = document.getElementById('testing_commissioning_status')?.value || 'not_started';
                let pendingFailReason = null;
                let pendingTestingFailed = false;
                let lastValidationMessage = null;

                const defaults = {
                    site_preparation_status: 'not_started',
                    fixing_welding_status: 'not_started',
                    testing_commissioning_status: 'not_started',
                    final_inspection_status: 'ready',
                    client_handover_status: 'pending',
                };

                function getField(id) {
                    return document.getElementById(id);
                }

                function setFieldValue(id, value) {
                    const el = getField(id);
                    if (!el) return;
                    el.value = value;
                }

                function applyInstallationRules({ resetDisabled = false } = {}) {
                    const site = getField('site_preparation_status')?.value || defaults.site_preparation_status;
                    const fixing = getField('fixing_welding_status')?.value || defaults.fixing_welding_status;
                    const testing = getField('testing_commissioning_status')?.value || defaults.testing_commissioning_status;
                    const final = getField('final_inspection_status')?.value || defaults.final_inspection_status;

                    const fixingEl = getField('fixing_welding_status');
                    const testingEl = getField('testing_commissioning_status');
                    const finalEl = getField('final_inspection_status');
                    const handoverEl = getField('client_handover_status');

                    const fixingEnabled = site === 'done';
                    const testingEnabled = fixing === 'done';
                    const finalEnabled = testing === 'pass';
                    const handoverEnabled = final === 'passed';

                    if (fixingEl) fixingEl.disabled = !fixingEnabled;
                    if (testingEl) testingEl.disabled = !testingEnabled;
                    if (finalEl) finalEl.disabled = !finalEnabled;
                    if (handoverEl) handoverEl.disabled = !handoverEnabled;

                    if (!resetDisabled) return false;

                    let didReset = false;
                    if (!fixingEnabled) {
                        if (fixingEl && fixingEl.value !== defaults.fixing_welding_status) { setFieldValue('fixing_welding_status', defaults.fixing_welding_status); didReset = true; }
                        if (testingEl && testingEl.value !== defaults.testing_commissioning_status) { setFieldValue('testing_commissioning_status', defaults.testing_commissioning_status); didReset = true; }
                        if (finalEl && finalEl.value !== defaults.final_inspection_status) { setFieldValue('final_inspection_status', defaults.final_inspection_status); didReset = true; }
                        if (handoverEl && handoverEl.value !== defaults.client_handover_status) { setFieldValue('client_handover_status', defaults.client_handover_status); didReset = true; }
                        return didReset;
                    }
                    if (!testingEnabled) {
                        if (testingEl && testingEl.value !== defaults.testing_commissioning_status) { setFieldValue('testing_commissioning_status', defaults.testing_commissioning_status); didReset = true; }
                        if (finalEl && finalEl.value !== defaults.final_inspection_status) { setFieldValue('final_inspection_status', defaults.final_inspection_status); didReset = true; }
                        if (handoverEl && handoverEl.value !== defaults.client_handover_status) { setFieldValue('client_handover_status', defaults.client_handover_status); didReset = true; }
                        return didReset;
                    }
                    if (!finalEnabled) {
                        if (finalEl && finalEl.value !== defaults.final_inspection_status) { setFieldValue('final_inspection_status', defaults.final_inspection_status); didReset = true; }
                        if (handoverEl && handoverEl.value !== defaults.client_handover_status) { setFieldValue('client_handover_status', defaults.client_handover_status); didReset = true; }
                        return didReset;
                    }
                    if (!handoverEnabled) {
                        if (handoverEl && handoverEl.value !== defaults.client_handover_status) { setFieldValue('client_handover_status', defaults.client_handover_status); didReset = true; }
                        return didReset;
                    }
                    return didReset;
                }

                function showInlineError(message) {
                    lastValidationMessage = message || '';
                    // Ideally you add an inline error near the select; fallback to alert if not found
                    const target = document.getElementById('testing_commissioning_status');
                    if (target) {
                        let hint = document.getElementById('testing_commissioning_status_error');
                        if (!hint) {
                            hint = document.createElement('p');
                            hint.id = 'testing_commissioning_status_error';
                            hint.className = 'mt-1 text-xs text-rose-600';
                            target.insertAdjacentElement('afterend', hint);
                        }
                        hint.textContent = message;
                    } else if (message) {
                        alert(message);
                    }
                }

                async function saveInstallationNow() {
                    if (installationSaving) return;
                    installationSaving = true;
                    try {
                        const fd = new FormData();
                        fields.forEach(f => fd.append(f.name, f.value));
                        fd.append('_method', 'PATCH');
                        if (pendingTestingFailed) {
                            fd.append('testing_failed', '1');
                        }
                        if (pendingFailReason) {
                            fd.append('fail_reason', pendingFailReason);
                        }
                        const resp = await fetch(updateUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: fd,
                        });
                        const data = await resp.json().catch(() => ({}));
                        if (!resp.ok) {
                            const msg = data?.errors?.fail_reason?.[0]
                                || data?.message
                                || 'Unable to save testing & commissioning. Please try again.';
                            showInlineError(msg);
                            throw new Error(msg);
                        }
                        showInlineError('');
                        pendingFailReason = null;
                        pendingTestingFailed = false;
                        if (window.Swal) {
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'success',
                                title: 'Checklist saved',
                                showConfirmButton: false,
                                timer: 1200,
                                timerProgressBar: true,
                            });
                        }
                    } catch (e) {
                        // Inline error already shown (if any)
                    } finally {
                        installationSaving = false;
                    }
                }

                fields.forEach(f => {
                    f.addEventListener('change', function () {
                        const testingEl = getField('testing_commissioning_status');
                        if (testingEl && f === testingEl && testingEl.value === 'fail') {
                            const prev = lastTestingValue;
                            lastTestingValue = testingEl.value;

                            (async () => {
                                const result = await Swal.fire({
                                    title: 'Testing failed',
                                    text: 'Enter failure reason. This will restart the installation checklist.',
                                    icon: 'warning',
                                    input: 'textarea',
                                    inputPlaceholder: 'Failure reason...',
                                    inputAttributes: { 'aria-label': 'Failure reason', maxlength: 1000 },
                                    showCancelButton: true,
                                    confirmButtonText: 'Restart',
                                    cancelButtonText: 'Cancel',
                                    confirmButtonColor: '#e11d48',
                                });

                                if (!result.isConfirmed || !String(result.value || '').trim()) {
                                    testingEl.value = prev;
                                    lastTestingValue = prev;
                                    applyInstallationRules();
                                    showInlineError('Reason is required when marking as fail.');
                                    return;
                                }

                                pendingFailReason = String(result.value || '').trim();
                                pendingTestingFailed = true;
                                showInlineError('');

                                setFieldValue('site_preparation_status', defaults.site_preparation_status);
                                setFieldValue('fixing_welding_status', defaults.fixing_welding_status);
                                setFieldValue('testing_commissioning_status', defaults.testing_commissioning_status);
                                setFieldValue('final_inspection_status', defaults.final_inspection_status);
                                setFieldValue('client_handover_status', defaults.client_handover_status);
                                applyInstallationRules();
                                updateInstallationCompleteState();

                                if (installationSaveTimer) clearTimeout(installationSaveTimer);
                                installationSaveTimer = setTimeout(saveInstallationNow, 50);
                            })();
                            return;

                        } else if (testingEl && f === testingEl) {
                            lastTestingValue = testingEl.value;
                            showInlineError('');
                        }

                        const didReset = applyInstallationRules({ resetDisabled: true });
                        updateInstallationCompleteState();
                        if (didReset) {
                            if (installationSaveTimer) clearTimeout(installationSaveTimer);
                            installationSaveTimer = setTimeout(saveInstallationNow, 50);
                            return;
                        }

                        if (installationSaveTimer) clearTimeout(installationSaveTimer);
                        installationSaveTimer = setTimeout(saveInstallationNow, 350);
                    });
                });

                applyInstallationRules();
                updateInstallationCompleteState();
            }

            if (completeBtn) {
                installationStatusFields.forEach(f => f.addEventListener('change', updateInstallationCompleteState));
                updateInstallationCompleteState();
            }

            if (shouldPromptCompletedAmc) {
                promptCompletedStageAmcOnProjectPage();
            }

            function initDeliveryAddressAutocomplete() {
                if (!window.google?.maps?.places) return;

                document.querySelectorAll('.js-delivery-address-autocomplete').forEach(function (textarea) {
                    if (textarea.dataset.googleAutocompleteBound === '1') return;

                    const autocomplete = new google.maps.places.Autocomplete(textarea, {
                        componentRestrictions: { country: ['in'] },
                        fields: ['formatted_address'],
                    });

                    autocomplete.addListener('place_changed', function () {
                        const place = autocomplete.getPlace();
                        if (!place || !place.formatted_address) return;

                        textarea.value = place.formatted_address;
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        textarea.dispatchEvent(new Event('change', { bubbles: true }));
                    });

                    textarea.dataset.googleAutocompleteBound = '1';
                });
            }

            initDeliveryAddressAutocomplete();
        });
    </script>
    @if(filled(config('services.google_maps.key')))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places" async defer onload="if(window.google?.maps?.places) { document.querySelectorAll('.js-delivery-address-autocomplete').forEach(function(textarea){ if(textarea.dataset.googleAutocompleteBound !== '1'){ const ac = new google.maps.places.Autocomplete(textarea, { componentRestrictions: { country: ['in'] }, fields: ['formatted_address'] }); ac.addListener('place_changed', function(){ const p = ac.getPlace(); if(p?.formatted_address){ textarea.value = p.formatted_address; textarea.dispatchEvent(new Event('input', {bubbles:true})); textarea.dispatchEvent(new Event('change', {bubbles:true})); } }); textarea.dataset.googleAutocompleteBound = '1'; } }); }"></script>
        <style>
            .pac-container {
                z-index: 999999 !important;
            }
        </style>
    @endif
    @endpush
</x-app-layout>
