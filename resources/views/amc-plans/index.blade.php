<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AMC Plans</h2>
                <p class="mt-1 text-sm text-slate-500">Create company AMC plans with scheduled-only, on-call-only, or hybrid support modes.</p>
            </div>
            <a href="{{ route('amcs.create') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                Back to Create AMC
            </a>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8">
        <div class="grid gap-4 px-2 sm:px-6 lg:grid-cols-[420px_minmax(0,1fr)] lg:px-8">
            <section class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <h3 class="text-lg font-bold text-slate-900">Create Plan</h3>
                <p class="mt-1 text-sm text-slate-500">Examples: 6 Months AMC, 1 Year AMC, 2 Years Premium AMC.</p>

                <form method="POST" action="{{ route('amc-plans.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="name" value="Plan Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name') }}" placeholder="e.g. 1 Year AMC" maxlength="191" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="duration_months" value="Duration (Months)" />
                        <x-text-input id="duration_months" type="number" name="duration_months" class="mt-1 block w-full" value="{{ old('duration_months', 12) }}" min="1" max="120" required />
                        <x-input-error :messages="$errors->get('duration_months')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="service_mode" value="Plan Mode" />
                        <select id="service_mode" name="service_mode" class="mt-1 block w-full rounded-md border-slate-300" onchange="document.getElementById('visit_count').value = this.value === 'on_call' ? 0 : Math.max(1, Number(document.getElementById('visit_count').value || 0));">
                            @foreach(\App\Models\AmcPlan::SERVICE_MODE_OPTIONS as $value => $label)
                                <option value="{{ $value }}" @selected(old('service_mode', 'scheduled') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('service_mode')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="visit_count" value="Number of Visits" />
                        <x-text-input id="visit_count" type="number" name="visit_count" class="mt-1 block w-full" value="{{ old('visit_count', 4) }}" min="0" max="120" required />
                        <p class="mt-1 text-xs text-slate-500">Use `0` for On Call Only plans. Hybrid and scheduled plans should have at least 1 visit.</p>
                        <x-input-error :messages="$errors->get('visit_count')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="price" value="Plan Price" />
                        <x-text-input id="price" type="number" step="0.01" name="price" class="mt-1 block w-full" value="{{ old('price', 0) }}" min="0" max="999999999.99" required />
                        <x-input-error :messages="$errors->get('price')" class="mt-2" />
                    </div>
                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label for="description" value="Description" />
                            <x-speech-to-text target="description" />
                        </div>
                        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-slate-300" placeholder="Describe what is included in this AMC plan..." maxlength="2000">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                    <label class="flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600" @checked(old('is_active', true))>
                        <span class="text-sm font-medium text-slate-700">Plan is active for AMC creation</span>
                    </label>
                    <x-primary-button class="w-full justify-center">Save AMC Plan</x-primary-button>
                </form>
            </section>

            <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-6">
        <div>
            <h3 class="text-xl font-bold text-slate-900">Existing Plans</h3>
            <p class="mt-1 text-sm text-slate-500">Manage your available AMC service plans.</p>
        </div>
        <div class="rounded-full bg-slate-100 px-4 py-1 text-sm font-bold text-slate-600">
            {{ $plans->count() }} {{ Str::plural('Plan', $plans->count()) }}
        </div>
    </div>

    @if($plans->isEmpty())
        <div class="text-center py-12 border-2 border-dashed border-slate-200 rounded-xl">
            <p class="text-sm text-slate-500 italic">No AMC plans created yet.</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200">
            <table class="w-full text-left border-collapse">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600 min-w-[150px]">Plan Name</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600 min-w-[120px]">Duration</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600 min-w-[140px]">Mode</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600">Visits</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600">Price</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600 min-w-[240px]">Description</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600">Status</th>
                        <th class="px-4 py-4 text-xs font-bold uppercase tracking-wider text-600 text-center">Used</th>
                        <th class="px-4 py-4 text-right text-xs font-bold uppercase tracking-wider text-600">Actions</th>
                    </tr>
                </thead>
               <tbody class="divide-y divide-slate-100">
    @foreach($plans as $plan)
        @php $formId = 'amc-plan-form-' . $plan->id; @endphp
        <tr class="hover:bg-slate-50 transition-colors">
            <td class="px-4 py-4">
                <x-text-input form="{{ $formId }}" name="name" class="w-full text-sm" value="{{ $plan->name }}" maxlength="191" required />
            </td>
            <!-- Fixed Alignment Section -->
            <td class="px-4 py-4 align-top">
    <div class="flex flex-col">
        <x-text-input form="{{ $formId }}" name="duration_months" type="number" class="w-20 text-sm" value="{{ $plan->duration_months }}" min="1" max="120" required />
        <div class="mt-1 text-[10px] text-slate-400 font-medium">{{ \App\Models\Amc::formatDurationMonths($plan->duration_months) }}</div>
    </div>
</td>
            <td class="px-4 py-4 align-top">
                <select form="{{ $formId }}" name="service_mode" class="block w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach(\App\Models\AmcPlan::SERVICE_MODE_OPTIONS as $value => $label)
                        <option value="{{ $value }}" @selected(($plan->service_mode ?: ($plan->includes_on_call_support ? 'on_call' : 'scheduled')) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </td>
            <td class="px-4 py-4 align-top">
                <x-text-input form="{{ $formId }}" name="visit_count" type="number" class="w-20 text-sm" value="{{ $plan->visit_count }}" min="0" max="120" required />
            </td>
            <td class="px-4 py-4 align-top">
                <x-text-input form="{{ $formId }}" name="price" type="number" step="0.01" class="w-28 text-sm" value="{{ $plan->price }}" min="0" required />
            </td>
            <td class="px-4 py-4 align-top">
                <div class="flex items-center justify-end mb-1">
                    <x-speech-to-text target="plan_desc_{{ $plan->id }}" compact="true" />
                </div>
                <textarea id="plan_desc_{{ $plan->id }}" form="{{ $formId }}" name="description" rows="2" class="w-full rounded-lg border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ $plan->description }}</textarea>
            </td>
            <td class="px-4 py-4 align-top">
                <label class="flex items-center gap-2 text-sm cursor-pointer mt-2">
                    <input form="{{ $formId }}" type="hidden" name="is_active" value="0">
                    <input form="{{ $formId }}" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked($plan->is_active)>
                    <span class="text-slate-600 font-medium">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span>
                </label>
            </td>
            <td class="px-4 py-4 align-top text-center text-sm font-bold text-slate-700">
                <div class="mt-2">{{ $plan->amcs_count }}</div>
            </td>
            <td class="px-4 py-4 align-top text-right">
                <div class="flex h-full items-center justify-center gap-3">
                    <form id="{{ $formId }}" method="POST" action="{{ route('amc-plans.update', $plan) }}">
                        @csrf @method('PATCH')
                        <x-primary-button class="!bg-transparent !shadow-none !border-0
                            !text-green-600 hover:!text-green-700
                            hover:!bg-green-50
                            !px-2 !py-2"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                        </x-primary-button>
                    </form>
                    <form method="POST" action="{{ route('amc-plans.destroy', $plan) }}" data-swal-confirm="Delete this AMC plan?">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs font-bold text-rose-600 hover:text-rose-700 px-2"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                        </button>
                    </form>
                </div>
            </td>
        </tr>
    @endforeach
</tbody>
            </table>
        </div>
    @endif
</section>
        </div>
    </div>
</x-app-layout>
