<x-guest-layout>
    @php
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
        $publicLink = $publicLink ?? route('customer.support.qr.show', ['token' => $qrToken]);
    @endphp

    <div id="customer-support-complaint-page" class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-cyan-50 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-5xl">
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="border-b border-slate-200 px-6 py-6 sm:px-8">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            @if($companyLogoUrl)
                                <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?: 'Company' }}" class="h-14 w-14 rounded-2xl border border-slate-200 bg-white object-contain p-2 shadow-sm">
                            @else
                                <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-900 text-lg font-bold text-white">
                                    {{ strtoupper(substr((string) ($company?->name ?: 'CW'), 0, 2)) }}
                                </div>
                            @endif
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.24em] text-cyan-600">Customer QR Complaint Portal</p>
                                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $company?->name ?: 'Company' }}</h1>
                                <p class="mt-1 text-sm text-slate-500">Please share the issue details below. Your customer profile has already been loaded.</p>
                            </div>
                        </div>

                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-600 ring-1 ring-inset ring-slate-200">
                            <div class="font-semibold text-slate-900">{{ $client->name }}</div>
                            <div>{{ $client->email ?: '-' }} · {{ $client->mobile ?: $client->phone ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 px-6 py-6 lg:grid-cols-3 lg:px-8">
                    <div class="space-y-6 lg:col-span-1">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Customer Information</p>
                            <div class="mt-4 space-y-3 text-sm text-slate-700">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Customer Name</div>
                                    <div class="mt-1 font-medium text-slate-900">{{ $client->name }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Company Name</div>
                                    <div class="mt-1 font-medium text-slate-900">{{ $company?->name ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Email</div>
                                    <div class="mt-1 font-medium text-slate-900">{{ $client->email ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Mobile Number</div>
                                    <div class="mt-1 font-medium text-slate-900">{{ $client->mobile ?: $client->phone ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Address</div>
                                    <div class="mt-1 font-medium text-slate-900">{{ $client->address ?: '-' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">City / State / Country</div>
                                    <div class="mt-1 font-medium text-slate-900">
                                        {{ $client->city?->name ?: '-' }}, {{ $client->state?->name ?: '-' }}, India
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-cyan-100 bg-cyan-50/70 p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">AMC Details</p>
                            @if($amc)
                                <div class="mt-4 space-y-2 text-sm text-slate-700">
                                    <div><span class="font-semibold text-slate-900">Contract No:</span> {{ $amc->contract_no ?: '-' }}</div>
                                    <div><span class="font-semibold text-slate-900">Project:</span> {{ $amc->project_name ?: '-' }}</div>
                                    <div><span class="font-semibold text-slate-900">Service Mode:</span> {{ $amc->service_mode ? str($amc->service_mode)->replace('_', ' ')->headline()->toString() : '-' }}</div>
                                    <div><span class="font-semibold text-slate-900">Valid Till:</span> {{ $amc->end_date?->format('d M Y') ?: '-' }}</div>
                                </div>
                            @else
                                <p class="mt-4 text-sm text-slate-600">No AMC details available for this customer.</p>
                            @endif
                        </div>
                    </div>

                    <div class="lg:col-span-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h2 class="text-xl font-bold text-slate-900">Complaint Form</h2>
                                    <p class="mt-1 text-sm text-slate-500">Fields marked with * are required.</p>
                                </div>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-inset ring-emerald-200">
                                    Token Verified
                                </span>
                            </div>

                            @if (session('qr_form_error'))
                                <div class="mt-6 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800" role="alert">
                                    {{ session('qr_form_error') }}
                                </div>
                            @endif

                            <form action="{{ route('customer.support.qr.store', ['token' => $qrToken]) }}" method="POST" enctype="multipart/form-data" class="mt-6 space-y-5">
                                @csrf

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Complaint Subject *</label>
                                        <input type="text" name="subject" value="{{ old('subject') }}" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="Short complaint title">
                                        @error('subject')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Priority *</label>
                                        <select name="priority" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                            <option value="">Select priority</option>
                                            @foreach(['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $key => $label)
                                                <option value="{{ $key }}" @selected(old('priority') === $key)>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('priority')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Category *</label>
                                        <select name="category" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                            <option value="">Select category</option>
                                            @foreach([
                                                // 'support' => 'Support',
                                                'amc' => 'AMC',
                                                // 'installation' => 'Installation',
                                                // 'repair' => 'Repair',
                                                // 'service' => 'Service',
                                                // 'other' => 'Other'
                                                'complain'=>"Complain"
                                                ]
                                                as $key => $label)
                                                <option value="{{ $key }}" @selected(old('category') === $key)>
                                                    {{ $key === 'amc' && $amc ? $label . ' (' . (int) $amcRemainingVisits . ')' : $label }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Preferred Visit Date</label>
                                        <input type="date" name="preferred_visit_date" value="{{ old('preferred_visit_date') }}" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                        @error('preferred_visit_date')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Preferred Visit Time</label>
                                        <input type="time" name="preferred_visit_time" value="{{ old('preferred_visit_time') }}" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500">
                                        @error('preferred_visit_time')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>

                                    <div>
                                        <label class="block text-sm font-semibold text-slate-700">Attachment (optional)</label>
                                        <input type="file" name="attachment" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm file:mr-4 file:rounded-lg file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800" accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mov,.avi,.mkv">
                                        <p class="mt-1 text-xs text-slate-500">Allowed: Photo, PDF, Video. Max 10 MB.</p>
                                        @error('attachment')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-semibold text-slate-700">Complaint Description *</label>
                                    <textarea name="description" rows="7" class="mt-2 w-full rounded-2xl border-slate-300 px-4 py-3 text-sm focus:border-cyan-500 focus:ring-cyan-500" placeholder="Describe the issue in detail">{{ old('description') }}</textarea>
                                    @error('description')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                                </div>

                                <div class="flex flex-col gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm text-slate-500">By submitting, a support ticket will be created automatically.</p>
                                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-cyan-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-cyan-500">
                                        Submit Complaint
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
