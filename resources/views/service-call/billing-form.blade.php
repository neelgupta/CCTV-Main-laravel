<x-app-layout>
    <div id="service-call-billing-page">
    @php
        $isInvoice = $documentType === 'invoice';
        $title = $isInvoice ? 'Generate Invoice' : 'Create Quotation';
        $backRoute = $isInvoice ? route('service-call.invoices.index') : route('service-call.quotations.index');
        $document = $document ?? null;
        $isEdit = $document !== null;
        $existingClient = $document?->client;
        $selectedClientId = old('client_id', $existingClient?->id ?? '');
        $selectedClientRecord = $existingClient
            ?: collect($clients ?? [])->firstWhere('id', (int) $selectedClientId);
        $documentNumberLabel = $isInvoice ? 'Invoice Number' : 'Quotation Number';
        $documentNumberValue = $document?->document_number ?? ($documentNumber ?? '');
        $manualProductEntry = (bool) ($company?->manual_product_entry ?? false);
        $defaultTermsHtml = $isInvoice
            ? ($company?->invoiceTermsConditionsHtml() ?? '')
            : ($company?->quotationTermsConditionsHtml() ?? '');
        $documentTerms = trim((string) ($document?->terms_conditions ?? ''));
        $termsValue =  $documentTerms ? $documentTerms : $defaultTermsHtml;
        $defaultAdditionalNotes = trim((string) ($company?->default_invoice_notes ?? ''));
        $hasDefaultAdditionalNotes = (bool) ($company?->has_default_invoice_notes ?? false);
        $documentAdditionalNotes = trim((string) ($document?->additional_notes ?? ''));
        $additionalNotesValue = $isEdit
            ? $documentAdditionalNotes
            : ($hasDefaultAdditionalNotes ? $defaultAdditionalNotes : '');
        $submittedNames = is_array(old('name')) ? array_values(old('name', [])) : [];
        $submittedProductIds = is_array(old('product_id')) ? array_values(old('product_id', [])) : [];
        $submittedQuantities = is_array(old('qty')) ? array_values(old('qty', [])) : [];
        $submittedPrices = is_array(old('price')) ? array_values(old('price', [])) : [];
        $submittedGsts = is_array(old('gst')) ? array_values(old('gst', [])) : [];
        $submittedNotes = is_array(old('note')) ? array_values(old('note', [])) : [];
        $submittedDescriptions = is_array(old('description')) ? array_values(old('description', [])) : [];
        $submittedUnits = is_array(old('unit')) ? array_values(old('unit', [])) : [];
        $submittedBrandModels = is_array(old('brand_model')) ? array_values(old('brand_model', [])) : [];

        $existingRows = [];
        if ($manualProductEntry && $submittedNames !== []) {
            $rowCount = max(count($submittedNames), count($submittedQuantities), count($submittedPrices), count($submittedGsts), count($submittedNotes), count($submittedDescriptions), count($submittedUnits), count($submittedBrandModels));

            $existingRows = collect(range(0, max(0, $rowCount - 1)))->map(function (int $index) use ($submittedNames, $submittedProductIds, $submittedQuantities, $submittedPrices, $submittedGsts, $submittedNotes, $submittedDescriptions, $submittedUnits, $submittedBrandModels) {
                return [
                    'product_id' => (string) ($submittedProductIds[$index] ?? ''),
                    'name' => (string) ($submittedNames[$index] ?? ''),
                    'qty' => (float) ($submittedQuantities[$index] ?? 1),
                    'price' => (float) ($submittedPrices[$index] ?? 0),
                    'gst' => (float) ($submittedGsts[$index] ?? 0),
                    'note' => (string) ($submittedNotes[$index] ?? ''),
                    'description' => (string) ($submittedDescriptions[$index] ?? ''),
                    'unit' => (string) ($submittedUnits[$index] ?? ''),
                    'brand_model' => (string) ($submittedBrandModels[$index] ?? ''),
                ];
            })->filter(fn ($item) => filled($item['name'] ?? null))->values();
        } elseif ($document) {
            $existingRows = collect($document->items ?? [])->filter(fn ($item) => is_array($item))->values();
        } else {
            $existingRows = collect();
        }
        $billingUnitOptions = collect($billingUnits ?? [])
            ->pluck('name')
            ->merge(collect($existingRows)->pluck('unit'))
            ->merge($billingProducts->pluck('unit'))
            ->map(fn ($unit) => trim((string) $unit))
            ->filter()
            ->unique()
            ->values();
        $gstOptions = collect($gstRates ?? [])
            ->pluck('rate')
            ->merge($billingProducts->pluck('gst'))
            ->merge([0, 5, 9, 12, 18, 28])
            ->map(fn ($rate) => (float) $rate)
            ->filter(fn ($rate) => $rate >= 0)
            ->unique(fn ($rate) => number_format((float) $rate, 2, '.', ''))
            ->sort()
            ->values();
        $invoiceStatusOptions = collect([
            ['id' => '0', 'label' => 'Unpaid'],
            ['id' => '1', 'label' => 'Paid'],
        ])->values();
        $paymentMethodOptions = collect([
            ['id' => '0', 'label' => 'Cash'],
            ['id' => '1', 'label' => 'Online'],
        ])->values();
        $formAction = $isEdit
            ? route('service-call.billing-document.update', $document)
            : route('service-call.billing-document.save');

        $billingFormConfig = [
            'clientId' => (string) $selectedClientId,
            'selectedClient' => $selectedClientRecord ? [
                'id' => (string) $selectedClientRecord->id,
                'name' => (string) $selectedClientRecord->name,
                'email' => (string) ($selectedClientRecord->email ?? ''),
                'mobile' => (string) ($selectedClientRecord->mobile ?? ''),
                'phone' => (string) ($selectedClientRecord->phone ?? ''),
                'gst_number' => (string) ($selectedClientRecord->gst_number ?? ''),
                'address' => (string) ($selectedClientRecord->address ?? ''),
                'state_id' => $selectedClientRecord->state_id ? (string) $selectedClientRecord->state_id : '',
                'city_id' => $selectedClientRecord->city_id ? (string) $selectedClientRecord->city_id : '',
            ] : null,
            'customerName' => (string) old('client_name', $selectedClientRecord?->name ?? ''),
            'mobile' => (string) old('client_mobile', $selectedClientRecord?->mobile ?: $selectedClientRecord?->phone ?: ''),
            'email' => (string) old('client_email', $selectedClientRecord?->email ?? ''),
            'subject' => (string) old('subject', $document?->subject ?? ''),
            'gstNumber' => (string) old('client_gst_number', $selectedClientRecord?->gst_number ?? ''),
            'address' => (string) old('client_address', $selectedClientRecord?->address ?? ''),
            'stateId' => (string) old('client_state_id', $selectedClientRecord?->state_id ? (string) $selectedClientRecord->state_id : ''),
            'cityId' => (string) old('client_city_id', $selectedClientRecord?->city_id ? (string) $selectedClientRecord->city_id : ''),
            'cityOptions' => ($selectedClientRecord?->state_id ? ($states->firstWhere('id', $selectedClientRecord->state_id)?->cities ?? collect()) : collect())->map(fn ($city) => [
                'id' => (string) $city->id,
                'name' => (string) $city->name,
            ])->values()->all(),
            'documentType' => $documentType,
            'isInvoice' => (bool) $isInvoice,
            'clients' => $clients->map(fn ($client) => [
                'id' => (string) $client->id,
                'name' => (string) $client->name,
                'email' => (string) ($client->email ?? ''),
                'mobile' => (string) ($client->mobile ?? ''),
                'phone' => (string) ($client->phone ?? ''),
                'gst_number' => (string) ($client->gst_number ?? ''),
                'address' => (string) ($client->address ?? ''),
                'state_id' => $client->state_id ? (string) $client->state_id : '',
                'city_id' => $client->city_id ? (string) $client->city_id : '',
                'search' => \Illuminate\Support\Str::lower(trim(implode(' ', array_filter([
                    $client->name, $client->email, $client->mobile, $client->phone, $client->address,
                ])))),
            ])->values()->all(),
            'cityOptionsByState' => $states->mapWithKeys(fn ($state) => [
                (string) $state->id => $state->cities->map(fn ($city) => ['id' => (string) $city->id, 'name' => $city->name])->values(),
            ]),
            'stateNamesById' => $states->mapWithKeys(fn ($state) => [(string) $state->id => $state->name]),
            'companyState' => trim((string) $companyState),
            'manualProductEntry' => (bool) $manualProductEntry,
            'gstRateOptions' => $gstOptions->all(),
            'billingUnitOptions' => $billingUnitOptions->all(),
            'discount' => (float) old('discount', $document?->discount ?? 0),
            'additionalNotes' => (string) old('additional_notes', $additionalNotesValue),
            'rows' => $existingRows->isNotEmpty() ? $existingRows->map(fn ($item) => [
                'product_id' => (string) ($item['product_id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'description' => (string) ($item['description'] ?? ''),
                'qty' => (float) ($item['qty'] ?? 1),
                'price' => (float) ($item['price'] ?? 0),
                'gst' => (float) ($item['gst'] ?? 0),
                'note' => (string) ($item['note'] ?? ''),
                'unit' => (string) ($item['unit'] ?? ''),
                'brand_model' => (string) ($item['brand_model'] ?? ''),
                'product_search' => '',
                'product_open' => false,
            ])->values()->all() : [['product_id' => '', 'name' => '', 'description' => '', 'qty' => 1, 'price' => 0, 'gst' => 0, 'note' => '', 'unit' => '', 'brand_model' => '', 'product_search' => '', 'product_open' => false]],
            'products' => $billingProducts->map(fn ($product) => [
                'id' => (string) $product->id,
                'name' => (string) $product->name,
                'description' => (string) ($product->description ?? ''),
                'brand' => (string) ($product->brand ?? ''),
                'model' => (string) ($product->model ?? ''),
                'unit' => (string) ($product->unit ?? ''),
                'price' => (float) ($product->price ?? 0),
                'gst' => (float) ($product->gst ?? 0),
            ])->values()->all(),
            'templates' => (! $isInvoice && isset($quotationTemplates)) ? $quotationTemplates->map(fn ($t) => [
                'id' => (string) $t->id,
                'name' => (string) $t->name,
                'description' => (string) ($t->description ?? ''),
                'items' => is_array($t->items) ? $t->items : [],
            ])->values()->all() : [],
            'templateStoreUrl' => route('settings.quotation-templates.store'),
        ];
    @endphp

    <x-slot name="header">
        <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold leading-tight text-slate-950">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">Select a client and add service rows for this {{ $isInvoice ? 'invoice' : 'quotation' }}.</p>
                </div>
                <a href="{{ $backRoute }}" class="rounded-none border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="w-full px-2 py-6 sm:px-6 lg:px-8">
        <form
            method="POST"
            action="{{ $formAction }}"
            id="service-call-billing-form"
            class="rounded-none border border-slate-200 bg-white p-5 shadow-sm overflow-visible"
            x-init="$el.dataset.selectedClient = JSON.stringify(selectedClient || null)"
            x-data="billingFormData(@js($billingFormConfig))"
            x-on:submit="if (!clientId && !customerName.trim()) { $event.preventDefault(); alert('Please select or enter a client.'); }"
        >
            @csrf
            @if($isEdit)
                @method('PUT')
            @endif
            <input type="hidden" name="document_type" value="{{ $documentType }}">
            <input type="hidden" name="client_id" :value="clientId">
            <input type="hidden" id="terms_conditions" name="terms_conditions" value="{{ $termsValue }}">

            <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <x-input-label for="document_number" :value="$documentNumberLabel" />
                <x-text-input
                    id="document_number"
                    name="document_number"
                    type="text"
                    class="mt-1 block w-full rounded-xl bg-white"
                    value="{{ $documentNumberValue }}"
                    readonly
                />
            </div>

            

            <div class="grid gap-4 lg:grid-cols-3 relative z-30">
                <div class="relative" @click.outside="showNameDropdown = false">
                    <label for="client_name" class="ml-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Name <span class="text-red-600">*</span>
                    </label>
                    <div class="relative mt-1">
                        <input
                            type="text"
                            name="client_name"
                            id="client_name"
                            x-model="customerName"
                            @input="onNameInput()"
                            @focus="showNameDropdown = true"
                            placeholder="Search or enter client name"
                            autocomplete="off"
                            data-msg-required="Client name is required."
                            class="block w-full rounded-xl border-slate-300 pr-11 text-sm text-slate-700 shadow-sm placeholder:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                        <button
                            type="button"
                            @click="showNameDropdown = !showNameDropdown"
                            class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-slate-600"
                            aria-label="Show existing clients"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </div>
                    <div
                        x-show="showNameDropdown"
                        x-cloak
                        x-transition
                        class="absolute left-0 top-full z-50 mt-1 w-full min-w-[340px] max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl"
                    >
                        <div class="border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Existing Clients
                        </div>
                        <div class="max-h-[320px] divide-y divide-slate-100 overflow-y-auto overscroll-contain p-1">
                            <template x-for="client in filteredClients()" :key="client.id">
                                <button
                                    type="button"
                                    @click="selectClient(client)"
                                    class="flex w-full flex-col items-start gap-1 rounded-xl px-3.5 py-2.5 text-left transition hover:bg-slate-50"
                                    :class="String(clientId) === String(client.id) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                                >
                                    <span class="block w-full font-medium text-slate-900" x-text="client.name"></span>
                                    <span
                                        class="block w-full break-words text-xs leading-relaxed text-slate-500"
                                        x-show="[client.email, client.mobile, client.address].filter(Boolean).length > 0"
                                        x-text="[client.email, client.mobile, client.address].filter(Boolean).join(' • ')"
                                    ></span>
                                </button>
                            </template>
                            <div x-show="filteredClients().length === 0" class="px-4 py-3 text-sm text-slate-500">No matching clients found.</div>
                        </div>
                    </div>
                    @if($clients->isEmpty())
                        <p class="mt-2 text-xs font-semibold text-amber-700">No clients are available yet — type a name to add a new one.</p>
                    @else
                        <p class="mt-1 text-xs text-slate-500">Search an existing client, or type a new name to add one.</p>
                    @endif
                    <span id="client_name_error" class="error-message block min-h-5 text-sm text-red-600"></span>
                </div>

                <div class="relative">
                    <label for="client_mobile" class="ml-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Contact Number</label>
                    <input
                        type="text"
                        name="client_mobile"
                        id="client_mobile"
                        x-model="mobile"
                        placeholder="Enter contact number"
                        data-msg-required="Contact number is required."
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm text-slate-700 shadow-sm placeholder:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <span id="client_mobile_error" class="error-message block min-h-5 text-sm text-red-600"></span>
                </div>

                <div class="relative">
                    <label for="client_email" class="ml-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Email</label>
                    <input
                        type="email"
                        name="client_email"
                        id="client_email"
                        x-model="email"
                        placeholder="Enter email address"
                        data-msg-required="Email is required."
                        class="mt-1 block w-full rounded-xl border-slate-300 text-sm text-slate-700 shadow-sm placeholder:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <span id="client_email_error" class="error-message block min-h-5 text-sm text-red-600"></span>
                </div>
            </div>

            <div class="mt-4">
                <label class="ml-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Subject</label>
                <input
                    type="text"
                    name="subject"
                    id="subject"
                    x-model="subject"
                    placeholder="Enter subject"
                    autocomplete="off"
                    class="mt-1 block w-full rounded-xl border-slate-300 text-sm text-slate-700 shadow-sm placeholder:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                >
                <span id="subject_error" class="error-message block min-h-5 text-sm text-red-600"></span>
            </div>

            <div class="mt-4">
                <label class="ml-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Address</label>
                <textarea
                    id="client_address"
                    name="client_address"
                    x-model="address"
                    rows="3"
                    placeholder="Enter customer address"
                    data-msg-required="Address is required."
                    autocomplete="off"
                    class="mt-1 block w-full rounded-2xl border-slate-300 text-sm text-slate-700 shadow-sm placeholder:text-slate-500 focus:border-indigo-500 focus:ring-indigo-500"
                ></textarea>
                <span id="client_address_error" class="error-message block min-h-5 text-sm text-red-600"></span>
            </div>

            <div class="mt-4 grid gap-4 lg:grid-cols-12 relative z-10">
                <div class="lg:col-span-3">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">GST Number</label>
                    <input type="text" name="client_gst_number" id="client_gst_number" x-model="gstNumber" data-msg-required="GST number is required." class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                    <span id="client_gst_number_error" class="error-message block min-h-5 text-sm text-red-600"></span>
                </div>
                <div class="lg:col-span-3 relative" :class="open ? 'z-50' : 'z-20'" x-data="{
                    open: false,
                    query: '',
                    selectedId: stateId,
                    selectedLabel: stateId ? (stateNamesById[stateId] || '') : '',
                    get options() { return @js($states->map(fn ($state) => [
                        'id' => (string) $state->id,
                        'label' => (string) $state->name,
                        'search' => \Illuminate\Support\Str::lower(trim((string) $state->name)),
                    ])->values()); },
                    setOption(option) {
                        this.selectedId = option ? String(option.id) : '';
                        this.selectedLabel = option ? option.label : '';
                        this.query = this.selectedLabel;
                        this.open = false;
                        stateId = this.selectedId;
                        cityId = '';
                        cityOptions = cityOptionsByState[this.selectedId] || [];
                    },
                    filteredOptions() {
                        const query = this.query.trim().toLowerCase();
                        if (!query) return this.options;
                        return this.options.filter((option) => String(option.search || '').includes(query));
                    },
                    refreshSelection() {
                        const current = this.options.find((option) => String(option.id) === String(stateId));
                        this.selectedLabel = current ? current.label : '';
                        this.query = this.selectedLabel;
                        this.selectedId = String(stateId || '');
                    },
                    reset() {
                        this.selectedId = '';
                        this.selectedLabel = '';
                        this.query = '';
                    },
                }" x-init="refreshSelection(); $watch('stateId', () => { reset(); open = false; $nextTick(() => refreshSelection()); })" class="relative">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
                    <button type="button" @click="open = !open" class="flex w-full items-center justify-between rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-left text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <span class="truncate" x-text="selectedLabel || 'Select state'"></span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" style="display:none;">
                        <div class="border-b border-slate-100 p-3">
                            <input type="search" x-model="query" placeholder="Search state..." class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                            <button type="button" @click="setOption(null)" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50" :class="!selectedId ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'">
                                <span>Select state</span>
                            </button>
                            <template x-for="option in filteredOptions()" :key="option.id">
                                <button type="button" @click="setOption(option)" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50" :class="String(selectedId) === String(option.id) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'">
                                    <span x-text="option.label"></span>
                                    <span x-show="String(selectedId) === String(option.id)" class="text-xs font-semibold text-indigo-600">Selected</span>
                                </button>
                            </template>
                        </div>
                    </div>
                    <input type="hidden" name="client_state_id" id="client_state_id" :value="selectedId" data-msg-required="State is required.">
                    <span id="client_state_id_error" class="error-message mt-1 block min-h-5 text-sm text-red-600"></span>
                </div>
                <div class="lg:col-span-3 relative" :class="open ? 'z-50' : 'z-20'" x-data="{
                    open: false,
                    query: '',
                    selectedId: cityId,
                    selectedLabel: '',
                    get options() { return cityOptions.map((city) => ({ id: String(city.id), label: city.name, search: String(city.name || '').toLowerCase() })); },
                    setOption(option) {
                        this.selectedId = option ? String(option.id) : '';
                        this.selectedLabel = option ? option.label : '';
                        this.query = this.selectedLabel;
                        this.open = false;
                        cityId = this.selectedId;
                    },
                    filteredOptions() {
                        const query = this.query.trim().toLowerCase();
                        if (!query) return this.options;
                        return this.options.filter((option) => String(option.search || '').includes(query));
                    },
                    reset() {
                        this.selectedId = '';
                        this.selectedLabel = '';
                        this.query = '';
                    },
                    refreshSelection() {
                        const current = this.options.find((option) => String(option.id) === String(cityId));
                        this.selectedLabel = current ? current.label : '';
                        this.query = this.selectedLabel;
                        this.selectedId = String(cityId || '');
                    }
                }" x-init="refreshSelection(); $watch('stateId', () => { reset(); open = false; $nextTick(() => refreshSelection()); })" class="relative">
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
                    <button type="button" @click="open = !open" :disabled="!stateId" class="flex w-full items-center justify-between rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-left text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0 disabled:cursor-not-allowed disabled:bg-slate-50">
                        <span class="truncate" x-text="selectedLabel || 'Select city'"></span>
                        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z" clip-rule="evenodd"/></svg>
                    </button>
                    <div x-show="open" @click.outside="open = false" x-transition class="absolute z-50 mt-2 w-full overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" style="display:none;">
                        <div class="border-b border-slate-100 p-3">
                            <input type="search" x-model="query" placeholder="Search city..." class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                            <button type="button" @click="setOption(null)" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50" :class="!selectedId ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'">
                                <span>Select city</span>
                            </button>
                            <template x-for="option in filteredOptions()" :key="option.id">
                                <button type="button" @click="setOption(option)" class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50" :class="String(selectedId) === String(option.id) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'">
                                    <span x-text="option.label"></span>
                                    <span x-show="String(selectedId) === String(option.id)" class="text-xs font-semibold text-indigo-600">Selected</span>
                                </button>
                            </template>
                            <div x-show="filteredOptions().length === 0" class="px-4 py-3 text-sm text-slate-500">No matching cities found.</div>
                        </div>
                    </div>
                    <input type="hidden" name="client_city_id" id="client_city_id" :value="selectedId" data-msg-required="City is required.">
                    <span id="client_city_id_error" class="error-message mt-1 block min-h-5 text-sm text-red-600"></span>
                </div>
                
                @if($isInvoice)
                    <div
                        class="contents"
                        x-data="{
                            paymentStatus: @js((string) old('payment_status', $document?->payment_status ?? 0)),
                        }"
                        x-on:searchable-select:change.window="
                            if ($event.detail?.name === 'payment_status') {
                                paymentStatus = String($event.detail.value || '');
                                if (paymentStatus !== '1') {
                                    const paymentMethodSelect = $el.querySelector('select[name=\'payment_method\']');
                                    if (paymentMethodSelect) {
                                        paymentMethodSelect.value = '';
                                        paymentMethodSelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            }
                        "
                    >
                        <div class="lg:col-span-3">
                            <x-searchable-select
                                name="payment_status"
                                id="payment_status"
                                label="Payment Status"
                                :options="$invoiceStatusOptions"
                                :selected="(string) old('payment_status', $document?->payment_status ?? 0)"
                                empty-label="Select payment status"
                                placeholder="Search payment status..."
                            />
                            <span id="payment_status_error" class="error-message mt-1 block min-h-5 text-sm text-red-600"></span>
                        </div>

                        <div class="lg:col-span-3" x-show="paymentStatus === '1'" x-cloak>
                            <x-searchable-select
                                name="payment_method"
                                id="payment_method"
                                label="Payment Method"
                                :options="$paymentMethodOptions"
                                :selected="(string) old('payment_method', $document?->payment_method ?? '')"
                                empty-label="Select method"
                                placeholder="Search payment method..."
                            />
                            <span id="payment_method_error" class="error-message mt-1 block min-h-5 text-sm text-red-600"></span>
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 overflow-visible">
                @if(! $isInvoice)
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3 flex-wrap gap-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold text-slate-900">
                            <span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Equipment &amp; Items
                        </h3>
                        <div class="flex items-center gap-3 flex-wrap">
                            <template x-if="templates.length > 0">
                                <div class="flex items-center gap-1.5">
                                    <select x-model="selectedTemplateId" @change="applyTemplate($event.target.value)"
                                        class="rounded-xl border-slate-300 bg-white py-1.5 pl-3 pr-8 text-xs font-semibold text-slate-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">-- Load from Template --</option>
                                        <template x-for="t in templates" :key="t.id">
                                            <option :value="t.id" x-text="t.name + ' (' + (t.items ? t.items.length : 0) + ' items)'"></option>
                                        </template>
                                    </select>
                                </div>
                            </template>
                            <button type="button" @click="openSaveTemplateModal()"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition-colors shadow-sm"
                                title="Save current items as template for other clients">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"/></svg>
                                Save Items as Template
                            </button>
                            <button type="button" @click="addRow()" class="app-button app-button-primary">+ Add Item</button>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                        <h3 class="text-lg font-bold text-slate-900">Equipment &amp; Items</h3>
                        <button type="button" @click="addRow()" class="app-button app-button-primary">+ Add Item</button>
                    </div>
                @endif
                @error('product_id')
                    <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror
                @error('name')
                    <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
                @enderror

                <div class="mt-4 overflow-visible">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead>
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-1/4">Item Name</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white">Brand/Model</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-28">Unit</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-24">Qty</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-32">Unit Price</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-32">GST %</th>
                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-white w-32">Total</th>
                                <th class="px-4 py-2 w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(row, index) in rows" :key="index">
                                <tr>
                                    <td class="relative px-4 py-2" :class="row.product_open ? 'z-50' : ''">
                                        <div class="space-y-2">
                                            <template x-if="manualProductEntry">
                                                <textarea
                                                    :name="`name[${index}]`"
                                                    x-model="row.name"
                                                    maxlength="500"
                                                    rows="2"
                                                    placeholder="Enter product name"
                                                    class="w-full resize-y rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    required
                                                ></textarea>
                                            </template>
                                            <template x-if="!manualProductEntry">
                                                <div class="relative min-w-[200px]" @click.outside="row.product_open = false">
                                                    <input
                                                        type="text"
                                                        :value="row.product_search || productLabel(row)"
                                                        @input="row.product_search = $event.target.value; row.product_open = true"
                                                        @focus="row.product_open = true"
                                                        @keydown.escape="row.product_open = false"
                                                        :placeholder="productLabel(row) || 'Search products...'"
                                                        autocomplete="off"
                                                        class="w-full rounded-lg border-slate-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                    >
                                                    <input type="hidden" :name="`product_id[${index}]`" :value="row.product_id">
                                                    <div x-cloak x-show="row.product_open" class="absolute left-0 top-full z-50 mt-2 w-full min-w-[260px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" style="display:none;">
                                                        <div class="border-b border-slate-100 p-3">
                                                            <input type="search" :value="row.product_search" @input="row.product_search = $event.target.value" @click.stop placeholder="Search product..." class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                        </div>
                                                        <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                                                            <button type="button" @mousedown.prevent="row.product_id = ''; row.name = ''; row.brand_model = ''; row.product_search = ''; row.product_open = false" class="flex w-full items-center px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50">Select Product</button>
                                                            <template x-for="product in filteredProducts(row)" :key="product.id">
                                                                <button type="button" @mousedown.prevent="chooseProduct(row, product, $event.target.closest('tr'))" class="block w-full px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50">
                                                                    <span class="block truncate" x-text="product.name"></span>
                                                                    <span class="block truncate text-xs text-slate-500" x-text="[product.brand, product.model, product.unit].filter(Boolean).join(' • ')"></span>
                                                                </button>
                                                            </template>
                                                            <div x-show="filteredProducts(row).length === 0" class="px-4 py-3 text-sm text-slate-500">No matching products found.</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </template>
                                            <input type="hidden" :name="`name[${index}]`" :value="row.name" :disabled="manualProductEntry">
                                            <template x-if="manualProductEntry">
                                                <input type="hidden" :name="`product_id[${index}]`" value="">
                                            </template>
                                            <textarea
                                                :name="`description[${index}]`"
                                                x-model="row.description"
                                                maxlength="2000"
                                                rows="2"
                                                placeholder="Description (optional)"
                                                class="w-full resize-y rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            ></textarea>
                                        </div>
                                    </td>
                                    <td class="px-4 py-2">
                                        <input type="text" :name="`brand_model[${index}]`" x-model="row.brand_model" :placeholder="productBrandModel(row) || 'Brand / model'" maxlength="500" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    </td>
                                    <td class="px-2 py-2">
                                        <select
                                            :name="`unit[${index}]`"
                                            x-model="row.unit"
                                            class="w-full min-w-[100px] rounded-lg border-slate-300 px-2 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select unit</option>
                                            @foreach($billingUnitOptions as $unitOption)
                                                <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                            @endforeach
                                            <template x-for="unitOption in unitOptionsFor(row.unit).filter(u => !@js($billingUnitOptions).includes(u))" :key="unitOption">
                                                <option :value="unitOption" x-text="unitOption"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-2 py-2">
                                        <input
                                            type="number"
                                            min="0.01"
                                            step="any"
                                            :name="`qty[${index}]`"
                                            x-model.number="row.qty"
                                            @input="row.qty = row.qty === '' ? '' : (Number(row.qty) <= 0 ? 0.01 : row.qty)"
                                            class="w-full min-w-[80px] rounded-lg border-slate-300 px-2 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                            required
                                        >
                                    </td>
                                    <td class="px-4 py-2">
                                        <input
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            :name="`price[${index}]`"
                                            x-model.number="row.price"
                                            @input="row.price = Math.max(0, row.price || 0)"
                                            class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </td>
                                    <td class="px-4 py-2">
                                        <select
                                            :name="`gst[${index}]`"
                                            x-model.number="row.gst"
                                            class="w-full min-w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            @foreach($gstOptions as $gstOption)
                                                <option value="{{ (float) $gstOption }}">{{ (int) $gstOption }}%</option>
                                            @endforeach
                                            <template x-for="rate in gstOptionsFor(row.gst).filter(r => !@js($gstOptions).map(Number).includes(Number(r)))" :key="rate">
                                                <option :value="Number(rate)" x-text="Number(rate).toFixed(0) + '%'"></option>
                                            </template>
                                        </select>
                                    </td>
                                    <td class="px-4 py-2 text-sm font-semibold text-slate-900" x-text="'₹' + rowTotal(row)"></td>
                                    <td class="px-4 py-2 text-center">
                                        <button type="button" @click="removeRow(index)" class="text-red-500 hover:text-red-700" title="Remove item">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                <h3 class="border-b border-slate-200 pb-3 text-lg font-bold text-slate-900">Pricing Summary</h3>
                <div class="mt-4 max-w-xl space-y-4">
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-slate-500">Equipment Subtotal:</span>
                        <span class="font-bold text-slate-900" x-text="'₹' + itemsSubtotal().toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-slate-500">Discount:</span>
                        <div class="w-32">
                            <input type="number" name="discount" min="0" step="0.01" :value="discount" @input="discount = Number($event.target.value || 0)" class="w-full rounded-lg border-slate-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <span id="discount_error" class="mt-1 block min-h-5 text-sm text-red-600"></span>
                        </div>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-slate-500">Taxable Amount:</span>
                        <span class="font-bold text-slate-900" x-text="'₹' + taxableAmount().toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="font-medium text-slate-500" x-text="taxLabel()"></span>
                        <span class="font-bold text-slate-900" x-text="'₹' + gstTotal().toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between border-t pt-4 text-sm">
                        <span class="text-lg font-bold text-slate-900">Grand Total:</span>
                        <span class="text-2xl font-black text-indigo-600" x-text="'₹' + grandTotal().toFixed(2)"></span>
                    </div>
                </div>
            </div>
            @if($isInvoice)
                <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <x-input-label for="additional_notes" value="Additional Notes" />
                        <p class="mt-1 text-sm text-slate-500">Default text is pulled from Company Settings &rarr; Invoice Settings. You can edit it for this invoice only.</p>
                        <textarea
                            id="additional_notes"
                            name="additional_notes"
                            rows="4"
                            placeholder="Enter additional notes..."
                            x-model="additionalNotes"
                            class="mt-3 block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >{{ old('additional_notes', $additionalNotesValue) }}</textarea>
                        <x-input-error :messages="$errors->get('additional_notes')" class="mt-2" />
                    </div>
                </div>
            @endif
            @unless($isInvoice)
                <div class="mb-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <x-input-label for="terms_conditions_editor" value="Terms & Conditions" />
                    <p class="mt-1 text-sm text-slate-500">You can add or edit terms for this specific quotation.</p>
                    <div class="mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 bg-slate-50 px-3 py-3">
                            <div class="flex flex-wrap gap-2">
                                <input type="text" value="untitled" readonly class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                <select data-terms-editor-file class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <option value="">File</option>
                                    <option value="clear">Clear</option>
                                    <option value="reset">Reset to default</option>
                                </select>
                                <select data-terms-editor-format class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <option value="">Format</option>
                                    <option value="p">Paragraph</option>
                                    <option value="h1">Heading 1</option>
                                    <option value="h2">Heading 2</option>
                                    <option value="h3">Heading 3</option>
                                    <option value="blockquote">Quote</option>
                                </select>
                                <select data-terms-editor-size class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <option value="">Font size</option>
                                    <option value="1">12</option>
                                    <option value="2">14</option>
                                    <option value="3">16</option>
                                    <option value="4">18</option>
                                    <option value="5">24</option>
                                    <option value="6">32</option>
                                    <option value="7">48</option>
                                </select>
                                <label class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <span>Color</span>
                                    <input type="color" data-terms-editor-color value="#000000" class="h-6 w-8 border-0 bg-transparent p-0">
                                </label>
                                <label class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <span>Background</span>
                                    <input type="color" data-terms-editor-background value="#ffffff" class="h-6 w-8 border-0 bg-transparent p-0">
                                </label>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button type="button" data-terms-editor-cmd="undo" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Undo</button>
                                <button type="button" data-terms-editor-cmd="redo" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Redo</button>
                                <button type="button" data-terms-editor-cmd="bold" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">B</button>
                                <button type="button" data-terms-editor-cmd="underline" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">U</button>
                                <button type="button" data-terms-editor-cmd="italic" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">I</button>
                                <button type="button" data-terms-editor-cmd="strikeThrough" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">S</button>
                                <button type="button" data-terms-editor-cmd="justifyLeft" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Left</button>
                                <button type="button" data-terms-editor-cmd="justifyCenter" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Center</button>
                                <button type="button" data-terms-editor-cmd="justifyRight" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Right</button>
                                <button type="button" data-terms-editor-cmd="justifyFull" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Justify</button>
                                <button type="button" data-terms-editor-cmd="insertUnorderedList" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">UL</button>
                                <button type="button" data-terms-editor-cmd="insertOrderedList" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">OL</button>
                                <button type="button" data-terms-editor-cmd="createLink" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Link</button>
                                <button type="button" data-terms-editor-cmd="unlink" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Unlink</button>
                                <button type="button" data-terms-editor-cmd="removeFormat" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Code</button>
                            </div>
                        </div>
                        <div id="terms_conditions_editor"
                            class="min-h-[220px] px-4 py-3 text-sm leading-6 text-slate-800 outline-none"
                            contenteditable="true"
                            spellcheck="true"
                            data-placeholder="Enter terms and conditions here."
                        >{!! $termsValue !!}</div>
                    </div>
                    <x-input-error :messages="$errors->get('terms_conditions')" class="mt-2" />
                </div>
            @endunless
            <div class="mt-5 flex flex-wrap justify-end gap-3">
                <x-primary-button>{{ $isEdit ? 'Update' : $title }}</x-primary-button>
            </div>

            @if(! $isInvoice)
                <!-- Save as Template Modal in Quotation Generator -->
                <div x-cloak x-show="saveTemplateModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display:none;" role="dialog" aria-modal="true">
                    <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" @click="saveTemplateModalOpen = false"></div>
                        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
                            <div class="flex items-center justify-between border-b pb-3">
                                <h3 class="text-lg font-bold text-slate-900">Save Quotation Items as Template</h3>
                                <button type="button" @click="saveTemplateModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                This will save all items in this quotation (materials, quantities, units, rates, and GST) so you can reuse them for any other client. Client details will not be included.
                            </p>
                            <div class="mt-4 space-y-4">
                                <div>
                                    <x-input-label for="sc_tpl_name" value="Template Name *" />
                                    <x-text-input id="sc_tpl_name" x-model="templateForm.name" class="mt-1 block w-full rounded-xl" placeholder="e.g. Standard 4-Ch CCTV Package" />
                                </div>
                                <div>
                                    <x-input-label for="sc_tpl_desc" value="Description (Optional)" />
                                    <textarea id="sc_tpl_desc" x-model="templateForm.description" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Optional notes about what this template contains..."></textarea>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end gap-3">
                                <button type="button" @click="saveTemplateModalOpen = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                <button type="button" @click="submitSaveTemplate()" :disabled="savingTemplate" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50">
                                    <span x-show="!savingTemplate">Save Template</span>
                                    <span x-show="savingTemplate">Saving...</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </form>
    </div>

    @if(filled(config('services.google_maps.key')))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const addressInput = document.getElementById('client_address');
                if (!addressInput || !window.google?.maps?.places) {
                    return;
                }

                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: ['in'] },
                    fields: ['formatted_address'],
                });

                autocomplete.addListener('place_changed', function () {
                    const place = autocomplete.getPlace();
                    if (!place || !place.formatted_address) {
                        return;
                    }

                    addressInput.value = place.formatted_address;
                    addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                });
            });
        </script>
    @endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editor = document.getElementById('terms_conditions_editor');
            const hiddenInput = document.getElementById('terms_conditions');
            const form = editor?.closest('form');

            if (!editor || !hiddenInput) {
                return;
            }

            const currentValue = hiddenInput.value || '';
            const resetValue = @js($defaultTermsHtml);
            editor.innerHTML = currentValue ? currentValue : '<p></p>';

            const syncEditor = () => {
                hiddenInput.value = editor.innerHTML;
            };

            const applyCommand = (command, value = null) => {
                editor.focus();
                document.execCommand(command, false, value);
                syncEditor();
            };

            editor.addEventListener('input', syncEditor);
            editor.addEventListener('blur', syncEditor);
            if (form) {
                form.addEventListener('submit', syncEditor);
            }

            document.querySelectorAll('[data-terms-editor-cmd]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const command = button.getAttribute('data-terms-editor-cmd');
                    if (!command) return;

                    if (command === 'createLink') {
                        const url = window.prompt('Enter link URL');
                        if (!url) return;
                        applyCommand(command, url);
                        return;
                    }

                    if (command === 'clear') {
                        editor.innerHTML = '<p></p>';
                        syncEditor();
                        return;
                    }

                    if (command === 'reset') {
                        editor.innerHTML = resetValue ? resetValue : '<p></p>';
                        syncEditor();
                        return;
                    }

                    applyCommand(command);
                });
            });

            document.querySelectorAll('[data-terms-editor-file]').forEach(function (select) {
                select.addEventListener('change', function (event) {
                    const action = event.target.value;
                    event.target.value = '';
                    if (action === 'clear') {
                        editor.innerHTML = '<p></p>';
                        syncEditor();
                    } else if (action === 'reset') {
                        editor.innerHTML = resetValue ? resetValue : '<p></p>';
                        syncEditor();
                    }
                });
            });

            document.querySelectorAll('[data-terms-editor-format]').forEach(function (select) {
                select.addEventListener('change', function (event) {
                    const tag = event.target.value;
                    event.target.value = '';
                    if (!tag) return;
                    editor.focus();
                    document.execCommand('formatBlock', false, tag);
                    syncEditor();
                });
            });

            document.querySelectorAll('[data-terms-editor-size]').forEach(function (select) {
                select.addEventListener('change', function (event) {
                    const size = event.target.value;
                    event.target.value = '';
                    if (!size) return;
                    editor.focus();
                    document.execCommand('fontSize', false, size);
                    syncEditor();
                });
            });

            document.querySelectorAll('[data-terms-editor-color]').forEach(function (input) {
                input.addEventListener('input', function (event) {
                    editor.focus();
                    document.execCommand('foreColor', false, event.target.value);
                    syncEditor();
                });
            });

            document.querySelectorAll('[data-terms-editor-background]').forEach(function (input) {
                input.addEventListener('input', function (event) {
                    editor.focus();
                    document.execCommand('hiliteColor', false, event.target.value);
                    syncEditor();
                });
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const $form = $('#service-call-billing-form');
            if (!$form.length || !$.fn.validate) {
                return;
            }

            $.validator.addMethod('clientPhone', function (value, element) {
                const trimmed = $.trim(value);
                return this.optional(element) || /^[0-9+\-\s()]{6,20}$/.test(trimmed);
            }, 'Please enter a valid contact number.');

            $.validator.addMethod('gstFormat', function (value, element) {
                const trimmed = $.trim(value).toUpperCase();
                return this.optional(element) || /^[0-9A-Z]{15}$/.test(trimmed);
            }, 'Please enter a valid GST number.');

            const placeError = function (error, element) {
                const name = element.attr('name') || '';
                const slotId = {
                    client_name: '#client_name_error',
                    client_mobile: '#client_mobile_error',
                    client_email: '#client_email_error',
                    client_address: '#client_address_error',
                    client_gst_number: '#client_gst_number_error',
                    client_state_id: '#client_state_id_error',
                    client_city_id: '#client_city_id_error',
                    payment_status: '#payment_status_error',
                    payment_method: '#payment_method_error',
                    terms_conditions: '#terms_conditions_error',
                    discount: '#discount_error',
                }[name];

                if (slotId) {
                    const $slot = $(slotId);
                    if ($slot.length) {
                        $slot.empty().append(error);
                        return;
                    }
                }

                error.insertAfter(element);
            };

            const setFieldError = function (slotSelector, message) {
                const $slot = $(slotSelector);
                if (!$slot.length) return;
                $slot.html(`<span class="block text-sm text-red-600">${message}</span>`);
            };

            const clearFieldError = function (slotSelector) {
                const $slot = $(slotSelector);
                if ($slot.length) {
                    $slot.empty();
                }
            };

            $form.data('selectedClient', null);

            const selectedClientFromState = function () {
                const raw = $form.attr('data-selected-client') || $form.data('selectedClient');

                if (!raw) {
                    return null;
                }

                if (typeof raw === 'object') {
                    return raw;
                }

                try {
                    return JSON.parse(raw);
                } catch (error) {
                    return null;
                }
            };

            const getClientId = function () {
                return $.trim($form.find('input[name="client_id"]').val() || '');
            };

            const getPaymentStatus = function () {
                return $.trim($form.find('select[name="payment_status"]').val() || '');
            };

            const syncClientValidation = function () {
                const validator = $form.data('validator');
                if (!validator) {
                    return;
                }

                validator.element('#client_name');

                if (getClientId() !== '') {
                    clearFieldError('#client_name_error');
                    $('#client_name').removeClass('error border-red-500');
                }
            };

            $form.validate({
                ignore: [],
                errorElement: 'span',
                errorClass: 'block text-sm text-red-600',
                onkeyup: function (element) {
                    $(element).valid();
                },
                onfocusout: function (element) {
                    $(element).valid();
                },
                rules: {
                    client_name: {
                        required: {
                            depends: function () {
                                return getClientId() === '';
                            }
                        },
                        normalizer: function (value) { return $.trim(value); },
                        minlength: 2,
                        maxlength: 191
                    },
                    client_mobile: {
                        normalizer: function (value) { return $.trim(value); },
                        clientPhone: true,
                        minlength: 6,
                        maxlength: 20
                    },
                    client_email: {
                        normalizer: function (value) { return $.trim(value); },
                        email: true,
                        maxlength: 191
                    },
                    client_address: {
                        normalizer: function (value) { return $.trim(value); },
                        maxlength: 1000
                    },
                    client_gst_number: {
                        normalizer: function (value) { return $.trim(value).toUpperCase(); },
                        gstFormat: true,
                        maxlength: 15
                    },
                    client_state_id: {
                        digits: true
                    },
                    client_city_id: {
                        digits: true
                    },
                    payment_status: {
                        required: @js($isInvoice)
                    },
                    payment_method: {
                        required: {
                            depends: function () {
                                return @js($isInvoice) && getPaymentStatus() === '1';
                            }
                        }
                    },
                    discount: {
                        number: true,
                        min: 0
                    }
                },
                messages: {
                    client_name: {
                        required: 'Please enter a client name.',
                        minlength: 'Client name must be at least 2 characters.',
                        maxlength: 'Client name may not be greater than 191 characters.'
                    },
                    client_mobile: {
                        clientPhone: 'Please enter a valid contact number.',
                        minlength: 'Contact number must be at least 6 characters.',
                        maxlength: 'Contact number may not be greater than 20 characters.'
                    },
                    client_email: {
                        email: 'Please enter a valid email address.',
                        maxlength: 'Email may not be greater than 191 characters.'
                    },
                    client_address: {
                        maxlength: 'Address may not be greater than 1000 characters.'
                    },
                    client_gst_number: {
                        maxlength: 'GST number may not be greater than 15 characters.'
                    },
                    client_state_id: {
                        digits: 'State must be a valid numeric value.'
                    },
                    client_city_id: {
                        digits: 'City must be a valid numeric value.'
                    },
                    payment_status: {
                        required: 'Please select payment status.'
                    },
                    payment_method: {
                        required: 'Please select payment method.'
                    },
                    discount: {
                        number: 'Discount must be numeric.',
                        min: 'Discount cannot be negative.'
                    }
                },
                errorPlacement: placeError,
                showErrors: function (errorMap, errorList) {
                    this.defaultShowErrors();
                    if (errorMap.client_name) {
                        setFieldError('#client_name_error', errorMap.client_name);
                    }
                },
                highlight: function (element) {
                    $(element).addClass('border-red-500');
                },
                unhighlight: function (element) {
                    $(element).removeClass('border-red-500');
                    const name = $(element).attr('name') || '';
                    const slotId = {
                        client_name: '#client_name_error',
                        client_mobile: '#client_mobile_error',
                        client_email: '#client_email_error',
                        client_address: '#client_address_error',
                        client_gst_number: '#client_gst_number_error',
                        client_state_id: '#client_state_id_error',
                        client_city_id: '#client_city_id_error',
                        payment_status: '#payment_status_error',
                        payment_method: '#payment_method_error',
                        terms_conditions: '#terms_conditions_error',
                        discount: '#discount_error',
                    }[name];
                    if (slotId) {
                        $(slotId).empty();
                    }
                },
                submitHandler: function (form) {
                    const editor = document.getElementById('terms_conditions_editor');
                    const hiddenInput = document.getElementById('terms_conditions');
                    const clientName = $.trim($('#client_name').val() || '');
                    const clientId = getClientId();
                    const paymentStatus = getPaymentStatus();
                    const paymentMethodInput = $form.find('select[name="payment_method"]');

                    if (!clientId && !clientName) {
                        setFieldError('#client_name_error', 'Client name is required.');
                        $('#client_name').addClass('border-red-500').focus();
                        return false;
                    }

                    if (clientId && !clientName) {
                        const selectedClient = selectedClientFromState();
                        if (selectedClient && selectedClient.name) {
                            $('#client_name').val(selectedClient.name);
                        }
                    }

                    syncClientValidation();

                    if (@js($isInvoice) && paymentStatus !== '1') {
                        paymentMethodInput.val('');
                        clearFieldError('#payment_method_error');
                    }

                    if (clientName) {
                        clearFieldError('#client_name_error');
                    }

                    if (editor && hiddenInput) {
                        hiddenInput.value = editor.innerHTML;
                    }
                    form.submit();
                }
            });
        });
    </script>
    <script>
        function billingFormData(config) {
            return {
                clientId: config.clientId || '',
                selectedClient: config.selectedClient || null,
                customerName: config.customerName || '',
                showNameDropdown: false,
                mobile: config.mobile || '',
                email: config.email || '',
                subject: config.subject || '',
                gstNumber: config.gstNumber || '',
                address: config.address || '',
                stateId: config.stateId || '',
                cityId: config.cityId || '',
                cityOptions: config.cityOptions || [],
                documentType: config.documentType,
                isInvoice: Boolean(config.isInvoice),
                clients: config.clients || [],
                cityOptionsByState: config.cityOptionsByState || {},
                stateNamesById: config.stateNamesById || {},
                companyState: config.companyState || '',
                manualProductEntry: Boolean(config.manualProductEntry),
                gstRateOptions: Array.isArray(config.gstRateOptions) ? config.gstRateOptions : [],
                unitOptions: Array.isArray(config.billingUnitOptions) ? config.billingUnitOptions : [],
                ensureUnitOption(value = '') {
                    const unit = String(value || '').trim();
                    if (unit && !this.unitOptions.includes(unit)) {
                        this.unitOptions.push(unit);
                    }
                    return unit;
                },
                ensureGstOption(value = 0) {
                    const rate = Number(value || 0);
                    if (!this.gstRateOptions.some((r) => Math.abs(Number(r) - rate) < 0.001)) {
                        this.gstRateOptions.push(rate);
                        this.gstRateOptions.sort((a, b) => Number(a) - Number(b));
                    }
                    return rate;
                },
                unitOptionsFor(selectedUnit = '') {
                    const selected = String(selectedUnit || '').trim();
                    if (selected && !this.unitOptions.includes(selected)) {
                        this.unitOptions.push(selected);
                    }
                    return this.unitOptions;
                },
                discount: Number(config.discount || 0),
                additionalNotes: config.additionalNotes || '',
                rows: Array.isArray(config.rows) && config.rows.length ? config.rows : [
                    { product_id: '', name: '', description: '', qty: 1, price: 0, gst: 0, note: '', unit: '', brand_model: '', product_search: '', product_open: false }
                ],
                products: Array.isArray(config.products) ? config.products : [],
                templates: Array.isArray(config.templates) ? config.templates : [],
                selectedTemplateId: '',
                saveTemplateModalOpen: false,
                savingTemplate: false,
                templateForm: {
                    name: '',
                    description: '',
                },
                templateStoreUrl: config.templateStoreUrl || '',
                paymentStatuses: [
                    { value: '0', label: 'Unpaid' },
                    { value: '1', label: 'Paid' },
                ],
                paymentMethods: [
                    { value: '0', label: 'Cash' },
                    { value: '1', label: 'Online' },
                ],
                citiesForState() {
                    return this.cityOptions;
                },
                findProduct(row) {
                    return this.products.find((item) => String(item.id) === String(row.product_id));
                },
                productBrandModel(row) {
                    const product = this.findProduct(row);
                    return product ? [product.brand, product.model].filter(Boolean).join(' / ') : '';
                },
                filteredProducts(row) {
                    const query = String(row.product_search || '').trim().toLowerCase();
                    if (!query) return this.products;
                    return this.products.filter((product) => [product.name, product.brand, product.model, product.unit].filter(Boolean).join(' ').toLowerCase().includes(query));
                },
                productUnit(row) {
                    const product = this.findProduct(row);
                    return product ? product.unit : (row.unit || '');
                },
                productLabel(row) {
                    const product = this.findProduct(row);
                    return product ? product.name : (row.name || '');
                },
                gstOptionsFor(selectedGst) {
                    const rates = [...this.gstRateOptions];
                    const selected = Number(selectedGst || 0);
                    if (!rates.some((rate) => Math.abs(Number(rate) - selected) < 0.001)) {
                        rates.push(selected);
                    }
                    return rates
                        .map((rate) => Number(rate))
                        .filter((rate) => Number.isFinite(rate) && rate >= 0)
                        .sort((a, b) => a - b);
                },
                itemsSubtotal() {
                    return this.rows.reduce((sum, row) => sum + (Number(row.qty || 0) * Number(row.price || 0)), 0);
                },
                taxableAmount() {
                    const subtotal = this.itemsSubtotal();
                    const capped = Math.min(Math.max(0, Number(this.discount || 0)), subtotal);
                    return Math.max(0, subtotal - capped);
                },
                gstTotal() {
                    return this.rows.reduce((sum, row) => {
                        const base = Number(row.qty || 0) * Number(row.price || 0);
                        return sum + ((base * Number(row.gst || 0)) / 100);
                    }, 0);
                },
                grandTotal() {
                    return this.taxableAmount() + this.gstTotal();
                },
                taxLabel() {
                    const clientStateName = this.stateNamesById[this.stateId] || '';
                    const isIntraState = this.companyState !== '' && clientStateName !== ''
                        && this.companyState.toLowerCase() === clientStateName.toLowerCase();
                    return isIntraState ? 'SGST + CGST (item-wise)' : 'IGST (item-wise)';
                },
                filteredClients() {
                    const query = this.customerName.trim().toLowerCase();
                    if (!query) return this.clients;
                    return this.clients.filter((item) => item.search.includes(query));
                },
                onNameInput() {
                    this.showNameDropdown = true;
                    if (this.clientId) {
                        const client = this.clients.find((item) => String(item.id) === String(this.clientId));
                        if (!client || client.name !== this.customerName) {
                            this.clientId = '';
                            this.selectedClient = null;
                            this.$el.dataset.selectedClient = JSON.stringify(null);
                        }
                    }
                },
                selectClient(client) {
                    this.clientId = client.id;
                    this.customerName = client.name;
                    this.selectedClient = client;
                    this.$el.dataset.selectedClient = JSON.stringify(client);
                    this.showNameDropdown = false;
                    this.mobile = client.mobile || client.phone || '';
                    this.email = client.email || '';
                    this.subject = client.name || '';
                    this.gstNumber = client.gst_number || '';
                    this.address = client.address || '';
                    this.stateId = client.state_id || '';
                    this.cityId = client.city_id || '';
                    this.cityOptions = this.cityOptionsByState[this.stateId] || [];
                    this.$nextTick(() => {
                        const validator = $('#service-call-billing-form').data('validator');
                        if (validator) {
                            validator.element('#client_name');
                        }
                    });
                },
                addRow() {
                    this.rows.push({ product_id: '', name: '', description: '', qty: 1, price: 0, gst: 0, note: '', unit: '', brand_model: '', product_search: '', product_open: false });
                },
                removeRow(index) {
                    if (this.rows.length > 1) this.rows.splice(index, 1);
                },
                selectProduct(row, rowElement = null) {
                    const product = this.products.find((item) => String(item.id) === String(row.product_id));
                    if (!product) return;
                    row.price = Number(product.price || 0);
                    const gst = Number(product.gst || 0);
                    this.ensureGstOption(gst);
                    row.gst = gst;
                    row.name = product.name || (row.name || '');
                    row.description = product.description || (row.description || '');
                    const unit = String(product.unit || '').trim();
                    if (unit) {
                        this.ensureUnitOption(unit);
                        row.unit = unit;
                    }
                    row.brand_model = product ? [product.brand, product.model].filter(Boolean).join(' / ') : (row.brand_model || '');
                    row.product_search = '';
                    row.product_open = false;
                    this.$nextTick(() => {
                        if (unit) row.unit = unit;
                        row.gst = gst;

                        const container = rowElement || document;
                        const selects = container.querySelectorAll('select');
                        selects.forEach((select) => {
                            if (typeof select._syncCustomSelect === 'function') {
                                select._syncCustomSelect();
                            } else {
                                select.dispatchEvent(new Event('change', { bubbles: true }));
                            }
                        });
                    });
                },
                chooseProduct(row, product, rowElement = null) {
                    row.product_id = String(product.id);
                    this.selectProduct(row, rowElement);
                },
                rowTotal(row) {
                    const gst = Number(row.gst || 0);
                    const base = Number(row.qty || 0) * Number(row.price || 0);
                    return (base + ((base * gst) / 100)).toFixed(2);
                },
                selectOptionFromSearch(field, option) {
                    if (!option) return;
                    field.selectedId = String(option.id);
                    field.selectedLabel = option.label;
                    field.query = option.label;
                    field.open = false;
                    if (typeof field.onSelect === 'function') {
                        field.onSelect(option);
                    }
                },
                applyTemplate(templateId) {
                    if (!templateId) return;
                    const tmpl = this.templates.find((t) => String(t.id) === String(templateId));
                    if (!tmpl || !Array.isArray(tmpl.items) || !tmpl.items.length) {
                        return;
                    }

                    const isOnlyEmptyRow = this.rows.length === 1 && !this.rows[0].name && !this.rows[0].product_id && Number(this.rows[0].price || 0) === 0;
                    if (isOnlyEmptyRow) {
                        this.rows = [];
                    }

                    tmpl.items.forEach((ti) => {
                        const gstRate = Number(ti.gst || 0);
                        if (!this.gstRateOptions.some((rate) => Math.abs(Number(rate) - gstRate) < 0.001)) {
                            this.gstRateOptions.push(gstRate);
                        }

                        let prodId = String(ti.product_id || '');
                        let matchedProduct = null;
                        if (prodId) {
                            matchedProduct = this.products.find((p) => String(p.id) === prodId);
                        }
                        if (!matchedProduct && ti.name) {
                            matchedProduct = this.products.find((p) => String(p.name || '').trim().toLowerCase() === String(ti.name || '').trim().toLowerCase());
                        }
                        if (matchedProduct) {
                            prodId = String(matchedProduct.id);
                        }

                        this.rows.push({
                            product_id: prodId,
                            name: matchedProduct ? matchedProduct.name : String(ti.name || ti.material_name || ti.description || ''),
                            description: String(ti.description || matchedProduct?.description || ''),
                            brand_model: String(ti.brand_model || (matchedProduct ? [matchedProduct.brand, matchedProduct.model].filter(Boolean).join(' / ') : '')),
                            unit: String(ti.unit || matchedProduct?.unit || ''),
                            qty: Number(ti.qty || 1),
                            price: Number(ti.price || ti.rate || matchedProduct?.price || 0),
                            gst: gstRate,
                            note: '',
                            product_search: '',
                            product_open: false,
                        });
                    });

                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Template Loaded',
                            text: `Loaded ${tmpl.items.length} items from "${tmpl.name}"`,
                            timer: 2000,
                            showConfirmButton: false,
                        });
                    }

                    this.selectedTemplateId = '';

                    this.$nextTick(() => {
                        const tableBody = document.querySelector('table tbody');
                        if (tableBody) {
                            tableBody.querySelectorAll('select').forEach((select) => {
                                if (typeof select._syncCustomSelect === 'function') {
                                    select._syncCustomSelect();
                                } else {
                                    select.dispatchEvent(new Event('change', { bubbles: true }));
                                }
                            });
                        }
                    });
                },
                openSaveTemplateModal() {
                    const validRows = this.rows.filter((row) => (row.name || this.productLabel(row) || '').trim() !== '');
                    if (!validRows.length) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'warning',
                                title: 'No Items',
                                text: 'Please add at least one item before saving as a template.',
                            });
                        } else {
                            alert('Please add at least one item before saving as a template.');
                        }
                        return;
                    }
                    this.templateForm.name = '';
                    this.templateForm.description = '';
                    this.saveTemplateModalOpen = true;
                },
                async submitSaveTemplate() {
                    const name = (this.templateForm.name || '').trim();
                    if (!name) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'warning', title: 'Required', text: 'Template name is required.' });
                        } else {
                            alert('Template name is required.');
                        }
                        return;
                    }

                    const validRows = this.rows.filter((row) => (row.name || this.productLabel(row) || '').trim() !== '');
                    if (!validRows.length) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'warning', title: 'No Items', text: 'Please add at least one item before saving.' });
                        } else {
                            alert('Please add at least one item before saving.');
                        }
                        return;
                    }

                    this.savingTemplate = true;

                    try {
                        const payloadItems = validRows.map((row) => {
                            const prod = this.findProduct(row);
                            const itemName = (row.name || prod?.name || '').trim();
                            const brandModel = (row.brand_model || this.productBrandModel(row) || '').trim();
                            const unit = (row.unit || this.productUnit(row) || '').trim();
                            const qty = Number(row.qty || 1);
                            const price = Number(row.price || 0);
                            const gst = Number(row.gst || 0);
                            const baseTotal = qty * price;
                            const gstAmount = (baseTotal * gst) / 100;
                            const total = baseTotal + gstAmount;

                            return {
                                product_id: row.product_id || (prod ? String(prod.id) : ''),
                                category_id: prod?.category_id || '',
                                category_name: prod?.category_name || '',
                                name: itemName,
                                description: row.description || prod?.description || '',
                                brand_model: brandModel,
                                unit: unit,
                                qty: qty,
                                price: price,
                                gst: gst,
                                base_total: baseTotal,
                                gst_amount: gstAmount,
                                total: total,
                            };
                        });

                        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                            || document.querySelector('input[name="_token"]')?.value
                            || '';

                        const response = await fetch(this.templateStoreUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                name: name,
                                description: this.templateForm.description,
                                items: payloadItems,
                            }),
                        });

                        const result = await response.json();

                        if (response.ok && result.success) {
                            if (result.template) {
                                this.templates.unshift(result.template);
                            }
                            this.saveTemplateModalOpen = false;
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Saved as Template!',
                                    text: 'Only items and their details were saved. Client details were excluded.',
                                    timer: 3000,
                                    showConfirmButton: false,
                                });
                            }
                        } else {
                            const msg = result.message || 'Failed to save quotation template.';
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({ icon: 'error', title: 'Error', text: msg });
                            } else {
                                alert(msg);
                            }
                        }
                    } catch (err) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'An error occurred while saving the template.' });
                        } else {
                            alert('An error occurred while saving the template.');
                        }
                    } finally {
                        this.savingTemplate = false;
                    }
                },
            };
        }
    </script>
</x-app-layout>
