<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageBoqCategories = $authUser && $authUser->hasPermission('boq.categories.manage');
        $canManageBoqProducts = $authUser && $authUser->hasPermission('boq.products.manage');
    @endphp
    <x-slot name="header">
        <div id="product-master-page" class="rounded-none border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950 leading-tight">Product Master</h2>
            <p class="mt-0.5 text-sm text-slate-500">Manage company categories and products.</p>
        </div>
    </x-slot>

    <div class="w-full py-3 sm:px-4 lg:px-6 space-y-3">
        @if(session('success'))
            <div class="rounded-none border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-none border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        @php($boqProductImportSummary = session('boqProductImportSummary'))
        @if($boqProductImportSummary)
            <div class="border border-cyan-200 bg-cyan-50 px-4 py-4 text-sm text-cyan-950 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-cyan-950">Product Import Summary</p>
                        <p class="text-cyan-800">The file was processed row by row. Successful rows were saved even if some rows failed.</p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div class="bg-white/70 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase tracking-widest text-cyan-700">Total Rows</div>
                        <div class="mt-1 text-lg font-bold text-cyan-950">{{ $boqProductImportSummary['total_rows'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white/70 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase tracking-widest text-cyan-700">Imported Products</div>
                        <div class="mt-1 text-lg font-bold text-cyan-950">{{ $boqProductImportSummary['imported_products'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white/70 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase tracking-widest text-cyan-700">Created Categories</div>
                        <div class="mt-1 text-lg font-bold text-cyan-950">{{ $boqProductImportSummary['created_categories'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white/70 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase tracking-widest text-cyan-700">Created Units</div>
                        <div class="mt-1 text-lg font-bold text-cyan-950">{{ $boqProductImportSummary['created_units'] ?? 0 }}</div>
                    </div>
                    <div class="bg-white/70 px-3 py-2">
                        <div class="text-[11px] font-semibold uppercase tracking-widest text-cyan-700">Skipped Duplicate Products</div>
                        <div class="mt-1 text-lg font-bold text-cyan-950">{{ $boqProductImportSummary['skipped_duplicate_products'] ?? ($boqProductImportSummary['skipped_products'] ?? 0) }}</div>
                    </div>
                </div>

                @if(!empty($boqProductImportSummary['failed_products']))
                    <div class="border border-cyan-100 bg-white/80 p-3">
                        <div class="text-sm font-semibold text-cyan-950">Failed Products</div>
                        <ul class="mt-2 max-h-52 space-y-1 overflow-auto pl-5 text-sm text-cyan-900 list-disc">
                            @foreach($boqProductImportSummary['failed_products'] as $failure)
                                <li>{{ $failure }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        <div x-data="{ boqTab: @js($errors->has('product_import_file') ? 'products' : request('tab', $canManageBoqProducts && !$canManageBoqCategories ? 'products' : 'categories')) }" class="border border-slate-200 bg-white shadow-sm">

            {{-- Tab bar --}}
            <div class="border-b border-slate-200 px-4">
                <nav class="flex">
                    @if($canManageBoqCategories)
                    <button type="button" @click="boqTab = 'categories'"
                        :class="boqTab === 'categories' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="border-b-2 px-4 py-3 text-sm font-semibold transition">
                        Categories
                    </button>
                    @endif
                    @if($canManageBoqProducts)
                    <button type="button" @click="boqTab = 'products'"
                        :class="boqTab === 'products' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                        class="border-b-2 px-4 py-3 text-sm font-semibold transition">
                        Products
                    </button>
                    @endif
                </nav>
            </div>

            <div class="p-4 space-y-4">

                @if($canManageBoqCategories)
                <div x-show="boqTab === 'categories'" class="space-y-4">
                    <div class="flex justify-end">
                        <button type="button" id="open-boq-category-modal" class="inline-flex items-center justify-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Add Category
                        </button>
                    </div>
                    {{-- Categories Table --}}
                    <div class="overflow-hidden rounded-none border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Name</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Products</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($categories as $category)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $category->name }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $category->products_count }}</td>
                                        <td class="px-4 py-3 text-right">
                                            @if($canManageBoqCategories)
                                                <button type="button" class="rounded-none bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700" data-boq-category-edit data-id="{{ $category->id }}" data-name="{{ $category->name }}"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg></button>
                                                <form method="POST" action="{{ route('settings.boq.categories.destroy', $category) }}" class="inline boq-delete-form" data-delete-message="Delete this BOQ category?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-none bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg></button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No categories yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($categories->hasPages())
                        <div class="flex justify-end">
                            {{ $categories->appends(array_merge(request()->query(), ['tab' => 'categories']))->links() }}
                        </div>
                    @endif
                </div>
                @endif

                @if($canManageBoqProducts)
                <div x-show="boqTab === 'products'" class="space-y-4">
                    <div class="flex flex-col gap-3 rounded-none border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-bold text-slate-900">Import or export products</h3>
                            <p class="mt-1 text-sm text-slate-500">Upload product sheets or download the current master list.</p>
                        </div>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button type="button" id="toggle-boq-product-filter" class="inline-flex items-center justify-center rounded-none border border-slate-300 bg-white px-3 py-2.5 text-slate-700 transition hover:bg-slate-50" aria-controls="boq-product-filter-wrapper" aria-expanded="false" title="Show or hide filters">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path d="M3 5h18l-7 8v5l-4 1v-6L3 5z"></path>
                                </svg>
                                <span class="sr-only">Toggle product filters</span>
                            </button>
                            <a href="{{ route('settings.boq.products.export', request()->query()) }}" class="inline-flex items-center justify-center rounded-none border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                                Export
                            </a>
                            <button type="button" id="open-product-import-modal" class="inline-flex items-center justify-center rounded-none border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-sm font-semibold text-cyan-800 transition hover:bg-cyan-100">
                                Import
                            </button>
                        </div>
                    </div>
                    <div id="boq-product-filter-wrapper" class="hidden rounded-none border border-slate-200 bg-slate-50 p-4">
                        <form method="GET" action="{{ route('settings.boq.index') }}" class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                            <input type="hidden" name="tab" value="products">
                            <div class="xl:col-span-2">
                                <x-input-label for="product_search" value="Search All Fields" />
                                <x-text-input id="product_search" name="product_search" class="mt-1 block w-full rounded-none" value="{{ $filters['product_search'] ?? '' }}" placeholder="Product, brand, model, unit, category, price, GST..." />
                            </div>
                            <div>
                                <x-input-label for="product_category_filter" value="Category" />
                                <select id="product_category_filter" name="product_category_id" class="mt-1 block w-full rounded-none border-slate-300">
                                    <option value="">All categories</option>
                                    @foreach($categoryOptions as $category)
                                        <option value="{{ $category->id }}" @selected((string) ($filters['product_category_id'] ?? null) === (string) $category->id)>{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="price_min" value="Min Price" />
                                <x-text-input id="price_min" name="price_min" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-none" value="{{ $filters['price_min'] ?? '' }}" />
                            </div>
                            <div>
                                <x-input-label for="price_max" value="Max Price" />
                                <x-text-input id="price_max" name="price_max" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-none" value="{{ $filters['price_max'] ?? '' }}" />
                            </div>
                            <div>
                                <x-input-label for="product_gst_filter" value="GST" />
                                <select id="product_gst_filter" name="product_gst" class="mt-1 block w-full rounded-none border-slate-300">
                                    <option value="">All GST</option>
                                    <option value="0" @selected(isset($filters['product_gst']) && (float) $filters['product_gst'] === 0.0)>0.00%</option>
                                    @foreach($gstRates as $gstRate)
                                        <option value="{{ $gstRate->rate }}" @selected(isset($filters['product_gst']) && (float) $filters['product_gst'] === (float) $gstRate->rate)>{{ number_format((float) $gstRate->rate, 2) }}%</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="product_status_filter" value="Status" />
                                <select id="product_status_filter" name="product_status" class="mt-1 block w-full rounded-none border-slate-300">
                                    <option value="">All Status</option>
                                    <option value="active" @selected(($filters['product_status'] ?? null) === 'active')>Active</option>
                                    <option value="inactive" @selected(($filters['product_status'] ?? null) === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div class="md:col-span-2 xl:col-span-6 flex items-end gap-3">
                                <button type="submit" class="inline-flex items-center justify-center rounded-none bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">Apply Filters</button>
                                <a href="{{ route('settings.boq.index', ['tab' => 'products']) }}" class="inline-flex items-center justify-center rounded-none border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Reset</a>
                            </div>
                        </form>
                    </div>
                    {{-- Add Product Form --}}
                    <div class="rounded-none border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-base font-bold text-slate-900">Add Product</h3>
                        <form id="boq-product-create-form" method="POST" action="{{ route('settings.boq.products.store', ['tab' => 'products']) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 md:grid-cols-2" novalidate>
                            @csrf
                            <x-searchable-select
                                name="category_id"
                                id="product_category_id"
                                label="Category"
                                :options="$categoryOptions->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values()"
                                :selected="old('category_id')"
                                placeholder="Search category..."
                                empty-label="Select category"
                                helper-text="Search and select a product category."
                            />
                            <div>
                                <x-input-label for="product_name" value="Product Name" />
                                <x-text-input id="product_name" name="name" class="mt-1 block w-full rounded-none" minlength="2" maxlength="191" value="{{ old('name') }}" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="product_brand" value="Brand" />
                                <x-text-input id="product_brand" name="brand" class="mt-1 block w-full rounded-none" maxlength="191" value="{{ old('brand') }}" />
                                <x-input-error :messages="$errors->get('brand')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="product_model" value="Model" />
                                <x-text-input id="product_model" name="model" class="mt-1 block w-full rounded-none" maxlength="191" value="{{ old('model') }}" />
                                <x-input-error :messages="$errors->get('model')" class="mt-2" />
                            </div>
                            <x-searchable-select
                                name="unit"
                                id="product_unit"
                                label="Unit"
                                :options="$units->map(fn ($unit) => ['id' => $unit->name, 'name' => $unit->name])->values()"
                                :selected="old('unit')"
                                placeholder="Search unit..."
                                empty-label="Select unit"
                                helper-text="Search and select a unit."
                            />
                            <div>
                                <x-input-label for="product_price" value="Unit Price" />
                                <x-text-input id="product_price" name="price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-none" value="{{ old('price') }}" />
                                <x-input-error :messages="$errors->get('price')" class="mt-2" />
                            </div>
                            <x-searchable-select
                                name="gst"
                                id="product_gst"
                                label="GST (%)"
                                :options="collect([['id' => '0', 'name' => '0.00%']])->merge($gstRates->filter(fn ($gstRate) => (float) $gstRate->rate > 0)->map(fn ($gstRate) => ['id' => (string) $gstRate->rate, 'name' => number_format((float) $gstRate->rate, 2) . '%'])->values())"
                                :selected="old('gst', '')"
                                placeholder="Search GST..."
                                empty-label="Select GST"
                                helper-text="Search and select a GST rate."
                            />
                            <div>
                                <x-input-label for="product_image" value="Product Image" />
                                <input id="product_image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700" />
                                <x-input-error :messages="$errors->get('product_image')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2">
                                <div class="flex items-center justify-between">
                                    <x-input-label for="product_description" value="Description" />
                                    <x-speech-to-text target="product_description" />
                                </div>
                                <textarea id="product_description" name="description" rows="2" maxlength="1000" class="mt-1 block w-full rounded-xl border-slate-300">{{ old('description') }}</textarea>
                                <x-input-error :messages="$errors->get('description')" class="mt-2" />
                            </div>
                            <div class="md:col-span-2 flex items-center justify-between gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-slate-300 text-indigo-600">
                                    Active
                                </label>
                                <x-primary-button id="boq-product-create-submit">Add Product</x-primary-button>
                            </div>
                        </form>
                    </div>
                    {{-- Products Table --}}
                    <div class="overflow-hidden rounded-none border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Product</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Category</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Price</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">GST</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($products as $product)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">
                                            <div class="flex items-center gap-3">
                                                @if($product->image_path)
                                                    <img src="{{ route('settings.boq.products.image', $product) }}" alt="{{ $product->name }}" class="h-10 w-10 rounded-lg border border-slate-200 object-cover" loading="lazy" onerror="this.classList.add('hidden')">
                                                @endif
                                                <div>
                                                    <div class="flex items-center gap-2">
                                                        <span>{{ $product->name }}</span>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $product->is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600' }}">
                                                            {{ $product->is_active ? 'Active' : 'Inactive' }}
                                                        </span>
                                                    </div>
                                                    @if($product->brand || $product->model)
                                                        <div class="text-xs font-medium text-slate-500">{{ trim(($product->brand ?? '').' '.($product->model ?? '')) }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ $product->category?->name ?? '-' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ number_format((float) $product->price, 2) }} / {{ $product->unit }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ number_format((float) $product->gst, 2) }}%</td>
                                        <td class="px-4 py-3 text-right">
                                            @if($canManageBoqProducts)
                                            <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700" data-boq-product-edit
                                                data-id="{{ $product->id }}"
                                                data-category-id="{{ $product->category_id }}"
                                                data-name="{{ $product->name }}"
                                                data-brand="{{ $product->brand }}"
                                                data-model="{{ $product->model }}"
                                                data-unit="{{ $product->unit }}"
                                                data-price="{{ $product->price }}"
                                                data-gst="{{ $product->gst }}"
                                                data-description="{{ e($product->description ?? '') }}"
                                                data-is-active="{{ $product->is_active ? 1 : 0 }}"
                                            ><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg></button>
                                            <form method="POST" action="{{ route('settings.boq.products.destroy', $product) }}" class="inline boq-delete-form" data-delete-message="Delete this BOQ product?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg></button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No products found for the current filters.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($products->hasPages())
                        <div class="flex justify-end">
                            {{ $products->appends(array_merge(request()->query(), ['tab' => 'products']))->links() }}
                        </div>
                    @endif
                </div>
                @endif

            </div>
        </div>
    </div>

    <div id="boq-category-create-modal" class="{{ $errors->has('name') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6" aria-hidden="{{ $errors->has('name') ? 'false' : 'true' }}">
        <div class="absolute inset-0" data-boq-modal-close></div>
        <div class="relative w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-bold text-slate-950">Add Category</h4>
                <button type="button" class="rounded-full p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" data-boq-modal-close aria-label="Close add category modal">&times;</button>
            </div>
            <form id="boq-category-create-form" method="POST" action="{{ route('settings.boq.categories.store', ['tab' => 'categories']) }}" class="mt-5 space-y-4" novalidate>
                @csrf
                <div>
                    <x-input-label for="category_name" value="Category Name" />
                    <x-text-input id="category_name" name="name" class="mt-1 block w-full rounded-xl" placeholder="Electrical, Civil, CCTV" minlength="2" maxlength="191" value="{{ old('name') }}" />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" data-boq-modal-close>Cancel</button>
                    <x-primary-button id="boq-category-create-submit">Add Category</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <div id="boq-category-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
        <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-bold text-slate-950">Edit Category</h4>
                <button type="button" class="text-slate-500" data-boq-modal-close>✕</button>
            </div>
            <form id="boq-category-edit-form" method="POST" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="boq_category_edit_name" value="Category Name" />
                    <x-text-input id="boq_category_edit_name" name="name" class="mt-1 block w-full rounded-xl" minlength="2" maxlength="191" />
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-boq-modal-close>Cancel</button>
                    <x-primary-button>Update Category</x-primary-button>
                </div>
            </form>
        </div>
    </div>

    <div id="boq-product-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
        <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between">
                <h4 class="text-lg font-bold text-slate-950">Edit Product</h4>
                <button type="button" class="text-slate-500" data-boq-modal-close>✕</button>
            </div>
            <form id="boq-product-edit-form" method="POST" enctype="multipart/form-data" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                @method('PATCH')
                <x-searchable-select
                    name="category_id"
                    id="boq_product_edit_category_id"
                    label="Category"
                    :options="$categoryOptions->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values()"
                    selected=""
                    placeholder="Search category..."
                    empty-label="Select category"
                    helper-text="Search and select a category."
                />
                <div>
                    <x-input-label for="boq_product_edit_name" value="Product Name" />
                    <x-text-input id="boq_product_edit_name" name="name" class="mt-1 block w-full rounded-xl" minlength="2" maxlength="191" />
                </div>
                <div>
                    <x-input-label for="boq_product_edit_brand" value="Brand" />
                    <x-text-input id="boq_product_edit_brand" name="brand" class="mt-1 block w-full rounded-xl" maxlength="191" />
                </div>
                <div>
                    <x-input-label for="boq_product_edit_model" value="Model" />
                    <x-text-input id="boq_product_edit_model" name="model" class="mt-1 block w-full rounded-xl" maxlength="191" />
                </div>
                <x-searchable-select
                    name="unit"
                    id="boq_product_edit_unit"
                    label="Unit"
                    :options="$units->map(fn ($unit) => ['id' => $unit->name, 'name' => $unit->name])->values()"
                    selected=""
                    placeholder="Search unit..."
                    empty-label="Select unit"
                    helper-text="Search and select a unit."
                />
                <div>
                    <x-input-label for="boq_product_edit_price" value="Unit Price" />
                    <x-text-input id="boq_product_edit_price" name="price" type="number" min="0" step="0.01" class="mt-1 block w-full rounded-xl" />
                </div>
                <x-searchable-select
                    name="gst"
                    id="boq_product_edit_gst"
                    label="GST (%)"
                    :options="collect([['id' => '0', 'name' => '0.00%']])->merge($gstRates->filter(fn ($gstRate) => (float) $gstRate->rate > 0)->map(fn ($gstRate) => ['id' => (string) $gstRate->rate, 'name' => number_format((float) $gstRate->rate, 2) . '%'])->values())"
                    selected="0"
                    placeholder="Search GST..."
                    empty-label="Select GST"
                    helper-text="Search and select a GST rate."
                />
                <div>
                    <x-input-label for="boq_product_edit_image" value="Replace Product Image" />
                    <input id="boq_product_edit_image" name="product_image" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full rounded-xl border border-slate-300 bg-white text-sm text-slate-700 file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-slate-700" />
                </div>
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between">
                        <x-input-label for="boq_product_edit_description" value="Description" />
                        <x-speech-to-text target="boq_product_edit_description" />
                    </div>
                    <textarea id="boq_product_edit_description" name="description" rows="2" maxlength="1000" class="mt-1 block w-full rounded-xl border-slate-300"></textarea>
                </div>
                <div class="md:col-span-2 flex items-center justify-between">
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input id="boq_product_edit_is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600">
                        Active
                    </label>
                    <div class="flex gap-3">
                        <button type="button" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-boq-modal-close>Cancel</button>
                        <x-primary-button>Update Product</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div id="product-import-modal"
        class="{{ $errors->has('product_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6"
        aria-hidden="{{ $errors->has('product_import_file') ? 'false' : 'true' }}"
        data-modal-open="{{ $errors->has('product_import_file') ? 'true' : 'false' }}">
        <div class="absolute inset-0" data-close-product-import-modal></div>
        <div class="relative w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h4 class="text-lg font-bold text-slate-950">Import Products</h4>
                    <p class="mt-1 text-sm text-slate-500">Upload CSV, XLS, or XLSX for the product master.</p>
                </div>
                <button type="button" data-close-product-import-modal class="rounded-full p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close import modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            @if ($errors->has('product_import_file'))
                <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    @foreach ($errors->get('product_import_file') as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('settings.boq.products.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="product_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose file</label>
                    <input id="product_import_file" type="file" name="product_import_file" accept=".csv,.xls,.xlsx" class="block w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('settings.boq.products.import.sample') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Download Sample
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-cyan-700">
                        Import Products
                    </button>
                </div>
            </form>

            <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                Product import required columns: <span class="font-semibold">category, name, unit, price</span>. Optional columns like brand, model, gst, description, and is_active are also supported.
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.validate !== 'function') {
                    return;
                }

                const $ = window.jQuery;
                const categoryForm = document.getElementById('boq-category-create-form');
                const categorySubmit = document.getElementById('boq-category-create-submit');
                const categoryName = document.getElementById('category_name');
                const categoryCreateModal = document.getElementById('boq-category-create-modal');
                const categoryCreateOpenButton = document.getElementById('open-boq-category-modal');
                const productForm = document.getElementById('boq-product-create-form');
                const productSubmit = document.getElementById('boq-product-create-submit');
                const productName = document.getElementById('product_name');
                const productCategory = document.getElementById('product_category_id');
                const productUnit = document.getElementById('product_unit');
                const productPrice = document.getElementById('product_price');
                const categoryModal = document.getElementById('boq-category-modal');
                const categoryEditForm = document.getElementById('boq-category-edit-form');
                const categoryEditName = document.getElementById('boq_category_edit_name');
                const productModal = document.getElementById('boq-product-modal');
                const productEditForm = document.getElementById('boq-product-edit-form');
                const productEditCategory = document.getElementById('boq_product_edit_category_id');
                const productEditName = document.getElementById('boq_product_edit_name');
                const productEditBrand = document.getElementById('boq_product_edit_brand');
                const productEditModel = document.getElementById('boq_product_edit_model');
                const productEditUnit = document.getElementById('boq_product_edit_unit');
                const productEditPrice = document.getElementById('boq_product_edit_price');
                const productEditGst = document.getElementById('boq_product_edit_gst');
                const productEditDescription = document.getElementById('boq_product_edit_description');
                const productEditIsActive = document.getElementById('boq_product_edit_is_active');
                const productImportModal = document.getElementById('product-import-modal');
                const productImportOpenButton = document.getElementById('open-product-import-modal');
                const productFilterToggle = document.getElementById('toggle-boq-product-filter');
                const productFilterWrapper = document.getElementById('boq-product-filter-wrapper');

                function showValidationError(message) {
                    if (window.appAlert) {
                        window.appAlert(message, 'Validation Error', 'error');
                        return;
                    }
                    alert(message);
                }

                function placeFieldError(error, element) {
                    error.addClass('text-xs text-rose-600 mt-2 block');
                    error.insertAfter(element);
                }

                function attachTrimToMaxLength(form) {
                    form?.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(function (field) {
                        field.addEventListener('input', function () {
                            const maxLength = Number(this.getAttribute('maxlength') || 0);
                            if (maxLength > 0 && this.value.length > maxLength) {
                                this.value = this.value.slice(0, maxLength);
                            }
                        });
                    });
                }

                function disableSubmit(button, text) {
                    if (!button) return;
                    button.disabled = true;
                    button.textContent = text;
                }

                function ensureSelectOption(select, value) {
                    if (!select || !value) {
                        return;
                    }

                    const exists = Array.from(select.options).some(function (option) {
                        return option.value === value;
                    });

                    if (!exists) {
                        const option = document.createElement('option');
                        option.value = value;
                        option.textContent = `${value} (Current)`;
                        select.appendChild(option);
                    }
                }

                function syncSearchableSelect(select, value) {
                    if (!select) return;
                    ensureSelectOption(select, value);
                    select.value = value || '';
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    select.dispatchEvent(new Event('input', { bubbles: true }));
                }

                function validateCategoryForm(form, submitButton, pendingText) {
                    if (!form) return;
                    $(form).validate({
                        rules: {
                            name: { required: true, minlength: 2, maxlength: 191 },
                        },
                        messages: {
                            name: {
                                required: 'Category name is required.',
                                minlength: 'Category name must be at least 2 characters.',
                                maxlength: 'Category name must not exceed 191 characters.',
                            },
                        },
                        errorClass: 'text-xs text-rose-600 mt-2 block',
                        errorPlacement: placeFieldError,
                        highlight: function (element) {
                            $(element).addClass('border-rose-400 ring-1 ring-rose-300');
                        },
                        unhighlight: function (element) {
                            $(element).removeClass('border-rose-400 ring-1 ring-rose-300');
                        },
                        invalidHandler: function (event, validator) {
                            showValidationError(validator?.errorList?.[0]?.message || 'Please check the category details.');
                        },
                        submitHandler: function (formEl) {
                            disableSubmit(submitButton, pendingText);
                            formEl.submit();
                            return false;
                        }
                    });

                    attachTrimToMaxLength(form);
                }

                function validateProductForm(form, submitButton, pendingText) {
                    if (!form) return;
                    $(form).validate({
                        rules: {
                            category_id: { required: true },
                            name: { required: true, minlength: 2, maxlength: 191 },
                            brand: { maxlength: 191 },
                            model: { maxlength: 191 },
                            unit: { required: true, minlength: 1, maxlength: 50 },
                            price: { required: true, number: true, min: 0 },
                            gst: { required: true, number: true, min: 0, max: 100 },
                            description: { maxlength: 1000 },
                        },
                        messages: {
                            category_id: { required: 'Category is required.' },
                            name: {
                                required: 'Product name is required.',
                                minlength: 'Product name must be at least 2 characters.',
                                maxlength: 'Product name must not exceed 191 characters.',
                            },
                            brand: {
                                maxlength: 'Brand must not exceed 191 characters.',
                            },
                            model: {
                                maxlength: 'Model must not exceed 191 characters.',
                            },
                            unit: {
                                required: 'Unit is required.',
                                minlength: 'Unit must be at least 1 character.',
                                maxlength: 'Unit must not exceed 50 characters.',
                            },
                            price: {
                                required: 'Unit price is required.',
                                number: 'Unit price must be a valid number.',
                                min: 'Unit price cannot be negative.',
                            },
                            gst: {
                                required: 'GST is required.',
                                number: 'GST must be a valid number.',
                                min: 'GST cannot be negative.',
                                max: 'GST must not exceed 100%.',
                            },
                            description: {
                                maxlength: 'Description must not exceed 1000 characters.',
                            },
                        },
                        errorClass: 'text-xs text-rose-600 mt-2 block',
                        errorPlacement: placeFieldError,
                        highlight: function (element) {
                            $(element).addClass('border-rose-400 ring-1 ring-rose-300');
                        },
                        unhighlight: function (element) {
                            $(element).removeClass('border-rose-400 ring-1 ring-rose-300');
                        },
                        invalidHandler: function (event, validator) {
                            showValidationError(validator?.errorList?.[0]?.message || 'Please check the product details.');
                        },
                        submitHandler: function (formEl) {
                            disableSubmit(submitButton, pendingText);
                            formEl.submit();
                            return false;
                        }
                    });

                    attachTrimToMaxLength(form);
                }

                validateCategoryForm(categoryForm, categorySubmit, 'Adding...');
                validateProductForm(productForm, productSubmit, 'Adding...');
                validateCategoryForm(categoryEditForm, categoryEditForm?.querySelector('button[type="submit"]'), 'Updating...');
                validateProductForm(productEditForm, productEditForm?.querySelector('button[type="submit"]'), 'Updating...');

                if (categoryCreateModal && categoryCreateOpenButton) {
                    const closeCategoryCreateModal = function () {
                        categoryCreateModal.classList.add('hidden');
                        categoryCreateModal.setAttribute('aria-hidden', 'true');
                    };

                    const openCategoryCreateModal = function () {
                        categoryCreateModal.classList.remove('hidden');
                        categoryCreateModal.setAttribute('aria-hidden', 'false');
                        categoryName?.focus();
                    };

                    categoryCreateOpenButton.addEventListener('click', openCategoryCreateModal);
                    categoryCreateModal.querySelectorAll('[data-boq-modal-close]').forEach(function (button) {
                        button.addEventListener('click', closeCategoryCreateModal);
                    });

                    if (categoryCreateModal.getAttribute('aria-hidden') === 'false') {
                        openCategoryCreateModal();
                    }
                }

                document.querySelectorAll('[data-boq-category-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        categoryEditForm.action = `{{ url('/settings/boq/categories') }}/${button.dataset.id}`;
                        categoryEditName.value = button.dataset.name || '';
                        categoryModal.classList.remove('hidden');
                    });
                });

                document.querySelectorAll('[data-boq-product-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        productEditForm.action = `{{ url('/settings/boq/products') }}/${button.dataset.id}`;
                        syncSearchableSelect(productEditCategory, button.dataset.categoryId || '');
                        productEditName.value = button.dataset.name || '';
                        productEditBrand.value = button.dataset.brand || '';
                        productEditModel.value = button.dataset.model || '';
                        syncSearchableSelect(productEditUnit, button.dataset.unit || '');
                        productEditPrice.value = button.dataset.price || '';
                        syncSearchableSelect(productEditGst, button.dataset.gst || '0');
                        productEditDescription.value = button.dataset.description || '';
                        productEditIsActive.checked = button.dataset.isActive === '1';
                        productModal.classList.remove('hidden');
                    });
                });

                document.querySelectorAll('[data-boq-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        categoryModal?.classList.add('hidden');
                        productModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('.boq-delete-form').forEach(function (form) {
                    form.addEventListener('submit', async function (event) {
                        if (form.dataset.confirmed === '1') {
                            return;
                        }

                        event.preventDefault();

                        let confirmed = false;
                        if (window.appConfirm) {
                            confirmed = await window.appConfirm(form.dataset.deleteMessage || 'Are you sure you want to delete this item?', {
                                title: 'Confirm Delete',
                                confirmText: 'Yes, delete it',
                                cancelText: 'Cancel',
                                icon: 'warning',
                                confirmButtonColor: '#e11d48',
                                cancelButtonColor: '#475569',
                            });
                        } else {
                            confirmed = window.confirm(form.dataset.deleteMessage || 'Are you sure you want to delete this item?');
                        }

                        if (confirmed) {
                            form.dataset.confirmed = '1';
                            form.submit();
                        }
                    });
                });

                if (productImportModal && productImportOpenButton) {
                    const closeButtons = productImportModal.querySelectorAll('[data-close-product-import-modal]');
                    const openImportModal = function () {
                        productImportModal.classList.remove('hidden');
                        productImportModal.setAttribute('aria-hidden', 'false');
                    };
                    const closeImportModal = function () {
                        productImportModal.classList.add('hidden');
                        productImportModal.setAttribute('aria-hidden', 'true');
                    };

                    productImportOpenButton.addEventListener('click', openImportModal);
                    closeButtons.forEach(function (button) {
                        button.addEventListener('click', closeImportModal);
                    });

                    if (productImportModal.dataset.modalOpen === 'true') {
                        openImportModal();
                    }
                }

                if (productFilterToggle && productFilterWrapper) {
                    const filterInputs = productFilterWrapper.querySelectorAll('input, select, textarea');
                    const hasActiveFilter = Array.from(filterInputs).some(function (field) {
                        if (!field.name || field.name === 'tab') {
                            return false;
                        }

                        const value = typeof field.value === 'string' ? field.value.trim() : String(field.value || '').trim();
                        if (!value) {
                            return false;
                        }

                        if (field.type === 'hidden') {
                            return false;
                        }

                        if (field.tagName === 'SELECT') {
                            return value !== '';
                        }

                        return value !== '';
                    });

                    if (hasActiveFilter) {
                        productFilterWrapper.classList.remove('hidden');
                        productFilterToggle.setAttribute('aria-expanded', 'true');
                    }

                    productFilterToggle.addEventListener('click', function () {
                        const isHidden = productFilterWrapper.classList.toggle('hidden');
                        productFilterToggle.setAttribute('aria-expanded', String(!isHidden));
                    });
                }

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        categoryCreateModal?.classList.add('hidden');
                        categoryModal?.classList.add('hidden');
                        productModal?.classList.add('hidden');
                        productImportModal?.classList.add('hidden');
                    }
                });
            })();
        </script>
    @endpush
</x-app-layout>
