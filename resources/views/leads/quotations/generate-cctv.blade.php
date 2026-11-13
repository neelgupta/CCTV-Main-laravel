<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Generate Quotation - {{ $lead->client_name }}
                </h2>
                <p class="text-sm text-gray-500 mt-1">Fill in the details to generate a professional quotation.</p>
            </div>
              <a href="{{ route('leads.show', $lead) }}"
    class="inline-flex h-10 items-center rounded-none bg-[#c01917] px-5 text-sm font-semibold text-white shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:bg-[#a61413] hover:shadow-md">
    Back to Lead
</a>
        </div>
    </x-slot>

    @php
        $company = $lead->company ?? auth()->user()?->company;
        $formData = $quotation?->form_data ?? [];
        $manualProductEntry = $company?->usesManualProductEntry() ?? false;
        $allUnitsList = collect($units ?? [])
            ->pluck('name')
            ->merge(($boqProducts ?? collect())->pluck('unit'))
            ->merge(collect($leadMaterials ?? [])->pluck('unit'))
            ->map(fn ($u) => trim((string) $u))
            ->filter()
            ->unique()
            ->values();
        $allGstList = collect($gstRates ?? [])
            ->pluck('rate')
            ->merge(($boqProducts ?? collect())->pluck('gst'))
            ->merge([0, 5, 9, 12, 18, 28])
            ->map(fn ($r) => (float) $r)
            ->filter(fn ($r) => $r >= 0)
            ->unique(fn ($r) => number_format((float) $r, 2, '.', ''))
            ->sort()
            ->values();
    @endphp
    @php $displayVersion = $quotation->version ?? $currentVersion ?? $company?->nextAvailableQuotationVersion() ?? '1.0'; @endphp
    <div class="py-12" x-data="quotationForm(@js($formData), @js($units ?? []), @js($quotation?->amount ?? 0), @js($leadMaterials ?? []), @js(($gstRates ?? collect())->pluck('rate')->map(fn($rate) => (float) $rate)->values()->all()), @js(($boqProducts ?? collect())->map(fn ($product) => ['id' => (string) $product->id, 'name' => (string) $product->name, 'description' => (string) ($product->description ?? ''), 'brand' => (string) ($product->brand ?? ''), 'model' => (string) ($product->model ?? ''), 'unit' => (string) ($product->unit ?? ''), 'price' => (float) ($product->price ?? 0), 'gst' => (float) ($product->gst ?? 0), 'category_id' => (string) ($product->category_id ?? ''), 'category_name' => (string) ($product->category?->name ?? '')])->values()), @js($manualProductEntry), @js($quotationTemplates ?? []))">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form id="quotation-form" method="POST" action="{{ route('leads.quotation.generate.store', $lead) }}" class="space-y-8" novalidate @submit.prevent="submitForm($event)">
                @csrf
                <input type="hidden" name="amount" :value="totalAmount">

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2"><span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Quotation Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <x-input-label for="version" value="Quotation Version" />
                            <x-text-input id="version" name="version" type="text" class="mt-1 block w-full bg-gray-50" value="{{ $currentVersion }}" readonly required />
                        </div>
                        <div>
                            <x-input-label for="quotation_date" value="Quotation Date" />
                            <x-text-input id="quotation_date" name="form_data[date]" type="date" class="mt-1 block w-full bg-gray-50" value="{{ date('Y-m-d') }}" readonly required />
                        </div>
                        <div>
                            <x-input-label for="valid_until" value="Valid Until" />
                            <x-text-input id="valid_until" name="form_data[valid_until]" type="date" class="mt-1 block w-full bg-gray-50" value="{{ date('Y-m-d', strtotime('+30 days')) }}" readonly required />
                        </div>
                        <div class="col-span-full mb-4">
                            <x-input-label for="subject" value="Quotation Subject" />
                            <x-text-input id="subject" name="form_data[project][subject]" type="text"
                                class="mt-1 block w-full"
                                placeholder="e.g. Interior Design and Execution for 3BHK Apartment"
                                value="{{ data_get($formData, 'project.subject', '') }}" maxlength="150" />
                        </div>


                    </div>
                </div>

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2"><span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Client Details</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-input-label for="client_name" value="Client Name" />
                            <x-text-input id="client_name" name="form_data[client][name]" type="text" class="mt-1 block w-full bg-gray-50" value="{{ $lead->client_name }}" readonly required />
                        </div>
                        <div>
                            <x-input-label for="billing_address" value="Billing Address" />
                            <textarea id="billing_address" name="form_data[client][billing_address]" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm bg-gray-50" readonly>{{ $lead->address }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="site_address" value="Site Address" />
                            <textarea id="site_address" name="form_data[client][site_address]" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm bg-gray-50" readonly>{{ $lead->address }}</textarea>
                        </div>
                        <div>
                            <x-input-label for="contact" value="Contact Person / Number" />
                            <x-text-input id="contact" name="form_data[client][contact]" type="text" class="mt-1 block w-full bg-gray-50" value="{{ $lead->mobile }}" readonly />
                        </div>
                    </div>
                </div>

                @if(!empty($quotationCustomFields))
                    <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                        <h3 class="text-lg font-bold text-slate-900 border-b pb-3">Custom Fields</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            @include('leads.quotations.partials.custom-fields', ['fields' => $quotationCustomFields, 'values' => $customFieldValues ?? []])
                        </div>
                    </div>
                @endif

                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3 flex-wrap gap-2">
                        <h3 class="flex items-center gap-2 text-lg font-bold text-slate-900"><span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Equipment & Items</h3>
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
                            <template x-if="leadMaterials.length > 0">
                                <button type="button" @click="loadFromMaterials()"
                                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-700 transition-colors shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                    Load from Lead Materials
                                </button>
                            </template>
                            <template x-if="leadMaterials.length > 0 && !leadMaterialsOpen">
                                <button type="button" @click="leadMaterialsOpen = true"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-white px-4 py-2 text-xs font-bold text-emerald-700 hover:bg-emerald-50 transition-colors shadow-sm">
                                    View Material List
                                </button>
                            </template>
                            <button type="button" @click="addItem()" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">+ Add Item</button>
                        </div>
                    </div>

                    <template x-if="leadMaterials.length > 0">
                        <div x-show="leadMaterialsOpen" x-transition class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <p class="text-xs font-bold uppercase tracking-widest text-emerald-700">Lead Materials (click "Load" to import as items)</p>
                                <button type="button" @click="leadMaterialsOpen = false"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-full text-emerald-700 hover:bg-emerald-100 hover:text-emerald-900 transition-colors"
                                    aria-label="Close lead material list">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>
                            <div class="overflow-x-auto">
                                <table class="min-w-full text-xs">
                                    <thead>
                                        <tr class="text-emerald-600 font-bold uppercase tracking-wide">
                                            <th class="text-left py-1 pr-4">Material</th>
                                            <th class="text-left py-1 pr-4">Qty</th>
                                            <th class="text-left py-1 pr-4">Unit</th>
                                            <th class="text-left py-1 pr-4">Rate</th>
                                            <th class="text-left py-1 pr-4">GST %</th>
                                            <th class="text-right py-1">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-emerald-100">
                                        <template x-for="(m, i) in leadMaterials" :key="i">
                                            <tr class="text-emerald-900">
                                                <td class="py-1 pr-4 font-medium" x-text="m.material_name"></td>
                                                <td class="py-1 pr-4" x-text="m.qty"></td>
                                                <td class="py-1 pr-4" x-text="m.unit || '-'"></td>
                                                <td class="py-1 pr-4" x-text="'₹' + Number(m.rate).toFixed(2)"></td>
                                                <td class="py-1 pr-4" x-text="Number(m.gst).toFixed(2) + '%'"></td>
                                                <td class="py-1 text-right font-bold" x-text="'₹' + materialTotal(m).toFixed(2)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </template>
                    <div class="overflow-visible">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead>
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-1/3">Item Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider">Brand/Model</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-28">Unit</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-24">Qty</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-32">Unit Price</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-32">GST %</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-32">Total</th>
                                    <th class="px-4 py-2 text-left text-xs font-bold text-500 uppercase tracking-wider w-10"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <template x-for="(item, index) in items" :key="index">
                                    <tr class="hover:bg-gray-50/50" :class="item.product_open ? 'relative z-50' : ''">
                                        <!-- Item Name -->
                                            <td class="relative px-2 py-2" :class="item.product_open ? 'z-50' : ''">
                                            <template x-if="manualProductEntry">
                                                <textarea :name="'form_data[items]['+index+'][name]'" x-model="item.name" rows="1" placeholder="Enter product name" required class="w-full resize-y rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                            </template>
                                            <template x-if="!manualProductEntry">
                                            <div class="relative min-w-[220px]" @click.outside="item.product_open = false">
                                                <button type="button" @click="item.product_open = !item.product_open" class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm text-gray-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-indigo-500">
                                                    <span class="truncate" x-text="item.name || 'Select Product'"></span>
                                                    <svg class="ml-2 h-4 w-4 shrink-0 text-gray-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z" clip-rule="evenodd"/></svg>
                                                </button>
                                                <div x-cloak x-show="item.product_open" class="absolute left-0 top-full z-50 mt-2 w-full min-w-[280px] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl" style="display:none;">
                                                    <div class="border-b border-slate-100 p-3">
                                                        <input type="search" x-model="item.product_search" @click.stop placeholder="Search product..." class="block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                    </div>
                                                    <div class="max-h-72 overflow-y-auto py-1">
                                                        <button type="button" @mousedown.prevent="item.product_id = ''; item.name = ''; item.brand_model = ''; item.product_search = ''; item.product_open = false" class="flex w-full items-center px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50">Select Product</button>
                                                        <template x-for="product in filteredProducts(item)" :key="product.id">
                                                            <button type="button" @mousedown.prevent="selectProduct(item, product, $event.target.closest('tr'))" class="block w-full px-4 py-2.5 text-left text-sm text-slate-700 hover:bg-slate-50">
                                                                <span class="block truncate" x-text="product.name"></span>
                                                                <span class="block truncate text-xs text-slate-500" x-text="[product.brand, product.model, product.unit].filter(Boolean).join(' • ')"></span>
                                                            </button>
                                                        </template>
                                                        <div x-show="filteredProducts(item).length === 0" class="px-4 py-3 text-sm text-slate-500">No matching products found.</div>
                                                    </div>
                                                </div>
                                                <input type="hidden" :name="'form_data[items]['+index+'][product_id]'" :value="item.product_id || ''">
                                                <input type="hidden" :name="'form_data[items]['+index+'][name]'" :value="item.name">
                                            </div>
                                            </template>
                                            <template x-if="manualProductEntry"><input type="hidden" :name="'form_data[items]['+index+'][product_id]'" value=""></template>
                                            <div class="mt-2">
                                                <textarea
                                                    :name="'form_data[items]['+index+'][description]'"
                                                    x-model="item.description"
                                                    maxlength="2000"
                                                    rows="2"
                                                    placeholder="Description (optional)"
                                                    class="w-full resize-y rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                ></textarea>
                                            </div>
                                        </td>

                                        <!-- Brand/Model -->
                                        <td class="px-2 py-2">
                                            <input type="text" :name="'form_data[items]['+index+'][brand_model]'" x-model="item.brand_model" class="w-full text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500" placeholder="Model/Brand">
                                        </td>

                                        <!-- Unit -->
                                        <td class="px-2 py-2">
                                            <select :name="'form_data[items]['+index+'][unit]'"
                                                x-model="item.unit"
                                                class="w-full min-w-[115px] text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pl-2 pr-7 py-1.5 text-ellipsis overflow-hidden">
                                                <option value="">Select Unit</option>
                                                @foreach($allUnitsList as $unitOption)
                                                    <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                                @endforeach
                                                <template x-for="unitOption in unitOptionsFor(item.unit).filter(u => !@js($allUnitsList).includes(u))" :key="unitOption">
                                                    <option :value="unitOption" x-text="unitOption"></option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Qty -->
                                        <td class="px-2 py-2">
                                            <input type="number" min="0" max="999999" step="any" :name="'form_data[items]['+index+'][qty]'" x-model.number="item.qty" class="w-full min-w-[90px] text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" @input="item.qty = Math.min(999999, Math.max(0, Number(item.qty) || 0)); calculateRow(item)">
                                        </td>

                                        <!-- Unit Price -->
                                        <td class="px-2 py-2">
                                            <input type="number" min="0" max="99999999" step="0.01" :name="'form_data[items]['+index+'][price]'" x-model.number="item.price" class="w-full min-w-[110px] text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-2 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" @input="item.price = Math.min(99999999, Math.max(0, Number(item.price) || 0)); calculateRow(item)">
                                        </td>

                                        <!-- GST % -->
                                        <td class="px-2 py-2">
                                            <select :name="'form_data[items]['+index+'][gst]'"
                                                x-model.number="item.gst"
                                                class="w-full min-w-[85px] text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 pl-2 pr-6 py-1.5"
                                                @change="calculateRow(item)">
                                                @foreach($allGstList as $gstRate)
                                                    <option value="{{ (float) $gstRate }}">{{ number_format((float) $gstRate, 2) }}%</option>
                                                @endforeach
                                                <template x-for="gstRate in gstOptionsFor(item.gst).filter(g => !@js($allGstList).map(Number).includes(Number(g)))" :key="gstRate">
                                                    <option :value="Number(gstRate)" x-text="Number(gstRate).toFixed(2) + '%'"></option>
                                                </template>
                                            </select>
                                        </td>

                                        <!-- Total -->
                                        <td class="px-2 py-2 text-right whitespace-nowrap">
                                            <span class="text-sm font-semibold text-gray-900" x-text="'₹' + (Number(item.total) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                                            <input type="hidden" :name="'form_data[items]['+index+'][base_total]'" :value="item.base_total">
                                            <input type="hidden" :name="'form_data[items]['+index+'][gst_amount]'" :value="item.gst_amount">
                                            <input type="hidden" :name="'form_data[items]['+index+'][total]'" :value="item.total">
                                        </td>

                                        <!-- Remove Action -->
                                        <td class="px-2 py-2 text-center">
                                            <button type="button" @click="removeItem(index)" class="text-red-400 hover:text-red-600 font-bold transition-colors">✕</button>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

               <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2"><span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Pricing Summary</h3>
                    <div class="space-y-4 max-w-xl">
                        <!-- Equipment Subtotal -->
                        <div class="flex items-center justify-between text-sm gap-4">
                            <span class="text-slate-500 font-medium">Equipment Subtotal:</span>
                            <span class="text-slate-900 font-bold whitespace-nowrap" x-text="'₹' + (Number(itemsSubtotal) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>

                        <!-- Discount -->
                        <div class="flex items-center justify-between text-sm gap-4">
                            <span class="text-slate-500 font-medium">Discount:</span>
                            <div class="min-w-[140px] max-w-[200px]">
                                <input type="number" 
                                    name="form_data[pricing][discount]" 
                                    x-model.number="discount" 
                                    min="0" 
                                    :max="itemsSubtotal"
                                    step="0.01" 
                                    class="w-full text-right text-sm rounded-lg border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 px-3 py-1.5 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" 
                                    @input="discount = Math.min(itemsSubtotal || 0, Math.max(0, Number(discount) || 0)); updateTotal()">
                            </div>
                        </div>

                        <!-- Taxable Amount -->
                        <div class="flex items-center justify-between text-sm gap-4">
                            <span class="text-slate-500 font-medium">Taxable Amount:</span>
                            <span class="text-slate-900 font-bold whitespace-nowrap" x-text="'₹' + (Number(taxableAmount) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>

                        <!-- GST Amount -->
                        <div class="flex items-center justify-between text-sm gap-4">
                            <span class="text-slate-500 font-medium" x-text="taxLabel"></span>
                            <span class="text-slate-900 font-bold whitespace-nowrap" x-text="'₹' + (Number(gstAmount) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>

                        <!-- Grand Total -->
                        <div class="flex items-center justify-between text-sm pt-4 border-t gap-4">
                            <span class="text-slate-900 font-bold text-lg">Grand Total:</span>
                            <span class="text-indigo-600 font-black text-2xl whitespace-nowrap" x-text="'₹' + (Number(totalAmount) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                        </div>
                    </div>
                </div>

                @php
                    $savedTerms = $formData['terms'] ?? [];
                    $legacyTermsText = collect([
                        ['label' => 'Warranty', 'value' => data_get($savedTerms, 'warranty')],
                        ['label' => 'Payment Terms', 'value' => data_get($savedTerms, 'payment')],
                        ['label' => 'Delivery Timeline', 'value' => data_get($savedTerms, 'delivery')],
                    ])
                        ->filter(fn ($row) => filled($row['value'] ?? null))
                        ->map(fn ($row) => $row['label'] . ': ' . $row['value'])
                        ->implode("\n");
                    $defaultTermsText = trim((string) ($company?->defaultTermsConditionsText() ?? ''));
                    $savedTermsText = trim((string) ($savedTerms['conditions'] ?? ''));
                    $initialTerms = $savedTermsText !== ''
                        ? $savedTermsText
                        : (trim($legacyTermsText) !== '' ? $legacyTermsText : ($quotation?->exists ? trim((string) data_get($quotation->form_data ?? [], 'terms.conditions', '')) : $defaultTermsText));
                    $termsHtml = $initialTerms !== ''
                        ? (\Illuminate\Support\Str::contains($initialTerms, '<') ? $initialTerms : nl2br(e($initialTerms)))
                        : $defaultTermsText;
                @endphp
                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                    <h3 class="text-lg font-bold text-slate-900 border-b border-slate-100 pb-3 flex items-center gap-2"><span class="h-5 w-1 rounded-full bg-[#c01917]"></span>Terms & Conditions</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                    <x-input-label for="terms_conditions" value="Terms & Conditions" />
                            <input type="hidden" id="terms_conditions" name="form_data[terms][conditions]" value="{{ $initialTerms }}">
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
                                        <button type="button" data-terms-editor-cmd="removeFormat" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Clear</button>
                                    </div>
                                </div>
                                <div id="terms_conditions_editor"
                                    class="min-h-[220px] px-4 py-3 text-sm leading-6 text-slate-800 outline-none"
                                    contenteditable="true"
                                    spellcheck="true"
                                >{!! $termsHtml !!}</div>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <x-input-label for="notes" value="Internal Notes" />
                                <x-speech-to-text target="notes" />
                            </div>
                            <textarea id="notes" name="notes" rows="2" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Any private notes for this quotation...">{{ $quotation?->notes ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-100 pt-6">
                    <button type="submit" data-action="preview" class="inline-flex h-[46px] items-center rounded-xl border border-[#c01917] px-10 text-base font-semibold text-[#c01917] shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:bg-red-50 hover:shadow-md">
                        Preview Quotation
                    </button>
                    <x-primary-button class="!h-[46px] px-10 text-base normal-case tracking-normal" type="submit" data-action="save">Generate &amp; Save Quotation</x-primary-button>
                </div>
            </form>

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
                                <x-input-label for="gen_tpl_name" value="Template Name *" />
                                <x-text-input id="gen_tpl_name" x-model="templateForm.name" class="mt-1 block w-full rounded-xl" placeholder="e.g. Standard 4-Ch CCTV Package" />
                            </div>
                            <div>
                                <x-input-label for="gen_tpl_desc" value="Description (Optional)" />
                                <textarea id="gen_tpl_desc" x-model="templateForm.description" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" placeholder="Optional notes about what this template contains..."></textarea>
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
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editor = document.getElementById('terms_conditions_editor');
            const hiddenInput = document.getElementById('terms_conditions');
            const editorRoot = editor?.closest('.rounded-2xl');

            if (!editor || !hiddenInput) {
                return;
            }

            const syncEditor = () => {
                hiddenInput.value = editor.innerHTML;
            };

            const applyCommand = (command, value = null) => {
                editor.focus();
                document.execCommand(command, false, value);
                syncEditor();
            };

            const setBlockFormat = (tagName) => {
                editor.focus();
                document.execCommand('formatBlock', false, tagName);
                syncEditor();
            };

            editor.addEventListener('input', syncEditor);
            editor.addEventListener('blur', syncEditor);

            document.querySelectorAll('[data-terms-editor-cmd]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const command = button.getAttribute('data-terms-editor-cmd');
                    if (command === 'createLink') {
                        const url = window.prompt('Enter link URL');
                        if (!url) return;
                        applyCommand(command, url);
                        return;
                    }

                    if (command === 'removeFormat') {
                        applyCommand(command);
                        syncEditor();
                        return;
                    }

                    applyCommand(command);
                });
            });

            editorRoot?.querySelector('[data-terms-editor-file]')?.addEventListener('change', function (event) {
                const action = event.target.value;
                if (action === 'clear') {
                    editor.innerHTML = '';
                    syncEditor();
                }
                if (action === 'reset') {
                    editor.innerHTML = hiddenInput.value || '';
                    syncEditor();
                }
                event.target.value = '';
            });

            editorRoot?.querySelector('[data-terms-editor-format]')?.addEventListener('change', function (event) {
                if (!event.target.value) return;
                setBlockFormat(event.target.value);
                event.target.value = '';
            });

            editorRoot?.querySelector('[data-terms-editor-size]')?.addEventListener('change', function (event) {
                if (!event.target.value) return;
                applyCommand('fontSize', event.target.value);
                event.target.value = '';
            });

            editorRoot?.querySelector('[data-terms-editor-color]')?.addEventListener('input', function (event) {
                applyCommand('foreColor', event.target.value);
            });

            editorRoot?.querySelector('[data-terms-editor-background]')?.addEventListener('input', function (event) {
                applyCommand('hiliteColor', event.target.value);
            });

            editor.closest('form')?.addEventListener('submit', syncEditor);
            syncEditor();
        });
    </script>

    @include('leads.partials.generator-validation-assets')

    <script>
        function quotationForm(formData = {}, units = [], initialAmount = 0, leadMaterials = [], gstRates = [], products = [], manualProductEntry = false, quotationTemplates = []) {
            return {
                units: Array.isArray(units) ? [...new Set([
                    ...units.map(unit => String(unit.name || unit || '').trim()).filter(Boolean),
                    ...(Array.isArray(products) ? products.map(p => String(p.unit || '').trim()).filter(Boolean) : []),
                    ...(Array.isArray(leadMaterials) ? leadMaterials.map(m => String(m.unit || '').trim()).filter(Boolean) : []),
                ])] : [],
                gstRates: Array.isArray(gstRates) ? [...new Set([
                    0,
                    ...gstRates.map(rate => Number(rate)).filter(rate => Number.isFinite(rate)),
                    ...(Array.isArray(products) ? products.map(p => Number(p.gst || 0)).filter(rate => Number.isFinite(rate)) : []),
                ])].sort((a, b) => Number(a) - Number(b)) : [0],
                products: Array.isArray(products) ? products : [],
                templates: Array.isArray(quotationTemplates) ? quotationTemplates : [],
                selectedTemplateId: '',
                saveTemplateModalOpen: false,
                savingTemplate: false,
                templateForm: {
                    name: '',
                    description: '',
                },
                manualProductEntry: Boolean(manualProductEntry),
                items: formData.items?.length ? formData.items.map(item => ({
                    ...item,
                    product_id: String(item.product_id || ''),
                    product_search: '',
                    product_open: false,
                    description: String(item.description || ''),
                    unit: String(item.unit || ''),
                    qty: Number(item.qty || 0),
                    price: Number(item.price || 0),
                    gst: Number(item.gst || 0),
                    base_total: Number(item.base_total || 0),
                    gst_amount: Number(item.gst_amount || 0),
                    total: Number(item.total || 0),
                })) : [{ product_id: '', product_search: '', product_open: false, name: '', description: '', brand_model: '', unit: '', qty: 1, price: 0, gst: 0, base_total: 0, gst_amount: 0, total: 0 }],
                itemsSubtotal: 0,
                discount: Number(formData?.pricing?.discount ?? formData?.discount ?? 0),
                gstPercent: Number(formData?.pricing?.gst_percent || 18),
                taxableAmount: 0,
                gstAmount: 0,
                totalAmount: Number(initialAmount || 0),
                leadMaterials: Array.isArray(leadMaterials) ? leadMaterials : [],
                leadMaterialsOpen: true,
                previewUrl: @js(route('leads.quotation.preview', $lead)),
                saveUrl: @js(isset($quotation) ? route('leads.quotation.update', [$lead, $quotation]) : route('leads.quotation.generate.store', $lead)),
                isIntraState: @js(filled(trim((string) ($company?->state ?? ''))) && filled(trim((string) (($client?->state?->name ?? $lead?->state?->name ?? '')))) && strcasecmp(trim((string) ($company?->state ?? '')), trim((string) (($client?->state?->name ?? $lead?->state?->name ?? '')))) === 0),

                get taxLabel() {
                    return this.isIntraState ? 'SGST + CGST (item-wise)' : 'IGST (item-wise)';
                },

                normalizeLookup(value = '') {
                    return String(value || '').trim().toLowerCase();
                },

                resolveItemUnit(item = {}) {
                    const currentUnit = String(item?.unit || '').trim();
                    if (currentUnit !== '') {
                        return currentUnit;
                    }

                    const itemName = this.normalizeLookup(item?.name);
                    if (!itemName) {
                        return '';
                    }

                    const matchedMaterial = this.leadMaterials.find((material) => {
                        const materialName = this.normalizeLookup(material?.material_name);
                        return materialName && materialName === itemName;
                    });

                    if (matchedMaterial) {
                        return String(matchedMaterial.unit || '').trim();
                    }

                    return '';
                },

                ensureUnitOption(value = '') {
                    const unit = String(value || '').trim();
                    if (unit && !this.units.includes(unit)) {
                        this.units.push(unit);
                    }
                    return unit;
                },

                unitOptionsFor(selectedUnit = '') {
                    const selected = String(selectedUnit || '').trim();
                    if (selected && !this.units.includes(selected)) {
                        this.units.push(selected);
                    }
                    return this.units;
                },

                filteredProducts(item) {
                    const query = String(item.product_search || '').trim().toLowerCase();
                    if (!query) return this.products;
                    return this.products.filter(product => [product.name, product.brand, product.model, product.unit].filter(Boolean).join(' ').toLowerCase().includes(query));
                },

                selectProduct(item, product, rowElement = null) {
                    item.product_id = String(product.id);
                    item.name = product.name || '';
                    item.description = product.description || item.description || '';
                    item.brand_model = [product.brand, product.model].filter(Boolean).join(' / ');
                    const unit = String(product.unit || item.unit || '').trim();
                    if (unit) {
                        this.ensureUnitOption(unit);
                    }
                    item.unit = unit;
                    item.price = Number(product.price || 0);
                    const gst = Number(product.gst || 0);
                    item.gst = this.ensureGstOption(gst);
                    item.product_search = '';
                    item.product_open = false;
                    item.category_id = String(product.category_id || item.category_id || '');
                    item.category_name = product.category_name || item.category_name || '';
                    this.calculateRow(item);
                    this.$nextTick(() => {
                        if (unit) item.unit = unit;
                        item.gst = gst;

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

                addItem() {
                    this.items.push({ product_id: '', product_search: '', product_open: false, name: '', description: '', brand_model: '', unit: '', qty: 1, price: 0, gst: 0, base_total: 0, gst_amount: 0, total: 0 });
                    this.updateTotal();
                },

                itemBaseAmount(item) {
                    const qty = Math.max(0, parseFloat(item?.qty) || 0);
                    const price = Math.max(0, parseFloat(item?.price) || 0);
                    return qty * price;
                },

                itemTaxAmount(item) {
                    const baseAmount = this.itemBaseAmount(item);
                    const gstRate = Math.min(100, Math.max(0, parseFloat(item?.gst) || 0));
                    return (baseAmount * gstRate) / 100;
                },

                itemTotalWithTax(item) {
                    return this.itemBaseAmount(item) + this.itemTaxAmount(item);
                },

                materialTotal(material) {
                    const baseAmount = Math.max(0, parseFloat(material?.rate) || 0) * Math.max(0, parseFloat(material?.qty) || 0);
                    const gstRate = Math.min(100, Math.max(0, parseFloat(material?.gst) || 0));
                    return baseAmount + ((baseAmount * gstRate) / 100);
                },

                applyTemplate(templateId) {
                    if (!templateId) return;
                    const tmpl = this.templates.find(t => String(t.id) === String(templateId));
                    if (!tmpl || !Array.isArray(tmpl.items) || !tmpl.items.length) {
                        return;
                    }

                    // Check if current items are default empty row
                    const isOnlyEmptyRow = this.items.length === 1 && !this.items[0].name && Number(this.items[0].price || 0) === 0;
                    if (isOnlyEmptyRow) {
                        this.items = [];
                    }

                    tmpl.items.forEach(ti => {
                        const gstRate = this.ensureGstOption(ti.gst || 0);
                        this.items.push({
                            product_id: String(ti.product_id || ''),
                            category_id: String(ti.category_id || ''),
                            category_name: String(ti.category_name || ''),
                            product_search: '',
                            product_open: false,
                            name: ti.name || ti.material_name || ti.description || '',
                            description: ti.description || '',
                            brand_model: ti.brand_model || '',
                            unit: ti.unit || '',
                            qty: Number(ti.qty || 1),
                            price: Number(ti.price || ti.rate || 0),
                            gst: gstRate,
                            base_total: 0,
                            gst_amount: 0,
                            total: 0,
                        });
                    });

                    this.items.forEach(item => this.calculateRow(item));
                    this.updateTotal();

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
                    const validItems = this.items.filter(item => (item.name || '').trim() !== '');
                    if (!validItems.length) {
                        this.showError('Please add at least one item/material before saving as a template.');
                        return;
                    }
                    this.templateForm.name = '';
                    this.templateForm.description = '';
                    this.saveTemplateModalOpen = true;
                },

                async submitSaveTemplate() {
                    const name = (this.templateForm.name || '').trim();
                    if (!name) {
                        this.showError('Template name is required.');
                        return;
                    }

                    const validItems = this.items.filter(item => (item.name || '').trim() !== '');
                    if (!validItems.length) {
                        this.showError('Please add at least one item before saving.');
                        return;
                    }

                    this.savingTemplate = true;

                    try {
                        const response = await fetch(@js(route('settings.quotation-templates.store')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || document.querySelector('input[name="_token"]')?.value || '',
                            },
                            body: JSON.stringify({
                                name: name,
                                description: this.templateForm.description,
                                items: validItems.map(item => ({
                                    product_id: item.product_id || '',
                                    category_id: item.category_id || '',
                                    category_name: item.category_name || '',
                                    name: item.name,
                                    description: item.description || '',
                                    brand_model: item.brand_model || '',
                                    unit: item.unit || '',
                                    qty: item.qty || 1,
                                    price: item.price || 0,
                                    gst: item.gst || 0,
                                    base_total: item.base_total || 0,
                                    gst_amount: item.gst_amount || 0,
                                    total: item.total || 0,
                                }))
                            })
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
                                    showConfirmButton: false
                                });
                            }
                        } else {
                            this.showError(result.message || 'Failed to save quotation template.');
                        }
                    } catch (err) {
                        this.showError('An error occurred while saving the template.');
                    } finally {
                        this.savingTemplate = false;
                    }
                },

                loadFromMaterials() {
                    if (!this.leadMaterials.length) return;
                    this.leadMaterials.forEach(m => {
                        const gstRate = this.ensureGstOption(m.gst);
                        this.items.push({
                            name: m.material_name,
                            description: '',
                            brand_model: '',
                            unit: m.unit || '',
                            qty: m.qty || 1,
                            price: m.rate || 0,
                            gst: gstRate,
                            base_total: 0,
                            gst_amount: 0,
                            total: 0,
                        });
                    });
                    this.items.forEach(item => this.calculateRow(item));
                    this.updateTotal();
                },

                removeItem(index) {
                    if (this.items.length > 1) {
                        this.items.splice(index, 1);
                        this.updateTotal();
                    }
                },

                calculateRow(item) {
                    const qty = Math.max(0, Number(item.qty) || 0);
                    const price = Math.max(0, Number(item.price) || 0);
                    const gstRate = Math.max(0, Number(item.gst) || 0);

                    const baseTotal = qty * price;
                    const gstAmount = (baseTotal * gstRate) / 100;
                    const grandTotal = baseTotal + gstAmount;

                    // Guard against NaN or Infinity overflow
                    item.base_total = isFinite(baseTotal) ? Number(baseTotal.toFixed(2)) : 0;
                    item.gst_amount = isFinite(gstAmount) ? Number(gstAmount.toFixed(2)) : 0;
                    item.total = isFinite(grandTotal) ? Number(grandTotal.toFixed(2)) : 0;

                    this.updateTotal();
                },

                updateTotal() {
                    const subtotal = this.items.reduce((sum, item) => sum + (Number(item.base_total) || this.itemBaseAmount(item)), 0);
                    const disc = Math.max(0, parseFloat(this.discount) || 0);
                    const cappedDiscount = Math.min(disc, subtotal);
                    const taxableAmount = Math.max(0, subtotal - cappedDiscount);
                    // GST is on full item base (discount only reduces product total, not GST)
                    const gstAmount = this.items.reduce((sum, item) => {
                        return sum + (Number(item.gst_amount) || this.itemTaxAmount(item));
                    }, 0);
                    this.itemsSubtotal = Number(subtotal.toFixed(2));
                    this.discount = Number(cappedDiscount.toFixed(2));
                    this.taxableAmount = Number(taxableAmount.toFixed(2));
                    this.gstAmount = Number(gstAmount.toFixed(2));
                    this.gstPercent = taxableAmount > 0 ? Number(((gstAmount / taxableAmount) * 100).toFixed(2)) : 0;
                    this.totalAmount = Number((taxableAmount + gstAmount).toFixed(2));
                },

                init() {
                    if (Array.isArray(this.items) && this.items.length) {
                        this.items = this.items.map(item => ({
                            ...item,
                            unit: this.resolveItemUnit(item),
                            qty: Number(item.qty || 0),
                            price: Number(item.price || 0),
                            gst: this.ensureGstOption(item.gst),
                            base_total: Number(item.base_total || 0),
                            gst_amount: Number(item.gst_amount || 0),
                            total: Number(item.total || 0),
                        }));
                        this.items.forEach(item => this.calculateRow(item));
                    }
                    this.updateTotal();
                },

                showError(msg) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Validation Error',
                        text: msg,
                        confirmButtonColor: '#4F46E5'
                    });
                },

                async submitForm(e) {
                    const submitterAction = e?.submitter?.dataset?.action || 'save';
                    if (!this.items.length) {
                        this.addItem();
                        await this.$nextTick();
                    }

                    const form = e.target;
                    const validationRules = [
                        {
                            selector: '#subject',
                            rules: { minlength: 3, maxlength: 150 }
                        },
                        {
                            selector: '#billing_address',
                            rules: { maxlength: 1000 }
                        },
                        {
                            selector: '#site_address',
                            rules: { maxlength: 1000 }
                        },
                        {
                            selector: ":input[name^='form_data[items]'][name$='[name]']",
                            rules: { required: true, minlength: 3 }
                        },
                        {
                            selector: "input[name^='form_data[items]'][name$='[brand_model]']",
                            rules: { maxlength: 100 }
                        },
                        {
                            selector: "select[name^='form_data[items]'][name$='[unit]']",
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
                            selector: "input[name='form_data[pricing][discount]']",
                            rules: { number: true, min: 0 }
                        },
                        {
                            selector: '#notes',
                            rules: { maxlength: 2000 }
                        }
                    ];

                    if (!window.generatorValidation.validate(form, validationRules)) {
                        return;
                    }

                    if (this.totalAmount <= 0) {
                        Swal.fire({
                            title: 'Zero Total',
                            text: "The total amount is 0. Do you still want to proceed?",
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#4F46E5',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Yes, save it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                e.target.submit();
                            }
                        });
                        return;
                    }

                    e.target.action = submitterAction === 'preview' ? this.previewUrl : this.saveUrl;
                    e.target.target = submitterAction === 'preview' ? '_blank' : '_self';
                    e.target.submit();
                }
            }
        }
    </script>
</x-app-layout>
