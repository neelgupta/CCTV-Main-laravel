<x-app-layout>
    @php
        $boqPreviewUrl = route('leads.boq.preview', $lead);
        $boqSaveUrl = $quotation ? route('leads.boq.update', [$lead, $quotation]) : route('leads.boq.generate.store', $lead);
        $allBoqUnits = collect($units ?? [])
            ->pluck('name')
            ->merge(($boqProducts ?? collect())->pluck('unit'))
            ->merge(collect($leadMaterials ?? [])->pluck('unit'))
            ->map(fn ($u) => trim((string) $u))
            ->filter()
            ->unique()
            ->values();
        $allBoqGstRates = collect($gstRates ?? [])
            ->pluck('rate')
            ->merge(($boqProducts ?? collect())->pluck('gst'))
            ->merge([0, 5, 9, 12, 18, 28])
            ->map(fn ($r) => (float) $r)
            ->filter(fn ($r) => $r >= 0)
            ->unique(fn ($r) => number_format((float) $r, 2, '.', ''))
            ->sort()
            ->values();
    @endphp
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4 max-w-[98%] mx-auto px-4 sm:px-6">
            <div>
                <h2 class="font-black text-2xl text-slate-900 tracking-tight">Generate BOQ</h2>
                <p class="text-sm text-slate-500 mt-1">{{ $lead->client_name }} • Build from saved products or custom rows.</p>
            </div>
            <a href="{{ route('leads.show', $lead) }}" class="inline-flex items-center px-5 py-2.5 bg-slate-900 text-white text-sm font-bold hover:bg-slate-800 transition-all shadow-sm">
                Back to Lead
            </a>
        </div>
    </x-slot>

    <div id="lead-boq-page" class="py-6 bg-slate-50/50 min-h-screen" x-data="boqForm(@js($boqProducts), @js($boqCategories), @js($units ?? []), @js($boqItems ?? []), @js($boqFormData ?? []), @js($leadMaterials ?? []), @js(($gstRates ?? collect())->pluck('rate')->map(fn($rate) => (float) $rate)->values()->all()))">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <form x-ref="boqForm" method="POST"
                action="{{ $boqSaveUrl }}"
                class="space-y-6" @submit.prevent="submitForm($event)">
                @csrf
                <input type="hidden" name="amount" :value="totalAmount">
                <input type="hidden" name="form_data[boq_type]" value="boq">
                <div class="bg-white border border-slate-200 shadow-xl shadow-slate-200/50 rounded-3xl p-6 md:p-8 space-y-8">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-6 border-b border-slate-100">
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-slate-900">Project Details</h3>
                            <p class="text-sm text-slate-500">Essential identifiers for this BOQ.</p>
                        </div>
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label for="version" value="BOQ Version" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <x-text-input id="version" name="version" type="text" class="mt-1.5 block w-full bg-slate-50 border-none ring-1 ring-slate-200" value="{{ $version }}" readonly />
                            </div>
                            <div>
                                <x-input-label for="quotation_date" value="BOQ Date" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <x-text-input id="quotation_date" name="form_data[date]" type="date" class="mt-1.5 block w-full bg-slate-50 border-none ring-1 ring-slate-200" value="{{ date('Y-m-d') }}" readonly />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="subject" value="Subject / Project Title" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <x-text-input id="subject" name="form_data[subject]" type="text"
                                    class="mt-1.5 block w-full border-slate-200 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="e.g., Installation of Solar Panels at Site A"
                                    x-model="subject" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-6 border-b border-slate-100">
                        <div class="space-y-1">
                            <h3 class="text-lg font-bold text-slate-900">Client Information</h3>
                            <p class="text-sm text-slate-500">Verified contact data from the lead.</p>
                        </div>
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div>
                                <x-input-label value="Client Name" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <p class="mt-1 text-slate-900 font-medium">{{ $lead->client_name }}</p>
                            </div>
                            <div>
                                <x-input-label value="Mobile" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <p class="mt-1 text-slate-900 font-medium">{{ $lead->mobile }}</p>
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label value="Billing Address" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <p class="mt-1 text-slate-600 text-sm leading-relaxed">{{ $lead->address }}</p>
                            </div>
                        </div>
                    </div>

                    @if(!empty($quotationCustomFields))
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pb-6 border-b border-slate-100">
                            <div class="space-y-1">
                                <h3 class="text-lg font-bold text-slate-900">Custom Fields</h3>
                                <p class="text-sm text-slate-500">Additional details configured for BOQ/Quotation.</p>
                            </div>
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-5">
                                @include('leads.quotations.partials.custom-fields', ['fields' => $quotationCustomFields, 'values' => $customFieldValues ?? []])
                            </div>
                        </div>
                    @endif

                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-bold text-slate-900">Line Items</h3>
                            <div class="flex items-center gap-3">
                                <template x-if="leadMaterials.length > 0">
                                    <button type="button" @click="loadFromMaterials()"
                                        class="inline-flex items-center gap-1.5 bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                        Load from Lead Materials
                                    </button>
                                </template>
                                <template x-if="leadMaterials.length > 0 && !leadMaterialsOpen">
                                    <button type="button" @click="leadMaterialsOpen = true"
                                        class="inline-flex items-center gap-1.5 border border-emerald-200 bg-white px-4 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors shadow-sm">
                                        View Material List
                                    </button>
                                </template>
                                <span class="text-xs font-medium px-2.5 py-1 bg-indigo-50 text-indigo-600">All prices in INR (₹)</span>
                            </div>
                        </div>

                        <template x-if="leadMaterials.length > 0">
                            <div x-show="leadMaterialsOpen" x-transition class="border border-emerald-200 bg-emerald-50 p-4">
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Lead Materials ({{ $lead->materials->count() }} items saved)</p>
                                    <button type="button" @click="leadMaterialsOpen = false"
                                        class="inline-flex h-8 w-8 items-center justify-center text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900 transition-colors"
                                        aria-label="Close lead material list">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    </button>
                                </div>
                                <div class="p-2 overflow-x-auto">
                                    <table class="w-full table-fixed divide-y divide-slate-100">
                                        <thead>
                                            <tr class="text-[11px] uppercase tracking-wider text-slate-400 font-bold border-b border-slate-100">
                                                <!-- Balanced proportions to give numeric fields adequate breathing room -->
                                                <th class="px-2 py-3 text-left w-[36%]">Product Selection & Description</th>
                                                <th class="px-2 py-3 text-left w-[12%]">Unit</th>
                                                <th class="px-2 py-3 text-left w-[11%]">Qty</th>
                                                <th class="px-2 py-3 text-left w-[15%]">Rate (₹)</th>
                                                <th class="px-2 py-3 text-left w-[12%]">GST %</th>
                                                <th class="px-2 py-3 text-right w-[11%]">Amount</th>
                                                <th class="px-1 py-3 w-7"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="item in itemsInCategory(category.id)" :key="item._rowId">
                                                <tr class="group hover:bg-slate-50/50">
                                                    
                                                    <!-- Product Selection & Description -->
                                                    <td class="px-2 py-2">
                                                        <div class="flex flex-col sm:flex-row items-center gap-2">
                                                            <select :name="'form_data[items]['+item._rowId+'][product_id]'"
                                                                    x-model="item.product_id"
                                                                    @change="applyProduct(item, $event.target.closest('tr'))"
                                                                    class="w-full sm:w-1/2 text-xs rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-2.5 pr-6 py-1.5 truncate">
                                                                <option value="">-- Select Product --</option>
                                                                <template x-for="product in productsByCategory(item.category_id)" :key="product.id">
                                                                    <option :value="String(product.id)" x-text="product.name"></option>
                                                                </template>
                                                            </select>
                                                            
                                                            <input type="text" 
                                                                :name="'form_data[items]['+item._rowId+'][description]'" 
                                                                x-model="item.description"
                                                                class="w-full sm:w-1/2 text-xs rounded-lg border-slate-200 bg-white shadow-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 px-2.5 py-1.5" 
                                                                placeholder="Detailed description...">
                                                        </div>
                                                    </td>

                                                    <!-- Unit (Expanded dropdown padding to fit labels like "Nos", "Pcs", "Mtr") -->
                                                    <td class="px-2 py-2">
                                                        <select :name="'form_data[items]['+item._rowId+'][unit]'"
                                                                x-model="item.unit"
                                                                class="w-full text-xs rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-2 pr-6 py-1.5 truncate">
                                                            <option value="">Unit</option>
                                                            <template x-for="unitOption in unitOptionsFor(item.unit)" :key="unitOption">
                                                                <option :value="unitOption" x-text="unitOption"></option>
                                                            </template>
                                                        </select>
                                                    </td>

                                                    <!-- Qty (Centered, spinner-free, minimum min-w-[60px]) -->
                                                    <td class="px-2 py-2">
                                                        <input type="number"
                                                            min="0.01"
                                                            step="any"
                                                            :name="'form_data[items]['+item._rowId+'][qty]'"
                                                            x-model.number="item.qty"
                                                            @input="calculateRow(item)"
                                                            class="w-full min-w-[60px] text-xs font-medium text-slate-800 rounded-lg border-slate-200 px-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                    </td>

                                                    <!-- Rate (Formatted left-aligned with proper number space) -->
                                                    <td class="px-2 py-2">
                                                        <div class="relative rounded-lg shadow-sm">
                                                            <input type="number" 
                                                                min="0" 
                                                                step="0.01" 
                                                                :name="'form_data[items]['+item._rowId+'][price]'" 
                                                                x-model.number="item.price" 
                                                                @input="calculateRow(item)" 
                                                                class="w-full min-w-[80px] text-xs font-medium text-slate-800 rounded-lg border-slate-200 px-2.5 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                        </div>
                                                    </td>

                                                    <!-- GST % (Adjusted pr-6 so chevron arrow doesn't overlap percentage text) -->
                                                    <td class="px-2 py-2">
                                                        <select :name="'form_data[items]['+item._rowId+'][gst]'" 
                                                                x-model="item.gst" 
                                                                @change="calculateRow(item)" 
                                                                class="w-full text-xs rounded-lg border-slate-200 bg-white pl-2.5 pr-6 py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                                            <option value="0">0%</option>
                                                            <template x-for="gstRate in gstOptionsFor(item.gst)" :key="gstRate">
                                                                <option :value="String(gstRate)" x-text="Number(gstRate).toFixed(0) + '%'"></option>
                                                            </template>
                                                        </select>
                                                    </td>

                                                    <!-- Calculated Total Amount -->
                                                    <td class="px-2 py-2 text-right whitespace-nowrap">
                                                        <span class="text-xs font-bold text-slate-900 tracking-tight" x-text="formatMoney(item.total)"></span>
                                                    </td>

                                                    <!-- Action Column -->
                                                    <td class="px-1 py-2 text-center">
                                                        <button type="button" @click="removeItem(item._rowId)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-500 transition-all">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                                </div>
                            </template>

                            <div class="space-y-8">
                                <template x-for="category in visibleCategoryObjects()" :key="category.id">
                                    <div class="bg-slate-50/50 rounded-2xl border border-slate-100">
                                        <div class="flex items-center justify-between bg-slate-100/50 px-5 py-3 rounded-t-2xl">
                                            <div class="flex items-center gap-3">
                                                <button type="button"
                                                        @click="moveCategoryUp(category.id)"
                                                        class="text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:hover:text-slate-400 transition-colors"
                                                        :disabled="isFirstCategory(category.id)">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                        @click="moveCategoryDown(category.id)"
                                                        class="text-slate-400 hover:text-indigo-600 disabled:opacity-30 disabled:hover:text-slate-400 transition-colors"
                                                        :disabled="isLastCategory(category.id)">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </button>
                                                <h4 class="text-xs font-black uppercase tracking-widest text-slate-600" x-text="category.name"></h4>
                                            </div>
                                            <div class="flex items-center gap-4">
                                                <button type="button" @click="addItem(category.id)" class="text-xs font-bold text-indigo-600 hover:text-indigo-800">Add Item</button>
                                                <button type="button" @click="removeCategory(category.id)" class="text-slate-400 hover:text-rose-600 transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </div>
                                        </div>

                                    <div class="p-2 overflow-x-auto">
                                        <table class="w-full table-fixed divide-y divide-slate-100">
                                            <thead>
                                                <tr class="text-[11px] uppercase tracking-wider text-slate-400 font-bold border-b border-slate-100">
                                                    <th class="px-2 py-3 text-left w-[36%]">Product Selection & Description</th>
                                                    <th class="px-2 py-3 text-left w-[12%]">Unit</th>
                                                    <th class="px-2 py-3 text-left w-[11%]">Qty</th>
                                                    <th class="px-2 py-3 text-left w-[15%]">Rate (₹)</th>
                                                    <th class="px-2 py-3 text-left w-[12%]">GST %</th>
                                                    <th class="px-2 py-3 text-right w-[11%]">Amount</th>
                                                    <th class="px-1 py-3 w-7"></th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-100">
                                                <template x-for="item in itemsInCategory(category.id)" :key="item._rowId">
                                                    <tr class="group hover:bg-slate-50/50">
                                                        <!-- Product & Description -->
                                                        <td class="px-2 py-2">
                                                            <div class="flex flex-col sm:flex-row items-center gap-2">
                                                                <select :name="'form_data[items]['+item._rowId+'][product_id]'"
                                                                        x-model="item.product_id"
                                                                        @change="applyProduct(item)"
                                                                        class="w-full sm:w-1/2 text-xs rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-2.5 pr-6 py-1.5 truncate">
                                                                    <option value="">-- Select Saved Product --</option>
                                                                    <template x-for="product in productsByCategory(item.category_id)" :key="product.id">
                                                                        <option :value="String(product.id)" x-text="product.name"></option>
                                                                    </template>
                                                                </select>
                                                                
                                                                <input type="text" 
                                                                    :name="'form_data[items]['+item._rowId+'][description]'" 
                                                                    x-model="item.description"
                                                                    class="w-full sm:w-1/2 text-xs rounded-lg border-slate-200 bg-white shadow-sm placeholder-slate-400 focus:border-indigo-500 focus:ring-indigo-500 px-2.5 py-1.5" 
                                                                    placeholder="Detailed description...">
                                                                <input type="hidden" :name="'form_data[items]['+item._rowId+'][category_name]'" :value="categoryName(item.category_id)">
                                                                <input type="hidden" :name="'form_data[items]['+item._rowId+'][category_id]'" :value="item.category_id">
                                                            </div>
                                                        </td>

                                                        <!-- Unit Field -->
                                                        <td class="px-2 py-2">
                                                            <select :name="'form_data[items]['+item._rowId+'][unit]'"
                                                                    x-model="item.unit"
                                                                    @change="item.unit = normalizeUnit(item.unit)"
                                                                    class="w-full text-xs rounded-lg border-slate-200 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 pl-2 pr-6 py-1.5 truncate">
                                                                <option value="">Unit</option>
                                                                @foreach($allBoqUnits as $unitOption)
                                                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                                                @endforeach
                                                                <template x-for="unitOption in unitOptionsFor(item.unit).filter(u => !@js($allBoqUnits).includes(u))" :key="unitOption">
                                                                    <option :value="unitOption" x-text="unitOption"></option>
                                                                </template>
                                                            </select>
                                                        </td>

                                                        <!-- Qty Field -->
                                                        <td class="px-2 py-2">
                                                            <input type="number"
                                                                min="0.01"
                                                                step="any"
                                                                :name="'form_data[items]['+item._rowId+'][qty]'"
                                                                x-model.number="item.qty"
                                                                @input="calculateRow(item)"
                                                                class="w-full min-w-[50px] text-xs font-medium text-slate-800 rounded-lg border-slate-200 px-2 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                        </td>

                                                        <!-- Rate Field -->
                                                        <td class="px-2 py-2">
                                                            <input type="number" 
                                                                min="0" 
                                                                step="0.01" 
                                                                :name="'form_data[items]['+item._rowId+'][price]'" 
                                                                x-model.number="item.price" 
                                                                @input="calculateRow(item)" 
                                                                class="w-full min-w-[70px] text-xs font-medium text-slate-800 rounded-lg border-slate-200 px-2.5 py-1.5 focus:border-indigo-500 focus:ring-indigo-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                                                            <input type="hidden" :name="'form_data[items]['+item._rowId+'][rate]'" :value="item.rate ?? item.price">
                                                            <input type="hidden" :name="'form_data[items]['+item._rowId+'][unit_price]'" :value="item.unit_price ?? item.price">
                                                        </td>

                                                        <!-- GST Field -->
                                                        <td class="px-2 py-2">
                                                            <select :name="'form_data[items]['+item._rowId+'][gst]'" 
                                                                    x-model="item.gst" 
                                                                    @change="calculateRow(item)" 
                                                                    class="w-full text-xs rounded-lg border-slate-200 bg-white pl-2.5 pr-6 py-1.5 focus:border-indigo-500 focus:ring-indigo-500">
                                                                @foreach($allBoqGstRates as $gstRate)
                                                                    <option value="{{ (float) $gstRate }}">{{ (int) $gstRate }}%</option>
                                                                @endforeach
                                                                <template x-for="gstRate in gstOptionsFor(item.gst).filter(g => !@js($allBoqGstRates).map(Number).includes(Number(g)))" :key="gstRate">
                                                                    <option :value="String(gstRate)" x-text="Number(gstRate).toFixed(0) + '%'"></option>
                                                                </template>
                                                            </select>
                                                        </td>

                                                        <!-- Total Amount Column -->
                                                        <td class="px-2 py-2 text-right whitespace-nowrap">
                                                            <span class="text-xs font-bold text-slate-900 tracking-tight" x-text="formatMoney(item.total)"></span>
                                                            <input type="hidden" :name="'form_data[items]['+item._rowId+'][base_total]'" :value="item.base_total">
                                                            <input type="hidden" :name="'form_data[items]['+item._rowId+'][gst_amount]'" :value="item.gst_amount">
                                                            <input type="hidden" :name="'form_data[items]['+item._rowId+'][total]'" :value="item.total">
                                                        </td>

                                                        <!-- Action Column -->
                                                        <td class="px-1 py-2 text-center">
                                                            <button type="button" @click="removeItem(item._rowId)" class="opacity-0 group-hover:opacity-100 text-slate-400 hover:text-rose-500 transition-all">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                                            </button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="pt-6 mt-6 border-t-2 border-slate-50 flex flex-col md:flex-row justify-between items-start gap-8">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 w-full md:max-w-md">
                            <div>
                                <x-input-label for="discount" value="Discount (Amount)" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <x-text-input id="discount" type="number" name="form_data[discount]" min="0" class="mt-1.5 block w-full border-slate-200" x-model.number="discount" @input="updateTotal()" />
                            </div>
                            <div>
                                <x-input-label for="taxable_amount" value="Taxable Amount" class="text-xs uppercase tracking-wider text-slate-400 font-bold" />
                                <x-text-input id="taxable_amount" type="text" class="mt-1.5 block w-full border-slate-200 bg-slate-50" x-bind:value="formatMoney(taxableAmount)" readonly />
                            </div>
                        </div>

                        <div class="w-full md:w-80 bg-slate-900 p-6 text-white shadow-2xl shadow-indigo-200">
                            <div class="space-y-3">
                                @php
                                    $companyStateName = \App\Support\QuotationTaxCalculator::resolveCompanyStateName($company);
                                    $customerStateName = \App\Support\QuotationTaxCalculator::resolveCustomerStateName($quotation?->form_data ?? [], $lead, $client ?? null);
                                    $isIntraState = \App\Support\QuotationTaxCalculator::isIntraState($companyStateName, $customerStateName);
                                @endphp
                                <div class="flex justify-between text-slate-400 text-sm">
                                    <span>Subtotal</span>
                                    <span x-text="formatMoney(subtotalAmount)"></span>
                                </div>
                                <div class="flex justify-between text-slate-400 text-sm">
                                    <span>Discount</span>
                                    <span x-text="'-' + formatMoney(discount)"></span>
                                </div>
                                @if($isIntraState)
                                <div class="flex justify-between text-slate-400 text-sm">
                                    <span>SGST</span>
                                    <span x-text="'₹' + ((Number(gstAmount) || 0) / 2).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                                </div>
                                <div class="flex justify-between text-slate-400 text-sm">
                                    <span>CGST</span>
                                    <span x-text="'₹' + ((Number(gstAmount) || 0) - ((Number(gstAmount) || 0) / 2)).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                                </div>
                                @else
                                <div class="flex justify-between text-slate-400 text-sm">
                                    <span>IGST</span>
                                    <span x-text="'₹' + Number(gstAmount || 0).toLocaleString(undefined, {minimumFractionDigits: 2})"></span>
                                </div>
                                @endif
                                <div class="pt-3 border-t border-slate-800 flex justify-between items-end">
                                    <span class="text-xs uppercase font-bold tracking-widest text-indigo-400">Grand Total</span>
                                    <span class="text-3xl font-black text-white" x-text="formatMoney(totalAmount)"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <button type="submit" data-action="preview" class="inline-flex items-center border border-indigo-600 px-10 py-3 text-base font-semibold text-indigo-600 hover:bg-indigo-50">
                        Preview BOQ
                    </button>
                    <x-primary-button class="px-10 py-3 text-base" type="submit" data-action="save">Generate & Save BOQ</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @include('leads.partials.generator-validation-assets')

    <script>
        function boqForm(products = [], categories = [], units = [], initialItems = [], initialFormData = {}, leadMaterials = [], gstRates = []) {
            const groupedProducts = products.reduce((carry, product) => {
                const categoryId = String(product.category_id || '');
                if (!carry[categoryId]) carry[categoryId] = [];
                carry[categoryId].push(product);
                return carry;
            }, {});

            return {
                subject: '',
                leadMaterialsOpen: true,
                categories: categories || [],
                products: products || [],
                units: Array.isArray(units)
                    ? [...new Set([
                        ...units.map(unit => String(unit?.name || unit || '').trim()).filter(Boolean),
                        ...(Array.isArray(products) ? products.map(p => String(p.unit || '').trim()).filter(Boolean) : []),
                        ...(Array.isArray(leadMaterials) ? leadMaterials.map(m => String(m.unit || '').trim()).filter(Boolean) : []),
                    ])]
                    : [],
                gstRates: Array.isArray(gstRates)
                    ? [...new Set([
                        0,
                        ...gstRates.map(rate => Number(rate)).filter(rate => Number.isFinite(rate)),
                        ...(Array.isArray(products) ? products.map(p => Number(p.gst || 0)).filter(rate => Number.isFinite(rate)) : []),
                    ])].sort((a, b) => Number(a) - Number(b))
                    : [0],
                groupedProducts,
                visibleCategories: (categories || []).map(category => String(category.id)),
                nextRowId: 1,
                items: [],
                discount: 0,
                gstPercent: 18,
                leadMaterials: Array.isArray(leadMaterials) ? leadMaterials : [],
                subtotalAmount: 0,
                taxableAmount: 0,
                gstAmount: 0,
                totalAmount: 0,
                previewUrl: @js($boqPreviewUrl),
                saveUrl: @js($boqSaveUrl),
                initialItems,
                initialFormData,

                seedRows() {
                    if (!this.categories.length) {
                        this.addItem('');
                        return;
                    }
                    this.categories.forEach(category => this.addItem(category.id));
                },
                visibleCategoryObjects() {
                    return this.categories.filter(category => this.visibleCategories.includes(String(category.id)));
                },

                isFirstCategory(categoryId) {
                    return this.visibleCategories.indexOf(String(categoryId)) === 0;
                },
                isLastCategory(categoryId) {
                    const idx = this.visibleCategories.indexOf(String(categoryId));
                    return idx === this.visibleCategories.length - 1 || idx === -1;
                },
                moveCategoryUp(categoryId) {
                    const fromIndex = this.visibleCategories.indexOf(String(categoryId));
                    if (fromIndex <= 0) return; // Already at the top

                    const toIndex = fromIndex - 1;
                    // Swap positions in array
                    const element = this.visibleCategories.splice(fromIndex, 1)[0];
                    this.visibleCategories.splice(toIndex, 0, element);

                    this.reorderCategoriesArray();
                },
                moveCategoryDown(categoryId) {
                    const fromIndex = this.visibleCategories.indexOf(String(categoryId));
                    if (fromIndex === -1 || fromIndex >= this.visibleCategories.length - 1) return; // Already at bottom

                    const toIndex = fromIndex + 1;
                    // Swap positions in array
                    const element = this.visibleCategories.splice(fromIndex, 1)[0];
                    this.visibleCategories.splice(toIndex, 0, element);

                    this.reorderCategoriesArray();
                },
                reorderCategoriesArray() {
                    // Keeps the primary mapping layout configuration synchronized with the new visual tracking array index sequence
                    this.categories.sort((a, b) => {
                        const indexA = this.visibleCategories.indexOf(String(a.id));
                        const indexB = this.visibleCategories.indexOf(String(b.id));

                        // If an item isn't visible, keep it at the end
                        if (indexA === -1) return 1;
                        if (indexB === -1) return -1;
                        return indexA - indexB;
                    });
                },

                itemsInCategory(categoryId) {
                    return this.items.filter(item => String(item.category_id) === String(categoryId));
                },
                addItem(categoryId = '') {
                    const fallbackCategory = categoryId || this.visibleCategories[0] || this.categories[0]?.id || '';
                    this.items.push({
                        _rowId: this.nextRowId++,
                        product_id: '',
                        category_id: fallbackCategory ? String(fallbackCategory) : '',
                        description: '',
                        unit: '',
                        qty: 1,
                        price: 0,
                        rate: 0,
                        gst: 0,
                        base_total: 0,
                        gst_amount: 0,
                        total: 0,
                    });
                },
                removeItem(rowId) {
                    this.items = this.items.filter(item => item._rowId !== rowId);
                    if (!this.items.length) {
                        this.seedRows();
                    }
                    this.updateTotal();
                },
                removeCategory(categoryId) {
                    this.visibleCategories = this.visibleCategories.filter(id => String(id) !== String(categoryId));
                    this.items = this.items.filter(item => String(item.category_id) !== String(categoryId));
                    if (!this.visibleCategories.length) {
                        this.addItem('');
                    }
                    if (!this.items.length) {
                        this.seedRows();
                    }
                    this.updateTotal();
                },
                categoryName(categoryId) {
                    const category = this.categories.find(c => String(c.id) === String(categoryId));
                    return category ? category.name : '';
                },
                productsByCategory(categoryId) {
                    return this.groupedProducts[String(categoryId)] || [];
                },
                normalizeLookup(value = '') {
                    return String(value || '').trim().toLowerCase();
                },
                normalizeUnit(value = '') {
                    return String(value || '').trim();
                },
                unitOptionsFor(selectedUnit = '') {
                    const selected = this.normalizeUnit(selectedUnit);
                    return selected && !this.units.includes(selected)
                        ? [...this.units, selected]
                        : this.units;
                },
                ensureUnitOption(value = '') {
                    const unit = this.normalizeUnit(value);
                    if (unit && !this.units.includes(unit)) {
                        this.units.push(unit);
                    }
                    return unit;
                },
                gstOptionsFor(selectedGst = 0) {
                    const selected = Number(selectedGst || 0);
                    if (!this.gstRates.some(rate => Math.abs(Number(rate) - selected) < 0.001)) {
                        this.gstRates.push(selected);
                        this.gstRates.sort((a, b) => Number(a) - Number(b));
                    }

                    return [...new Set(this.gstRates.map(r => Number(r)))]
                        .filter(rate => Number.isFinite(rate))
                        .sort((a, b) => a - b);
                },
                ensureGstOption(value = 0) {
                    const rate = Math.min(100, Math.max(0, Number(value || 0)));
                    if (!this.gstRates.some(existing => Math.abs(Number(existing) - rate) < 0.001)) {
                        this.gstRates.push(rate);
                        this.gstRates.sort((a, b) => Number(a) - Number(b));
                    }

                    return String(rate);
                },
                itemBaseAmount(item) {
                    const qty = Math.max(0, Number(item?.qty || 0));
                    const price = Math.max(0, Number(item?.price ?? item?.rate ?? 0));
                    return qty * price;
                },
                itemTaxAmount(item) {
                    const baseAmount = this.itemBaseAmount(item);
                    const gstRate = Math.min(100, Math.max(0, Number(item?.gst || 0)));
                    return (baseAmount * gstRate) / 100;
                },
                itemTotalWithTax(item) {
                    return this.itemBaseAmount(item) + this.itemTaxAmount(item);
                },
                materialTotal(material) {
                    const baseAmount = Math.max(0, Number(material?.rate || 0)) * Math.max(0, Number(material?.qty || 0));
                    const gstRate = Math.min(100, Math.max(0, Number(material?.gst || 0)));
                    return baseAmount + ((baseAmount * gstRate) / 100);
                },
                applyProduct(item, rowElement = null) {
                    const product = this.products.find(p => String(p.id) === String(item.product_id));
                    if (product) {
                        item.description = product.description || product.name;
                        item.unit = this.ensureUnitOption(product.unit || '');
                        item.category_id = String(product.category_id || item.category_id || '');
                        item.price = Number(product.price || 0);
                        item.rate = Number(product.price || 0);
                        item.gst = this.ensureGstOption(product.gst);
                    }
                    this.calculateRow(item);
                    this.$nextTick(() => {
                        const unit = this.ensureUnitOption(product?.unit || '');
                        if (unit) item.unit = unit;
                        if (product) item.gst = this.ensureGstOption(product.gst);

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
                findProductForMaterial(material) {
                    if (material?.product_id) {
                        const product = this.products.find(p => String(p.id) === String(material.product_id));
                        if (product) return product;
                    }

                    const materialName = this.normalizeLookup(material?.material_name);
                    if (!materialName) return null;

                    const materialUnit = this.normalizeLookup(material?.unit);
                    const materialRate = Number(material?.rate || 0);

                    return this.products.find(product => {
                        const productName = this.normalizeLookup(product.name);
                        const productDescription = this.normalizeLookup(product.description);

                        if (productName !== materialName && productDescription !== materialName) {
                            return false;
                        }

                        const productUnit = this.normalizeLookup(product.unit);
                        const productPrice = Number(product.price || 0);

                        return !materialUnit || !productUnit || productUnit === materialUnit || Math.abs(productPrice - materialRate) < 0.01;
                    }) || null;
                },
                categoryIdByName(categoryName = '') {
                    const normalizedName = this.normalizeLookup(categoryName);
                    if (!normalizedName) return '';

                    const category = this.categories.find(candidate => this.normalizeLookup(candidate.name) === normalizedName);
                    return category ? String(category.id) : '';
                },
                resolveMaterialCategoryId(material, product = null) {
                    const directCategoryId = String(material?.category_id || '');
                    if (directCategoryId && this.categories.some(category => String(category.id) === directCategoryId)) {
                        return directCategoryId;
                    }

                    const productCategoryId = String(product?.category_id || '');
                    if (productCategoryId && this.categories.some(category => String(category.id) === productCategoryId)) {
                        return productCategoryId;
                    }

                    const categoryIdFromName = this.categoryIdByName(material?.category_name || '');
                    if (categoryIdFromName) {
                        return categoryIdFromName;
                    }

                    return this.visibleCategories[0] || (this.categories[0] ? String(this.categories[0].id) : '');
                },
                ensureCategoryVisible(categoryId) {
                    const normalizedCategoryId = String(categoryId || '');
                    if (normalizedCategoryId && !this.visibleCategories.includes(normalizedCategoryId)) {
                        this.visibleCategories.push(normalizedCategoryId);
                    }
                },
                isBlankDraftItem(item) {
                    return !String(item.product_id || '').trim()
                        && !String(item.description || '').trim()
                        && !this.normalizeUnit(item.unit)
                        && Number(item.price || item.rate || 0) <= 0
                        && Number(item.gst || 0) <= 0;
                },
                loadFromMaterials() {
                    if (!this.leadMaterials.length) return;
                    this.items = this.items.filter(item => !this.isBlankDraftItem(item));
                    this.leadMaterials.forEach(m => {
                        const product = this.findProductForMaterial(m);
                        const categoryId = this.resolveMaterialCategoryId(m, product);
                        const gstRate = this.ensureGstOption(Number(m.gst ?? product?.gst ?? 0));
                        this.ensureCategoryVisible(categoryId);

                        const rowId = this.nextRowId++;
                        this.items.push({
                            _rowId: rowId,
                            product_id: product ? String(product.id) : String(m.product_id || ''),
                            category_id: categoryId,
                            description: m.material_name || product?.description || product?.name || '',
                            unit: this.ensureUnitOption(m.unit || product?.unit || ''),
                            qty: m.qty || 1,
                            price: m.rate || 0,
                            rate: m.rate || 0,
                            gst: gstRate,
                            base_total: 0,
                            gst_amount: 0,
                            total: 0,
                        });
                    });
                    const usedCategoryIds = new Set(this.items.map(item => String(item.category_id || '')).filter(Boolean));
                    const orderedUsedCategoryIds = this.categories
                        .map(category => String(category.id))
                        .filter(categoryId => usedCategoryIds.has(categoryId));

                    if (orderedUsedCategoryIds.length) {
                        this.visibleCategories = orderedUsedCategoryIds;
                    }

                    this.items.forEach(item => this.calculateRow(item));
                    this.updateTotal();
                },
                calculateRow(item) {
                    const qty = Math.max(0, Number(item.qty || 0));
                    const price = Math.max(0, Number(item.price ?? item.rate ?? 0));
                    item.qty = qty;
                    item.price = price;
                    item.rate = price;
                    item.unit = this.ensureUnitOption(item.unit);
                    item.gst = this.ensureGstOption(item.gst);
                    item.base_total = this.itemBaseAmount(item);
                    item.gst_amount = this.itemTaxAmount(item);
                    item.total = this.itemTotalWithTax(item);
                    this.updateTotal();
                },
                formatMoney(value) {
                    return '\u20B9' + Number(value || 0).toLocaleString('en-IN', {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    });
                },
                updateTotal() {
                    const subtotal = this.items.reduce((s, i) => s + this.itemBaseAmount(i), 0);
                    const discount = Math.min(Math.max(0, Number(this.discount || 0)), subtotal);
                    const taxable = Math.max(0, subtotal - discount);
                    // GST is on full item base (discount only reduces product total, not GST)
                    const gstAmount = this.items.reduce((sum, item) => {
                        const baseAmount = this.itemBaseAmount(item);
                        const gstRate = Math.min(100, Math.max(0, Number(item?.gst || 0)));
                        return sum + ((baseAmount * gstRate) / 100);
                    }, 0);
                    const grandTotal = taxable + gstAmount;

                    this.subtotalAmount = Number(subtotal.toFixed(2));
                    this.discount = Number(discount.toFixed(2));
                    this.taxableAmount = Number(taxable.toFixed(2));
                    this.gstAmount = Number(gstAmount.toFixed(2));
                    this.gstPercent = taxable > 0 ? Number(((gstAmount / taxable) * 100).toFixed(2)) : 0;
                    this.totalAmount = Number(grandTotal.toFixed(2));
                },
                async submitForm(event) {
                    const submitterAction = event?.submitter?.dataset?.action || 'save';

                    if (!this.items.length) {
                        this.seedRows();
                        await this.$nextTick();
                    }

                    const form = event.target;
                    const validationRules = [
                        {
                            selector: '#subject',
                            rules: { minlength: 3, maxlength: 150 }
                        },
                        {
                            selector: "input[name^='form_data[items]'][name$='[description]']",
                            rules: { required: true, minlength: 3, maxlength: 100 }
                        },
                        {
                            selector: "input[name^='form_data[items]'][name$='[unit]']",
                            rules: { required: true, maxlength: 50 }
                        },
                        {
                            selector: "input[name^='form_data[items]'][name$='[qty]']",
                            rules: { required: true, number: true, min: 0.01 }
                        },
                        {
                            selector: "input[name^='form_data[items]'][name$='[price]']",
                            rules: { required: true, number: true, min: 0 }
                        },
                        {
                            selector: "select[name^='form_data[items]'][name$='[gst]']",
                            rules: { required: true, number: true, range: [0, 100] }
                        },
                        {
                            selector: '#discount',
                            rules: { number: true, min: 0 }
                        }
                    ];

                    if (!window.generatorValidation.validate(form, validationRules)) {
                        return;
                    }

                    this.items.forEach(item => {
                        if (!item.category_id && this.visibleCategories.length) {
                            item.category_id = this.visibleCategories[0];
                        }
                    });
                    this.items.forEach(i => this.calculateRow(i));
                    event.target.action = submitterAction === 'preview' ? this.previewUrl : this.saveUrl;
                    event.target.target = submitterAction === 'preview' ? '_blank' : '_self';
                    event?.target?.submit();
                },
                init() {
                    if (Array.isArray(this.initialItems) && this.initialItems.length) {
                        this.items = this.initialItems.map((item, index) => ({
                            _rowId: Number(item._rowId || index + 1),
                            product_id: item.product_id ? String(item.product_id) : '',
                            category_id: item.category_id ? String(item.category_id) : '',
                            description: item.description || '',
                            unit: this.ensureUnitOption(item.unit || ''),
                            qty: Number(item.qty || 0),
                            price: Number(item.price ?? item.rate ?? item.unit_price ?? 0),
                            rate: Number(item.rate ?? item.price ?? item.unit_price ?? 0),
                            gst: this.ensureGstOption(item.gst),
                            base_total: Number(item.base_total || 0),
                            gst_amount: Number(item.gst_amount || 0),
                            total: Number(item.total ?? item.amount ?? 0),
                        }));
                        this.nextRowId = this.items.reduce((max, item) => Math.max(max, Number(item._rowId) || 0), 0) + 1;
                        this.subject = this.initialFormData.subject || '';
                        this.discount = Number(this.initialFormData.discount || 0);
                        this.gstPercent = Number(this.initialFormData.gstPercent || 18);
                        this.visibleCategories = [...new Set(this.items.map(item => String(item.category_id || '')).filter(Boolean))];
                        if (!this.visibleCategories.length) {
                            this.visibleCategories = (this.categories || []).map(category => String(category.id));
                        }
                        this.items.forEach(item => this.calculateRow(item));
                        return;
                    }
                    this.leadMaterials.forEach(material => this.ensureUnitOption(material.unit || ''));
                    this.seedRows();
                },
            }
        }
    </script>
</x-app-layout>
