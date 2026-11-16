<x-app-layout>
    @php
        $user = Auth::user();
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Dashboard</h2>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-2xl font-semibold text-slate-950">Welcome back, {{ $user->name }}!</h3>
                    <p class="text-sm text-slate-500">Here's what's happening with your customers today.</p>
                </div>
            </div>
        </div>

        <section class="rounded-2xl bg-[#141b2d] px-5 py-5 text-white shadow-lg shadow-slate-950/10">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-bold">Super Admin Portal</div>
                    <div class="text-sm text-slate-300">Quick access to global admin tools.</div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('super-admin.companies.create') }}"
                        class="inline-flex items-center rounded-2xl bg-indigo-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-400 shadow-sm">
                        + Register Company
                    </a>
                    <a href="{{ route('super-admin.subscriptions') }}"
                        class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        Subscriptions
                    </a>
                    <a href="{{ route('super-admin.payments') }}"
                        class="inline-flex items-center rounded-2xl border border-white/20 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10">
                        Payments
                    </a>
                    <a href="{{ route('super-admin.locations') }}"
                        class="inline-flex items-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                        Manage States & Cities
                    </a>
                </div>
            </div>
        </section>

        <!-- Comprehensive Financial Metrics Cards -->
        <section class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Subscription & Revenue Financial Overview</h3>
                    <p class="text-xs text-slate-500">Live breakdown of gross collection, base subscription value, and differentiated GST amounts.</p>
                </div>
                <div class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    GST Rate: {{ number_format(\App\Support\SubscriptionPricing::gstRate(), 0) }}% standard
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <!-- Total Clients / Companies -->
                <div class="relative overflow-hidden rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-sky-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-sky-800">Total Client / Company</span>
                        <span class="rounded-xl bg-sky-100 p-2 text-sky-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-950">{{ $stats['companies'] ?? 0 }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-sky-100/80 pt-2.5 text-xs text-slate-600">
                        <span>Active: <strong class="text-slate-800">{{ $stats['activeCompanies'] ?? 0 }}</strong></span>
                        <span class="text-sky-700 font-semibold">{{ ($stats['companies'] ?? 0) - ($stats['activeCompanies'] ?? 0) }} Inactive</span>
                    </div>
                </div>

                <!-- Total Sold License Key -->
                <div class="relative overflow-hidden rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-amber-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-800">Total Sold License Key</span>
                        <span class="rounded-xl bg-amber-100 p-2 text-amber-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-950">{{ $stats['totalSoldLicenseKeys'] ?? 0 }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-amber-100/80 pt-2.5 text-xs text-slate-600">
                        <span>Paid License Seats</span>
                        <span class="font-bold text-amber-700">Active</span>
                    </div>
                </div>

                <!-- All-Time Gross Amount -->
                <div class="relative overflow-hidden rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50 via-white to-emerald-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-emerald-800">Total Subscription Amount</span>
                        <span class="rounded-xl bg-emerald-100 p-2 text-emerald-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-950">₹{{ number_format((float) ($stats['totalEarnedAmount'] ?? 0), 2) }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-emerald-100/80 pt-2.5 text-xs text-slate-600">
                        <span>Base: <strong class="text-slate-800">₹{{ number_format((float) ($stats['totalBaseAmount'] ?? 0), 2) }}</strong></span>
                        <span class="text-emerald-700 font-semibold">GST: ₹{{ number_format((float) ($stats['totalGstAmount'] ?? 0), 2) }}</span>
                    </div>
                </div>

                <!-- Total GST Collected -->
                <div class="relative overflow-hidden rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-violet-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-violet-800">Total GST Differentiated</span>
                        <span class="rounded-xl bg-violet-100 p-2 text-violet-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-violet-950">₹{{ number_format((float) ($stats['totalGstAmount'] ?? 0), 2) }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-violet-100/80 pt-2.5 text-xs text-slate-600">
                        <span>CGST + SGST (18%)</span>
                        <span class="font-medium text-violet-800">Split Included</span>
                    </div>
                </div>

                <!-- Today's Total & GST -->
                <div class="relative overflow-hidden rounded-2xl border border-sky-200 bg-gradient-to-br from-sky-50 via-white to-sky-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-sky-800">Daily Total (Today)</span>
                        <span class="rounded-xl bg-sky-100 p-2 text-sky-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-950">₹{{ number_format((float) ($stats['todayTotalAmount'] ?? 0), 2) }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-sky-100/80 pt-2.5 text-xs text-slate-600">
                        <span>Base: <strong class="text-slate-800">₹{{ number_format((float) ($stats['todayBaseAmount'] ?? 0), 2) }}</strong></span>
                        <span class="text-sky-700 font-semibold">GST: ₹{{ number_format((float) ($stats['todayGstAmount'] ?? 0), 2) }}</span>
                    </div>
                </div>

                <!-- Monthly Total & GST -->
                <div class="relative overflow-hidden rounded-2xl border border-indigo-200 bg-gradient-to-br from-indigo-50 via-white to-indigo-50/40 p-5 shadow-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-800">Monthly Total ({{ now()->format('M Y') }})</span>
                        <span class="rounded-xl bg-indigo-100 p-2 text-indigo-700">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-3 text-2xl font-black text-slate-950">₹{{ number_format((float) ($stats['monthlyRevenue'] ?? 0), 2) }}</div>
                    <div class="mt-3 flex items-center justify-between border-t border-indigo-100/80 pt-2.5 text-xs text-slate-600">
                        <span>Base: <strong class="text-slate-800">₹{{ number_format((float) ($stats['monthlyBaseAmount'] ?? 0), 2) }}</strong></span>
                        <span class="text-indigo-700 font-semibold">GST: ₹{{ number_format((float) ($stats['monthlyGstAmount'] ?? 0), 2) }}</span>
                    </div>
                </div>
            </div>

            <!-- Subscription Status Pills summary (Clickable Filters) -->
            @php
                $currentStatusFilter = $clientReportFilters['status'] ?? '';
            @endphp
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'active' ? '' : 'active', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'active' ? 'border-emerald-500 bg-emerald-100/90 ring-2 ring-emerald-500/30' : 'border-emerald-200 bg-emerald-50/60 hover:bg-emerald-100/60' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Active Plans</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-emerald-950">{{ $stats['statusCounts']['active'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-emerald-700/80 mt-0.5">{{ $currentStatusFilter === 'active' ? '● Filtering active' : 'Click to filter' }}</div>
                </a>

                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'trialing' ? '' : 'trialing', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'trialing' ? 'border-purple-500 bg-purple-100/90 ring-2 ring-purple-500/30' : 'border-purple-200 bg-purple-50/60 hover:bg-purple-100/60' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-purple-600"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-purple-800">Trial</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-purple-950">{{ $stats['statusCounts']['trialing'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-purple-700/80 mt-0.5">{{ $currentStatusFilter === 'trialing' ? '● Filtering trial' : 'Click to filter' }}</div>
                </a>

                <!-- No Subscription Plan Card -->
                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'no_plan' ? '' : 'no_plan', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'no_plan' ? 'border-sky-500 bg-sky-100/90 ring-2 ring-sky-500/30' : 'border-sky-200 bg-sky-50/60 hover:bg-sky-100/60' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-sky-600"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-sky-800">No Plan</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-sky-950">{{ $stats['statusCounts']['no_plan'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-sky-700/80 mt-0.5">{{ $currentStatusFilter === 'no_plan' ? '● Filtering no plan' : 'Click to filter' }}</div>
                </a>

                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'expired' ? '' : 'expired', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'expired' ? 'border-slate-500 bg-slate-200 ring-2 ring-slate-400/30' : 'border-slate-200 bg-slate-50 hover:bg-slate-100/80' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-slate-500"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-700">Expired</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-slate-900">{{ $stats['statusCounts']['expired'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-slate-600 mt-0.5">{{ $currentStatusFilter === 'expired' ? '● Filtering expired' : 'Click to filter' }}</div>
                </a>

                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'pending' ? '' : 'pending', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'pending' ? 'border-amber-500 bg-amber-100/90 ring-2 ring-amber-500/30' : 'border-amber-200 bg-amber-50/60 hover:bg-amber-100/60' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-amber-800">Pending</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-amber-950">{{ $stats['statusCounts']['pending'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-amber-700/80 mt-0.5">{{ $currentStatusFilter === 'pending' ? '● Filtering pending' : 'Click to filter' }}</div>
                </a>

                <a href="{{ route('super-admin.dashboard', array_merge(request()->query(), ['client_status' => $currentStatusFilter === 'cancelled' ? '' : 'cancelled', 'clients_page' => 1])) }}#clients-section"
                   class="group rounded-xl border p-3.5 text-center transition shadow-2xs hover:shadow-sm {{ $currentStatusFilter === 'cancelled' ? 'border-rose-500 bg-rose-100/90 ring-2 ring-rose-500/30' : 'border-rose-200 bg-rose-50/60 hover:bg-rose-100/60' }}">
                    <div class="flex items-center justify-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-rose-800">Cancelled</span>
                    </div>
                    <div class="mt-1.5 text-2xl font-black text-rose-950">{{ $stats['statusCounts']['cancelled'] ?? 0 }}</div>
                    <div class="text-[10px] font-semibold text-rose-700/80 mt-0.5">{{ $currentStatusFilter === 'cancelled' ? '● Filtering cancelled' : 'Click to filter' }}</div>
                </a>
            </div>
        </section>

        <!-- Interactive Revenue & GST Charts Section -->
        <section class="grid gap-6 lg:grid-cols-3">
            <!-- 14-Day Daily Revenue & GST Bar/Line Chart -->
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-indigo-600">Trend Analysis</span>
                        <h4 class="text-lg font-bold text-slate-900">Daily Revenue & GST (Last 14 Days)</h4>
                    </div>
                    <div class="flex items-center gap-4 text-xs font-medium text-slate-600">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-full bg-indigo-600"></span> Total
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-full bg-emerald-500"></span> Base Plan
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-3 w-3 rounded-full bg-amber-500"></span> GST (18%)
                        </span>
                    </div>
                </div>
                <div class="mt-4 relative" style="height: 280px;">
                    <canvas id="financial-daily-chart"></canvas>
                </div>
            </div>

            <!-- Subscription Status Distribution Doughnut Chart -->
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-emerald-600">Status Ratio</span>
                    <h4 class="text-lg font-bold text-slate-900">Subscription Status</h4>
                </div>
                <div class="mt-4 relative flex items-center justify-center" style="height: 280px;">
                    <canvas id="financial-status-chart"></canvas>
                </div>
            </div>
        </section>

        <!-- Monthly Financial Comparison Chart & Plan Breakdown -->
        <section class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm lg:col-span-2">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                    <div>
                        <span class="text-xs font-semibold uppercase tracking-wider text-sky-600">Monthly Performance</span>
                        <h4 class="text-lg font-bold text-slate-900">Monthly Revenue & Tax Growth (Last 6 Months)</h4>
                    </div>
                    <div class="text-xs text-slate-500">
                        Monthly aggregated subscription collection
                    </div>
                </div>
                <div class="mt-4 relative" style="height: 260px;">
                    <canvas id="financial-monthly-chart"></canvas>
                </div>
            </div>

            <!-- Plan-wise Breakdown List -->
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="border-b border-slate-100 pb-4">
                    <span class="text-xs font-semibold uppercase tracking-wider text-purple-600">Active Plans Distribution</span>
                    <h4 class="text-lg font-bold text-slate-900">Revenue By Active Plan</h4>
                </div>
                <div class="mt-4 divide-y divide-slate-100 overflow-y-auto max-h-[260px]">
                    @forelse($stats['planBreakdown'] ?? [] as $p)
                        <div class="py-2.5 first:pt-0 last:pb-0">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-slate-900 text-sm">{{ $p['name'] }}</span>
                                <span class="font-bold text-slate-950 text-sm">₹{{ number_format($p['total_amount'], 2) }}</span>
                            </div>
                            <div class="mt-1 flex items-center justify-between text-xs text-slate-500">
                                <span>{{ $p['active_count'] }} active {{ str('subscription')->plural($p['active_count']) }}</span>
                                <span class="text-slate-600 font-mono">GST (18%): ₹{{ number_format($p['gst_amount'], 2) }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-xs text-slate-400">No active plan subscriptions found.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <!-- Clients with Total Earned Amount & Plan / Trial Details -->
        <section id="clients-section" class="scroll-mt-6 rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm" x-data="{
            licenseModalOpen: false,
            selectedClient: null,
            openLicenses(clientData) {
                this.selectedClient = clientData;
                this.licenseModalOpen = true;
            },
            closeLicenses() {
                this.licenseModalOpen = false;
                this.selectedClient = null;
            },
            copiedKey: null,
            copyToClipboard(text) {
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text);
                } else {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                }
                this.copiedKey = text;
                setTimeout(() => {
                    if (this.copiedKey === text) {
                        this.copiedKey = null;
                    }
                }, 2000);
            }
        }">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Financial & Plan Overview</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-950">Clients & Subscription Earnings</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        @if(($clientReportFilters['status'] ?? '') === 'active')
                            Showing clients with current <span class="font-semibold text-emerald-700">Active Plans</span> (newest companies first).
                        @elseif(($clientReportFilters['status'] ?? '') === 'trialing')
                            Showing clients with current <span class="font-semibold text-purple-700">Trial Plans</span> (newest companies first).
                        @elseif(($clientReportFilters['status'] ?? '') === 'no_plan')
                            Showing companies with <span class="font-semibold text-sky-700">No Subscription Plan Assigned</span> (newest companies first).
                        @elseif(($clientReportFilters['status'] ?? '') === 'expired')
                            Showing clients with <span class="font-semibold text-slate-700">Expired Plans</span> (newest companies first).
                        @elseif(($clientReportFilters['status'] ?? '') === 'pending')
                            Showing clients with <span class="font-semibold text-amber-700">Pending Plans</span> (newest companies first).
                        @elseif(($clientReportFilters['status'] ?? '') === 'cancelled')
                            Showing clients with <span class="font-semibold text-rose-700">Cancelled Plans</span> (newest companies first).
                        @else
                            Total earned amount per client with current plan details, start/end dates, and trial status (newest companies first).
                        @endif
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    @if(!empty($clientReportFilters['status']))
                        <a href="{{ route('super-admin.dashboard') }}#clients-section" class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition">
                            ✕ Clear Status Filter ({{ ucfirst($clientReportFilters['status']) }})
                        </a>
                    @endif
                    <span class="rounded-full bg-emerald-50 px-3.5 py-1.5 text-xs font-semibold text-emerald-800 border border-emerald-200">
                        Total Clients: {{ $clientsWithEarnings->total() }}
                    </span>
                    <span class="rounded-full bg-slate-100 px-3.5 py-1.5 text-xs font-semibold text-slate-700">
                        Total Earned: ₹{{ number_format((float) ($stats['totalEarnedAmount'] ?? 0), 2) }}
                    </span>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" action="{{ route('super-admin.dashboard') }}#clients-section" class="grid gap-3 border-b border-slate-200 py-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Search Client</label>
                    <input type="search" name="client_search" value="{{ $clientReportFilters['search'] ?? '' }}" placeholder="Name, code, email, phone..." class="block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Status Filter</label>
                    <select name="client_status" class="block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All Statuses</option>
                        <option value="active" @selected(($clientReportFilters['status'] ?? '') === 'active')>Active Plans ({{ $stats['statusCounts']['active'] ?? 0 }})</option>
                        <option value="trialing" @selected(($clientReportFilters['status'] ?? '') === 'trialing')>Trial ({{ $stats['statusCounts']['trialing'] ?? 0 }})</option>
                        <option value="no_plan" @selected(($clientReportFilters['status'] ?? '') === 'no_plan')>No Plan Assigned ({{ $stats['statusCounts']['no_plan'] ?? 0 }})</option>
                        <option value="expired" @selected(($clientReportFilters['status'] ?? '') === 'expired')>Expired Plans ({{ $stats['statusCounts']['expired'] ?? 0 }})</option>
                        <option value="pending" @selected(($clientReportFilters['status'] ?? '') === 'pending')>Pending ({{ $stats['statusCounts']['pending'] ?? 0 }})</option>
                        <option value="cancelled" @selected(($clientReportFilters['status'] ?? '') === 'cancelled')>Cancelled ({{ $stats['statusCounts']['cancelled'] ?? 0 }})</option>
                        <option value="inactive" @selected(($clientReportFilters['status'] ?? '') === 'inactive')>Inactive Clients</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Rows per page</label>
                    <select name="client_per_page" class="block w-full rounded-xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        @foreach([5, 10, 25, 50] as $opt)
                            <option value="{{ $opt }}" @selected((int) ($clientReportFilters['per_page'] ?? 10) === $opt)>{{ $opt }} per page</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                        Filter
                    </button>
                    @if(request()->filled('client_search') || request()->filled('client_status') || request()->filled('client_per_page'))
                        <a href="{{ route('super-admin.dashboard') }}#clients-section" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Reset
                        </a>
                    @endif
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-5 py-3.5 text-left">Client / Company</th>
                            <th class="px-5 py-3.5 text-left">Total Collected Amount</th>
                            <th class="px-5 py-3.5 text-left">Current Plan Details</th>
                            <th class="px-5 py-3.5 text-center">Total License Keys</th>
                            <th class="px-5 py-3.5 text-left">Status</th>
                            <th class="px-5 py-3.5 text-left">Start & End Dates</th>
                            <th class="px-5 py-3.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($clientsWithEarnings as $client)
                            @php
                                $subscription = $client->currentOrLatestSubscription();
                                $isTrial = $subscription ? ($subscription->is_trial || $subscription->status === 'trialing') : false;
                                $earned = (float) ($client->total_earned ?? 0);
                                $clientGstSplit = \App\Support\SubscriptionPricing::fromTotal($earned);

                                // Get all assigned/used license keys for this company's staff
                                $usedStaffKeys = $client->staff
                                    ->pluck('license_key')
                                    ->filter()
                                    ->map(fn ($k) => (string) $k)
                                    ->all();

                                // Collect license keys and all plans info for popup
                                $clientSubscriptionsData = [];
                                $totalLicenseKeysCount = 0;

                                foreach ($client->subscriptions as $sub) {
                                    $keys = $sub->licenseKeyList();
                                    $keyCount = count($keys);
                                    $totalLicenseKeysCount += $keyCount;

                                    $formattedKeys = [];
                                    $subUsedCount = 0;
                                    foreach ($keys as $k) {
                                        $isUsed = in_array((string) $k, $usedStaffKeys, true);
                                        if ($isUsed) {
                                            $subUsedCount++;
                                        }
                                        $formattedKeys[] = [
                                            'key' => (string) $k,
                                            'is_used' => $isUsed,
                                            'status_label' => $isUsed ? 'Used' : 'Available',
                                        ];
                                    }

                                    $isSubTrial = $sub->is_trial || $sub->status === 'trialing';
                                    $clientSubscriptionsData[] = [
                                        'id' => $sub->id,
                                        'plan_name' => $sub->plan?->name ?? 'Custom Plan',
                                        'status' => $isSubTrial ? 'Trial' : str($sub->status)->headline()->toString(),
                                        'is_trial' => $isSubTrial,
                                        'validity' => 'Started: ' . ($sub->starts_at?->format('M d, Y') ?? 'N/A') . ' • Valid till: ' . (($isSubTrial ? ($sub->trial_ends_at ?? $sub->ends_at) : $sub->ends_at)?->format('M d, Y') ?? 'Ongoing'),
                                        'starts_at' => $sub->starts_at?->format('M d, Y') ?? 'N/A',
                                        'ends_at' => ($isSubTrial ? ($sub->trial_ends_at ?? $sub->ends_at) : $sub->ends_at)?->format('M d, Y') ?? 'Ongoing',
                                        'billing_cycle' => $sub->billing_cycle ? ucfirst($sub->billing_cycle) : 'Monthly',
                                        'total_keys' => $keyCount,
                                        'used_keys' => $subUsedCount,
                                        'remaining_keys' => max(0, $keyCount - $subUsedCount),
                                        'license_keys' => $formattedKeys,
                                        'amount_paid' => (float) $sub->amount_paid,
                                    ];
                                }

                                $clientModalPayload = [
                                    'company_name' => $client->name,
                                    'company_code' => $client->company_code,
                                    'email' => $client->email,
                                    'total_license_keys' => $totalLicenseKeysCount,
                                    'subscriptions' => $clientSubscriptionsData,
                                ];
                            @endphp
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-slate-950">{{ $client->name }}</div>
                                    <div class="flex items-center gap-2 text-xs text-slate-500 mt-0.5">
                                        <span class="font-mono">{{ $client->company_code }}</span>
                                        <span>•</span>
                                        <span>{{ $client->email }}</span>
                                    </div>
                                    @if($client->phone_number || $client->mobile_number)
                                        <div class="text-xs text-slate-400 mt-0.5">
                                            {{ $client->mobile_number ?: $client->phone_number }}
                                        </div>
                                    @endif
                                </td>

                                <!-- Total Collected Amount -->
                                <td class="px-5 py-4">
                                    <div class="inline-flex items-baseline gap-1">
                                        <span class="text-base font-bold {{ $earned > 0 ? 'text-emerald-700' : 'text-slate-500' }}">
                                            ₹{{ number_format($earned, 2) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $earned > 0 ? 'All-time collected' : 'No payments yet' }}
                                    </div>
                                </td>

                                <td class="px-5 py-4">
                                    @if($subscription && $subscription->plan)
                                        <div class="font-semibold text-slate-900">{{ $subscription->plan->name }}</div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            Cycle: {{ $subscription->billing_cycle ? ucfirst($subscription->billing_cycle) : 'Standard' }}
                                        </div>
                                    @elseif($subscription)
                                        <div class="font-semibold text-slate-900">Custom Plan</div>
                                        <div class="text-xs text-slate-500 mt-0.5">
                                            Cycle: {{ $subscription->billing_cycle ? ucfirst($subscription->billing_cycle) : 'Standard' }}
                                        </div>
                                    @else
                                        <span class="text-xs font-medium text-slate-400">No Plan Assigned</span>
                                    @endif
                                </td>

                                <!-- Total License Key Column with Clickable Badge -->
                                <td class="px-5 py-4 text-center">
                                    @if($totalLicenseKeysCount > 0)
                                        <button type="button"
                                            @click="openLicenses(@js($clientModalPayload))"
                                            class="group inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50/80 px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-100 hover:border-indigo-300 shadow-2xs"
                                            title="Click to view license keys and plans details">
                                            <svg class="h-3.5 w-3.5 text-indigo-500 transition group-hover:scale-110" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                                            </svg>
                                            <span>{{ $totalLicenseKeysCount }} {{ str('Key')->plural($totalLicenseKeysCount) }}</span>
                                        </button>
                                    @else
                                        <span class="inline-flex items-center rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-400">
                                            0 Keys
                                        </span>
                                    @endif
                                </td>

                                <td class="px-5 py-4">
                                    @if($subscription)
                                        @php
                                            $isTrialActive = $isTrial || $subscription->status === 'trialing';
                                        @endphp
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                            {{ $isTrialActive ? 'bg-purple-100 text-purple-700' : ($subscription->status === 'active' ? 'bg-emerald-100 text-emerald-700' : ($subscription->status === 'pending' ? 'bg-amber-100 text-amber-700' : ($subscription->status === 'expired' ? 'bg-slate-200 text-slate-700' : 'bg-rose-100 text-rose-700'))) }}">
                                            {{ $isTrialActive ? 'Trial' : str($subscription->status)->headline() }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400">-</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-xs text-slate-700">
                                    @if($subscription)
                                        <div class="space-y-1">
                                            <div>
                                                <span class="font-semibold text-slate-500">Start Date:</span>
                                                <span class="font-medium text-slate-900">{{ $subscription->starts_at?->format('d M, Y') ?? '-' }}</span>
                                            </div>
                                            @if($isTrial)
                                                <div class="rounded-md bg-purple-50 px-2 py-1 text-purple-800">
                                                    <span class="font-bold">Trial Ends:</span>
                                                    <span>{{ ($subscription->trial_ends_at ?? $subscription->ends_at)?->format('d M, Y') ?? '-' }}</span>
                                                </div>
                                            @else
                                                <div>
                                                    <span class="font-semibold text-slate-500">End Date:</span>
                                                    <span class="font-medium text-slate-900">{{ $subscription->ends_at?->format('d M, Y') ?? 'Ongoing' }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>

                                <td class="px-5 py-4 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('super-admin.payments.history', $client) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition" title="View payment invoices and transactions">
                                            Payments
                                        </a>
                                        <a href="{{ route('super-admin.subscriptions', ['company_id' => $client->id]) }}" class="inline-flex items-center rounded-lg border border-indigo-200 bg-indigo-50 px-2.5 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition" title="Manage subscription">
                                            Manage
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-sm text-slate-500">
                                    No client records found matching the filters.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($clientsWithEarnings->hasPages())
                <div class="mt-4 border-t border-slate-200 pt-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between text-xs text-slate-500">
                    <div>
                        Showing {{ $clientsWithEarnings->firstItem() }} to {{ $clientsWithEarnings->lastItem() }} of {{ $clientsWithEarnings->total() }} clients
                    </div>
                    <div>
                        {{ $clientsWithEarnings->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif

            <!-- License Keys Modal Popup -->
            <div x-cloak x-show="licenseModalOpen"
                class="fixed inset-0 z-50 overflow-y-auto"
                aria-labelledby="modal-title" role="dialog" aria-modal="true"
                @keydown.escape.window="closeLicenses()">
                <!-- Backdrop -->
                <div x-show="licenseModalOpen"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-slate-950/50 backdrop-blur-xs transition-opacity"
                    @click="closeLicenses()"></div>

                <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                    <div x-show="licenseModalOpen"
                        x-transition:enter="ease-out duration-300"
                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave="ease-in duration-200"
                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                        class="relative w-full max-w-xl transform overflow-hidden rounded-[28px] border border-slate-200 bg-white text-left shadow-xl transition-all">

                        <!-- Modal Header -->
                        <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                            <div>
                                <h3 class="text-xl font-bold text-slate-950">Subscription Plan Details</h3>
                                <p class="mt-1 text-sm text-slate-500" x-text="selectedClient?.company_name">Company</p>
                            </div>
                            <button type="button" @click="closeLicenses()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700 transition" aria-label="Close modal">
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        <!-- Modal Body -->
                        <div class="space-y-6 px-6 py-6 max-h-[75vh] overflow-y-auto">
                            <template x-for="(sub, index) in (selectedClient?.subscriptions || [])" :key="sub.id || index">
                                <div class="space-y-4">
                                    <!-- Plan Header Banner Card -->
                                    <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <div class="text-lg font-bold text-slate-950" x-text="sub.plan_name">Enterprise</div>
                                            <div class="mt-1 text-xs text-slate-500" x-text="sub.validity">Started: Nov 16, 2026 • Valid till: Dec 16, 2026</div>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold"
                                                :class="{
                                                    'bg-purple-100 text-purple-700': sub.is_trial,
                                                    'bg-emerald-100 text-emerald-700': !sub.is_trial && sub.status.toLowerCase() === 'active',
                                                    'bg-amber-100 text-amber-700': !sub.is_trial && sub.status.toLowerCase() === 'pending',
                                                    'bg-slate-200 text-slate-700': !sub.is_trial && sub.status.toLowerCase() === 'expired',
                                                    'bg-rose-100 text-rose-700': !sub.is_trial && sub.status.toLowerCase() === 'cancelled'
                                                }"
                                                x-text="sub.is_trial ? 'Trial' : (sub.status.toLowerCase() === 'active' ? 'Paid' : sub.status)">
                                            </span>
                                            <span class="text-xs text-slate-500 font-medium" x-text="'(' + sub.billing_cycle + ')'"></span>
                                        </div>
                                    </div>

                                    <!-- 3 Metric Cards: TOTAL KEYS, USED KEYS, REMAINING -->
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-2xl border border-slate-200 bg-white p-3.5 text-center shadow-2xs">
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Keys</div>
                                            <div class="mt-1 text-2xl font-bold text-slate-900" x-text="sub.total_keys">1</div>
                                        </div>
                                        <div class="rounded-2xl border border-rose-200 bg-rose-50/40 p-3.5 text-center shadow-2xs">
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-rose-600">Used Keys</div>
                                            <div class="mt-1 text-2xl font-bold text-rose-700" x-text="sub.used_keys">0</div>
                                        </div>
                                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-3.5 text-center shadow-2xs">
                                            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Remaining</div>
                                            <div class="mt-1 text-2xl font-bold text-emerald-700" x-text="sub.remaining_keys">1</div>
                                        </div>
                                    </div>

                                    <!-- Key List Section with Copy & Available/Used Status -->
                                    <template x-if="sub.license_keys && sub.license_keys.length > 0">
                                        <div class="space-y-1.5 pt-1">
                                            <template x-for="item in sub.license_keys" :key="item.key">
                                                <div class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/70 px-3.5 py-2 text-xs">
                                                    <div class="flex items-center gap-2.5">
                                                        <span class="font-mono font-medium text-slate-800 tracking-wider" x-text="item.key"></span>
                                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wide"
                                                            :class="item.is_used ? 'bg-amber-100 text-amber-800 border border-amber-200' : 'bg-emerald-100 text-emerald-800 border border-emerald-200'"
                                                            x-text="item.status_label">
                                                        </span>
                                                    </div>
                                                    <button type="button"
                                                        @click="copyToClipboard(item.key)"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100 transition shadow-2xs">
                                                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3" />
                                                        </svg>
                                                        <span x-text="copiedKey === item.key ? 'Copied!' : 'Copy'"></span>
                                                    </button>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div class="flex justify-end pt-2">
                                <button type="button" @click="closeLicenses()" class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Dashboard Insight</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-950">Company App Usage</h3>
                    <p class="mt-1 text-sm text-slate-500">Monitor which companies are active on the mobile app and how their staff are using it.</p>
                </div>
                <a href="{{ route('super-admin.company-app-usage.index') }}" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    View Full Report
                </a>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-sky-50 p-4 ring-1 ring-sky-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Total Companies</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_companies'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Companies Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['companies_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Companies Not Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['companies_not_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Total Staff</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_staff'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-cyan-50 p-4 ring-1 ring-cyan-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-700">Total Staff Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_staff_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-violet-50 p-4 ring-1 ring-violet-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-violet-700">Active App Users Today</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_today'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-4 ring-1 ring-slate-200">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700">Active in Last 7 Days</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_7_days'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-4 ring-1 ring-slate-200">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700">Active in Last 30 Days</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_30_days'] ?? 0 }}</div>
                </div>
            </div>

            <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Company</th>
                            <th class="px-4 py-3 text-left">Plan</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Active Today</th>
                            <th class="px-4 py-3 text-left">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($companyAppUsageHighlights ?? [] as $company)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-950">{{ $company->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $company->company_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $company->latestSubscription?->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    @php
                                        $compSubStatus = $company->latestSubscription?->status ?? 'none';
                                        $isCompTrial = (bool) ($company->latestSubscription?->is_trial || $compSubStatus === 'trialing');
                                    @endphp
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $compSubStatus === 'active' && !$isCompTrial ? 'bg-emerald-100 text-emerald-700' : ($isCompTrial ? 'bg-purple-100 text-purple-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ $isCompTrial ? 'Trial' : str($compSubStatus)->headline() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-950">{{ $company->active_today ?? 0 }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ filled($company->last_app_activity) ? \Illuminate\Support\Carbon::parse($company->last_app_activity)->format('d M Y, h:i A') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No company app usage data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const financialData = @js($financialCharts ?? []);

                // 1. Daily 14-Day Revenue & GST Chart
                const dailyCtx = document.getElementById('financial-daily-chart');
                if (dailyCtx && financialData.daily) {
                    new Chart(dailyCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: financialData.daily.labels || [],
                            datasets: [
                                {
                                    label: 'Base Plan (₹)',
                                    data: financialData.daily.base || [],
                                    backgroundColor: 'rgba(16, 185, 129, 0.75)',
                                    borderColor: '#10b981',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    order: 2,
                                },
                                {
                                    label: 'GST 18% (₹)',
                                    data: financialData.daily.gst || [],
                                    backgroundColor: 'rgba(245, 158, 11, 0.75)',
                                    borderColor: '#f59e0b',
                                    borderWidth: 1,
                                    borderRadius: 4,
                                    order: 2,
                                },
                                {
                                    type: 'line',
                                    label: 'Total Collected (₹)',
                                    data: financialData.daily.total || [],
                                    borderColor: '#4f46e5',
                                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                    borderWidth: 2.5,
                                    pointBackgroundColor: '#4f46e5',
                                    pointRadius: 3,
                                    pointHoverRadius: 5,
                                    fill: false,
                                    tension: 0.3,
                                    order: 1,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            interaction: {
                                mode: 'index',
                                intersect: false,
                            },
                            plugins: {
                                legend: {
                                    display: false
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return `${context.dataset.label}: ₹${Number(context.raw || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: {
                                        display: false
                                    },
                                    ticks: {
                                        font: { size: 11 }
                                    }
                                },
                                y: {
                                    beginAtZero: true,
                                    grid: {
                                        color: '#f1f5f9'
                                    },
                                    ticks: {
                                        font: { size: 11 },
                                        callback: function(value) {
                                            return '₹' + Number(value).toLocaleString('en-IN');
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // 2. Subscription Status Distribution Doughnut Chart
                const statusCtx = document.getElementById('financial-status-chart');
                if (statusCtx && financialData.status) {
                    const statusValues = financialData.status.values || [];
                    const hasStatusData = statusValues.some(v => v > 0);

                    new Chart(statusCtx.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: financialData.status.labels || ['Active', 'Trial', 'No Plan', 'Expired', 'Pending', 'Cancelled'],
                            datasets: [{
                                data: hasStatusData ? statusValues : [1],
                                backgroundColor: hasStatusData ? [
                                    '#10b981', // active
                                    '#8b5cf6', // trial
                                    '#0284c7', // no plan
                                    '#64748b', // expired
                                    '#f59e0b', // pending
                                    '#ef4444', // cancelled
                                ] : ['#e2e8f0'],
                                borderWidth: 2,
                                borderColor: '#ffffff',
                                hoverOffset: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '68%',
                            plugins: {
                                legend: {
                                    position: 'bottom',
                                    labels: {
                                        boxWidth: 12,
                                        font: { size: 11 },
                                        padding: 12
                                    }
                                },
                                tooltip: {
                                    enabled: hasStatusData,
                                    callbacks: {
                                        label: function (context) {
                                            return ` ${context.label}: ${context.raw} subscriptions`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                }

                // 3. Monthly Financial Growth Bar Chart
                const monthlyCtx = document.getElementById('financial-monthly-chart');
                if (monthlyCtx && financialData.monthly) {
                    new Chart(monthlyCtx.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: financialData.monthly.labels || [],
                            datasets: [
                                {
                                    label: 'Base Revenue (₹)',
                                    data: financialData.monthly.base || [],
                                    backgroundColor: '#0284c7',
                                    borderRadius: 6,
                                },
                                {
                                    label: 'GST Split (₹)',
                                    data: financialData.monthly.gst || [],
                                    backgroundColor: '#a855f7',
                                    borderRadius: 6,
                                }
                            ]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'top',
                                    align: 'end',
                                    labels: {
                                        boxWidth: 12,
                                        font: { size: 11 }
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function (context) {
                                            return `${context.dataset.label}: ₹${Number(context.raw || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    stacked: true,
                                    grid: { display: false },
                                    ticks: { font: { size: 11 } }
                                },
                                y: {
                                    stacked: true,
                                    beginAtZero: true,
                                    grid: { color: '#f1f5f9' },
                                    ticks: {
                                        font: { size: 11 },
                                        callback: function(value) {
                                            return '₹' + Number(value).toLocaleString('en-IN');
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
