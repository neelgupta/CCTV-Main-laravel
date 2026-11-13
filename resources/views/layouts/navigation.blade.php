<nav x-data="{ open: false }" class="relative">
    @php
        $authUser = Auth::user();
        $companyLogoUrl = $authUser?->company?->logo_url;
        $sidebarBrandName = $authUser?->company?->name ?? config('app.name', 'CCTV Workshop');
        $canManageCompanySettings = $authUser?->canManageCompanySettings();
        $sidebarLogoLabel = $sidebarBrandName . ' Logo';

        $navLinks = [];

        if ($authUser && $authUser->isSuperAdmin()) {
            $navLinks = [
                [
                    'label' => 'Dashboard',
                    'route' => route('super-admin.dashboard'),
                    'active' => request()->routeIs('super-admin.dashboard'),
                    'icon' => 'M4 13h6v7H4v-7zm10-8h6v15h-6V5zM4 3h6v8H4V3zm10 12h6v3h-6v-3z',
                ],
                [
                    'label' => 'Inquiries',
                    'route' => route('super-admin.inquiries.index'),
                    'active' => request()->routeIs('super-admin.inquiries.*'),
                    'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                ],
                [
                    'label' => 'Live Analytics',
                    'route' => route('superadmin.live-analytics'),
                    'active' => request()->routeIs('superadmin.live-analytics*'),
                    'icon' => 'M4 19h16M6 16l3-4 3 3 4-6 2 3',
                ],
                [
                    'label' => 'Locations',
                    'route' => route('super-admin.locations'),
                    'active' => request()->routeIs('super-admin.locations') || request()->routeIs('super-admin.states.*') || request()->routeIs('super-admin.cities.*'),
                    'icon' => 'M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11zm0-8a3 3 0 100-6 3 3 0 000 6z',
                ],
                [
                    'label' => 'Subscriptions',
                    'route' => route('super-admin.subscriptions'),
                    'active' => request()->routeIs('super-admin.subscriptions') || request()->routeIs('super-admin.plans.*'),
                    'icon' => 'M12 3v18M3 12h18',
                ],
                [
                    'label' => 'Companies',
                    'route' => route('super-admin.companies'),
                    'active' => request()->routeIs('super-admin.companies*') && ! request()->routeIs('super-admin.companies.inactive'),
                    'icon' => 'M4 7h16M4 12h16M4 17h16',
                ],
                [
                    'label' => 'Inactive Companies',
                    'route' => route('super-admin.companies.inactive'),
                    'active' => request()->routeIs('super-admin.companies.inactive'),
                    'icon' => 'M18 8a6 6 0 10-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4',
                ],
                [
                    'label' => 'Payments',
                    'route' => route('super-admin.payments'),
                    'active' => request()->routeIs('super-admin.payments'),
                    'icon' => 'M12 3v18M3 12h18',
                ],
                [
                    'label' => 'Backup Settings',
                    'route' => route('super-admin.database-backups.index'),
                    'active' => request()->routeIs('super-admin.database-backups.*'),
                    'icon' => 'M4 7a2 2 0 012-2h12a2 2 0 012 2v10a2 2 0 01-2 2H6a2 2 0 01-2-2V7zm2 0h12v2H6V7zm0 4h12v6H6v-6zm2 1.5a1 1 0 100 2 1 1 0 000-2z',
                ],
                [
                    'label' => 'Integrations',
                    'route' => route('super-admin.integrations'),
                    'active' => request()->routeIs('super-admin.integrations'),
                    'icon' => 'M13 10V3L4 14h7v7l9-11h-7z',
                ],
                [
                    'label' => 'Settings',
                    'route' => route('super-admin.settings.index'),
                    'active' => request()->routeIs('super-admin.settings.*'),
                    'icon' => 'M12 8.25a3.75 3.75 0 100 7.5 3.75 3.75 0 000-7.5zm8.25 3.75a6.75 6.75 0 00-.15-1.44l2.03-1.58-2-3.46-2.47 1a6.88 6.88 0 00-2.49-1.44l-.37-2.64H9.24l-.37 2.64a6.88 6.88 0 00-2.49 1.44l-2.47-1-2 3.46 2.03 1.58A6.75 6.75 0 004.5 12c0 .49.05.97.15 1.44l-2.03 1.58 2 3.46 2.47-1c.75.6 1.58 1.1 2.49 1.44l.37 2.64h5.52l.37-2.64c.91-.34 1.74-.84 2.49-1.44l2.47 1 2-3.46-2.03-1.58c.1-.47.15-.95.15-1.44z',
                ],

            ];
        } else {
            $navLinks[] = [
                'label' => 'Dashboard',
                'route' => route('dashboard'),
                'active' => request()->routeIs('dashboard'),
                'icon' => 'M4 13h6v7H4v-7zm10-8h6v15h-6V5zM4 3h6v8H4V3zm10 12h6v3h-6v-3z',
            ];
        }

        if ($authUser && ! $authUser->isSuperAdmin()) {
            if ($authUser->hasPermission('leads.view')) {
                $navLinks[] = [
                    'label' => 'Lead',
                    'route' => route('leads.index'),
                    'active' => request()->routeIs('leads.*') && ! request()->routeIs('appointments.*'),
                    'icon' => 'M12 20a8 8 0 10-8-8 8 8 0 008 8zm0-11a3 3 0 110 6 3 3 0 010-6zm-6.5 9.5A6.5 6.5 0 0118.5 18',
                ];
            }

            if ($authUser->company?->allow_appointment_module ?? false) {
                $navLinks[] = [
                    'label' => 'Appointments',
                    'route' => route('appointments.index'),
                    'active' => request()->routeIs('appointments.*'),
                    'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                ];
            }

            if ($authUser->hasPermission('projects.view')) {
                $navLinks[] = [
                    'label' => 'Project',
                    'route' => route('projects.index'),
                    'active' => request()->routeIs('projects.*'),
                    'icon' => 'M4 7h16M4 12h16M4 17h16M7 4v6M17 10V4',
                ];
            }

            

            if ($authUser->hasPermission('amc.view')) {
                $navLinks[] = [
                    'label' => 'AMC',
                    'route' => route('amcs.index'),
                    'active' => request()->routeIs('amcs.*') || request()->routeIs('amc-plans.*'),
                    'icon' => 'M12 2l3.2 6.5 7.2 1-5.2 5.1 1.2 7.2L12 18.9 5.6 21.8l1.2-7.2L1.6 9.5l7.2-1L12 2z',
                ];
            }

            $salesChildren = [];

            if ($authUser->hasAnyPermission('support.view', 'tickets.view', 'tickets.reply', 'tickets.manage')) {
                $salesChildren[] = [
                    'label' => 'Invoice',
                    'route' => route('service-call.invoices.index'),
                    'active' => request()->routeIs('service-call.invoices.*'),
                ];
            }

            if ($authUser->hasPermission('leads.quotation.manage')) {
                $salesChildren[] = [
                    'label' => 'Quotation',
                    'route' => route('service-call.quotations.index'),
                    'active' => request()->routeIs('service-call.quotations.*'),
                ];
            }

            if ($authUser->hasPermission('subscription.view')) {
                $salesChildren[] = [
                    'label' => 'Subscription',
                    'route' => route('subscription.show'),
                    'active' => request()->routeIs('subscription.*'),
                ];
            }

            if (($authUser->company?->enable_boq ?? true) && ($authUser->hasPermission('boq.categories.manage') || $authUser->hasPermission('boq.products.manage'))) {
                $salesChildren[] = [
                    'label' => 'Product',
                    'route' => route('settings.boq.index'),
                    'active' => request()->routeIs('settings.boq.*'),
                ];
            }

            $salesActive = collect($salesChildren)->contains(fn (array $child) => (bool) ($child['active'] ?? false));

            if ($salesChildren !== []) {
                $navLinks[] = [
                    'label' => 'Sales',
                    'active' => $salesActive,
                    'expanded' => $salesActive,
                    'icon' => 'M4 19V5M4 19h16M8 15l3-3 3 2 4-5',
                    'children' => $salesChildren,
                ];
            }
        }

        if ($authUser && ! $authUser->isSuperAdmin()) {
            if ($authUser->hasAnyPermission('tasks.view', 'tasks.manage') || $authUser->staff) {
                $navLinks[] = [
                    'label' => 'Task Management',
                    'route' => route('task-management.index'),
                    'active' => request()->routeIs('task-management.*'),
                    'icon' => 'M4 7h16M4 12h16M4 17h10M7 4v6M17 10V4',
                ];
            }

            $ticketAccess = $authUser->hasPermission('support.view')
                || $authUser->hasPermission('support.create');
            $serviceCallChildren = [];

            if ($ticketAccess) {
                $serviceCallChildren[] = [
                    'label' => 'Support',
                    'route' => route('tickets.index'),
                    'active' => request()->routeIs('tickets.*'),
                ];
            }

            $serviceCallActive = collect($serviceCallChildren)->contains(fn (array $child) => (bool) ($child['active'] ?? false));

            if ($serviceCallChildren !== []) {
                $navLinks[] = [
                    'label' => 'Service Call',
                    'active' => $serviceCallActive,
                    'expanded' => $serviceCallActive,
                    'icon' => 'M5 5h14M5 10h14M5 15h14M5 20h8',
                    'children' => $serviceCallChildren,
                ];
            }
        }

        if ($authUser && ! $authUser->isSuperAdmin() && $authUser->hasPermission('reports.view')) {
            $reportsActive = request()->routeIs('reports.*');

            $navLinks[] = [
                'label' => 'Reports',
                'active' => $reportsActive,
                'expanded' => $reportsActive,
                'icon' => 'M5 5v14M12 9v10M19 3v16M3 19h18',
                'children' => [
                    [
                        'label' => 'Leads Report',
                        'route' => route('reports.leads'),
                        'active' => request()->routeIs('reports.leads'),
                    ],
                    [
                        'label' => 'Project Report',
                        'route' => route('reports.projects'),
                        'active' => request()->routeIs('reports.projects'),
                    ],
                    [
                        'label' => 'AMC Report',
                        'route' => route('reports.amc'),
                        'active' => request()->routeIs('reports.amc'),
                    ],
                    [
                        'label' => 'Payment Report',
                        'route' => route('reports.payments'),
                        'active' => request()->routeIs('reports.payments'),
                    ],
                    [
                        'label' => 'Users Report',
                        'route' => route('reports.users'),
                        'active' => request()->routeIs('reports.users'),
                    ],
                    [
                        'label' => 'Service Report',
                        'route' => route('reports.services'),
                        'active' => request()->routeIs('reports.services'),
                    ],
                ],
            ];
        }

        if ($authUser && ! $authUser->isSuperAdmin()) {
            $usersChildren = [];

            if ($authUser->hasPermission('teams.staff.view') || $authUser->hasPermission('teams.staff.self.manage')) {
                $usersChildren[] = [
                    'label' => 'Staff',
                    'route' => route('teams.staff.index'),
                    'active' => request()->routeIs('teams.*') || request()->routeIs('attendance.*'),
                ];
            }

            if ($authUser->hasPermission('settings.roles.manage')) {
                $usersChildren[] = [
                    'label' => 'Roles',
                    'route' => route('settings.roles.index'),
                    'active' => request()->routeIs('settings.roles.*'),
                ];
            }

            if ($authUser->hasPermission('gallery.view')) {
                $usersChildren[] = [
                    'label' => 'Gallery',
                    'route' => route('settings.gallery.index'),
                    'active' => request()->routeIs('settings.gallery.*'),
                ];
            }

            if ($authUser->hasPermission('clients.view')) {
                $usersChildren[] = [
                    'label' => 'Clients',
                    'route' => route('settings.clients.index'),
                    'active' => request()->routeIs('settings.clients.*'),
                ];
            }

            if ($authUser->hasPermission('integrations.view')) {
                $usersChildren[] = [
                    'label' => 'Integrations',
                    'route' => route('integrations.index'),
                    'active' => request()->routeIs('integrations.*'),
                ];
            }

            $usersActive = collect($usersChildren)->contains(fn (array $child) => (bool) ($child['active'] ?? false));

            if ($usersChildren !== []) {
                $navLinks[] = [
                    'label' => 'Users',
                    'active' => $usersActive,
                    'expanded' => $usersActive,
                    'icon' => 'M17 20h5v-2a4 4 0 00-5-3.87M12 14a4 4 0 100-8 4 4 0 000 8zM7 20H2v-2a4 4 0 015-3.87m10-1.13a3 3 0 10-6 0 3 3 0 006 0z',
                    'children' => $usersChildren,
                ];
            }
        }

        if ($authUser && ! $authUser->isSuperAdmin() && $authUser->hasAnyPermission('accounting.dashboard.view', 'accounting.incomes.view', 'accounting.expenses.view', 'accounting.categories.manage', 'accounting.reports.view')) {
            $accountingActive = request()->routeIs('accounting.*');

            $navLinks[] = [
                'label' => 'Utilities',
                'active' => $accountingActive,
                'expanded' => $accountingActive,
                'icon' => 'M12 3v18m-6-6h12M7 6h10M7 12h10M7 18h10',
                'children' => [
                    [
                        'label' => 'Dashboard',
                        'route' => route('accounting.dashboard'),
                        'active' => request()->routeIs('accounting.dashboard'),
                    ],
                    [
                        'label' => 'Income',
                        'route' => route('accounting.incomes.index'),
                        'active' => request()->routeIs('accounting.incomes.*'),
                    ],
                    [
                        'label' => 'Expense',
                        'route' => route('accounting.expenses.index'),
                        'active' => request()->routeIs('accounting.expenses.*'),
                    ],
                    [
                        'label' => 'Categories',
                        'route' => route('accounting.categories.index'),
                        'active' => request()->routeIs('accounting.categories.*'),
                    ],
                    [
                        'label' => 'Reports',
                        'route' => route('accounting.reports.index'),
                        'active' => request()->routeIs('accounting.reports.*'),
                    ],
                ],
            ];
        }

    @endphp

    <aside class="hidden lg:fixed lg:inset-y-0 lg:flex lg:w-72 lg:flex-col">
        <div class="flex h-full flex-col border-r border-slate-200 bg-white/95 text-slate-700 shadow-[0_20px_70px_rgba(79,22,29,0.08)] backdrop-blur-xl">
            <div class="flex items-center border-b border-slate-200">
                <a href="{{ $authUser && $authUser->isSuperAdmin() ? route('super-admin.dashboard') : route('dashboard') }}" class="flex w-full items-center">
                    @if($companyLogoUrl)
                        <div class="flex h-20 w-full items-center justify-center px-6 py-4">
                            <img src="{{ $companyLogoUrl }}"
                                alt="{{ $sidebarLogoLabel }}"
                                class="max-h-full w-full object-contain"
                                loading="eager"
                                decoding="async">
                        </div>
                    @else
                        <div class="flex items-center gap-3 px-6 py-5">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden">
                                <x-application-logo class="max-h-11 max-w-11 object-contain" />
                            </div>
                            <div>
                                <div class="text-sm font-extrabold uppercase tracking-[0.18em] text-slate-800">{{ $sidebarBrandName }}</div>
                                <div class="text-xs text-slate-500">Enterprise Workspace</div>
                            </div>
                        </div>
                    @endif
                </a>
            </div>

            <div class="px-4 py-5">
                @if(session()->has('super_admin_impersonator_id') && $authUser && ! $authUser->isSuperAdmin())
                    <form method="POST" action="{{ route('super-admin.return') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center  border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-[#C22032] transition hover:bg-rose-100">
                            Return to Super Admin
                        </button>
                    </form>
                @endif
                {{-- <div class="rounded-3xl bg-white/5 p-4 ring-1 ring-white/10">
                    <div class="text-xs uppercase tracking-[0.24em] text-slate-400">Signed in as</div>
                    <div class="mt-2 text-sm font-semibold text-white">{{ $authUser?->name }}</div>
                    <div class="text-xs text-slate-400">{{ $authUser?->email }}</div>
                </div> --}}
            </div>

            <nav class="flex-1 overflow-y-auto px-3.5 pb-4">
                <div class="space-y-1.5">
                    @include('layouts.partials.sidebar-nav-links')
                </div>

            </nav>

            <!-- <div class="border-t border-gray-200 p-4">
                <a href="{{ route('profile.edit') }}" class="flex items-center justify-between  bg-gray-50 px-4 py-3 text-sm font-medium text-gray-600 ring-1 ring-gray-200 transition-all duration-250 hover:bg-red-50 hover:text-[#c01917] hover:ring-red-100">
                    <span>Profile Settings</span>
                </a>
                <form method="POST" action="{{ route('logout') }}" class="mt-3">
                    @csrf
                    <button type="submit" class="flex w-full items-center justify-center  bg-rose-500 px-4 py-3 text-sm font-semibold text-white shadow-sm shadow-rose-900/20 transition-all duration-250 hover:-translate-y-0.5 hover:bg-rose-400 hover:shadow-md">
                        Log Out
                    </button>
                </form>
            </div> -->
        </div>
    </aside>

    <div class="lg:hidden" x-on:toggle-mobile-nav.window="open = !open">
        <div class="fixed inset-x-0 top-0 z-50 border-b border-slate-200 bg-white/95 backdrop-blur-xl">
            <div class="flex items-center justify-between px-4 py-3 sm:px-6">
                <a href="{{ $authUser && $authUser->isSuperAdmin() ? route('super-admin.dashboard') : route('dashboard') }}" class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden">
                        @if($companyLogoUrl)
                            <img src="{{ $companyLogoUrl }}" alt="{{ $sidebarLogoLabel }}" class="h-7 w-7 rounded-lg object-contain" loading="eager" decoding="async">
                        @else
                            <x-application-logo class="max-h-10 max-w-10 object-contain" />
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-bold text-slate-800">{{ $sidebarBrandName }}</div>
                        <div class="text-xs text-slate-500">Enterprise Workspace</div>
                    </div>
                </a>

                <div class="flex items-center gap-2">
                    <x-support-menu compact />
                    <button @click="open = !open" class="inline-flex items-center justify-center  border border-slate-200 bg-white px-3 py-2 text-slate-700 shadow-lg shadow-black/5 transition hover:bg-rose-50 hover:text-[#C22032]">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div x-show="open" x-transition.opacity class="fixed inset-0 z-40 bg-slate-900/30" style="display:none" @click="open=false"></div>
        <div x-show="open" x-transition class="fixed inset-y-0 left-0 z-50 w-80 max-w-[85vw] bg-white text-slate-900 shadow-2xl" style="display:none">
            <div class="flex h-full flex-col">
                <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-5">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden">
                        @if($companyLogoUrl)
                            <img src="{{ $companyLogoUrl }}" alt="{{ $sidebarLogoLabel }}" class="h-8 w-8 rounded-xl object-contain" loading="eager" decoding="async">
                        @else
                            <x-application-logo class="max-h-11 max-w-11 object-contain" />
                        @endif
                    </div>
                    <div>
                        <div class="text-sm font-extrabold uppercase tracking-[0.18em] text-slate-800">{{ $sidebarBrandName }}</div>
                        <div class="text-xs text-slate-500">{{ $authUser?->name }}</div>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-3 py-4">
                    <div class="mb-4 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                        <div class="text-xs uppercase tracking-[0.24em] text-slate-500">Quick actions</div>
                        <div class="mt-4 grid grid-cols-2 gap-2">
                            @if(session()->has('super_admin_impersonator_id') && $authUser && ! $authUser->isSuperAdmin())
                                <form method="POST" action="{{ route('super-admin.return') }}" class="col-span-2">
                                    @csrf
                                    <button type="submit" class="inline-flex w-full items-center justify-center  border border-amber-200 bg-amber-50 px-3 py-3 text-sm font-semibold text-amber-700 transition hover:bg-amber-100">
                                        Return to Super Admin
                                    </button>
                                </form>
                            @endif
                            <button
                                type="button"
                                data-pwa-install-trigger
                                class="inline-flex items-center justify-center gap-2  bg-[#C22032] px-3 py-3 text-sm font-semibold text-white transition hover:bg-[#d63384]"
                                hidden
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M4 7a3 3 0 013-3h10a3 3 0 013 3v10a3 3 0 01-3 3H7a3 3 0 01-3-3V7z" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 8v8M8 12h8" stroke-linecap="round" />
                                </svg>
                                Install
                            </button>
                            <button type="button" class="inline-flex items-center justify-center gap-2  border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-rose-50 hover:text-[#C22032]">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 17a3 3 0 006 0" stroke-linecap="round"/></svg>
                                Alerts
                            </button>
                            <a href="{{ route('profile.edit') }}" @click="open=false" class="inline-flex items-center justify-center  border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-rose-50 hover:text-[#C22032]">
                                Profile
                            </a>
                            @if($canManageCompanySettings)
                                <a href="{{ route('settings.company.index') }}" @click="open=false" class="inline-flex items-center justify-center  border border-slate-200 bg-white px-3 py-3 text-sm font-semibold text-slate-700 transition hover:bg-rose-50 hover:text-[#C22032]">
                                    Settings
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="space-y-1">
                        @include('layouts.partials.sidebar-nav-links', ['mobile' => true])
                    </div>
                </div>

                <div class="border-t border-slate-200 bg-slate-50/70 p-4">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-[#C22032] px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#a61413]">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            Log Out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</nav>
