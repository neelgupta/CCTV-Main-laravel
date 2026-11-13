<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $appName = config('app.name', 'We4Work');
            $themeColor = '#C22032';
            $layoutUser = auth()->user();
            $layoutCompany = $layoutUser?->company;
            $canManageCompanySettings = $layoutUser?->canManageCompanySettings();
            $subscriptionLimitService = ($layoutUser && ! $layoutUser->isSuperAdmin() && $layoutUser->company_id)
                ? app(\App\Services\SubscriptionLimitService::class)
                : null;
            $expiredPlanMessage = $subscriptionLimitService?->expiredPlanMessage($layoutUser?->company_id);
            $showExpiredPlanBanner = $expiredPlanMessage
                && request()->routeIs('teams.*', 'attendance.*', 'subscription.*');
            $isSetupWarningExemptRoute = request()->routeIs('subscription.*', 'profile.*', 'settings.company.*', 'settings.letterhead.*', 'onboarding.*');
            $hasMissingProfileDetails = $layoutCompany && ! $layoutCompany->hasRequiredProfileDetails();
            $hasNoActivePlan = $subscriptionLimitService
                && ! $subscriptionLimitService->companyHasActivePlan($layoutCompany);
            $showRequiredSetupPopup = ! $isSetupWarningExemptRoute
                && ($hasMissingProfileDetails || $hasNoActivePlan)
                && ! request()->routeIs('settings.company.*')
                && ! request()->routeIs('settings.letterhead.*')
                && ! ($layoutCompany && $layoutCompany->hasRequiredProfileDetails());
            $browserIcon = asset('pwa/icon-192.png');
            $browserIconPath = public_path('pwa/icon-192.png');
            $browserIconVersion = is_file($browserIconPath) ? filemtime($browserIconPath) : '1';
            $routeName = request()->route()?->getName();
            $pageTitle = match (true) {
                request()->routeIs('dashboard') => 'Dashboard',
                request()->routeIs('super-admin.dashboard') => 'Super Admin Dashboard',
                request()->routeIs('super-admin.locations') => 'Locations',
                request()->routeIs('super-admin.settings.*') => 'Settings',
                request()->routeIs('super-admin.subscriptions') || request()->routeIs('super-admin.plans.*') => 'Subscriptions',
                request()->routeIs('super-admin.companies.create') => 'Register Company',
                request()->routeIs('super-admin.companies') => 'Companies',
                request()->routeIs('super-admin.companies.inactive') => 'Inactive Companies',
                request()->routeIs('super-admin.company-app-usage.*') => 'Company App Usage',
                request()->routeIs('super-admin.payments') => 'Payments',
                request()->routeIs('super-admin.database-backups.*') => 'Backup Settings',
                request()->routeIs('super-admin.payments.history') => 'Payment History',
                request()->routeIs('superadmin.live-analytics*') => 'Live Analytics',
                request()->routeIs('super-admin.*') => 'Super Admin',
                request()->routeIs('leads.*') => 'Leads',
                request()->routeIs('projects.*') => 'Projects',
                request()->routeIs('reports.leads') => 'Leads Report',
                request()->routeIs('reports.projects') => 'Project Report',
                request()->routeIs('reports.amc') => 'AMC Report',
                request()->routeIs('reports.payments') => 'Payment Report',
                request()->routeIs('reports.users') => 'Users Report',
                request()->routeIs('reports.services') => 'Service Report',
                request()->routeIs('reports.*') => 'Reports',
                request()->routeIs('accounting.*') => 'Accounting',
                request()->routeIs('amcs.*') || request()->routeIs('amc-plans.*') => 'AMC',
                request()->routeIs('teams.*') || request()->routeIs('attendance.*') => 'Staff',
                request()->routeIs('settings.roles.*') => 'Roles',
                request()->routeIs('settings.users.*') => 'Users',
                request()->routeIs('subscription.*') => 'Subscription',
                request()->routeIs('settings.company.*') => 'Company Settings',
                request()->routeIs('settings.daily-staff-update.*') => 'Daily Staff Update',
                request()->routeIs('settings.letterhead.*') => 'PDF Letterhead',
                request()->routeIs('tickets.*') => 'Supports',
                request()->routeIs('marketing.campaigns.*') => 'Campaigns',
                request()->routeIs('marketing.email-template', 'marketing.email-template.add', 'marketing.email-template.edit', 'marketing.email-template.preview', 'marketing.email-template.categories.*', 'marketing.email-template.sender-config.*') => 'Email Template',
                // request()->routeIs('marketing.whatsapp') => 'WhatsApp',
                request()->routeIs('services.*') => 'Service',
                request()->routeIs('settings.gallery.*') => 'Gallery',
                request()->routeIs('settings.clients.*') => 'Clients',
                request()->routeIs('settings.boq.*') => 'Products',
                request()->routeIs('profile.*') => 'Profile Settings',
                request()->routeIs('task-management.*')=>'Task Management',
                default => $appName,
            };

            $moduleSearchOptions = [
                ['keywords' => ['dashboard', 'home'], 'label' => 'Dashboard', 'route' => route('dashboard')],
                ['keywords' => ['lead', 'leads', 'crm'], 'label' => 'Leads', 'route' => route('leads.index')],
                ['keywords' => ['amc plan', 'amc plans', 'amc-plan', 'amc-plans'], 'label' => 'AMC Plans', 'route' => route('amc-plans.index')],
                ['keywords' => ['amc', 'amcs'], 'label' => 'AMC', 'route' => route('amcs.index')],
                ['keywords' => ['project', 'projects'], 'label' => 'Projects', 'route' => route('projects.index')],
                ['keywords' => ['report', 'reports'], 'label' => 'Reports', 'route' => route('reports.index')],
                ['keywords' => ['accounting', 'income', 'expense', 'account'], 'label' => 'Accounting', 'route' => route('accounting.dashboard')],
                ['keywords' => ['staff', 'team', 'teams'], 'label' => 'Staff', 'route' => route('teams.staff.index')],
                ['keywords' => ['client', 'clients'], 'label' => 'Clients', 'route' => route('settings.clients.index')],
                ['keywords' => ['service', 'services'], 'label' => 'Service', 'route' => route('services.index')],
                ['keywords' => ['support', 'ticket', 'tickets'], 'label' => 'Supports', 'route' => route('tickets.index')],
                ['keywords' => ['campaign', 'campaigns', 'marketing campaign'], 'label' => 'Campaigns', 'route' => route('marketing.campaigns.index')],
                ['keywords' => ['marketing', 'email', 'email template', 'template'], 'label' => 'Email Template', 'route' => route('marketing.email-template')],
                // ['keywords' => ['marketing whatsapp', 'whatsapp', 'wati', 'twilio', 'gupshup', 'interakt'], 'label' => 'WhatsApp', 'route' => route('marketing.whatsapp')],
                ['keywords' => ['role', 'roles'], 'label' => 'Roles', 'route' => route('settings.roles.index')],
                ['keywords' => ['subscription', 'plan'], 'label' => 'Subscription', 'route' => route('subscription.show')],
                ['keywords' => ['gallery'], 'label' => 'Gallery', 'route' => route('settings.gallery.index')],
                ['keywords' => ['product', 'products', 'boq'], 'label' => 'Products', 'route' => route('settings.boq.index')],
            ];
            if ($canManageCompanySettings) {
                $moduleSearchOptions[] = [
                    'keywords' => ['settings', 'company settings'],
                    'label' => 'Company Settings',
                    'route' => route('settings.company.index'),
                ];
                $moduleSearchOptions[] = [
                    'keywords' => ['daily update', 'daily staff update', 'report history'],
                    'label' => 'Daily Update History',
                    'route' => route('settings.company.index', ['tab' => 'daily-update-history']),
                ];
                $moduleSearchOptions[] = [
                    'keywords' => ['pdf letterhead', 'letterhead', 'pdf', 'print settings'],
                    'label' => 'PDF Letterhead',
                    'route' => route('settings.company.index', ['tab' => 'letterhead-settings']),
                ];
            }

            if ($layoutUser?->isSuperAdmin()) {
                $moduleSearchOptions[] = [
                    'keywords' => ['database backup', 'backup', 'db backup'],
                    'label' => 'Database Backup',
                    'route' => route('super-admin.database-backups.index'),
                ];
                $moduleSearchOptions[] = [
                    'keywords' => ['settings', 'global settings', 'platform settings'],
                    'label' => 'Settings',
                    'route' => route('super-admin.settings.index'),
                ];
                $moduleSearchOptions[] = [
                    'keywords' => ['company app usage', 'mobile usage', 'app usage'],
                    'label' => 'Company App Usage',
                    'route' => route('super-admin.company-app-usage.index'),
                ];
            }

            $layoutNotifications = $layoutNotifications ?? [
                'unread_count' => 0,
                'activity_notifications' => collect(),
                'blocker_notifications' => collect(),
                'today_schedule' => collect(),
                'task_reminders' => collect(),
                'amc_reminders' => collect(),
                'total_count' => 0,
                'has_items' => false,
            ];

            $pushPreference = $layoutUser?->notificationPreference;
            $firebaseServiceConfig = config('services.firebase');
            $firebasePushConfig = [
                'enabled' => (bool) (config('services.firebase.project_id') && config('services.firebase.api_key') && config('services.firebase.messaging_sender_id') && config('services.firebase.app_id') && config('services.firebase.vapid_key')),
                'prefersPush' => (bool) ($pushPreference?->push_enabled ?? true),
                'updatePreferenceUrl' => $layoutUser ? route('profile.notification-preference.update') : null,
                'registerTokenUrl' => $layoutUser ? route('profile.push-devices.store') : null,
                'unregisterTokenUrl' => $layoutUser ? route('profile.push-devices.destroy') : null,
                'clientConfig' => array_filter([
                    'apiKey' => $firebaseServiceConfig['api_key'] ?? null,
                    'authDomain' => filled($firebaseServiceConfig['project_id'] ?? null) ? ($firebaseServiceConfig['project_id'] . '.firebaseapp.com') : null,
                    'projectId' => $firebaseServiceConfig['project_id'] ?? null,
                    'messagingSenderId' => $firebaseServiceConfig['messaging_sender_id'] ?? null,
                    'appId' => $firebaseServiceConfig['app_id'] ?? null,
                    'vapidKey' => $firebaseServiceConfig['vapid_key'] ?? null,
                ], fn ($value) => filled($value)),
                'missingConfigKeys' => array_values(array_filter([
                    filled($firebaseServiceConfig['project_id'] ?? null) ? null : 'FIREBASE_PROJECT_ID',
                    filled($firebaseServiceConfig['client_email'] ?? null) ? null : 'FIREBASE_CLIENT_EMAIL',
                    filled($firebaseServiceConfig['private_key'] ?? null) ? null : 'FIREBASE_PRIVATE_KEY',
                    filled($firebaseServiceConfig['messaging_sender_id'] ?? null) ? null : 'FIREBASE_MESSAGING_SENDER_ID',
                    filled($firebaseServiceConfig['api_key'] ?? null) ? null : 'FIREBASE_API_KEY',
                    filled($firebaseServiceConfig['app_id'] ?? null) ? null : 'FIREBASE_APP_ID',
                    filled($firebaseServiceConfig['vapid_key'] ?? null) ? null : 'FIREBASE_VAPID_KEY',
                ])),
            ];
        @endphp

        <title>{{ $pageTitle }}</title>
        <meta name="description" content="SaaS Lead Management Workshop - efficiently manage your leads, projects, and teams.">
        <meta name="keywords" content="saas, lead management, crm, project management, pwa">
        <meta name="robots" content="index, follow">
        <meta name="theme-color" content="{{ $themeColor }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="{{ $appName }}">
        <meta name="format-detection" content="telephone=no">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <link rel="icon" type="image/png" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">
        <link rel="shortcut icon" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">
        <link rel="apple-touch-icon" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">

        @if($layoutUser)
            <script>
                window.__firebasePushConfig = @json($firebasePushConfig);
            </script>
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>[x-cloak]{display:none !important;}</style>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-100 text-slate-900 min-h-dvh overflow-x-hidden">
        <div x-data="{ notificationsOpen: false }" class="app-shell min-h-dvh">
            @include('layouts.navigation')

            <main class="pt-[76px] lg:pl-72 lg:pt-0">
                <div class="sticky top-0 z-30 hidden border-b border-slate-200 bg-white/90 shadow-sm backdrop-blur supports-[backdrop-filter]:bg-white/80 lg:block">
                    <div class="mx-auto flex w-full items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                        <div class="hidden items-center gap-4 lg:flex">
                            <form
                                id="module-search-form"
                                class="relative hidden w-full max-w-[360px] flex-none md:block"
                                autocomplete="off"
                                data-allow-multi-submit="1"
                                data-module-search-form
                            >
                                <label for="module-search-input" class="sr-only">Search modules</label>
                                <div class="relative">
                                    <svg class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M21 21l-4.35-4.35" stroke-linecap="round" stroke-linejoin="round" />
                                        <circle cx="11" cy="11" r="7" />
                                    </svg>
                                    <input
                                        id="module-search-input"
                                        name="module_search"
                                        type="search"
                                        placeholder="Search modules: lead, amc, projects..."
                                        class="h-11 w-full rounded-full border border-slate-200 bg-white pl-11 pr-4 text-sm text-slate-700 shadow-sm outline-none transition-all duration-200 placeholder:text-slate-400 focus:border-blue-500 focus:ring-4 focus:ring-blue-100"
                                    >
                                    <div
                                        id="module-search-suggestions"
                                        class="absolute left-0 right-0 top-full z-40 mt-2 hidden overflow-hidden rounded-[22px] border border-slate-200 bg-white shadow-2xl"
                                    ></div>
                                </div>
                            </form>
                        </div>

                        <div class="ml-auto hidden items-center gap-4 lg:flex">
                            <x-support-menu />
                            @if(auth()->user()?->hasPermission('support.create'))
                                <a href="{{ route('tickets.create') }}" class="app-button app-button-primary">Create Ticket</a>
                            @endif
                            <!-- <button
                                type="button"
                                data-pwa-install-trigger
                                class="app-button app-button-secondary"
                                hidden
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M4 7a3 3 0 013-3h10a3 3 0 013 3v10a3 3 0 01-3 3H7a3 3 0 01-3-3V7z" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 8v8M8 12h8" stroke-linecap="round" />
                                </svg>
                                <span class="hidden sm:inline">Install 12</span>
                            </button> -->

                            <button
                                type="button"
                                @click="notificationsOpen = true"
                                class="relative inline-flex h-11 w-11 items-center justify-center border border-slate-200 bg-white text-slate-600 transition hover:border-blue-200 hover:text-slate-950"
                                aria-label="Open notifications"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5" stroke-linecap="round" stroke-linejoin="round"/><path d="M9 17a3 3 0 006 0" stroke-linecap="round"/></svg>
                                @if(($layoutNotifications['unread_count'] ?? 0) > 0)
                                    <span class="absolute right-0.5 top-0.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1 text-[10px] font-bold leading-5 text-white ring-2 ring-white">
                                        {{ $layoutNotifications['unread_count'] }}
                                    </span>
                                @endif
                            </button>

                            @php
                                $authUser = auth()->user();
                                $authCompany = $authUser?->company;
                                $showAdminPanelLink = ! request()->routeIs('super-admin.dashboard');
                            @endphp
                            <div class="relative" x-data="{ open:false }">
                                <button @click="open = !open" class="flex items-center gap-3 px-2 py-1.5 transition hover:bg-slate-100">
                                    <img src="{{ $authUser?->profile_photo_url ?: 'https://ui-avatars.com/api/?name='.urlencode($authUser?->name ?? 'User').'&background=0f172a&color=fff' }}" class="h-10 w-10 rounded-full border border-slate-200 object-cover" alt="profile">
                                    <div class="hidden text-left sm:block">
                                        <div class="text-sm font-semibold text-slate-900">
                                            {{ $authUser?->isSuperAdmin() ? $authUser?->name : ($authCompany?->name ?? $authUser?->name) }}
                                        </div>
                                        @if(! $authUser?->isSuperAdmin() && filled($authCompany?->company_code))
                                            <div class="text-xs font-medium text-slate-500">Code: {{ $authCompany->company_code }}</div>
                                        @endif
                                        <div class="text-xs text-slate-500">{{ $authUser?->email }}</div>
                                    </div>
                                    <svg class="h-4 w-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </button>
                                <div x-show="open" @click.outside="open=false" x-transition class="absolute right-0 mt-2 w-56 rounded-[20px] border border-slate-200 bg-white p-2 shadow-2xl" style="display:none">
                                    <a href="{{ route('profile.edit') }}" class="block rounded-xl px-4 py-3 text-sm text-slate-700 hover:bg-slate-100 hover:text-slate-950">My Profile</a>
                                    @php $user = auth()->user(); @endphp

                                    @if($showAdminPanelLink && ($user?->isSuperAdmin() || $canManageCompanySettings))
                                        <a href="{{ $user?->isSuperAdmin() ? route('super-admin.dashboard') : route('settings.company.index') }}"
                                            @click="open=false"
                                            class="block rounded-xl px-4 py-3 text-sm text-slate-700 hover:bg-rose-50 hover:text-[#C22032]">
                                            {{ $user?->isSuperAdmin() ? 'Admin Panel' : 'Settings' }}
                                        </a>
                                    @endif
                                    {{-- <a href="{{ route('settings.company.index') }}" class="block rounded-xl px-4 py-3 text-sm text-slate-700 hover:bg-slate-50">Settings</a> --}}
                                    <button type="button" @click="notificationsOpen = true; open = false" class="block w-full rounded-xl px-4 py-3 text-left text-sm text-slate-700 hover:bg-rose-50 hover:text-[#C22032]">Notifications</button>
                                    <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="block w-full rounded-xl px-4 py-3 text-left text-sm text-rose-600 hover:bg-rose-50">Logout</button></form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                @if (isset($header))
                    <div class="border-b border-slate-200/70 bg-white/70 backdrop-blur supports-[backdrop-filter]:bg-white/60">
                        <div class="mx-auto w-full px-4 py-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </div>
                @endif

                <div class="mx-auto w-full px-4 py-4 pb-24 sm:px-6 lg:px-8">
                    @if ($showExpiredPlanBanner)
                        <div class="mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                            {{ $expiredPlanMessage }}
                        </div>
                    @endif
                    {{ $slot }}
                </div>
            </main>

            <div
                x-cloak
                x-show="notificationsOpen"
                @keydown.escape.window="notificationsOpen = false"
                class="fixed inset-0 z-[90]"
                aria-hidden="true"
            >
                <button
                    type="button"
                    class="absolute inset-0 h-full w-full bg-slate-950/50 backdrop-blur-sm"
                    @click="notificationsOpen = false"
                    aria-label="Close notifications"
                ></button>

                <aside
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full opacity-0"
                    x-transition:enter-end="translate-x-0 opacity-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0 opacity-100"
                    x-transition:leave-end="translate-x-full opacity-0"
                    class="absolute right-0 top-0 flex h-full w-full max-w-[420px] flex-col border-l border-slate-200 bg-white shadow-2xl"
                >
                    <div class="flex items-start justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <div class="text-xs font-bold uppercase tracking-[0.22em] text-cyan-600">Notifications</div>
                            <h3 class="mt-1 text-lg font-bold text-slate-950">Today at a glance</h3>
                            <p class="text-xs text-slate-500">{{ $layoutNotifications['total_count'] ?? 0 }} items, {{ $layoutNotifications['unread_count'] ?? 0 }} unread</p>
                        </div>
                        <div class="flex items-center gap-2">
                            @if(($layoutNotifications['unread_count'] ?? 0) > 0)
                                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                                    @csrf
                                    <button type="submit" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        Mark all read
                                    </button>
                                </form>
                            @endif
                            <button type="button" @click="notificationsOpen = false" class="rounded-xl p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-900" aria-label="Close sidebar">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="flex-1 overflow-y-auto px-5 py-5">
                        <div class="space-y-5">
                            <section>
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Module Activity</h4>
                                        <p class="text-xs text-slate-500">Recent create, update, and delete actions across the app.</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ ($layoutNotifications['activity_notifications'] ?? collect())->count() }} items</span>
                                </div>

                                <div class="space-y-3">
                                    @forelse(($layoutNotifications['activity_notifications'] ?? collect()) as $item)
                                        @php $isRead = ($item['status'] ?? 'unread') === 'read'; @endphp
                                        <article
                                            class= border {{ $isRead ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white shadow-sm' }} p-4 {{ !empty($item['url']) ? 'cursor-pointer transition hover:border-cyan-200 hover:bg-cyan-50/40' : '' }}"
                                            @if(!empty($item['url'])) data-notification-link="{{ $item['url'] }}" @endif
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    @if(!empty($item['url']))
                                                        <a href="{{ $item['url'] }}" class="block truncate text-sm font-bold text-slate-950 hover:text-cyan-700">
                                                            {{ $item['title'] }}
                                                        </a>
                                                    @else
                                                        <p class="truncate text-sm font-bold text-slate-950">{{ $item['title'] }}</p>
                                                    @endif
                                                </div>
                                                <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $isRead ? 'bg-slate-200 text-slate-700' : 'bg-amber-100 text-amber-800' }}">
                                                    {{ $item['status_label'] ?? ($isRead ? 'Read' : 'Unread') }}
                                                </span>
                                            </div>

                                            @if(! $isRead)
                                                <div class="mt-3 flex items-center justify-between gap-2">
                                                    <form method="POST" action="{{ route('notifications.status.update', $item['notification']) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="read">
                                                        <input type="hidden" name="redirect_back" value="1">
                                                        <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800">
                                                            Mark read
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </article>
                                    @empty
                                        <div class= border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                            No module activity yet.
                                        </div>
                                    @endforelse
                                </div>
                            </section>

                            <section>
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Task Blockers</h4>
                                        <p class="text-xs text-slate-500">High-priority blockers reported on tasks inside your projects.</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ ($layoutNotifications['blocker_notifications'] ?? collect())->count() }} items</span>
                                </div>

                                <div class="space-y-3">
                                    @forelse(($layoutNotifications['blocker_notifications'] ?? collect()) as $item)
                                        @php $isRead = ($item['status'] ?? 'unread') === 'read'; @endphp
                                        <article
                                            class="rounded-2xl border {{ $isRead ? 'border-slate-200 bg-slate-50' : 'border-rose-200 bg-rose-50/60 shadow-sm' }} p-4 {{ !empty($item['url']) ? 'cursor-pointer transition hover:border-rose-300 hover:bg-rose-50' : '' }}"
                                            @if(!empty($item['url'])) data-notification-link="{{ $item['url'] }}" @endif
                                        >
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 h-3 w-3 flex-none rounded-full" style="background-color: {{ $item['color'] ?? '#dc2626' }}"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="block truncate text-sm font-bold text-slate-950 hover:text-rose-700">
                                                                    {{ $item['title'] }}
                                                                </a>
                                                            @else
                                                                <p class="truncate text-sm font-bold text-slate-950">{{ $item['title'] }}</p>
                                                            @endif
                                                            <p class="mt-1 text-xs leading-5 text-slate-600 whitespace-pre-line">{{ $item['message'] }}</p>
                                                        </div>
                                                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $isRead ? 'bg-slate-200 text-slate-700' : 'bg-rose-100 text-rose-700' }}">
                                                            {{ $item['badge'] ?? ($isRead ? 'Read' : 'Unread') }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 flex items-center justify-between gap-2">
                                                        <div class="text-[11px] text-slate-500">{{ $item['time'] ?? $item['date_label'] ?? '' }}</div>
                                                        <div class="ml-auto flex items-center gap-2">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                                                    Open
                                                                </a>
                                                            @endif
                                                            @if(! $isRead)
                                                                <form method="POST" action="{{ route('notifications.status.update', $item['notification']) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="status" value="read">
                                                                    <input type="hidden" name="redirect_back" value="1">
                                                                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800">
                                                                        Mark read
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                            No task blockers yet.
                                        </div>
                                    @endforelse
                                </div>
                            </section>

                            <section>
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Today's Schedule</h4>
                                        <p class="text-xs text-slate-500">Follow-ups, deadlines, tasks, and attendance alerts.</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ ($layoutNotifications['today_schedule'] ?? collect())->count() }} items</span>
                                </div>

                                <div class="space-y-3">
                                    @forelse(($layoutNotifications['today_schedule'] ?? collect()) as $item)
                                        @php $isRead = ($item['status'] ?? 'unread') === 'read'; @endphp
                                        <article
                                            class= border {{ $isRead ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white shadow-sm' }} p-4 {{ !empty($item['url']) ? 'cursor-pointer transition hover:border-cyan-200 hover:bg-cyan-50/40' : '' }}"
                                            @if(!empty($item['url'])) data-notification-link="{{ $item['url'] }}" @endif
                                        >
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 h-3 w-3 flex-none rounded-full" style="background-color: {{ $item['color'] ?? '#64748b' }}"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="block truncate text-sm font-bold text-slate-950 hover:text-cyan-700">
                                                                    {{ $item['title'] }}
                                                                </a>
                                                            @else
                                                                <p class="truncate text-sm font-bold text-slate-950">{{ $item['title'] }}</p>
                                                            @endif
                                                        </div>
                                                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $isRead ? 'bg-slate-200 text-slate-700' : 'bg-cyan-100 text-cyan-800' }}">
                                                            {{ $item['status_label'] ?? ($isRead ? 'Read' : 'Unread') }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 flex items-center justify-between gap-2">
                                                        <div class="ml-auto flex items-center gap-2">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                                                    Open
                                                                </a>
                                                            @endif
                                                            @if(! $isRead)
                                                                <form method="POST" action="{{ route('notifications.status.update', $item['notification']) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="status" value="read">
                                                                    <input type="hidden" name="redirect_back" value="1">
                                                                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800">
                                                                        Mark read
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class= border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                            No schedule items for today.
                                        </div>
                                    @endforelse
                                </div>
                            </section>

                            <section>
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">Task Reminders</h4>
                                        <p class="text-xs text-slate-500">Tasks that are due for reminder today.</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ ($layoutNotifications['task_reminders'] ?? collect())->count() }} items</span>
                                </div>

                                <div class="space-y-3">
                                    @forelse(($layoutNotifications['task_reminders'] ?? collect()) as $item)
                                        @php $isRead = ($item['status'] ?? 'unread') === 'read'; @endphp
                                        <article
                                            class="rounded-2xl border {{ $isRead ? 'border-slate-200 bg-slate-50' : 'border-cyan-200 bg-cyan-50/60 shadow-sm' }} p-4 {{ !empty($item['url']) ? 'cursor-pointer transition hover:border-cyan-300 hover:bg-cyan-50' : '' }}"
                                            @if(!empty($item['url'])) data-notification-link="{{ $item['url'] }}" @endif
                                        >
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 h-3 w-3 flex-none rounded-full" style="background-color: {{ $item['color'] ?? '#0f766e' }}"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <h5 class="truncate text-sm font-semibold text-slate-900">{{ $item['title'] ?? 'Task Reminder' }}</h5>
                                                        @if(!empty($item['badge']))
                                                            <span class="rounded-full bg-cyan-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-cyan-800">{{ $item['badge'] }}</span>
                                                        @endif
                                                    </div>
                                                    @if(!empty($item['message']))
                                                        <p class="mt-1 text-sm text-slate-600">{{ $item['message'] }}</p>
                                                    @endif
                                                    <div class="mt-2 flex flex-wrap items-center gap-2 text-[11px] font-medium text-slate-500">
                                                        @if(!empty($item['time']))
                                                            <span>{{ $item['time'] }}</span>
                                                        @endif
                                                        @if(!empty($item['employee']))
                                                            <span>By {{ $item['employee'] }}</span>
                                                        @endif
                                                        @if(!empty($item['date_label']))
                                                            <span>{{ $item['date_label'] }}</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                            No task reminders for today.
                                        </div>
                                    @endforelse
                                </div>
                            </section>

                            <section>
                                <div class="mb-3 flex items-center justify-between">
                                    <div>
                                        <h4 class="text-sm font-bold text-slate-900">AMC Reminders</h4>
                                        <p class="text-xs text-slate-500">Upcoming services and contract expiry reminders.</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ ($layoutNotifications['amc_reminders'] ?? collect())->count() }} items</span>
                                </div>

                                <div class="space-y-3">
                                    @forelse(($layoutNotifications['amc_reminders'] ?? collect()) as $item)
                                        @php $isRead = ($item['status'] ?? 'unread') === 'read'; @endphp
                                        <article
                                            class= border {{ $isRead ? 'border-slate-200 bg-slate-50' : 'border-slate-200 bg-white shadow-sm' }} p-4 {{ !empty($item['url']) ? 'cursor-pointer transition hover:border-cyan-200 hover:bg-cyan-50/40' : '' }}"
                                            @if(!empty($item['url'])) data-notification-link="{{ $item['url'] }}" @endif
                                        >
                                            <div class="flex items-start gap-3">
                                                <span class="mt-1 h-3 w-3 flex-none rounded-full" style="background-color: {{ $item['color'] ?? '#64748b' }}"></span>
                                                <div class="min-w-0 flex-1">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="block truncate text-sm font-bold text-slate-950 hover:text-cyan-700">
                                                                    {{ $item['title'] }}
                                                                </a>
                                                            @else
                                                                <p class="truncate text-sm font-bold text-slate-950">{{ $item['title'] }}</p>
                                                            @endif
                                                        </div>
                                                        <span class="shrink-0 rounded-full px-2 py-1 text-[10px] font-bold uppercase tracking-wide {{ $isRead ? 'bg-slate-200 text-slate-700' : 'bg-rose-100 text-rose-700' }}">
                                                            {{ $item['status_label'] ?? ($isRead ? 'Read' : 'Unread') }}
                                                        </span>
                                                    </div>

                                                    <div class="mt-3 flex items-center justify-between gap-2">
                                                        <div class="ml-auto flex items-center gap-2">
                                                            @if(!empty($item['url']))
                                                                <a href="{{ $item['url'] }}" class="rounded-xl border border-slate-200 px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-100">
                                                                    Open
                                                                </a>
                                                            @endif
                                                            @if(! $isRead)
                                                                <form method="POST" action="{{ route('notifications.status.update', $item['notification']) }}">
                                                                    @csrf
                                                                    @method('PATCH')
                                                                    <input type="hidden" name="status" value="read">
                                                                    <input type="hidden" name="redirect_back" value="1">
                                                                    <button type="submit" class="rounded-xl bg-slate-900 px-3 py-1.5 text-[11px] font-semibold text-white hover:bg-slate-800">
                                                                        Mark read
                                                                    </button>
                                                                </form>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class= border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                            No reminders for now.
                                        </div>
                                    @endforelse
                                </div>
                            </section>
                        </div>
                    </div>
                </aside>
            </div>
        </div>

        <div id="global-submit-loader" class="hidden fixed inset-0 z-[9999] transition-opacity duration-300">
            <div class="absolute inset-0 bg-slate-950/60 backdrop-blur-sm"></div>
            <div class="relative h-full w-full flex items-center justify-center p-4">
                <div class="relative flex flex-col items-center justify-center rounded-3xl bg-white/95 px-8 py-7 shadow-2xl ring-1 ring-slate-900/10 backdrop-blur-md min-w-[220px]">
                    <div class="relative flex items-center justify-center mb-3">
                        <div class="h-12 w-12 rounded-full border-4 border-indigo-100 border-t-indigo-600 animate-spin"></div>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="h-3 w-3 rounded-full bg-indigo-600 animate-ping opacity-75"></span>
                        </div>
                    </div>
                    <div class="text-sm font-bold text-slate-800 tracking-wide">Processing...</div>
                    <p class="mt-0.5 text-xs text-slate-500 font-medium">Please wait a moment</p>
                </div>
            </div>
        </div>

        <x-pwa-install-banner />

        @auth
            <form id="idle-logout-form" method="POST" action="{{ route('logout') }}" class="hidden">
                @csrf
            </form>
        @endauth

        <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.20.0/dist/jquery.validate.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        @stack('scripts')

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const loader = document.getElementById('global-submit-loader');
                const moduleSearchForm = document.querySelector('[data-module-search-form]');
                const moduleSearchInput = document.getElementById('module-search-input');
                const moduleSearchSuggestions = document.getElementById('module-search-suggestions');
                const moduleSearchRoutes = @js($moduleSearchOptions);
                let moduleSearchHideTimer = null;

                function escapeHtml(value) {
                    return String(value)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function hideModuleSuggestions() {
                    if (!moduleSearchSuggestions) return;
                    moduleSearchSuggestions.classList.add('hidden');
                    moduleSearchSuggestions.innerHTML = '';
                }

                function showModuleSuggestions(items) {
                    if (!moduleSearchSuggestions) return;

                    if (!items.length) {
                        hideModuleSuggestions();
                        return;
                    }

                    moduleSearchSuggestions.innerHTML = items.map((item) => `
                        <a
                            href="${escapeHtml(item.route)}"
                            class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-slate-700 transition hover:bg-cyan-50 hover:text-cyan-950"
                            data-module-suggestion
                            data-module-route="${escapeHtml(item.route)}"
                        >
                            <span class="font-medium">${escapeHtml(item.label)}</span>
                            <span class="text-xs uppercase tracking-[0.2em] text-slate-400">${escapeHtml(item.keywords[0] || '')}</span>
                        </a>
                    `).join('');

                    moduleSearchSuggestions.classList.remove('hidden');
                }

                function getModuleMatches(query) {
                    const normalized = query.trim().toLowerCase();

                    if (!normalized) {
                        return [];
                    }

                    return moduleSearchRoutes
                        .map((item) => {
                            const label = String(item.label || '').toLowerCase();
                            const keywords = Array.isArray(item.keywords) ? item.keywords.map((keyword) => String(keyword).toLowerCase()) : [];
                            const score = keywords.reduce((currentScore, keyword) => {
                                if (keyword === normalized) return Math.max(currentScore, 3);
                                if (keyword.startsWith(normalized)) return Math.max(currentScore, 2);
                                if (keyword.includes(normalized)) return Math.max(currentScore, 1);
                                return currentScore;
                            }, 0) || (label.includes(normalized) ? 1 : 0);

                            return { ...item, score };
                        })
                        .filter((item) => item.score > 0)
                        .sort((a, b) => b.score - a.score || String(a.label).localeCompare(String(b.label)))
                        .slice(0, 6);
                }

                function navigateToModuleSuggestion(route) {
                    if (!route) {
                        return;
                    }

                    hideModuleSuggestions();
                    window.location.assign(route);
                }

                function showLoader(form) {
                    if (!loader) return;
                    if (form && form.dataset && form.dataset.noGlobalLoader === '1') return;
                    loader.classList.remove('hidden');
                }

                function lockForm(form) {
                    if (!form || form.dataset.allowMultiSubmit === '1') return true;
                    if (form.dataset.skipLoader === '1') return true;
                    if (form.dataset.submitted === '1') return false;
                    form.dataset.submitted = '1';

                    const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
                    buttons.forEach((btn) => {
                        try {
                            btn.disabled = true;
                            btn.classList.add('opacity-75', 'cursor-not-allowed');
                        } catch (e) {}
                    });

                    showLoader(form);
                    return true;
                }

                document.querySelectorAll('form').forEach((form) => {
                    form.addEventListener('submit', function (e) {
                        if (e.defaultPrevented) return;
                        try {
                            if (window.jQuery) {
                                const $form = window.jQuery(form);
                                if ($form.data('validator') && !$form.valid()) {
                                    e.preventDefault();
                                    return;
                                }
                            }
                        } catch (err) {}
                        const ok = lockForm(form);
                        if (!ok) e.preventDefault();
                    });
                });

                if (moduleSearchForm && moduleSearchInput) {
                    const updateModuleSuggestions = () => {
                        clearTimeout(moduleSearchHideTimer);
                        const matches = getModuleMatches(moduleSearchInput.value);
                        showModuleSuggestions(matches);
                    };

                    moduleSearchForm.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const rawValue = moduleSearchInput.value.trim().toLowerCase();
                        if (!rawValue) {
                            return;
                        }

                        const matchedRoute = moduleSearchRoutes.find((item) => {
                            return item.keywords.some((keyword) => rawValue === keyword || rawValue.includes(keyword));
                        });

                        if (matchedRoute) {
                            window.location.assign(matchedRoute.route);
                            return;
                        }

                        if (window.appAlert) {
                            window.appAlert('No module matched "' + moduleSearchInput.value.trim() + '". Try Lead, AMC, Projects, Reports, Clients, or Service.', 'Module Search', 'info');
                        } else {
                            window.alert('No module matched "' + moduleSearchInput.value.trim() + '".');
                        }

                        moduleSearchInput.focus();
                        moduleSearchInput.select();
                    });

                    moduleSearchInput.addEventListener('input', updateModuleSuggestions);
                    moduleSearchInput.addEventListener('focus', updateModuleSuggestions);
                    moduleSearchInput.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape') {
                            hideModuleSuggestions();
                        }
                    });

                    moduleSearchInput.addEventListener('blur', function () {
                        moduleSearchHideTimer = window.setTimeout(hideModuleSuggestions, 150);
                    });

                    if (moduleSearchSuggestions) {
                        moduleSearchSuggestions.addEventListener('pointerdown', function (event) {
                            clearTimeout(moduleSearchHideTimer);

                            const link = event.target.closest('[data-module-suggestion]');
                            if (!link) return;

                            event.preventDefault();
                            navigateToModuleSuggestion(link.dataset.moduleRoute || link.getAttribute('href'));
                        });

                        moduleSearchSuggestions.addEventListener('click', function (event) {
                            const link = event.target.closest('[data-module-suggestion]');
                            if (!link) return;

                            event.preventDefault();
                            navigateToModuleSuggestion(link.dataset.moduleRoute || link.getAttribute('href'));
                        });
                    }
                }
            });
        </script>

        <script>
            (function () {
                document.addEventListener('click', function (event) {
                    const card = event.target.closest('[data-notification-link]');
                    if (!card) {
                        return;
                    }

                    if (event.target.closest('a, button, form, input, textarea, select, label')) {
                        return;
                    }

                    const link = card.getAttribute('data-notification-link');
                    if (link) {
                        window.location.assign(link);
                    }
                });
            })();
        </script>

        <script>
            (function () {
                const idleLogoutMinutes = Number(@json((int) config('session.lifetime', 20)));
                const idleLogoutForm = document.getElementById('idle-logout-form');
                let idleLogoutTimer = null;
                let idleLogoutTriggered = false;

                function scheduleIdleLogout() {
                    if (!idleLogoutForm || !Number.isFinite(idleLogoutMinutes) || idleLogoutMinutes <= 0) {
                        return;
                    }

                    clearTimeout(idleLogoutTimer);
                    idleLogoutTimer = window.setTimeout(function () {
                        if (idleLogoutTriggered) {
                            return;
                        }

                        idleLogoutTriggered = true;
                        idleLogoutForm.submit();
                    }, idleLogoutMinutes * 60 * 1000);
                }

                function resetIdleLogoutTimer() {
                    if (idleLogoutTriggered) {
                        return;
                    }

                    scheduleIdleLogout();
                }

                if (idleLogoutForm) {
                    ['click', 'keydown', 'mousedown', 'mousemove', 'scroll', 'touchstart', 'pointerdown'].forEach(function (eventName) {
                        window.addEventListener(eventName, resetIdleLogoutTimer, { passive: true });
                    });

                    document.addEventListener('visibilitychange', function () {
                        if (document.visibilityState === 'visible') {
                            resetIdleLogoutTimer();
                        }
                    });

                    window.addEventListener('focus', resetIdleLogoutTimer);
                    scheduleIdleLogout();
                }

                function showAlert(message, title = 'Notice', icon = 'info') {
                    if (window.Swal) {
                        return Swal.fire({
                            title,
                            text: message,
                            icon,
                            confirmButtonColor: '#2563eb',
                        });
                    }

                    window.alert(message);
                }

                function showConfirm(message, options = {}) {
                    if (!window.Swal) {
                        return Promise.resolve(window.confirm(message));
                    }

                    return Swal.fire({
                        title: options.title || 'Are you sure?',
                        text: message,
                        icon: options.icon || 'warning',
                        showCancelButton: true,
                        confirmButtonText: options.confirmText || 'Yes',
                        cancelButtonText: options.cancelText || 'Cancel',
                        confirmButtonColor: options.confirmButtonColor || '#2563eb',
                        cancelButtonColor: options.cancelButtonColor || '#64748b',
                    }).then((result) => result.isConfirmed);
                }

                window.appAlert = showAlert;
                window.appConfirm = showConfirm;

                const dashboardUrl = @js(route('dashboard'));
                const companyDetailsUrl = @js($canManageCompanySettings ? route('settings.company.index', ['tab' => 'company-details']) : null);
                const subscriptionUrl = @js(route('subscription.show'));
                const dashboardPath = new URL(dashboardUrl, window.location.origin).pathname;
                const isOnDashboard = window.location.pathname === dashboardPath;

                @if (session('subscription_feature_warning'))
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Subscription Required',
                            text: 'You need to purchase a subscription plan to access this feature.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'OK',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#2563eb',
                            cancelButtonColor: '#64748b',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.assign(subscriptionUrl);
                                return;
                            }

                            if (!isOnDashboard) {
                                window.location.assign(dashboardUrl);
                            }
                        });
                    } else {
                        const proceed = window.confirm('You need to purchase a subscription plan to access this feature.');
                        if (proceed) {
                            window.location.assign(subscriptionUrl);
                        } else if (!isOnDashboard) {
                            window.location.assign(dashboardUrl);
                        }
                    }
                @elseif ($showRequiredSetupPopup)
                    if (window.Swal) {
                        Swal.fire({
                            title: 'Required Setup',
                            text: 'Please complete your required profile details (e.g., address)',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'OK',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#2563eb',
                            cancelButtonColor: '#64748b',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.assign(companyDetailsUrl);
                                return;
                            }

                            if (!isOnDashboard) {
                                window.location.assign(dashboardUrl);
                            }
                        });
                    } else {
                        const proceed = window.confirm('Please complete your required profile details (e.g., address)');
                        if (proceed) {
                            window.location.assign(companyDetailsUrl);
                        } else if (!isOnDashboard) {
                            window.location.assign(dashboardUrl);
                        }
                    }
                @endif

                document.querySelectorAll('form[data-swal-confirm]').forEach((form) => {
                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        const title = form.dataset.swalTitle || 'Are you sure?';
                        const text = form.dataset.swalConfirm || 'Please confirm this action.';
                        const ok = await showConfirm(text, {
                            title,
                            confirmText: form.dataset.swalConfirmButton || 'Yes',
                            cancelText: form.dataset.swalCancelButton || 'Cancel',
                            confirmButtonColor: form.dataset.swalConfirmColor || '#2563eb',
                            cancelButtonColor: form.dataset.swalCancelColor || '#64748b',
                        });

                        if (ok) {
                            form.removeAttribute('data-swal-confirm');
                            form.submit();
                        }
                    });
                });

                document.querySelectorAll('[data-swal-message]').forEach((el) => {
                    const message = el.dataset.swalMessage;
                    const title = el.dataset.swalTitle || 'Notice';
                    const icon = el.dataset.swalIcon || 'info';

                    el.addEventListener('click', function (event) {
                        if (el.tagName === 'A' && el.getAttribute('href') === '#') {
                            event.preventDefault();
                        }

                        showAlert(message, title, icon);
                    });
                });

                @if (session('error'))
                    @php
                        $flashError = (string) session('error');
                        $flashErrorLower = strtolower($flashError);
                        $isSubscriptionLimit = str_contains($flashErrorLower, 'upgrade your subscription')
                            || str_contains($flashErrorLower, 'upgrade your plan')
                            || str_contains($flashErrorLower, 'current subscription plan')
                            || str_contains($flashErrorLower, 'is deactivated');
                        $flashTitle = $isSubscriptionLimit ? 'Subscription Limit Reached' : 'Error';
                        $flashIcon = $isSubscriptionLimit ? 'warning' : 'error';
                    @endphp
                    showAlert(@js($flashError), @js($flashTitle), @js($flashIcon));
                @endif
            })();
        </script>

        <script>
            (function () {
                if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.validate !== 'function') return;

                const $ = window.jQuery;

                $.validator.setDefaults({
                    ignore: ':hidden:not(.js-validate-file)',
                    errorClass: 'text-xs text-red-600 mt-1 block',
                    validClass: '',
                    highlight: function (element) {
                        try { $(element).addClass('border-red-400 ring-1 ring-red-300'); } catch (e) {}
                    },
                    unhighlight: function (element) {
                        try { $(element).removeClass('border-red-400 ring-1 ring-red-300'); } catch (e) {}
                    },
                    errorPlacement: function (error, element) {
                        if (element.hasClass('js-validate-file')) {
                            const label = element.prev('label');
                            if (label && label.length) {
                                error.insertAfter(label);
                                return;
                            }
                        }
                        error.insertAfter(element);
                    }
                });

                $.validator.addMethod('maxFileSize', function (value, element) {
                    const maxSize = Number(element.dataset.maxSize || 0);
                    if (!maxSize) return true;
                    if (!element.files || !element.files.length) return true;
                    return Array.from(element.files).every(f => f.size <= maxSize);
                }, 'File is too large.');

                $(function () {
                    $('form').each(function () {
                        const form = this;
                        const action = form.getAttribute('action') || '';
                        const shouldValidateProject = action.includes('/projects');
                        if (!shouldValidateProject) return;
                        if (form.dataset.noJqueryValidate === '1') return;

                        const $form = $(form);

                        $form.find('input[type="file"][data-max-size]').each(function () {
                            $(this).addClass('js-validate-file').rules('add', { maxFileSize: true });
                        });

                        $form.validate();
                    });
                });
            })();
        </script>

        @include('partials.custom-select-init')
    </body>
</html>
