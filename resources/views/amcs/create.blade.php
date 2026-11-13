<x-app-layout>
    <style>
        label.error {
            display: block;
            color: #ef4444;
            font-size: 0.75rem;
            margin-top: 0.25rem;
            font-weight: 500;
        }
        .checkbox-group.error-box,
        .select-wrapper.error-box {
            border-color: #ef4444 !important;
        }
        input.error, select.error, textarea.error {
            border-color: #ef4444 !important;
            focus-ring: #ef4444 !important;
        }
    </style>

    @php
    $cancelUrl = ($isEdit ?? false) && $amc ? route('amcs.show', $amc) : route('amcs.index');
@endphp

    <x-slot name="header">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:items-start sm:justify-between sm:text-left">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ ($isEdit ?? false) ? 'Edit AMC' : 'Create AMC' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ ($isEdit ?? false) ? 'Update the annual maintenance contract details.' : 'Create a new annual maintenance contract.' }}</p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                <div class="mx-auto w-full max-w-xs bg-indigo-50 px-4 py-3 text-sm text-indigo-700 sm:mx-0 sm:w-auto">
                    <div class="font-semibold">{{ ($isEdit ?? false) ? 'Secure Reference' : 'Next Contract No' }}</div>
                    <div class="break-words">{{ ($isEdit ?? false) && $amc ? $amc->secure_reference : $nextAmcContractNo }}</div>
                </div>
                <a href="{{ $cancelUrl }}" class="inline-flex w-full items-center justify-center border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                    {{ $isEdit ? 'Back to AMC Details' : 'Back to AMC List' }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-3 sm:py-8">
        <div class="w-full space-y-4 px-3 sm:px-6 lg:px-8">
            @php
                $amcTypeSelectOptions = [
                    'comprehensive' => 'Comprehensive',
                    'non_comprehensive' => 'Non-Comprehensive',
                ];
                $isEdit = (bool) ($isEdit ?? false);
                $amc = $amc ?? null;
                $defaultPlanId = old('amc_plan_id', $amc?->amc_plan_id ? (string) $amc->amc_plan_id : ($amcPlans->first()?->id ? (string) $amcPlans->first()->id : ''));
                $defaultTaxPercent = old('tax_percent', $amc?->tax_percent ?? ($gstRates->first()?->rate ?? 0));
                $defaultClientMode = old('client_mode', $amc?->client_id ? 'existing' : 'new');
                $defaultProjectMode = old('project_mode', $amc?->project_id ? 'existing' : 'new');
                $defaultStartDate = $isEdit
                    ? (optional($amc?->start_date)->format('Y-m-d') ?: now()->format('Y-m-d'))
                    : old('start_date', now()->format('Y-m-d'));
                $defaultAmcAmount = old('amc_amount', ($amc?->amc_amount !== null ? (string) $amc->amc_amount : ''));
                $defaultDiscount = old('discount', $amc?->discount ?? 0);
                $defaultPlanDescription = old('plan_description', $amc?->plan_description ?? '');
                $defaultSelectedClientId = old('client_id', $amc?->client_id ?? '');
                $defaultSelectedProjectId = old('existing_project_id', $amc?->project_id ?? '');
                $defaultCustomerName = old('customer_name', $amc?->customer_name ?? '');
                $defaultProjectName = old('project_name', $amc?->project_name ?? '');
                $defaultAmcType = old('amc_type', in_array($amc?->amc_type, array_keys($amcTypeSelectOptions), true) ? $amc?->amc_type : 'non_comprehensive');
                $defaultMobile = old('mobile_number', $amc?->mobile_number ?? '');
                $defaultEmail = old('email', $amc?->email ?? '');
                $defaultAddress = old('address', $amc?->address ?? '');
                $defaultGstNumber = old('gst_number', $amc?->gst_number ?? '');
                $cancelUrl = $isEdit && $amc ? route('amcs.show', $amc) : route('amcs.index');
                $clientOptions = $amcClients->map(fn ($client) => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'meta' => collect([
                        $client->email,
                        $client->mobile ?: $client->phone,
                        $client->address,
                    ])->filter()->implode(' • '),
                ])->values();
                $projectOptions = $amcProjects->map(fn ($project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                    'meta' => collect([
                        $project->client_name,
                    ])->filter()->implode(' • '),
                ])->values();
                $planOptions = $amcPlans->map(fn ($plan) => [
                    'id' => (string) $plan->id,
                    'name' => $plan->name,
                    'meta' => collect([
                        \App\Models\Amc::formatDurationMonths($plan->duration_months),
                        $plan->visit_count . ' visits',
                        'Rs. ' . number_format((float) $plan->price, 2),
                    ])->filter()->implode(' • '),
                ])->values();
            @endphp
            <div
                class=" border border-slate-200 bg-white p-3 shadow-sm sm:p-6"
                x-data="amcFormData()"
            >
                <div class="mb-4 sm:mb-6">
                    <h3 class="text-lg font-bold text-slate-900">AMC Service Form</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ $isEdit ? 'Update AMC contract details, pricing, and visit plan.' : 'Create AMC contracts with customer details, system coverage, and payment details.' }}</p>
                </div>

                @if($amcPlans->isEmpty())
                    <div class="mb-6 border border-amber-200 bg-amber-50 px-4 py-4 text-sm text-amber-900">
                        <div class="font-semibold">No AMC plans available.</div>
                        <p class="mt-1">Create at least one AMC plan first so contract duration and visit count can be assigned automatically.</p>
                        @if($canManageAmcPlans)
                            <a href="{{ route('amc-plans.index') }}" class="mt-3 inline-flex items-center bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                                Manage AMC Plans
                            </a>
                        @endif
                    </div>
                @endif

                <form id="amcForm" method="POST" action="{{ $isEdit ? route('amcs.update', $amc) : route('amcs.store') }}" class="space-y-8">
                    @csrf
                    @if($isEdit)
                        @method('PATCH')
                    @endif
                    <input type="hidden" name="total_amount" :value="amcTotal()">

                    <section class="space-y-4">
                        <h4 class="text-base font-bold text-slate-900">1. Customer Details</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="client_mode" value="Client Type" />
                                <select id="client_mode" name="client_mode" x-model="amcClientMode" class="mt-1 block w-full rounded-md border-slate-300">
                                    <option value="existing">Existing Client</option>
                                    <option value="new">New Client</option>
                                </select>
                            </div>
                            <div x-show="amcClientMode === 'existing'">
                                <x-searchable-select
                                    name="client_id"
                                    id="client_id"
                                    label="Select Client"
                                    :options="$clientOptions"
                                    :selected="$defaultSelectedClientId"
                                    placeholder="Search clients by name, email, phone, or address"
                                    empty-label="Choose client"
                                    helper-text="Search and select the existing client for this AMC."
                                />
                            </div>
                            <div>
                                <x-input-label for="customer_name" value="Customer(Client) Name" />
                                <x-text-input id="customer_name" name="customer_name" x-model="amcCustomerName" class="mt-1 block w-full" placeholder="Enter customer or client name" minlength="2" maxlength="191" />
                                <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="project_mode" value="Project Type" />
                                <select id="project_mode" name="project_mode" x-model="amcProjectMode" class="mt-1 block w-full rounded-md border-slate-300">
                                    <option value="existing">Existing Project</option>
                                    <option value="new">New Project</option>
                                </select>
                            </div>
                            <div x-show="amcProjectMode === 'existing'">
                                <x-searchable-select
                                    name="existing_project_id"
                                    id="existing_project_id"
                                    label="Select Project"
                                    :options="$projectOptions"
                                    :selected="$defaultSelectedProjectId"
                                    placeholder="Search projects by name or client"
                                    empty-label="Choose project"
                                    helper-text="Search and select the project to link with this AMC."
                                />
                            </div>
                            <div>
                                <x-input-label for="project_name" value="Project Name" />
                                <x-text-input id="project_name" name="project_name" x-model="amcProjectName" class="mt-1 block w-full" placeholder="Enter AMC project name" minlength="2" maxlength="191" />
                                <x-input-error :messages="$errors->get('project_name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="mobile_number" value="Mobile Number" />
                                <x-text-input id="mobile_number" name="mobile_number" x-model="amcMobile" class="mt-1 block w-full" placeholder="Enter 10-digit mobile number" inputmode="numeric" minlength="10" maxlength="10" />
                                <x-input-error id="mobile_number-server-error" :messages="$errors->get('mobile_number')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="email" value="Email (Optional)" />
                                <x-text-input id="email" type="email" name="email" x-model="amcEmail" class="mt-1 block w-full" placeholder="name@example.com" maxlength="191" />
                                <p class="mt-1 text-xs text-slate-500">Leave this blank if you do not have an email address. If you enter one, it must be valid.</p>
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="address" value="Address" />
                                <div class="relative mt-1">
                                    <textarea id="address" name="address" x-model="amcAddress" rows="3" class="block w-full rounded-md border-slate-300" placeholder="Enter full customer address" minlength="5" maxlength="1000" autocomplete="off" data-location-autocomplete="amc-address" data-autocomplete-url="{{ config('services.location_autocomplete.url') }}"></textarea>
                                    <div
                                        id="address-suggestions"
                                        class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden border border-slate-200 bg-white shadow-xl"
                                        role="listbox"
                                        aria-label="AMC address suggestions"
                                    ></div>
                                </div>
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
                            </div>
                            <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $amc?->latitude) }}">
                            <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $amc?->longitude) }}">
                            <div>
                                <x-input-label for="gst_number" value="GST Number (Optional)" />
                                <x-text-input id="gst_number" name="gst_number" x-model="amcGstNumber" class="mt-1 block w-full uppercase" placeholder="22AAAAA0000A1Z5" maxlength="15" />
                                <x-input-error :messages="$errors->get('gst_number')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h4 class="text-base font-bold text-slate-900">2. AMC Details</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label value="AMC Contract No" />
                                <x-text-input value="{{ $nextAmcContractNo }}" readonly class="mt-1 block w-full bg-slate-50" placeholder="Auto-generated AMC contract number" />
                            </div>
                            <div>
                                <x-input-label for="start_date" value="Start Date" />
                                <x-text-input
                                    id="start_date"
                                    type="date"
                                    name="start_date"
                                    value="{{ $defaultStartDate }}"
                                    x-model="amcStartDate"
                                    @change="syncAmcEndDate()"
                                    class="mt-1 block w-full"
                                />
                                <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between gap-2">
                                    @if($canManageAmcPlans)
                                        <a href="{{ route('amc-plans.index') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">Manage Plans</a>
                                    @endif
                                </div>
                                <x-searchable-select
                                    name="amc_plan_id"
                                    id="amc_plan_id"
                                    label="AMC Plan"
                                    :options="$planOptions"
                                    :selected="$defaultPlanId"
                                    placeholder="Search plan by duration, visits, or price"
                                    empty-label="Choose plan"
                                    helper-text="Pick the AMC plan that defines duration, visits, and pricing."
                                    :disabled="$amcPlans->isEmpty()"
                                />
                                <x-input-error :messages="$errors->get('amc_plan_id')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_date" value="End Date" />
                                <x-text-input id="end_date" type="date" x-model="amcEndDate" readonly class="mt-1 block w-full bg-slate-50" placeholder="Auto-calculated from plan" />
                            </div>
                            <div>
                                <x-input-label for="amc_type" value="AMC Type" />
                                <select id="amc_type" name="amc_type" x-model="amcType" class="mt-1 block w-full max-w-full min-w-0 rounded-md border-slate-300">
                                    @foreach($amcTypeSelectOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($defaultAmcType === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-slate-500">Choose the coverage level for this contract. On-call support is controlled by the selected AMC plan.</p>
                            </div>
                            <div class="md:col-span-2">
                                <div class="grid grid-cols-1 gap-3 border border-slate-200 bg-slate-50 p-4 sm:grid-cols-5">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Selected Plan</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedAmcPlan()?.name || 'Choose a plan'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contract Duration</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900" x-text="amcPlanDurationLabel()"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plan Mode</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedPlanServiceModeLabel()"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Scheduled Visits</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900" x-text="selectedPlanGeneratesVisits() ? `${amcPlanVisits()} visits` : 'No scheduled visits'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">On-Call Support</div>
                                        <div class="mt-1 text-sm font-semibold" :class="selectedPlanIncludesOnCallSupport() ? 'text-emerald-700' : 'text-slate-900'" x-text="selectedPlanIncludesOnCallSupport() ? 'Included' : 'Not Included'"></div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Plan Price</div>
                                        <div class="mt-1 text-sm font-semibold text-slate-900">Rs. <span x-text="Number(selectedAmcPlan()?.price || 0).toFixed(2)"></span></div>
                                    </div>
                                </div>
                                <div class="mt-3 border border-dashed border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" x-show="selectedPlanIncludesOnCallSupport()">
                                    <span x-text="selectedPlanGeneratesVisits() ? 'This plan includes on-call support in addition to its scheduled preventive visits.' : 'This plan is on-call only, so scheduled preventive visits will not be created.'"></span>
                                </div>
                                <div class="mt-3">
                                    <x-input-label for="plan_description" value="Plan Description" />
                                    <textarea id="plan_description" name="plan_description" x-model="amcPlanDescription" rows="4" readonly class="mt-1 block w-full rounded-md border-slate-300 bg-slate-50" placeholder="Plan description will appear here when you choose a plan."></textarea>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h4 class="text-base font-bold text-slate-900">3. Pricing Details</h4>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <x-input-label for="amc_amount" value="AMC Amount" />
                                <x-text-input id="amc_amount" type="number" step="0.01" name="amc_amount" value="{{ $defaultAmcAmount }}" x-model="amcAmount" @input="amcAmountEdited = true" class="mt-1 block w-full" placeholder="Enter AMC amount" />
                                <p class="mt-1 text-xs text-slate-500">Plan price is auto-filled when you select a plan. You can still adjust it if needed.</p>
                                <x-input-error :messages="$errors->get('amc_amount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="discount" value="Discount" />
                                <x-text-input id="discount" type="number" step="0.01" name="discount" x-model="amcDiscount" class="mt-1 block w-full" placeholder="Enter discount amount" />
                                <x-input-error :messages="$errors->get('discount')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="tax_percent" value="Tax (GST %)" />
                                @if($gstRates->isNotEmpty())
                                    <select id="tax_percent" name="tax_percent" x-model="amcTaxPercent" class="mt-1 block w-full rounded-md border-slate-300">
                                        @foreach($gstRates as $gstRate)
                                            <option value="{{ $gstRate->rate }}">{{ number_format((float) $gstRate->rate, 2) }}%</option>
                                        @endforeach
                                    </select>
                                    <p class="mt-1 text-xs text-slate-500">GST rates are fetched from your GST Master settings.</p>
                                @else
                                    <x-text-input id="tax_percent" type="number" step="0.01" name="tax_percent" x-model="amcTaxPercent" class="mt-1 block w-full" placeholder="Enter GST percentage" />
                                    <p class="mt-1 text-xs text-amber-600">No GST rates found in GST Master, so manual entry is enabled.</p>
                                @endif
                                <x-input-error :messages="$errors->get('tax_percent')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label value="Total Amount" />
                                <x-text-input x-bind:value="amcTotal()" readonly class="mt-1 block w-full bg-slate-50" placeholder="Auto-calculated total amount" />
                            </div>
                            <div>
                                <x-input-label for="payment_mode" value="Payment Mode" />
                                <select id="payment_mode" name="payment_mode" class="mt-1 block w-full max-w-full min-w-0 rounded-md border-slate-300">
                                    @foreach($amcPaymentModeOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_mode', $amc?->payment_mode) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="payment_status" value="Payment Status" />
                                <select id="payment_status" name="payment_status" class="mt-1 block w-full max-w-full min-w-0 rounded-md border-slate-300">
                                    @foreach($amcPaymentStatusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_status', $amc?->payment_status ?? 'pending') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4">
                        <h4 class="text-base font-bold text-slate-900">4. Terms & Conditions</h4>
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="service_timing" value="Service Timing" />
                                    <x-speech-to-text target="service_timing" />
                                </div>
                                <textarea id="service_timing" name="service_timing" rows="2" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Describe visit timing, frequency, and response window" maxlength="1000">{{ old('service_timing', $amc?->service_timing) }}</textarea>
                                <x-input-error :messages="$errors->get('service_timing')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="parts_replacement_policy" value="Parts Replacement Policy" />
                                    <x-speech-to-text target="parts_replacement_policy" />
                                </div>
                                <textarea id="parts_replacement_policy" name="parts_replacement_policy" rows="2" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Explain spare parts coverage and replacement terms" maxlength="1000">{{ old('parts_replacement_policy', $amc?->parts_replacement_policy) }}</textarea>
                                <x-input-error :messages="$errors->get('parts_replacement_policy')" class="mt-2" />
                            </div>
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="emergency_support_terms" value="Emergency Support Terms" />
                                    <x-speech-to-text target="emergency_support_terms" />
                                </div>
                                <textarea id="emergency_support_terms" name="emergency_support_terms" rows="2" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Mention emergency support hours, SLA, and escalation" maxlength="1000">{{ old('emergency_support_terms', $amc?->emergency_support_terms) }}</textarea>
                                <x-input-error :messages="$errors->get('emergency_support_terms')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col gap-3 pt-4 sm:flex-row sm:justify-end">
                        <a href="{{ route('amcs.index') }}" class="inline-flex w-full items-center justify-center border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                            Cancel
                        </a>
                        <x-primary-button id="submitBtn" class="w-full justify-center sm:w-auto" :disabled="$amcPlans->isEmpty()">{{ $isEdit ? 'Update AMC' : 'Create AMC' }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
    <script>
        const amcClients = @js($amcClients->map(fn ($client) => [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'mobile' => $client->mobile ?: $client->phone,
            'phone' => $client->phone ?: $client->mobile,
            'address' => $client->address,
            'gst_number' => $client->gst_number,
        ])->values());
        const amcProjects = @js($amcProjects->map(fn ($amcProject) => [
            'id' => $amcProject->id,
            'name' => $amcProject->name,
            'client_name' => $amcProject->client_name,
        ])->values());
        const amcPlans = @js($amcPlans->map(fn ($plan) => [
            'id' => (string) $plan->id,
            'name' => $plan->name,
            'duration_months' => (int) $plan->duration_months,
            'visit_count' => (int) $plan->visit_count,
            'price' => (float) $plan->price,
            'description' => $plan->description,
            'includes_on_call_support' => (bool) $plan->includes_on_call_support,
            'service_mode' => $plan->service_mode ?: ($plan->includes_on_call_support ? 'on_call' : 'scheduled'),
        ])->values());

        function amcFormData() {
            return {
                amcClientMode: @js($defaultClientMode),
                amcProjectMode: @js($defaultProjectMode),
                amcStartDate: @js($defaultStartDate),
                amcEndDate: '',
                amcAmount: Number(@js($defaultAmcAmount)),
                amcDiscount: Number(@js($defaultDiscount)),
                amcTaxPercent: Number(@js($defaultTaxPercent)),
                amcPlanDescription: @js($defaultPlanDescription),
                amcAmountEdited: @js(old('amc_amount') !== null),
                amcSelectedPlanId: @js($defaultPlanId),
                amcSelectedClientId: @js($defaultSelectedClientId),
                amcSelectedProjectId: @js($defaultSelectedProjectId),
                isEditMode: @js($isEdit),
                amcCustomerName: @js($defaultCustomerName),
                amcProjectName: @js($defaultProjectName),
                amcType: @js($defaultAmcType),
                amcMobile: @js($defaultMobile),
                amcEmail: @js($defaultEmail),
                amcAddress: @js($defaultAddress),
                amcGstNumber: @js($defaultGstNumber),
                amcClients,
                amcProjects,
                amcPlans,
                init() {
                    this.syncAmcEndDate();
                    this.applySelectedPlanDetails();
                    if (this.amcSelectedClientId) this.applyAmcClient(this.amcSelectedClientId, { preserveAddress: this.isEditMode });
                    if (this.amcSelectedProjectId) this.applyAmcProject(this.amcSelectedProjectId);
                    this.bindSearchableSelects();
                },
                selectedAmcPlan() {
                    return this.amcPlans.find(item => String(item.id) === String(this.amcSelectedPlanId)) || null;
                },
                syncAmcEndDate() {
                    if (!this.amcStartDate || !this.selectedAmcPlan()) {
                        this.amcEndDate = '';
                        return;
                    }

                    const start = new Date(this.amcStartDate);
                    if (Number.isNaN(start.getTime())) return;
                    start.setMonth(start.getMonth() + Number(this.selectedAmcPlan().duration_months || 0));
                    start.setDate(start.getDate() - 1);
                    this.amcEndDate = start.toISOString().slice(0, 10);
                },
                applySelectedPlanDetails() {
                    const plan = this.selectedAmcPlan();
                    if (!plan) {
                        this.amcPlanDescription = '';
                        return;
                    }

                    this.amcPlanDescription = plan.description || '';

                    if (!this.amcAmountEdited || Number(this.amcAmount) === 0) {
                        this.amcAmount = Number(plan.price || 0);
                        this.amcAmountEdited = false;
                    }
                },
                amcPlanVisits() {
                    return this.selectedAmcPlan()?.visit_count || 0;
                },
                selectedPlanIncludesOnCallSupport() {
                    return ['on_call', 'hybrid'].includes(String(this.selectedAmcPlan()?.service_mode || 'scheduled'));
                },
                selectedPlanGeneratesVisits() {
                    return ['scheduled', 'hybrid'].includes(String(this.selectedAmcPlan()?.service_mode || 'scheduled'));
                },
                selectedPlanServiceModeLabel() {
                    const mode = String(this.selectedAmcPlan()?.service_mode || 'scheduled');
                    return {
                        scheduled: 'Scheduled Visits Only',
                        on_call: 'On Call Only',
                        hybrid: 'Hybrid (Visits + On Call)',
                    }[mode] || 'Scheduled Visits Only';
                },
                amcPlanDurationLabel() {
                    const months = Number(this.selectedAmcPlan()?.duration_months || 0);
                    if (!months) return '-';
                    if (months < 12) return `${months} Month${months === 1 ? '' : 's'}`;
                    if (months % 12 === 0) {
                        const years = months / 12;
                        return `${years} Year${years === 1 ? '' : 's'}`;
                    }
                    const years = Math.floor(months / 12);
                    const remMonths = months % 12;
                    const parts = [];
                    if (years > 0) parts.push(`${years} Year${years === 1 ? '' : 's'}`);
                    if (remMonths > 0) parts.push(`${remMonths} Month${remMonths === 1 ? '' : 's'}`);
                    return parts.join(' ');
                },
                amcSubtotal() {
                    return Math.max(0, (Number(this.amcAmount) || 0) - (Number(this.amcDiscount) || 0));
                },
                amcTotal() {
                    const subtotal = this.amcSubtotal();
                    const tax = subtotal * ((Number(this.amcTaxPercent) || 0) / 100);
                    return (subtotal + tax).toFixed(2);
                },
                applyAmcClient(clientId, options = {}) {
                    this.amcSelectedClientId = clientId;
                    const client = this.amcClients.find(item => String(item.id) === String(clientId));
                    if (!client) return;
                    this.amcCustomerName = client.name || this.amcCustomerName;
                    this.amcMobile = client.mobile || '';
                    this.amcEmail = client.email || '';
                    if (!options.preserveAddress && !this.amcAddress) {
                        this.amcAddress = client.address || '';
                    }
                    this.amcGstNumber = client.gst_number || '';
                    window.setTimeout(() => {
                        $("#mobile_number").valid();
                    }, 0);
                },
                applyAmcProject(projectId) {
                    this.amcSelectedProjectId = projectId;
                    const selected = this.amcProjects.find(item => String(item.id) === String(projectId));
                    if (selected) this.amcProjectName = selected.name || this.amcProjectName;
                },
                bindSearchableSelects() {
                    const clientSelect = document.getElementById('client_id');
                    const projectSelect = document.getElementById('existing_project_id');
                    const planSelect = document.getElementById('amc_plan_id');

                    clientSelect?.addEventListener('change', (event) => {
                        this.amcSelectedClientId = event.target.value;
                        this.applyAmcClient(event.target.value);
                    });

                    projectSelect?.addEventListener('change', (event) => {
                        this.amcSelectedProjectId = event.target.value;
                        this.applyAmcProject(event.target.value);
                    });

                    planSelect?.addEventListener('change', (event) => {
                        this.amcSelectedPlanId = event.target.value;
                        this.syncAmcEndDate();
                        this.applySelectedPlanDetails();
                    });
                },
            };
        }

        $(document).ready(function() {
            const addressInput = document.getElementById("address");
            const addressSuggestions = document.getElementById("address-suggestions");
            const addressAutocompleteUrl = addressInput?.dataset.autocompleteUrl || "";
            let addressAutocompleteTimer = null;
            let addressAutocompleteAbortController = null;
            let addressSuggestionsList = [];
            let highlightedAddressIndex = -1;
            let suppressAddressLookup = false;

            function escapeHtml(value) {
                return String(value).replace(/[&<>"']/g, function (char) {
                    return ({
                        "&": "&amp;",
                        "<": "&lt;",
                        ">": "&gt;",
                        '"': "&quot;",
                        "'": "&#039;"
                    })[char];
                });
            }

            function getAddressSuggestionLabel(item) {
                if (!item || typeof item !== "object") {
                    return "";
                }

                if (typeof item.display_name === "string" && item.display_name.trim() !== "") {
                    return item.display_name;
                }

                const properties = item.properties || {};
                const parts = [
                    properties.name,
                    properties.street,
                    properties.suburb,
                    properties.district,
                    properties.city,
                    properties.state,
                    properties.country,
                ].filter((value, index, values) => {
                    const normalized = String(value || "").trim();
                    return normalized !== "" && values.indexOf(value) === index;
                });

                return parts.join(", ");
            }

            function normalizeAddressSuggestions(payload) {
                if (Array.isArray(payload)) {
                    return payload;
                }

                if (Array.isArray(payload?.features)) {
                    return payload.features;
                }

                return [];
            }

            function hideAddressSuggestions() {
                if (!addressSuggestions) {
                    return;
                }

                addressSuggestions.classList.add("hidden");
                addressSuggestions.innerHTML = "";
                addressSuggestionsList = [];
                highlightedAddressIndex = -1;
            }

            function renderAddressSuggestions(results) {
                if (!addressSuggestions) {
                    return;
                }

                addressSuggestionsList = results;
                highlightedAddressIndex = -1;

                if (!results.length) {
                    addressSuggestions.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No matching locations found.</div>';
                    addressSuggestions.classList.remove("hidden");
                    return;
                }

                addressSuggestions.innerHTML = results.map((item, index) => {
                    const displayName = escapeHtml(getAddressSuggestionLabel(item));
                    return `
                        <button
                            type="button"
                            class="address-suggestion-item flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                            data-address-index="${index}"
                            role="option"
                            aria-selected="false"
                        >
                            <span class="mt-0.5 text-slate-400" aria-hidden="true">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 1.75a5.75 5.75 0 0 0-5.75 5.75c0 4.256 4.518 9.388 5.102 10.037a.875.875 0 0 0 1.296 0c.584-.649 5.102-5.781 5.102-10.037A5.75 5.75 0 0 0 10 1.75Zm0 7.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z" clip-rule="evenodd" />
                                </svg>
                            </span>
                            <span class="min-w-0 text-sm leading-5 text-slate-700">${displayName}</span>
                        </button>
                    `;
                }).join("");

                addressSuggestions.classList.remove("hidden");
            }

            function setHighlightedAddressSuggestion(index) {
                if (!addressSuggestions || !addressSuggestionsList.length) {
                    return;
                }

                const items = addressSuggestions.querySelectorAll(".address-suggestion-item");
                items.forEach((item, itemIndex) => {
                    const isActive = itemIndex === index;
                    item.classList.toggle("bg-slate-50", isActive);
                    item.setAttribute("aria-selected", isActive ? "true" : "false");
                });
                highlightedAddressIndex = index;
            }

            function applyAddressSuggestion(index) {
                const selectedSuggestion = addressSuggestionsList[index];
                if (!selectedSuggestion || !addressInput) {
                    return;
                }

                suppressAddressLookup = true;
                addressInput.value = getAddressSuggestionLabel(selectedSuggestion);
                addressInput.dispatchEvent(new Event("input", { bubbles: true }));
                hideAddressSuggestions();
                $("#address").valid();

                window.setTimeout(() => {
                    suppressAddressLookup = false;
                }, 0);
            }

            async function fetchAddressSuggestions(query) {
                if (!addressAutocompleteUrl || !addressSuggestions) {
                    return;
                }

                if (addressAutocompleteAbortController) {
                    addressAutocompleteAbortController.abort();
                }

                addressAutocompleteAbortController = new AbortController();

                try {
                    const response = await fetch(`${addressAutocompleteUrl}${encodeURIComponent(query)}`, {
                        method: "GET",
                        headers: {
                            "Accept": "application/json"
                        },
                        signal: addressAutocompleteAbortController.signal
                    });

                    if (!response.ok) {
                        hideAddressSuggestions();
                        return;
                    }

                    const results = await response.json();
                    renderAddressSuggestions(normalizeAddressSuggestions(results));
                } catch (error) {
                    if (error.name !== "AbortError") {
                        hideAddressSuggestions();
                    }
                }
            }

            function queueAddressAutocompleteLookup() {
                if (!addressInput || suppressAddressLookup) {
                    return;
                }

                const query = addressInput.value.trim();

                if (addressAutocompleteTimer) {
                    window.clearTimeout(addressAutocompleteTimer);
                }

                if (query.length < 3) {
                    hideAddressSuggestions();
                    return;
                }

                addressAutocompleteTimer = window.setTimeout(() => {
                    fetchAddressSuggestions(query);
                }, 350);
            }

            addressInput?.addEventListener("input", queueAddressAutocompleteLookup);
            addressInput?.addEventListener("keydown", function (event) {
                if (addressSuggestions?.classList.contains("hidden") || !addressSuggestionsList.length) {
                    return;
                }

                if (event.key === "ArrowDown") {
                    event.preventDefault();
                    const nextIndex = highlightedAddressIndex < addressSuggestionsList.length - 1
                        ? highlightedAddressIndex + 1
                        : 0;
                    setHighlightedAddressSuggestion(nextIndex);
                    return;
                }

                if (event.key === "ArrowUp") {
                    event.preventDefault();
                    const previousIndex = highlightedAddressIndex > 0
                        ? highlightedAddressIndex - 1
                        : addressSuggestionsList.length - 1;
                    setHighlightedAddressSuggestion(previousIndex);
                    return;
                }

                if (event.key === "Enter" && highlightedAddressIndex >= 0) {
                    event.preventDefault();
                    applyAddressSuggestion(highlightedAddressIndex);
                    return;
                }

                if (event.key === "Escape") {
                    hideAddressSuggestions();
                }
            });

            addressSuggestions?.addEventListener("mousedown", function (event) {
                const button = event.target.closest("[data-address-index]");
                if (!button) {
                    return;
                }

                event.preventDefault();
                applyAddressSuggestion(Number(button.dataset.addressIndex));
            });

            document.addEventListener("click", function (event) {
                if (!addressInput || !addressSuggestions) {
                    return;
                }

                if (event.target === addressInput || addressSuggestions.contains(event.target)) {
                    return;
                }

                hideAddressSuggestions();
            });

            const amcLatitudeInput = document.getElementById("latitude");
            const amcLongitudeInput = document.getElementById("longitude");

            if (window.google?.maps?.places && addressInput && amcLatitudeInput && amcLongitudeInput) {
                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: ["in"] },
                    fields: ["formatted_address", "geometry"],
                });

                autocomplete.addListener("place_changed", function () {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) {
                        return;
                    }

                    addressInput.value = place.formatted_address || addressInput.value;
                    amcLatitudeInput.value = place.geometry.location.lat();
                    amcLongitudeInput.value = place.geometry.location.lng();
                });
            }

            function syncComputedAmcState() {
                const startDate = $("#start_date").val();
                const selectedPlanId = String($("#amc_plan_id").val() || "");
                const selectedPlan = amcPlans.find((item) => String(item.id) === selectedPlanId);
                const durationMonths = Number(selectedPlan?.duration_months || 0);

                if (startDate && durationMonths > 0) {
                    const start = new Date(startDate);
                    if (!Number.isNaN(start.getTime())) {
                        start.setMonth(start.getMonth() + durationMonths);
                        start.setDate(start.getDate() - 1);
                        $("#end_date").val(start.toISOString().slice(0, 10));
                    }
                } else {
                    $("#end_date").val("");
                }

                const amount = parseFloat($("#amc_amount").val()) || 0;
                const discount = parseFloat($("#discount").val()) || 0;
                const taxPercent = parseFloat($("#tax_percent").val()) || 0;
                const subtotal = Math.max(0, amount - discount);
                const total = (subtotal + (subtotal * taxPercent / 100)).toFixed(2);

                $("input[name='total_amount']").val(total);
            }

            $.validator.addMethod("strictEmail", function(value, element) {
                if (this.optional(element)) {
                    return true;
                }

                return /^[A-Z0-9._%+-]+@[A-Z0-9-]+(\.[A-Z0-9-]+)+$/i.test(value);
            }, "Please enter a valid email address with a proper domain.");

            $.validator.addMethod("clientPhoneAvailable", function(value, element) {
                if (this.optional(element)) {
                    return true;
                }

                const phone = String(value || "").replace(/\D+/g, "");
                const selectedClientId = String($("#client_id").val() || "");

                if (!phone) {
                    return true;
                }

                if (phone.length !== 10) {
                    return true;
                }

                return !amcClients.some(function(client) {
                    const clientId = String(client.id || "");
                    const isSelectedClient = selectedClientId !== "" && clientId === selectedClientId;
                    const existingPhones = [
                        String(client.mobile || "").replace(/\D+/g, ""),
                        String(client.phone || "").replace(/\D+/g, "")
                    ].filter(Boolean);

                    if ($("#client_mode").val() === "existing" && isSelectedClient) {
                        return false;
                    }

                    if (isSelectedClient) {
                        return false;
                    }

                    return existingPhones.includes(phone);
                });
            }, "A client already exists with this phone number. Please choose the existing client instead.");

            $.validator.addMethod("gstFormat", function(value, element) {
                if (this.optional(element)) {
                    return true;
                }

                return /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/.test(value);
            }, "Please enter a valid GST number.");

            $.validator.addMethod("discountNotGreaterThanAmount", function(value, element) {
                if (this.optional(element)) {
                    return true;
                }

                const amount = parseFloat($("#amc_amount").val()) || 0;
                const discount = parseFloat(value) || 0;

                return discount <= amount;
            }, "Discount cannot be greater than AMC amount.");

            $("#gst_number").on("input blur", function() {
                this.value = this.value.toUpperCase().replace(/\s+/g, "");
            });

            $("#start_date").on("change blur", function() {
                syncComputedAmcState();
            });
            $("#amc_plan_id").on("change blur", syncComputedAmcState);

            $("#amc_amount, #discount, #tax_percent").on("input blur", syncComputedAmcState);
            $("#project_name").on("input change blur", function() {
                $("#project_name").valid();
            });
            $("#client_mode").on("change", function() {
                $("#client_id").valid();
                $("#mobile_number").valid();
            });
            $("#client_id").on("change", function() {
                $("#mobile_number").valid();
            });
            $("#project_mode").on("change", function() {
                $("#existing_project_id").valid();
            });
            $("#mobile_number").on("input blur", function() {
                $("#mobile_number-server-error").addClass("hidden");
                $("#mobile_number").valid();
            });
            syncComputedAmcState();

            $("#amcForm").validate({
                rules: {
                    client_mode: { required: true },
                    client_id: {
                        required: {
                            depends: function() {
                                return $("#client_mode").val() === "existing";
                            }
                        }
                    },
                    customer_name: { required: true, minlength: 2, maxlength: 191 },
                    project_mode: { required: true },
                    existing_project_id: {
                        required: {
                            depends: function() {
                                return $("#project_mode").val() === "existing";
                            }
                        }
                    },
                    project_name: { required: true, minlength: 2, maxlength: 191 },
                    mobile_number: { required: true, digits: true, minlength: 10, maxlength: 10, clientPhoneAvailable: true },
                    email: { maxlength: 191, strictEmail: true },
                    address: { required: true, minlength: 5, maxlength: 1000 },
                    gst_number: { maxlength: 15, gstFormat: true },
                    start_date: { required: true, dateISO: true },
                    amc_plan_id: { required: true },
                    amc_type: { required: true },
                    amc_amount: { required: true, number: true, min: 0 },
                    discount: { number: true, min: 0, discountNotGreaterThanAmount: true },
                    tax_percent: { number: true, min: 0, max: 100 },
                    payment_mode: { required: true },
                    payment_status: { required: true },
                    service_timing: { maxlength: 1000 },
                    parts_replacement_policy: { maxlength: 1000 },
                    emergency_support_terms: { maxlength: 1000 },
                },
                messages: {
                    customer_name: {
                        required: "Please enter the client name.",
                        minlength: "Client name must be at least 2 characters.",
                        maxlength: "Client name may not be greater than 191 characters."
                    },
                    project_name: {
                        required: "Please enter the project name.",
                        minlength: "Project name must be at least 2 characters.",
                        maxlength: "Project name may not be greater than 191 characters."
                    },
                    mobile_number: {
                        required: "Please enter the mobile number.",
                        digits: "Mobile number must contain only digits.",
                        minlength: "Mobile number must be exactly 10 digits.",
                        maxlength: "Mobile number must be exactly 10 digits.",
                        clientPhoneAvailable: "A client already exists with this phone number. Please choose the existing client instead."
                    },
                    email: {
                        maxlength: "Email may not be greater than 191 characters."
                    },
                    address: {
                        required: "Please enter the address.",
                        minlength: "Address must be at least 5 characters.",
                        maxlength: "Address may not be greater than 1000 characters."
                    },
                    gst_number: {
                        maxlength: "GST number must be 15 characters.",
                    },
                    start_date: {
                        required: "Please select the start date.",
                        dateISO: "Please enter a valid start date."
                    },
                    amc_plan_id: "Please select an AMC plan.",
                    amc_amount: {
                        required: "Please enter the contract amount.",
                        number: "AMC amount must be a valid number.",
                        min: "AMC amount cannot be negative."
                    },
                    discount: {
                        number: "Discount must be a valid number.",
                        min: "Discount cannot be negative."
                    },
                    tax_percent: {
                        number: "Tax percentage must be a valid number.",
                        min: "Tax percentage cannot be negative.",
                        max: "Tax percentage cannot be greater than 100."
                    },
                    service_timing: {
                        maxlength: "Service timing may not be greater than 1000 characters."
                    },
                    parts_replacement_policy: {
                        maxlength: "Parts replacement policy may not be greater than 1000 characters."
                    },
                    emergency_support_terms: {
                        maxlength: "Emergency support terms may not be greater than 1000 characters."
                    }
                },
                ignore: ":hidden:not(#client_id, #existing_project_id, #amc_plan_id)",
                errorElement: "label",
                errorClass: "error",
                errorPlacement: function(error, element) {
                    error.insertAfter(element);
                },
                highlight: function(element) {
                    $(element).addClass("error");
                },
                unhighlight: function(element) {
                    $(element).removeClass("error");
                },
                submitHandler: function(form) {
                    syncComputedAmcState();
                    form.submit();
                }
            });
        });
    </script>
</x-app-layout>

