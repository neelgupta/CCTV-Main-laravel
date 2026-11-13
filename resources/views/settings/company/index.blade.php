<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">
                {{ __('Company Settings') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">Manage your company profile, lead categories, project overview, and users in one place.</p>
        </div>
    </x-slot>

    @php
        $isCompanyAdmin = auth()->user()?->isCompanyAdmin();
        $isSuperAdmin = auth()->user()?->isSuperAdmin();
        $canManagePayrollSettings = $isSuperAdmin || $isCompanyAdmin;
        $enableQuotationGenerator = old('enable_quotation_generator', $company?->enable_quotation_generator ?? true);
        $enableBoq = old('enable_boq', $company?->enable_boq ?? true);
        $projectCustomFields = collect($company?->normalizedProjectCustomFields() ?? []);
        $selectedCompanyStateName = old('state', $company?->state);
        $selectedCompanyCityName = old('city', $company?->city);
        $selectedCompanyLatitude = old('latitude', $company?->latitude);
        $selectedCompanyLongitude = old('longitude', $company?->longitude);
        $selectedCompanyState = $states->firstWhere('name', $selectedCompanyStateName);
        $selectedCompanyCities = $selectedCompanyState?->cities ?? collect();
        $dailyStaffUpdateTime = old('daily_staff_update_time', $company?->daily_staff_update_time ? \Illuminate\Support\Carbon::parse($company->daily_staff_update_time)->format('H:i') : '16:00');
        $companyTimezone = old('timezone', $company?->timezone ?: config('app.timezone'));
        $companyMailSetting = $company?->mailSetting;
        $mailMailer = old('mailer', $companyMailSetting?->mailer ?? 'smtp');
        $mailHost = old('host', $companyMailSetting?->host ?? '');
        $mailPort = old('port', $companyMailSetting?->port ?? 587);
        $mailUsername = old('username', $companyMailSetting?->username ?? '');
        $mailEncryption = old('encryption', $companyMailSetting?->encryption ?? 'tls');
        $mailFromEmail = old('from_email', $companyMailSetting?->from_email ?? ($company?->email ?? ''));
        $mailFromName = old('from_name', $companyMailSetting?->from_name ?? ($company?->name ?? ''));
        $mailIsActive = old('is_active', $companyMailSetting?->is_active ?? true);
        $companyCityOptionsByState = $states->mapWithKeys(function ($state) {
            return [
                $state->name => $state->cities->map(fn ($city) => [
                    'name' => $city->name,
                ])->values(),
            ];
        });
    @endphp

    <div class="w-full py-6 sm:px-6 lg:px-8 space-y-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @php
            $activeSettingsTab = $activeTab ?? 'company-details';
        $settingsTabs = [
            'company-details' => 'Company Details',
            'lead-pipeline' => 'Lead Settings',
            'project-pipeline' => 'Project Settings',
            'service-settings' => 'Service Settings',
            'crm' => 'CRM Settings',
            'payroll' => 'Payroll Settings',
            'task-master' => 'Task Master',
            'invoice-settings' => 'Invoice Settings',
            'quotation-settings' => 'Quotation Settings',
            'letterhead-settings' => 'Letterhead Settings',
            'daily-update-history' => 'Daily Update History',
        ];
        if ($canManagePayrollSettings) {
            $settingsTabs['payroll'] = 'Payroll Settings';
        }

        if (! $canManagePayrollSettings && $activeSettingsTab === 'payroll') {
            $activeSettingsTab = 'company-details';
        }
    @endphp

        <nav class="rounded-3xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex gap-2 overflow-x-auto whitespace-nowrap">
                @foreach($settingsTabs as $key => $label)
                    <a
                        href="{{ route('settings.company.index', ['tab' => $key]) }}"
                        class="inline-flex items-center border-b-2 px-4 py-3 text-sm font-semibold transition {{ $activeSettingsTab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-600 hover:border-slate-300 hover:text-slate-900' }}"
                    >
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </nav>

        <section id="invoice-settings" @class(['hidden' => $activeSettingsTab !== 'invoice-settings'])>
            <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <h4 class="text-lg font-bold text-slate-950">Invoice Number</h4>
                <p class="mt-1 text-sm text-slate-500">Configure the invoice prefix and starting sequence.</p>
                <form id="invoice-numbering-form" method="POST" action="{{ route('settings.company.update') }}" class="mt-4 grid gap-4 md:grid-cols-2" novalidate>
                    @csrf
                    <div>
                        <x-input-label for="invoice_prefix" value="Invoice Prefix" />
                        <x-text-input
                            id="invoice_prefix"
                            name="invoice_prefix"
                            class="mt-1 block w-full rounded-xl"
                            value="{{ old('invoice_prefix', $company?->invoice_prefix) }}"
                            placeholder="INV"
                            maxlength="50"
                        />
                        <x-input-error :messages="$errors->get('invoice_prefix')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="invoice_year" value="Invoice Year" />
                        <x-text-input id="invoice_year" name="invoice_year" type="number" min="2000" max="2100" class="mt-1 block w-full rounded-xl" value="{{ old('invoice_year', $company?->invoice_year ?? date('Y')) }}" placeholder="{{ date('Y') }}" />
                        <x-input-error :messages="$errors->get('invoice_year')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="invoice_sequence" value="Invoice Sequence" />
                        <x-text-input id="invoice_sequence" name="invoice_sequence" type="number" min="1" class="mt-1 block w-full rounded-xl" value="{{ old('invoice_sequence', $company?->invoice_sequence ?? 1) }}" placeholder="1" />
                        <x-input-error :messages="$errors->get('invoice_sequence')" class="mt-2" />
                    </div>

                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="manual_product_entry" value="0">
                            <input
                                type="checkbox"
                                id="manual_product_entry"
                                name="manual_product_entry"
                                value="1"
                                @checked(old('manual_product_entry', $company?->manual_product_entry ?? false))
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                            >
                            <div>
                                <x-input-label for="manual_product_entry" value="Enable Manual Product Entry" class="cursor-pointer" />
                                <p class="mt-1 text-sm text-slate-500">When enabled, invoice and quotation items can be typed manually instead of selecting a saved product.</p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-4"
                        x-data="{ hasDefaultInvoiceNotes: @js((bool) old('has_default_invoice_notes', $company?->has_default_invoice_notes ?? false)) }"
                    >
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="has_default_invoice_notes" value="0">
                            <input
                                type="checkbox"
                                id="has_default_invoice_notes"
                                name="has_default_invoice_notes"
                                value="1"
                                x-model="hasDefaultInvoiceNotes"
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                            >
                            <div class="w-full">
                                <x-input-label for="has_default_invoice_notes" value="Add Additional Notes" class="cursor-pointer" />
                                <p class="mt-1 text-sm text-slate-500">When enabled, this note is pre-filled on every new invoice and printed on the invoice unless removed.</p>

                                <div x-show="hasDefaultInvoiceNotes" x-cloak class="mt-3">
                                    <div class="flex items-center justify-between">
                                        <x-input-label for="default_invoice_notes" value="Additional Notes" />
                                        <x-speech-to-text target="default_invoice_notes" />
                                    </div>
                                    <textarea
                                        id="default_invoice_notes"
                                        name="default_invoice_notes"
                                        rows="4"
                                        maxlength="5000"
                                        placeholder="Enter additional notes to show on every invoice..."
                                        class="mt-1 block w-full rounded-2xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    >{{ old('default_invoice_notes', $company?->default_invoice_notes ?? '') }}</textarea>
                                    <x-input-error :messages="$errors->get('default_invoice_notes')" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>Save Invoice Settings</x-primary-button>
                    </div>
                </form>
            </div>
        </section>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const editor = document.getElementById('invoice_terms_conditions_editor');
                const hiddenInput = document.getElementById('invoice_terms_conditions');
                const form = document.getElementById('invoice-numbering-form');

                if (!editor || !hiddenInput) {
                    return;
                }

                const initialValue = hiddenInput.value || '';
                editor.innerHTML = initialValue ? initialValue : '<p></p>';

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

                editor.addEventListener('keydown', function (event) {
                    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'a') {
                        return;
                    }
                });

                form?.addEventListener('submit', syncEditor);

                document.querySelectorAll('[data-invoice-editor-cmd]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const command = button.getAttribute('data-invoice-editor-cmd');
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
                            const defaultValue = initialValue || '';
                            editor.innerHTML = defaultValue ? defaultValue : '<p></p>';
                            syncEditor();
                            return;
                        }

                        applyCommand(command);
                    });
                });

                document.querySelectorAll('[data-invoice-editor-file]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const action = event.target.value;
                        event.target.value = '';
                        if (action === 'clear') {
                            editor.innerHTML = '<p></p>';
                            syncEditor();
                        } else if (action === 'reset') {
                            const defaultValue = initialValue || '';
                            editor.innerHTML = defaultValue ? defaultValue : '<p></p>';
                            syncEditor();
                        }
                    });
                });

                document.querySelectorAll('[data-invoice-editor-format]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const tag = event.target.value;
                        event.target.value = '';
                        if (!tag) return;
                        editor.focus();
                        document.execCommand('formatBlock', false, tag);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-invoice-editor-size]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const size = event.target.value;
                        event.target.value = '';
                        if (!size) return;
                        editor.focus();
                        document.execCommand('fontSize', false, size);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-invoice-editor-color]').forEach(function (input) {
                    input.addEventListener('input', function (event) {
                        editor.focus();
                        document.execCommand('foreColor', false, event.target.value);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-invoice-editor-background]').forEach(function (input) {
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
                const editor = document.getElementById('quotation_terms_conditions_editor');
                const hiddenInput = document.getElementById('quotation_terms_conditions');
                const form = document.getElementById('quotation-terms-form');

                if (!editor || !hiddenInput) {
                    return;
                }

                const initialValue = hiddenInput.value || '';
                editor.innerHTML = initialValue ? initialValue : '<p></p>';

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

                form?.addEventListener('submit', syncEditor);

                document.querySelectorAll('[data-quotation-editor-cmd]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        const command = button.getAttribute('data-quotation-editor-cmd');
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
                            const defaultValue = initialValue || '';
                            editor.innerHTML = defaultValue ? defaultValue : '<p></p>';
                            syncEditor();
                            return;
                        }

                        applyCommand(command);
                    });
                });

                document.querySelectorAll('[data-quotation-editor-file]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const action = event.target.value;
                        event.target.value = '';
                        if (action === 'clear') {
                            editor.innerHTML = '<p></p>';
                            syncEditor();
                        } else if (action === 'reset') {
                            const defaultValue = initialValue || '';
                            editor.innerHTML = defaultValue ? defaultValue : '<p></p>';
                            syncEditor();
                        }
                    });
                });

                document.querySelectorAll('[data-quotation-editor-format]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const tag = event.target.value;
                        event.target.value = '';
                        if (!tag) return;
                        editor.focus();
                        document.execCommand('formatBlock', false, tag);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-quotation-editor-size]').forEach(function (select) {
                    select.addEventListener('change', function (event) {
                        const size = event.target.value;
                        event.target.value = '';
                        if (!size) return;
                        editor.focus();
                        document.execCommand('fontSize', false, size);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-quotation-editor-color]').forEach(function (input) {
                    input.addEventListener('input', function (event) {
                        editor.focus();
                        document.execCommand('foreColor', false, event.target.value);
                        syncEditor();
                    });
                });

                document.querySelectorAll('[data-quotation-editor-background]').forEach(function (input) {
                    input.addEventListener('input', function (event) {
                        editor.focus();
                        document.execCommand('hiliteColor', false, event.target.value);
                        syncEditor();
                    });
                });
            });
        </script>

        <section id="quotation-settings" @class(['hidden' => $activeSettingsTab !== 'quotation-settings'])>
            <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <h4 class="text-lg font-bold text-slate-950">Quotation Number</h4>
                <p class="mt-1 text-sm text-slate-500">Configure the quotation prefix and starting sequence.</p>
                <form id="quotation-numbering-form" method="POST" action="{{ route('settings.company.update') }}" class="mt-4 grid gap-4 md:grid-cols-2" novalidate>
                    @csrf
                    <div>
                        <x-input-label for="quotation_settings_prefix" value="Quotation Prefix" />
                        <x-text-input
                            id="quotation_settings_prefix"
                            name="quotation_prefix"
                            class="mt-1 block w-full rounded-xl"
                            value="{{ old('quotation_prefix', $company?->quotation_prefix) }}"
                            placeholder="QTN"
                            maxlength="50"
                        />
                        <x-input-error :messages="$errors->get('quotation_prefix')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quotation_settings_year" value="Quotation Year" />
                        <x-text-input id="quotation_settings_year" name="quotation_year" type="number" min="2000" max="2100" class="mt-1 block w-full rounded-xl" value="{{ old('quotation_year', $company?->quotation_year ?? date('Y')) }}" placeholder="{{ date('Y') }}" />
                        <x-input-error :messages="$errors->get('quotation_year')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <x-input-label for="quotation_settings_sequence" value="Quotation Sequence" />
                        <x-text-input id="quotation_settings_sequence" name="quotation_sequence" type="number" min="1" class="mt-1 block w-full rounded-xl" value="{{ old('quotation_sequence', $company?->quotation_sequence ?? 1) }}" placeholder="1" />
                        <x-input-error :messages="$errors->get('quotation_sequence')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2 rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="manual_product_entry" value="0">
                            <input
                                type="checkbox"
                                id="manual_product_entry_quotation_settings"
                                name="manual_product_entry"
                                value="1"
                                @checked(old('manual_product_entry', $company?->manual_product_entry ?? false))
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                            >
                            <div>
                                <x-input-label for="manual_product_entry_quotation_settings" value="Enable Manual Product Entry" class="cursor-pointer" />
                                <p class="mt-1 text-sm text-slate-500">When enabled, invoice and quotation items can be typed manually instead of selecting a saved product.</p>
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2 flex justify-end">
                        <x-primary-button>Save Quotation Number</x-primary-button>
                    </div>
                </form>
            </div>

            <!-- Quotation Templates Management -->
            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5" x-data="{
                editModalOpen: false,
                viewModalOpen: false,
                currentTemplate: null,
                editForm: { id: '', name: '', description: '' },
                openEdit(tmpl) {
                    this.currentTemplate = tmpl;
                    this.editForm.id = tmpl.id;
                    this.editForm.name = tmpl.name;
                    this.editForm.description = tmpl.description || '';
                    this.editModalOpen = true;
                },
                openView(tmpl) {
                    this.currentTemplate = tmpl;
                    this.viewModalOpen = true;
                }
            }">
                <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                    <div>
                        <h4 class="text-lg font-bold text-slate-950">Quotation Templates</h4>
                        <p class="mt-1 text-sm text-slate-500">Materials and items saved as templates for quick use in new quotations.</p>
                    </div>
                    <span class="rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                        {{ isset($quotationTemplates) ? $quotationTemplates->count() : 0 }} Templates
                    </span>
                </div>

                @if(isset($quotationTemplates) && $quotationTemplates->count() > 0)
                    <div class="mt-4 overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left font-semibold text-700">Template Name</th>
                                    <th class="px-4 py-3 text-left font-semibold text-700">Description</th>
                                    <th class="px-4 py-3 text-center font-semibold text-700">Items Count</th>
                                    <th class="px-4 py-3 text-right font-semibold text-700">Est. Total</th>
                                    <th class="px-4 py-3 text-left font-semibold text-700">Created At</th>
                                    <th class="px-4 py-3 text-right font-semibold text-700">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach($quotationTemplates as $tpl)
                                    <tr class="hover:bg-slate-50/50">
                                        <td class="px-4 py-3 font-semibold text-slate-900">
                                            {{ $tpl->name }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-600 max-w-xs truncate">
                                            {{ $tpl->description ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-center text-slate-700">
                                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-800">
                                                {{ $tpl->items_count }} items
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right font-medium text-slate-900">
                                            ₹{{ number_format($tpl->estimated_total, 2) }}
                                        </td>
                                        <td class="px-4 py-3 text-slate-500 text-xs">
                                            {{ $tpl->created_at?->format('d M Y, h:i A') }}
                                        </td>
                                       <td class="px-4 py-3 text-right space-x-2">
                                            <!-- View Button -->
                                            <button type="button" @click="openView(@js($tpl))" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 underline inline-flex items-center">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                                                    <circle cx="12" cy="12" r="3"></circle>
                                                </svg>
                                            </button>

                                            <!-- Edit Button -->
                                            <button type="button" @click="openEdit(@js($tpl))" class="text-xs font-semibold text-amber-600 hover:text-amber-800 underline inline-flex items-center">
                                                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                    <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                                                </svg>
                                            </button>

                                            <!-- Delete Form / Button -->
                                            <form method="POST" action="{{ route('settings.quotation-templates.destroy', $tpl) }}" class="inline" data-swal-confirm="Are you sure you want to delete this template?" data-swal-title="Delete Quotation Template" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel" data-swal-confirm-color="#e11d48">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-xs font-semibold text-rose-600 hover:text-rose-800 underline inline-flex items-center">
                                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                        <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                        <svg class="mx-auto h-10 w-10 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <p class="mt-2 text-sm font-semibold text-slate-700">No quotation templates yet</p>
                        <p class="text-xs text-slate-500 mt-1">You can save any quotation's items as a template directly when creating or viewing a quotation.</p>
                    </div>
                @endif

                <!-- Edit Template Modal -->
                <div x-cloak x-show="editModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" @click="editModalOpen = false"></div>
                        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg p-6">
                            <h3 class="text-lg font-bold text-slate-900 mb-4">Edit Quotation Template</h3>
                            <form :action="'{{ url('settings/quotation-templates') }}/' + editForm.id" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="edit_template_name" value="Template Name" />
                                        <x-text-input id="edit_template_name" name="name" x-model="editForm.name" class="mt-1 block w-full rounded-xl" required />
                                    </div>
                                    <div>
                                        <x-input-label for="edit_template_desc" value="Description" />
                                        <textarea id="edit_template_desc" name="description" x-model="editForm.description" rows="3" class="mt-1 block w-full rounded-xl border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end gap-3">
                                    <button type="button" @click="editModalOpen = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                                    <x-primary-button>Save Changes</x-primary-button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- View Template Items Modal -->
                <div x-cloak x-show="viewModalOpen" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                    <div class="flex min-h-screen items-center justify-center p-4 text-center sm:p-0">
                        <div class="fixed inset-0 bg-slate-500 bg-opacity-75 transition-opacity" @click="viewModalOpen = false"></div>
                        <div class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl p-6">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-200">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900" x-text="currentTemplate?.name || 'Template Details'"></h3>
                                    <p class="text-xs text-slate-500" x-text="currentTemplate?.description || 'No description provided'"></p>
                                </div>
                                <button type="button" @click="viewModalOpen = false" class="text-slate-400 hover:text-slate-600">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </button>
                            </div>
                            <div class="mt-4 max-h-96 overflow-y-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-xs">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left font-bold uppercase text-600">Material / Item</th>
                                            <th class="px-3 py-2 text-left font-bold uppercase text-600">Brand / Model</th>
                                            <th class="px-3 py-2 text-center font-bold uppercase text-600">Qty</th>
                                            <th class="px-3 py-2 text-center font-bold uppercase text-600">Unit</th>
                                            <th class="px-3 py-2 text-right font-bold uppercase text-600">Rate</th>
                                            <th class="px-3 py-2 text-right font-bold uppercase text-600">GST %</th>
                                            <th class="px-3 py-2 text-right font-bold uppercase text-600">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-200">
                                        <template x-for="(item, idx) in (currentTemplate?.items || [])" :key="idx">
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-slate-900" x-text="item.name || item.material_name || item.description"></td>
                                                <td class="px-3 py-2 text-slate-600" x-text="item.brand_model || '—'"></td>
                                                <td class="px-3 py-2 text-center text-slate-700" x-text="item.qty"></td>
                                                <td class="px-3 py-2 text-center text-slate-700" x-text="item.unit || '—'"></td>
                                                <td class="px-3 py-2 text-right text-slate-700" x-text="'₹' + Number(item.price || item.rate || 0).toFixed(2)"></td>
                                                <td class="px-3 py-2 text-right text-slate-700" x-text="Number(item.gst || 0).toFixed(2) + '%'"></td>
                                                <td class="px-3 py-2 text-right font-bold text-slate-900" x-text="'₹' + Number(item.total || 0).toFixed(2)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <button type="button" @click="viewModalOpen = false" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="letterhead-settings" @class(['hidden' => $activeSettingsTab !== 'letterhead-settings'])>
            @if($errors->any() && $activeSettingsTab === 'letterhead-settings')
                <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    Please correct the highlighted fields and try again.
                </div>
            @endif

            @include('settings.letterhead.partials.form', [
                'company' => $company,
                'letterhead' => $letterhead ?? null,
            ])
        </section>

        <section id="daily-update-history" @class(['hidden' => $activeSettingsTab !== 'daily-update-history'])>
            @if($activeSettingsTab === 'daily-update-history')
                @include('daily-staff-update.partials.history', array_merge($dailyStaffUpdateHistory ?? [], [
                    'embedded' => true,
                    'historyIndexUrl' => route('settings.company.index', ['tab' => 'daily-update-history']),
                    'crmSettingsUrl' => route('settings.company.index', ['tab' => 'crm']),
                ]))
            @endif
        </section>

        <section id="company-details" @class(['grid', 'gap-6', 'lg:grid-cols-2', 'hidden' => $activeSettingsTab !== 'company-details'])>
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-lg font-bold text-slate-900">Company Details</h3>

                <form id="company-details-form" method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="mt-6 space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Company Name" />
                        <x-text-input id="name" name="name" class="mt-1 block w-full rounded-xl" value="{{ old('name', $company?->name) }}" placeholder="Enter Company Name" required />
                    </div>

                    {{-- <div>
                        <x-input-label for="company_code" value="Company Code" />
                        <x-text-input
                            id="company_code"
                            name="company_code"
                            class="mt-1 block w-full rounded-xl bg-slate-100"
                            value="{{ $company?->company_code ?? 'Will be generated automatically' }}"
                            readonly
                        />
                    </div> --}}

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="email" value="Company Email" />
                            <x-text-input id="email" name="email" type="email" required maxlength="191" class="mt-1 block w-full rounded-xl" value="{{ old('email', $company?->email) }}" placeholder="email@company.com" />
                        </div>

                        <div>
                            <x-input-label for="phone_number" value="Phone Number" />
                            <x-text-input id="phone_number" name="phone_number" type="text" required inputmode="numeric" maxlength="10" pattern="[0-9]{10}" class="mt-1 block w-full rounded-xl" value="{{ old('phone_number', $company?->phone_number) }}" placeholder="Phone Number" />
                        </div>

                        <div>
                            <x-input-label for="gst_number" value="GST Number" />
                            <x-text-input id="gst_number" name="gst_number" class="mt-1 block w-full rounded-xl" value="{{ old('gst_number', $company?->gst_number) }}" placeholder="27AAAAA0000A1Z5" />
                        </div>

                        <div>
                            <x-input-label for="pan_number" value="PAN Number" />
                            <x-text-input id="pan_number" name="pan_number" class="mt-1 block w-full rounded-xl" value="{{ old('pan_number', $company?->pan_number) }}" placeholder="ABCDE1234F" />
                        </div>

                        <div>
                            <x-input-label for="registration_number" value="Registration No" />
                            <x-text-input id="registration_number" name="registration_number" class="mt-1 block w-full rounded-xl" value="{{ old('registration_number', $company?->registration_number) }}" placeholder="Reg123343" />
                        </div>

                        <div>
                            <x-input-label for="registration_date" value="Registration Date" />
                            <x-text-input id="registration_date" name="registration_date" type="date" class="mt-1 block w-full rounded-xl" value="{{ old('registration_date', optional($company?->registration_date)->toDateString()) }}" />
                        </div>

                        <div>
                            <x-searchable-select
                                name="state"
                                id="state"
                                label="State"
                                :options="$states->map(fn ($state) => ['id' => $state->name, 'label' => $state->name, 'meta' => $state->name])->values()"
                                :selected="old('state', $company?->state)"
                                placeholder="Search state..."
                                empty-label="Select state"
                            />
                        </div>

                        <div>
                            <x-searchable-select
                                name="city"
                                id="city"
                                label="City"
                                :options="$selectedCompanyCities->map(fn ($city) => ['id' => $city->name, 'label' => $city->name, 'meta' => $city->name])->values()"
                                :selected="old('city', $company?->city)"
                                placeholder="Search city..."
                                empty-label="Select city"
                            />
                        </div>
                        <script>
                            (function () {
                                const stateSelect = document.getElementById('state');
                                const citySelect = document.getElementById('city');

                                if (!stateSelect || !citySelect) {
                                    return;
                                }

                                const optionsByState = @js($companyCityOptionsByState);

                                function getStateId() {
                                    return String(stateSelect.value || '');
                                }

                                function renderCompanyCities(selectedCity = '') {
                                    const cities = optionsByState[String(getStateId())] || [];
                                    const preservedCity = selectedCity || citySelect.dataset.selectedCity || citySelect.value || '';

                                    citySelect.innerHTML = '<option value="">Select city</option>';

                                    cities.forEach((city) => {
                                        const option = document.createElement('option');
                                        option.value = city.name;
                                        option.textContent = city.name;
                                        option.selected = city.name === preservedCity;
                                        citySelect.appendChild(option);
                                    });

                                    if (!cities.some((city) => city.name === preservedCity)) {
                                        citySelect.value = '';
                                    }

                                    citySelect.disabled = cities.length === 0;
                                    citySelect.dataset.selectedCity = citySelect.value || '';
                                    citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                                    citySelect.dispatchEvent(new Event('input', { bubbles: true }));
                                }

                                renderCompanyCities(@js($selectedCompanyCityName));
                                stateSelect.addEventListener('change', () => renderCompanyCities(''));
                                document.addEventListener('searchable-select:change', (event) => {
                                    if (event.detail?.id === 'state' || event.detail?.name === 'state') {
                                        renderCompanyCities('');
                                    }
                                });
                                citySelect.addEventListener('change', () => {
                                    citySelect.dataset.selectedCity = citySelect.value || '';
                                });
                                window.__companyDetailsRenderCities = renderCompanyCities;
                            })();
                        </script>
                    </div>

                   <div class="flex flex-wrap items-center gap-6 py-4">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="enable_quotation_generator" value="0">
                            <input type="checkbox" id="enable_quotation_generator" name="enable_quotation_generator" value="1"
                                @checked($enableQuotationGenerator)
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer">
                            <x-input-label for="enable_quotation_generator" value="Enable Quotation Form Generator" class="cursor-pointer" />
                        </div>

                        <div class="flex items-center gap-3">
                            <input type="hidden" name="enable_boq" value="0">
                            <input type="checkbox" id="enable_boq" name="enable_boq" value="1"
                                @checked($enableBoq)
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer">
                            <label for="enable_boq" class="text-sm font-medium text-slate-700 cursor-pointer">Enable BOQ Module</label>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <h4 class="text-sm font-semibold text-slate-900">Lead Form Fields</h4>
                        <p class="mt-1 text-xs text-slate-500">Choose which tax identity fields should appear on the lead add/edit forms.</p>

                        <div class="mt-4 flex flex-wrap items-center gap-6">
                            <div class="flex items-center gap-3">
                                <input type="hidden" name="show_lead_gst_field" value="0">
                                <input
                                    type="checkbox"
                                    id="show_lead_gst_field"
                                    name="show_lead_gst_field"
                                    value="1"
                                    @checked(old('show_lead_gst_field', $company?->show_lead_gst_field ?? true))
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                                >
                                <label for="show_lead_gst_field" class="cursor-pointer text-sm font-medium text-slate-700">Show GST field in Lead Form</label>
                            </div>

                            <div class="flex items-center gap-3">
                                <input type="hidden" name="show_lead_pan_field" value="0">
                                <input
                                    type="checkbox"
                                    id="show_lead_pan_field"
                                    name="show_lead_pan_field"
                                    value="1"
                                    @checked(old('show_lead_pan_field', $company?->show_lead_pan_field ?? true))
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 cursor-pointer"
                                >
                                <label for="show_lead_pan_field" class="cursor-pointer text-sm font-medium text-slate-700">Show PAN field in Lead Form</label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <x-input-label for="address" value="Address" />
                        <textarea
                            id="address"
                            name="address"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            placeholder="Enter full office address"
                            autocomplete="off"
                            data-location-autocomplete="company-address"
                            data-autocomplete-url="{{ config('services.location_autocomplete.url') }}"
                        >{{ old('address', $company?->address) }}</textarea>
                        <input type="hidden" id="latitude" name="latitude" value="{{ $selectedCompanyLatitude }}">
                        <input type="hidden" id="longitude" name="longitude" value="{{ $selectedCompanyLongitude }}">
                        <div id="company-address-suggestions" class="mt-2 hidden overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg"></div>
                    </div>

                    <div>
                        <div class="max-w-md p-4 border border-dashed border-slate-200 rounded-2xl">
                            <x-input-label for="logo" value="Company Logo" class="mb-2" />
                            <input id="logo" name="logo" type="file" class="block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            @if($company?->logo_url)
                                <img src="{{ $company->logo_url }}" alt="Company logo" class="mt-3 h-12 w-auto object-contain">
                            @endif
                        </div>
                    </div>

                    <div class="flex justify-end pt-4">
                        <div class="flex w-full flex-col gap-3 md:w-auto md:flex-row">
                            <x-primary-button class="w-full md:w-auto justify-center">
                                Save Company Details
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>

           <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm h-fit">
                <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-800">Current Company Profile</h3>
                </div>

                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-y-4">
                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Company Name
                            </dt>
                            <dd class="text-sm font-semibold text-slate-900 sm:text-right">
                                {{ $company?->name ?? 'N/A' }}
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Company Code
                            </dt>
                            <dd class="text-sm font-semibold text-sky-600 sm:text-right">
                                {{ $company?->company_code ?? 'N/A' }}
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Email Address
                            </dt>
                            <dd class="text-sm font-medium text-indigo-600 sm:text-right">
                                {{ $company?->email ?: 'N/A' }}
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Phone
                            </dt>
                            <dd class="text-sm font-medium text-slate-900 sm:text-right">
                                {{ $company?->phone_number ?: 'N/A' }}
                            </dd>
                        </div>

                        <div class="grid grid-cols-2 gap-4 border-b border-slate-50 pb-3">
                            <div>
                                <dt class="text-[10px] font-bold uppercase text-slate-400">GST Number</dt>
                                <dd class="text-sm font-mono text-slate-700">{{ $company?->gst_number ?: 'N/A' }}</dd>
                            </div>
                            <div class="text-right">
                                <dt class="text-[10px] font-bold uppercase text-slate-400">PAN Number</dt>
                                <dd class="text-sm font-mono text-slate-700">{{ $company?->pan_number ?: 'N/A' }}</dd>
                            </div>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Registration
                            </dt>
                            <dd class="text-sm text-slate-900 sm:text-right">
                                <span class="font-medium">{{ $company?->registration_number ?: 'N/A' }}</span>
                                <span class="mx-1 text-slate-300">•</span>
                                <span class="text-slate-500">{{ $company?->registration_date ? $company->registration_date->format('d M Y') : 'N/A' }}</span>
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-start justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                Address
                            </dt>
                            <dd class="max-w-[200px] text-sm text-slate-600 sm:text-right">
                                {{ $company?->address ?: 'N/A' }}
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                City
                            </dt>
                            <dd class="text-sm text-slate-900 sm:text-right">
                                {{ $company?->city ?: 'N/A' }}
                            </dd>
                        </div>

                        <div class="group flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-slate-50 pb-3 last:border-0 last:pb-0">
                            <dt class="text-xs font-medium uppercase tracking-wider text-slate-400 sm:text-sm sm:normal-case sm:text-slate-500">
                                State
                            </dt>
                            <dd class="text-sm text-slate-900 sm:text-right">
                                {{ $company?->state ?: 'N/A' }}
                            </dd>
                        </div>

                        <!-- <div class="flex items-center justify-between pt-2">
                            <dt class="text-xs font-medium text-slate-500">Business Type</dt>
                            <dd class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                {{ $company?->business_type ?: 'N/A' }}
                            </dd>
                        </div> -->
                    </dl>
                </div>
            </div>
        </section>

        @php
            $previewPrintLogoUrl = $company?->print_logo_url ?: $company?->logo_url;
            $previewDocGroups = [
                [
                    'name' => 'CCTV',
                    'items' => [
                        ['description' => 'Outdoor Bullet Camera', 'unit' => 'Set', 'qty' => 1, 'rate' => 150.00, 'amount' => 150.00],
                        ['description' => 'NVR 4 Channel', 'unit' => 'Set', 'qty' => 1, 'rate' => 110.00, 'amount' => 110.00],
                        ['description' => 'CAT6 Cable Roll', 'unit' => 'Roll', 'qty' => 2, 'rate' => 119.00, 'amount' => 238.00],
                    ],
                ],
                [
                    'name' => 'Accessories',
                    'items' => [
                        ['description' => 'Mounting Box', 'unit' => 'Nos', 'qty' => 4, 'rate' => 500.00, 'amount' => 500.00],
                    ],
                ],
                [
                    'name' => 'Installation',
                    'items' => [
                        ['description' => 'System Testing & Setup', 'unit' => 'Day', 'qty' => 1, 'rate' => 676.00, 'amount' => 676.00],
                    ],
                ],
            ];
            $previewSubtotal = collect($previewDocGroups)->flatMap(fn ($group) => $group['items'])->sum('amount');
            $previewDiscount = 0;
            $previewGstPercent = 18;
            $previewTaxable = max(0, $previewSubtotal - $previewDiscount);
            $previewTax = ($previewTaxable * $previewGstPercent) / 100;
            $previewGrandTotal = $previewTaxable + $previewTax;
        @endphp

        <section id="print-branding" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'print-branding'])>
            <div class="max-w-6xl">
                <h3 class="text-lg font-bold text-slate-900">Print Branding</h3>
                <p class="mt-1 text-sm text-slate-500">
                    Upload a logo that will be used in PDF and print templates. If this logo is present, the document header will show the logo and hide the company name in the branding area.
                </p>

                <div class="mt-6 space-y-6">
                    <form id="print-branding-form" method="POST" action="{{ route('settings.company.update') }}" enctype="multipart/form-data" class="space-y-5" novalidate>
                        @csrf

                        <input type="hidden" name="enable_quotation_generator" value="{{ $enableQuotationGenerator ? 1 : 0 }}">
                        <input type="hidden" name="enable_boq" value="{{ $enableBoq ? 1 : 0 }}">
                        <input type="hidden" id="enable_print_settings_value" name="enable_print_settings" value="{{ $company?->enable_print_settings ? 1 : 0 }}">
                        <input type="hidden" name="pf_enabled" value="{{ $company?->pf_enabled ? 1 : 0 }}">
                        @foreach(($company?->working_days ?? []) as $day)
                            <input type="hidden" name="working_days[]" value="{{ $day }}">
                        @endforeach

                        @php
                            $printSettings = $company?->effective_print_settings ?? [];
                            $storedPrintSettings = is_array($company?->print_settings ?? null) ? $company->print_settings : [];
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                            <div class="flex items-start gap-3">
                                <input type="checkbox" id="enable_print_settings" value="1" @checked(old('enable_print_settings', $company?->enable_print_settings)) class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <div>
                                    <label for="enable_print_settings" class="text-sm font-semibold text-slate-800">Enable custom print settings</label>
                                    <p class="mt-1 text-sm text-slate-500">When enabled, the company can control page margins, accent color, and letterhead behavior for print documents.</p>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="print_settings[accent_color]" value="Accent Color" />
                                        <input id="print_settings[accent_color]" name="print_settings[accent_color]" type="color" value="{{ old('print_settings.accent_color', $printSettings['accent_color'] ?? '#4f46e5') }}" class="mt-1 h-11 w-24 rounded-lg border border-slate-300 bg-white p-1">
                                    </div>
                                    <div>
                                        <x-input-label for="print_settings[page_margin_right]" value="Right Margin (mm)" />
                                        <x-text-input id="print_settings[page_margin_right]" name="print_settings[page_margin_right]" type="number" min="0" max="100" class="mt-1 block w-full rounded-xl" value="{{ old('print_settings.page_margin_right', $printSettings['page_margin_right'] ?? 20) }}" />
                                    </div>
                                    <div>
                                        <x-input-label for="print_settings[page_margin_left]" value="Left Margin (mm)" />
                                        <x-text-input id="print_settings[page_margin_left]" name="print_settings[page_margin_left]" type="number" min="0" max="100" class="mt-1 block w-full rounded-xl" value="{{ old('print_settings.page_margin_left', $printSettings['page_margin_left'] ?? 20) }}" />
                                    </div>
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <x-input-label for="print_settings[page_margin_top]" value="Top Margin (mm)" />
                                        <x-text-input id="print_settings[page_margin_top]" name="print_settings[page_margin_top]" type="number" min="0" max="100" class="mt-1 block w-full rounded-xl" value="{{ old('print_settings.page_margin_top', $printSettings['page_margin_top'] ?? 20) }}" />
                                    </div>
                                    <div>
                                        <x-input-label for="print_settings[page_margin_bottom]" value="Bottom Margin (mm)" />
                                        <x-text-input id="print_settings[page_margin_bottom]" name="print_settings[page_margin_bottom]" type="number" min="0" max="100" class="mt-1 block w-full rounded-xl" value="{{ old('print_settings.page_margin_bottom', $printSettings['page_margin_bottom'] ?? 20) }}" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-dashed border-slate-200 p-5">
                            <x-input-label for="letterhead_file" value="Letterhead File (used as print background)" class="mb-2" />
                            <input id="letterhead_file" name="letterhead_file" type="file" accept="image/png,image/jpeg,image/webp,application/pdf" data-max-size="52428800" data-max-size-label="50 MB" class="js-file-size-validate block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-2 text-xs text-slate-500">Upload your pre-designed letterhead as an image (PNG/JPG) or a PDF, up to 50 MB. It will be used as the full-page background on every printed document. Use the format builder below to place your invoice/quote fields exactly where they belong on the letterhead, or set the margins below to place them uniformly.</p>
                            <x-input-error :messages="$errors->get('letterhead_file')" class="mt-2" />
                            @if($company?->letterhead_url)
                                <div class="mt-4 flex items-center gap-3">
                                    <img src="{{ $company->letterhead_url }}" alt="Current letterhead" class="h-20 w-auto rounded-lg border border-slate-200 bg-white object-contain p-1">
                                    <div class="text-sm text-slate-600">
                                        Current letterhead is used as the background for all print/PDF documents when custom print settings are enabled.
                                        <a href="{{ route('settings.company.letterhead-format.builder') }}" class="ml-1 font-semibold text-indigo-600 hover:underline">Set Up Format &rarr;</a>
                                        @if($company->hasLetterheadFieldPositions())
                                            <span class="ml-1 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">Format configured</span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rounded-2xl border border-dashed border-slate-200 p-5">
                            <x-input-label for="print_logo" value="Print Logo for PDF/Print" class="mb-2" />
                            <input id="print_logo" name="print_logo" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml" data-max-size="52428800" data-max-size-label="50 MB" class="js-file-size-validate block w-full text-xs text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                            <p class="mt-2 text-xs text-slate-500">Upload up to 50 MB. The image is automatically resized and compressed on save so it stays lightweight for print/PDF use.</p>
                            <x-input-error :messages="$errors->get('print_logo')" class="mt-2" />
                            <div class="mt-4 flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <input type="hidden" name="print_settings[show_quotation_logo]" value="0">
                                <input
                                    type="checkbox"
                                    id="show_quotation_logo"
                                    name="print_settings[show_quotation_logo]"
                                    value="1"
                                    @checked(old('print_settings.show_quotation_logo', data_get($storedPrintSettings, 'show_quotation_logo', true)))
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                >
                                <div>
                                    <label for="show_quotation_logo" class="text-sm font-semibold text-slate-800">Show logo on invoice and quotation print pages</label>
                                    <p class="mt-1 text-sm text-slate-500">When disabled, every invoice and quotation print page will use the company name instead of the logo.</p>
                                </div>
                            </div>
                            @if($company?->print_logo_url)
                                <div class="mt-4 flex items-center gap-3">
                                    <img src="{{ $company->print_logo_url }}" alt="Current print logo" class="h-14 w-auto rounded-lg border border-slate-200 bg-white object-contain p-2">
                                    <div class="text-sm text-slate-600">
                                        Current logo will be used in print/PDF headers.
                                    </div>
                                </div>
                            @elseif($company?->logo_url)
                                <div class="mt-4 flex items-center gap-3">
                                    <img src="{{ $company->logo_url }}" alt="Company logo fallback" class="h-14 w-auto rounded-lg border border-slate-200 bg-white object-contain p-2">
                                    <div class="text-sm text-slate-600">
                                        No print logo set yet. The company logo is currently used for print/PDF headers.
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button class="w-full md:w-auto justify-center">
                                Save Print Branding
                            </x-primary-button>
                        </div>
                    </form>


                </div>
            </div>
        </section>

        <section id="lead-pipeline" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'lead-pipeline'])>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Lead Pipeline Settings</h3>
                    <p class="mt-1 text-sm text-slate-500">Manage your lead pipeline stages, lead sources, categories, and custom fields.</p>
                </div>
                @if($isCompanyAdmin)
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-status-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add Stage</button>
                @endif
            </div>

            <div class="mt-6">
                <div class="overflow-hidden rounded-[22px] border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="w-10 px-5 py-4"></th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Color</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Stage Name</th>
                                @if($isCompanyAdmin)
                                    <th class="px-5 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="status-sortable" class="divide-y divide-slate-200 bg-white">
                            @foreach($statuses as $status)
                                @php
                                    $isSystemStatus = $status->isSystemStage();
                                @endphp
                                <tr data-id="{{ $status->id }}" data-reorderable="{{ $isSystemStatus ? '0' : '1' }}" class="hover:bg-slate-50/60">
                                    <td class="px-5 py-4">
                                        @if(!$isSystemStatus)
                                            <div class="cursor-move text-slate-400 transition hover:text-slate-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                </svg>
                                            </div>
                                        @else
                                            <span class="inline-flex h-5 items-center rounded-full bg-slate-100 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Fixed</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="h-6 w-6 rounded-xl shadow-sm" style="background-color: {{ $status->color }}"></div>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $status->name }}</span>
                                            @if($isSystemStatus)
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200">System</span>
                                            @endif
                                        </div>
                                    </td>
                                    @if($isCompanyAdmin)
                                        <td class="px-5 py-4 text-right text-sm font-medium">
                                            @if(!$isSystemStatus)
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-status-edit', { detail: { id: {{ $status->id }}, name: @js($status->name), color: @js($status->color) } }))"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>

                                                    </button>
                                                    <form action="{{ route('settings.statuses.destroy', $status) }}" method="POST" class="inline company-status-delete-form" data-delete-message="Are you sure? This will fail if there are leads in this stage.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">Locked</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="px-6 py-4">
                    {{ $statuses->links() }}
                </div>
            </div>

            <div class="mt-8 grid gap-6">
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-lg font-bold text-slate-950">Lead Source Master List</h4>
                            <p class="mt-1 text-sm text-slate-500">Add single lead sources and manage the list shown in the lead form dropdown.</p>
                        </div>
                        @if($isCompanyAdmin)
                            <button
                                type="button"
                                class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800"
                                onclick="window.dispatchEvent(new CustomEvent('open-lead-source-modal'))"
                            >
                                Add Source
                            </button>
                        @endif
                    </div>

                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Lead Source</th>
                                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Referral Field</th>
                                    @if($isCompanyAdmin)
                                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @forelse($leadSources as $index => $leadSource)
                                    <tr class="hover:bg-slate-50/60">
                                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $leadSource['name'] }}</td>
                                        <td class="px-6 py-4 text-sm text-slate-600">
                                            {{ $leadSource['show_referral_person_name'] ? 'Show Referral Person Name' : 'Hidden' }}
                                        </td>
                                        @if($isCompanyAdmin)
                                            <td class="px-6 py-4 text-right text-sm font-medium">
                                                <div class="flex items-center justify-end gap-3">
                                                    <button
                                                        type="button"
                                                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-lead-source-edit', { detail: { key: @js($leadSource['key']), name: @js($leadSource['name']), showReferralPersonName: @js($leadSource['show_referral_person_name']) } }))"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                    </button>

                                                    <form action="{{ route('settings.company.lead-sources.destroy', $leadSource['key']) }}" method="POST" class="company-lead-source-delete-form inline" data-delete-message="Are you sure you want to delete the lead source '{{ $leadSource['name'] }}'?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $isCompanyAdmin ? 3 : 2 }}" class="px-6 py-10 text-center text-sm text-slate-500">No lead sources added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 px-2">
                        {{ $leadSources->links('pagination::tailwind') }}
                    </div>

                    @if($errors->has('lead_source_name'))
                        <p class="mt-3 text-sm text-rose-600">{{ $errors->first('lead_source_name') }}</p>
                    @endif
                </div>

                 @if($isCompanyAdmin)
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h4 class="text-lg font-bold text-slate-950">Default Lead Assignee</h4>
                                <p class="mt-1 text-sm text-slate-500">Select an active staff employee to assign all new leads to by default. If no staff is selected, the system continues with the current automatic lead assignment flow.</p>
                            </div>
                        </div>

                        <form method="POST" action="{{ route('settings.company.update') }}" class="mt-5 space-y-4 max-w-xl">
                            @csrf
                            <div>
                                <x-input-label for="lead_setting_default_lead_assignee_id" value="Default Assignee Staff (Active Employees)" />
                                <select
                                    id="lead_setting_default_lead_assignee_id"
                                    name="default_lead_assignee_id"
                                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                >
                                    <option value="">-- No default staff (Auto-assign / current flow) --</option>
                                    @foreach($eligibleStaffUsers as $staffUser)
                                        <option
                                            value="{{ $staffUser->id }}"
                                            @selected((string) old('default_lead_assignee_id', $company?->default_lead_assignee_id) === (string) $staffUser->id)
                                        >
                                            {{ $staffUser->name }} ({{ $staffUser->email }})
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('default_lead_assignee_id')" class="mt-2" />
                            </div>

                            <div class="flex justify-start">
                                <x-primary-button>Save Default Lead Assignee</x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
<!-- 
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <h4 class="text-lg font-bold text-slate-950">Quotation & BOQ Numbering</h4>
                    <p class="mt-1 text-sm text-slate-500">Configure the quotation prefix and starting sequence.</p>
                    <form id="crm-numbering-form" method="POST" action="{{ route('settings.company.update') }}" class="mt-4 grid gap-4 md:grid-cols-2" novalidate>
                        @csrf
                        <div>
                            <x-input-label for="quotation_prefix" value="Quotation Prefix" />
                            <x-text-input id="quotation_prefix" name="quotation_prefix" class="mt-1 block w-full rounded-xl" value="{{ old('quotation_prefix', $company?->quotation_prefix) }}" placeholder="QTN" minlength="1" maxlength="50" />
                            <x-input-error :messages="$errors->get('quotation_prefix')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="quotation_year" value="Quotation Year" />
                            <x-text-input id="quotation_year" name="quotation_year" type="number" min="2000" max="2100" class="mt-1 block w-full rounded-xl" value="{{ old('quotation_year', $company?->quotation_year ?? date('Y')) }}" placeholder="{{ date('Y') }}" />
                            <x-input-error :messages="$errors->get('quotation_year')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="quotation_sequence" value="Quotation Sequence" />
                            <x-text-input id="quotation_sequence" name="quotation_sequence" type="number" min="1" class="mt-1 block w-full rounded-xl" value="{{ old('quotation_sequence', $company?->quotation_sequence) }}" placeholder="1" />
                            <x-input-error :messages="$errors->get('quotation_sequence')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <x-primary-button>Save Quotation Numbering</x-primary-button>
                        </div>
                    </form>
                </div> -->
            </div>
               

            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h4 class="text-lg font-bold text-slate-950">Your Lead Categories</h4>
                                <p class="mt-1 text-sm text-slate-500">This list includes global categories and company categories.</p>
                            </div>
                            @if($isCompanyAdmin)
                                <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-category-create-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add Category</button>
                            @endif
                        </div>

                        <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Name</th>
                                        @if($isCompanyAdmin)
                                            <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @forelse($categories as $category)
                                        <tr class="hover:bg-slate-50/60">
                                            <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $category->name }}</td>
                                            @if($isCompanyAdmin)
                                                <td class="px-6 py-4 text-right text-sm font-medium">
                                                    @if($category->company_id)
                                                        <div class="flex items-center justify-end gap-3">
                                                            <button type="button"
                                                                class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                                                onclick="window.dispatchEvent(new CustomEvent('open-category-edit', { detail: { id: {{ $category->id }}, name: @js($category->name) } }))">
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>

                                                            </button>

                                                            <form action="{{ route('settings.categories.destroy', $category) }}" method="POST" class="company-category-delete-form inline" data-delete-message="Are you sure you want to delete the category '{{ $category->name }}'?">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>

                                                                </button>
                                                            </form>
                                                        </div>
                                                    @else
                                                        <span class="text-slate-400 italic text-xs">Global</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $isCompanyAdmin ? 2 : 1 }}" class="px-6 py-10 text-center text-sm text-slate-500">No categories found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 px-2">
                            {{ $categories->links('pagination::tailwind') }}
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
    <div class="flex items-start justify-between gap-4">
        <div>
            <h4 class="text-lg font-bold text-slate-950">Lead Custom Fields</h4>
            <p class="mt-1 text-sm text-slate-500">Add company-specific fields that appear on the lead create and edit forms.</p>
        </div>
        @if($isCompanyAdmin)
            <button
                type="button"
                class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800"
                onclick="window.dispatchEvent(new CustomEvent('open-lead-custom-field-modal'))"
            >
                Add Field
            </button>
        @endif
    </div>

    <!-- Updated wrapper with horizontal scrolling (overflow-x-auto) -->
    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Label</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Type</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Validation</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Filter</th>
                    <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">BOQ/Quotation</th>
                    @if($isCompanyAdmin)
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
                @forelse($leadCustomFields as $index => $field)
                    <tr class="hover:bg-slate-50/60">
                        <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $field['label'] }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::headline($field['type']) }}</td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $field['required'] ? 'Required' : 'Optional' }}</span>
                                @if(in_array($field['type'], ['dropdown', 'radiobutton'], true))
                                    <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ count($field['options'] ?? []) }} options</span>
                                @endif
                            </div>
                            @if(!empty($field['help_text']))
                                <p class="mt-2 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            @if(!empty($field['show_in_filters']))
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Shown in filters</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Hidden</span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-sm text-slate-600">
                            @if(!empty($field['show_in_boq_quotation']))
                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">Shown in BOQ/Quotation</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Hidden</span>
                            @endif
                        </td>
                        @if($isCompanyAdmin)
                            <td class="px-6 py-4 text-right text-sm font-medium whitespace-nowrap">
                                <div class="flex items-center justify-end gap-3">
                                    <button
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                        onclick="window.dispatchEvent(new CustomEvent('open-lead-custom-field-edit', { detail: { key: @js($field['key']), label: @js($field['label']), type: @js($field['type']), required: @js((bool) $field['required']), showInFilters: @js((bool) ($field['show_in_filters'] ?? false)), showInBoqQuotation: @js((bool) ($field['show_in_boq_quotation'] ?? false)), placeholder: @js($field['placeholder'] ?? ''), helpText: @js($field['help_text'] ?? ''), options: @js(implode(chr(10), $field['options'] ?? [])) } }))"
                                    >
                                        <svg class="h-4 w-4 shrink-0" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                    </button>

                                    <form action="{{ route('settings.company.lead-custom-fields.destroy', $field['key']) }}" method="POST" class="company-lead-custom-field-delete-form inline-flex shrink-0" data-delete-message="Are you sure you want to delete the custom field '{{ $field['label'] }}'?">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                            <svg class="h-4 w-4 shrink-0" width="16" height="16" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isCompanyAdmin ? 7 : 6 }}" class="px-6 py-10 text-center text-sm text-slate-500">No custom fields added yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 px-2">
        {{ $leadCustomFields->links('pagination::tailwind') }}
    </div>

    @if($errors->has('label'))
        <p class="mt-3 text-sm text-rose-600">{{ $errors->first('label') }}</p>
    @endif
    @if($errors->has('options'))
        <p class="mt-1 text-sm text-rose-600">{{ $errors->first('options') }}</p>
    @endif
</div>

            
            </div>
        </section>

        <section id="project-pipeline" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'project-pipeline'])>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Project Pipeline Settings</h3>
                    <p class="mt-1 text-sm text-slate-500">Manage project numbering, custom fields, contractors, and pipeline stages.</p>
                </div>
                @if($isCompanyAdmin)
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-project-stage-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add Stage</button>
                @endif
            </div>

            <div class="mt-6">
                <div class="overflow-hidden rounded-[22px] border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="w-10 px-5 py-4"></th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Color</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Stage Name</th>
                                @if($isCompanyAdmin)
                                    <th class="px-5 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="project-stage-sortable" class="divide-y divide-slate-200 bg-white">
                            @forelse($projectStages as $projectStage)
                                @php
                                    $isSystemProjectStage = $projectStage->isSystemStage();
                                    $isReorderLockedProjectStage = $projectStage->isReorderLocked();
                                @endphp
                                <tr data-id="{{ $projectStage->id }}" data-reorderable="{{ $isReorderLockedProjectStage ? '0' : '1' }}" class="hover:bg-slate-50/60">
                                    <td class="px-5 py-4">
                                        @if(!$isReorderLockedProjectStage)
                                            <div class="cursor-move text-slate-400 transition hover:text-slate-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                </svg>
                                            </div>
                                        @else
                                            <span class="inline-flex h-5 items-center rounded-full bg-slate-100 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Fixed</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="h-6 w-6 rounded-xl shadow-sm" style="background-color: {{ $projectStage->color }}"></div>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $projectStage->name }}</span>
                                            @if($isSystemProjectStage)
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200">System</span>
                                            @endif
                                        </div>
                                    </td>
                                    @if($isCompanyAdmin)
                                        <td class="px-5 py-4 text-right text-sm font-medium">
                                            @if(!$isSystemProjectStage)
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-project-stage-edit', { detail: { id: {{ $projectStage->id }}, name: @js($projectStage->name), color: @js($projectStage->color) } }))"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                    </button>
                                                    <form action="{{ route('settings.project-stages.destroy', $projectStage) }}" method="POST" class="inline company-project-stage-delete-form" data-delete-message="Are you sure? This will fail if there are projects in this stage.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">Locked</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isCompanyAdmin ? 4 : 3 }}" class="px-6 py-8 text-center text-sm text-slate-500">No project stages found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-4 px-1">
                    {{ $statuses->links() }}
                </div>
            </div>



        <br>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <h4 class="text-lg font-bold text-slate-950">Project Reference Format</h4>
                    <p class="mt-1 text-sm text-slate-500">Prefix + year + sequence, e.g., <span class="font-semibold text-slate-700">PRJ-2026-0001</span>.</p>

                    <form id="project-settings-form" method="POST" action="{{ route('settings.company.update') }}" class="mt-4 grid gap-4 md:grid-cols-2" novalidate>
                        @csrf
                        <div>
                            <x-input-label for="project_reference_prefix" value="Prefix" />
                            <x-text-input id="project_reference_prefix" name="project_reference_prefix" maxlength="50" class="mt-1 block w-full rounded-xl" value="{{ old('project_reference_prefix', $company?->project_reference_prefix) }}" placeholder="PRJ" />
                        </div>
                        <div>
                            <x-input-label for="project_reference_year" value="Year" />
                            <x-text-input id="project_reference_year" name="project_reference_year" type="number" class="mt-1 block w-full rounded-xl" value="{{ old('project_reference_year', $company?->project_reference_year ?? now()->year) }}" />
                        </div>
                        <div>
                            <x-input-label for="project_reference_sequence" value="Starting Sequence" />
                            <x-text-input id="project_reference_sequence" name="project_reference_sequence" type="number" class="mt-1 block w-full rounded-xl" value="{{ old('project_reference_sequence', $company?->project_reference_sequence) }}" placeholder="1" />
                        </div>
                        <div class="flex items-end">
                            <x-primary-button type="submit">Save Project Format</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <h4 class="text-lg font-bold text-slate-950">Delivery Challan Numbering</h4>
                    <p class="mt-1 text-sm text-slate-500">Configure the challan prefix and starting sequence.</p>

                    <form id="challan-settings-form" method="POST" action="{{ route('settings.company.update') }}" class="mt-4 grid gap-4 md:grid-cols-2" novalidate>
                        @csrf
                        <div>
                            <x-input-label for="challan_prefix" value="Challan Prefix" />
                            <x-text-input id="challan_prefix" name="challan_prefix" maxlength="50" class="mt-1 block w-full rounded-xl" value="{{ old('challan_prefix', $company?->challan_prefix) }}" placeholder="CHL" />
                        </div>
                        <div>
                            <x-input-label for="challan_sequence" value="Challan Sequence" />
                            <x-text-input id="challan_sequence" name="challan_sequence" type="number" class="mt-1 block w-full rounded-xl" value="{{ old('challan_sequence', $company?->challan_sequence) }}" placeholder="1" data-check-url="{{ route('settings.company.challan-sequence.check') }}" />
                            <p id="challan-sequence-note" class="mt-2 hidden text-xs font-medium"></p>
                        </div>
                        <div class="md:col-span-2 flex justify-end">
                            <x-primary-button type="submit">Save Challan Numbering</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="text-lg font-bold text-slate-950">Project Custom Fields</h4>
                        <p class="mt-1 text-sm text-slate-500">Add company-specific fields that appear on the project create and edit forms.</p>
                    </div>
                    @if($isCompanyAdmin)
                        <button
                            type="button"
                            class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800"
                            onclick="window.dispatchEvent(new CustomEvent('open-project-custom-field-modal'))"
                        >
                            Add Field
                        </button>
                    @endif
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Label</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Type</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Validation</th>
                                <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-500">Filter</th>
                                @if($isCompanyAdmin)
                                    <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($projectCustomFields ?? [] as $index => $field)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-6 py-4 text-sm font-semibold text-slate-900">{{ $field['label'] }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">{{ \Illuminate\Support\Str::headline($field['type']) }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ $field['required'] ? 'Required' : 'Optional' }}</span>
                                            @if(in_array($field['type'], ['dropdown', 'radiobutton'], true))
                                                <span class="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">{{ count($field['options'] ?? []) }} options</span>
                                            @endif
                                        </div>
                                        @if(!empty($field['help_text']))
                                            <p class="mt-2 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-600">
                                        @if(!empty($field['show_in_filters']))
                                            <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Shown in filters</span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-500">Hidden</span>
                                        @endif
                                    </td>
                                    @if($isCompanyAdmin)
                                        <td class="px-6 py-4 text-right text-sm font-medium">
                                            <div class="flex items-center justify-end gap-3">
                                                <button
                                                    type="button"
                                                    class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                                    onclick="window.dispatchEvent(new CustomEvent('open-project-custom-field-edit', { detail: { key: @js($field['key']), label: @js($field['label']), type: @js($field['type']), required: @js((bool) $field['required']), showInFilters: @js((bool) ($field['show_in_filters'] ?? false)), placeholder: @js($field['placeholder'] ?? ''), helpText: @js($field['help_text'] ?? ''), options: @js(implode(chr(10), $field['options'] ?? [])) } }))"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                </button>

                                                    <form action="{{ route('settings.company.project-custom-fields.destroy', $field['key']) }}" method="POST" class="company-project-custom-field-delete-form inline" data-delete-message="Are you sure you want to delete the custom field '{{ $field['label'] }}'?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isCompanyAdmin ? 6 : 5 }}" class="px-6 py-10 text-center text-sm text-slate-500">No custom fields added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[420px,1fr]">
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <h4 class="text-lg font-bold text-slate-950">Contractor Register</h4>
                    <p class="mt-1 text-sm text-slate-500">Save contractors once, then assign them to projects from the overview tab.</p>

                    <form id="contractor-create-form" method="POST" action="{{ route('settings.company.contractors.store') }}" class="mt-4 space-y-4" novalidate>
                        @csrf
                        <div>
                            <x-input-label for="contractor_name" value="Contractor Name" />
                            <x-text-input id="contractor_name" name="name" maxlength="191" class="mt-1 block w-full rounded-xl" value="{{ old('name') }}" placeholder="Enter contractor name" />
                        </div>
                        <div>
                            <x-input-label for="contractor_phone_number" value="Contact Number" />
                            <x-text-input id="contractor_phone_number" name="phone_number" inputmode="numeric" maxlength="10" class="mt-1 block w-full rounded-xl" value="{{ old('phone_number') }}" placeholder="9876543210" />
                        </div>
                        <div>
                            <x-input-label for="contractor_email" value="Email Address" />
                            <x-text-input id="contractor_email" name="email" type="email" maxlength="191" class="mt-1 block w-full rounded-xl" value="{{ old('email') }}" placeholder="contractor@example.com" />
                        </div>
                        <div>
                            <x-input-label for="contractor_referral_name" value="Referral Name" />
                            <x-text-input id="contractor_referral_name" name="referral_name" maxlength="191" class="mt-1 block w-full rounded-xl" value="{{ old('referral_name') }}" placeholder="Referral person name" />
                        </div>
                        <div>
                            <x-input-label for="contractor_address" value="Address" />
                            <div class="relative mt-1">
                                <textarea id="contractor_address" name="address" rows="3" maxlength="1000" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Contractor address" autocomplete="off" data-location-autocomplete="contractor-address" data-autocomplete-url="{{ config('services.location_autocomplete.url') }}">{{ old('address') }}</textarea>
                                <div
                                    id="contractor-address-suggestions"
                                    class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                                    role="listbox"
                                    aria-label="Contractor address suggestions"
                                ></div>
                            </div>
                        </div>
                        <div>
                            <x-input-label for="contractor_notes" value="Notes" />
                            <textarea id="contractor_notes" name="notes" rows="3" maxlength="2000" class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Additional notes or remarks">{{ old('notes') }}</textarea>
                        </div>
                        <div class="flex justify-end">
                            <x-primary-button id="contractor-create-submit">Add Contractor</x-primary-button>
                        </div>
                    </form>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h4 class="text-base font-bold text-slate-950">Saved Contractors</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Contractor</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Contact</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Referral</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-500">Projects</th>
                                    <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($contractors as $contractor)
                                    <tr>
                                        <td class="px-4 py-3 text-sm">
                                            <div class="font-semibold text-slate-900">{{ $contractor->name }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $contractor->address ?: 'No address added' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-700">
                                            <div>{{ $contractor->phone_number ?: 'N/A' }}</div>
                                            <div class="text-xs text-slate-500">{{ $contractor->email ?: 'No email' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-sm text-slate-700">{{ $contractor->referral_name ?: 'N/A' }}</td>
                                        <td class="px-4 py-3 text-center text-sm font-semibold text-slate-700">{{ $contractor->projects_count }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                type="button"
                                                class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                                                data-contractor-edit
                                                data-id="{{ $contractor->id }}"
                                                data-name="{{ $contractor->name }}"
                                                data-phone-number="{{ $contractor->phone_number }}"
                                                data-email="{{ $contractor->email }}"
                                                data-referral-name="{{ $contractor->referral_name }}"
                                                data-address="{{ $contractor->address }}"
                                                data-notes="{{ $contractor->notes }}"
                                            >
                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                            </button>
                                            <form method="POST" action="{{ route('settings.company.contractors.destroy', $contractor) }}" class="inline contractor-delete-form" data-delete-message="Delete this contractor?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No contractors added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>


        </section>

        <section id="service-settings" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'service-settings'])>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Service Settings</h3>
                    <p class="mt-1 text-sm text-slate-500">Manage your service kanban stages and their order. New, Open, and Close stay fixed.</p>
                </div>
                @if($isCompanyAdmin)
                    <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-service-stage-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add Stage</button>
                @endif
            </div>

            <div class="mt-6">
                <div class="overflow-hidden rounded-[22px] border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="w-10 px-5 py-4"></th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Color</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Stage Name</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Ticket Status</th>
                                <th class="px-5 py-4 text-left text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Description</th>
                                @if($isCompanyAdmin)
                                    <th class="px-5 py-4 text-right text-[11px] font-semibold uppercase tracking-[0.16em] text-500">Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody id="service-stage-sortable" class="divide-y divide-slate-200 bg-white">
                            @forelse($serviceStages as $serviceStage)
                                @php
                                    $isFixedServiceStage = $serviceStage->isFixedStage();
                                    $isReorderLockedServiceStage = $serviceStage->isReorderLocked();
                                @endphp
                                <tr data-id="{{ $serviceStage->id }}" data-reorderable="{{ $isReorderLockedServiceStage ? '0' : '1' }}" class="hover:bg-slate-50/60">
                                    <td class="px-5 py-4 align-top">
                                        @if(!$isReorderLockedServiceStage)
                                            <div class="cursor-move text-slate-400 transition hover:text-slate-600">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8h16M4 16h16" />
                                                </svg>
                                            </div>
                                        @else
                                            <span class="inline-flex h-5 items-center rounded-full bg-slate-100 px-2 text-[10px] font-semibold uppercase tracking-wide text-slate-500">Fixed</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <div class="h-6 w-6 rounded-xl shadow-sm" style="background-color: {{ $serviceStage->color }}"></div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-sm font-semibold text-slate-900">
                                        <div class="flex items-center gap-2">
                                            <span>{{ $serviceStage->name }}</span>
                                            @if($isFixedServiceStage)
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700 ring-1 ring-inset ring-emerald-200">System</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-sm text-slate-600">
                                        {{ $serviceStatusOptions[$serviceStage->mapped_status] ?? \Illuminate\Support\Str::of($serviceStage->mapped_status)->replace('_', ' ')->title() }}
                                    </td>
                                    <td class="px-5 py-4 align-top text-sm text-slate-600">
                                        {{ $serviceStage->description ?: 'No description added.' }}
                                    </td>
                                    @if($isCompanyAdmin)
                                        <td class="px-5 py-4 align-top text-right text-sm font-medium">
                                            @if(!$isFixedServiceStage)
                                                <div class="flex items-center justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-200"
                                                        onclick="window.dispatchEvent(new CustomEvent('open-service-stage-edit', { detail: { id: {{ $serviceStage->id }}, name: @js($serviceStage->name), color: @js($serviceStage->color), description: @js($serviceStage->description), mappedStatus: @js($serviceStage->mapped_status) } }))"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                    </button>
                                                    <form action="{{ route('settings.service-stages.destroy', $serviceStage) }}" method="POST" class="inline company-service-stage-delete-form" data-delete-message="Are you sure? This will fail if there are support tickets in this stage.">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-full bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 transition hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            @else
                                                <span class="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500">Locked</span>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isCompanyAdmin ? 6 : 5 }}" class="px-6 py-8 text-center text-sm text-slate-500">No service stages found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <hr class="hidden border-slate-200">


        <section id="task-master" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'task-master'])>
            @php
                $taskMasterCollection = collect($taskMasters ?? [])
                    ->map(function ($value) {
                        if (is_array($value)) {
                            $name = trim((string) ($value['name'] ?? ''));

                            return $name === '' ? null : [
                                'name' => $name,
                            ];
                        }

                        $name = trim((string) $value);

                        return $name === '' ? null : [
                            'name' => $name,
                        ];
                    })
                    ->filter()
                    ->values();
            @endphp

            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Task Master</h3>
                    <p class="mt-1 text-sm text-slate-500">Keep a reusable task library for tickets, service visits, and internal work orders.</p>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-[360px,1fr]">
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <h4 class="text-lg font-bold text-slate-950">Add Task</h4>
                    <p class="mt-1 text-sm text-slate-500">Create a reusable task label that can be picked from the API.</p>

                    @if($isCompanyAdmin || $isSuperAdmin)
                        <form id="task-master-create-form" method="POST" action="{{ route('settings.company.task-masters.store') }}" class="mt-4 space-y-4" novalidate>
                            @csrf
                            <div>
                                <x-input-label for="task_master_name" value="Task Name" />
                                <x-text-input id="task_master_name" name="task_master_name" maxlength="191" class="mt-1 block w-full rounded-xl" value="{{ old('task_master_name') }}" placeholder="e.g. Inspect camera feed" required />
                                <x-input-error :messages="$errors->get('task_master_name')" class="mt-2" />
                            </div>
                            <div class="flex justify-end">
                                <x-primary-button id="task-master-create-submit">Add Task</x-primary-button>
                            </div>
                        </form>
                    @else
                        <div class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-5 text-sm text-slate-500">
                            Only company admins can manage task masters.
                        </div>
                    @endif
                </div>

                <div class="rounded-3xl border border-slate-200 bg-white">
                    <div class="border-b border-slate-200 px-5 py-4">
                        <h4 class="text-base font-bold text-slate-950">Saved Tasks</h4>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Task</th>
                                    @if($isCompanyAdmin || $isSuperAdmin)
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($taskMasterCollection as $index => $taskMaster)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $taskMaster['name'] }}</td>
                                        @if($isCompanyAdmin || $isSuperAdmin)
                                            <td class="px-4 py-3 text-right">
                                                <div class="inline-flex items-center gap-2">
                                                    <button
                                                        type="button"
                                                        class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-200"
                                                        data-task-master-edit
                                                        data-index="{{ $index }}"
                                                        data-name="{{ $taskMaster['name'] }}"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                    </button>

                                                    <form action="{{ route('settings.company.task-masters.destroy', $index) }}" method="POST" class="inline company-task-master-delete-form" data-delete-message="Are you sure you want to delete the task '{{ $taskMaster['name'] }}'?">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-100">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ ($isCompanyAdmin || $isSuperAdmin) ? 2 : 1 }}" class="px-4 py-8 text-center text-sm text-slate-500">No tasks added yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

        <hr class="hidden border-slate-200">




        <section id="crm" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'crm'])>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">CRM Settings</h3>
                    <p class="text-sm text-slate-500">Manage default quotation terms, staff daily updates, and related CRM behavior.</p>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 class="text-lg font-bold text-slate-950">Daily Staff Update</h4>
                        <p class="mt-1 text-sm text-slate-500">Generate a read-only staff progress report automatically every day or manually on demand.</p>
                    </div>
                    <a href="{{ route('settings.company.index', ['tab' => 'daily-update-history']) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                        View Daily Update History
                    </a>
                </div>

                <form method="POST" action="{{ route('settings.company.update') }}" class="mt-5 space-y-5" novalidate>
                    @csrf
                    <div class="rounded-2xl border border-slate-200 bg-white p-4">
                        <div class="flex items-start gap-3">
                            <input type="hidden" name="enable_daily_staff_update" value="0">
                            <input
                                type="checkbox"
                                id="enable_daily_staff_update"
                                name="enable_daily_staff_update"
                                value="1"
                                @checked(old('enable_daily_staff_update', $company?->enable_daily_staff_update))
                                class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            >
                            <div>
                                <label for="enable_daily_staff_update" class="text-sm font-semibold text-slate-800">Enable Daily Staff Update</label>
                                <p class="mt-1 text-sm text-slate-500">When disabled, automatic generation is skipped. Manual generation still works.</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="daily_staff_update_time" value="Auto Generate Time" />
                            <input
                                id="daily_staff_update_time"
                                name="daily_staff_update_time"
                                type="time"
                                value="{{ $dailyStaffUpdateTime }}"
                                class="mt-1 block w-full rounded-xl border-slate-300"
                            >
                            <x-input-error :messages="$errors->get('daily_staff_update_time')" class="mt-2" />
                        </div>

                        <div class="flex items-end">
                            <div class="w-full rounded-2xl border border-dashed border-slate-200 bg-white p-4">
                                <p class="text-sm font-semibold text-slate-900">Automated run</p>
                                <p class="mt-1 text-sm text-slate-500">
                                    {{ $company?->enable_daily_staff_update ? 'Enabled at ' . ($company?->dailyStaffUpdateTimeLabel() ?? 'not set') : 'Disabled until you turn it on.' }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <x-input-label for="timezone" value="Company Timezone" />
                            <select id="timezone" name="timezone" class="mt-1 block w-full rounded-xl border-slate-300">
                                @foreach($timezones as $timezone)
                                    <option value="{{ $timezone }}" @selected($companyTimezone === $timezone)>{{ $timezone }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                            <input type="checkbox" name="force_generate" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                            Force regenerate today's report
                        </label>

                        <div class="flex gap-3">
                            <button
                                type="submit"
                                formaction="{{ route('settings.daily-staff-update.generate') }}"
                                class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800"
                            >
                                Generate Daily Update
                            </button>
                            <x-primary-button class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold hover:bg-indigo-500">
                                Save CRM Settings
                            </x-primary-button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h4 class="text-lg font-bold text-slate-950">Company Mail Settings</h4>
                        <p class="mt-1 text-sm text-slate-500">Configure the SMTP account used for company-specific mail sending.</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('settings.company.mail-settings.store') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="mailer" value="Mailer" />
                            <select id="mailer" name="mailer" class="mt-1 block w-full rounded-xl border-slate-300">
                                <option value="smtp" @selected($mailMailer === 'smtp')>SMTP</option>
                            </select>
                            <x-input-error :messages="$errors->get('mailer')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="host" value="SMTP Host" />
                            <input id="host" name="host" type="text" value="{{ $mailHost }}" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="smtp.gmail.com">
                            <x-input-error :messages="$errors->get('host')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="port" value="SMTP Port" />
                            <input id="port" name="port" type="number" min="1" max="65535" value="{{ $mailPort }}" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="587">
                            <x-input-error :messages="$errors->get('port')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="username" value="Username" />
                            <input id="username" name="username" type="text" value="{{ $mailUsername }}" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="smtp username">
                            <x-input-error :messages="$errors->get('username')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password" value="Password" />
                            <input id="password" name="password" type="password" value="" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="{{ $companyMailSetting ? 'Leave blank to keep current password' : 'Enter SMTP password' }}">
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="encryption" value="Encryption" />
                            <select id="encryption" name="encryption" class="mt-1 block w-full rounded-xl border-slate-300">
                                <option value="" @selected(blank($mailEncryption))>None</option>
                                <option value="tls" @selected($mailEncryption === 'tls')>TLS</option>
                                <option value="ssl" @selected($mailEncryption === 'ssl')>SSL</option>
                                <option value="none" @selected($mailEncryption === 'none')>None</option>
                            </select>
                            <x-input-error :messages="$errors->get('encryption')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="from_email" value="From Email" />
                            <input id="from_email" name="from_email" type="email" value="{{ $mailFromEmail }}" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="no-reply@example.com">
                            <x-input-error :messages="$errors->get('from_email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="from_name" value="From Name" />
                            <input id="from_name" name="from_name" type="text" value="{{ $mailFromName }}" class="mt-1 block w-full rounded-xl border-slate-300" placeholder="CCTV Workshop">
                            <x-input-error :messages="$errors->get('from_name')" class="mt-2" />
                        </div>
                        <div class="flex items-end">
                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" name="is_active" value="1" @checked((bool) $mailIsActive) class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                Active
                            </label>
                        </div>
                    </div>

                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                        <button
                            type="submit"
                            formaction="{{ route('settings.company.mail-settings.test') }}"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Test Email
                        </button>
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500"
                        >
                            Save Mail Settings
                        </button>
                    </div>
                </form>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <h4 class="text-lg font-bold text-slate-950">Default Terms & Conditions</h4>
                <p class="mt-1 text-sm text-slate-500">These defaults will be pre-filled in every new quotation. You can still edit them per quotation.</p>
                <form method="POST" action="{{ route('settings.company.update') }}" class="mt-4 space-y-4" novalidate>
                    @csrf
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <x-input-label for="default_terms_conditions" value="Default Terms & Conditions" />
                            <x-speech-to-text target="default_terms_conditions_editor" />
                        </div>
                        <input type="hidden" id="default_terms_conditions" name="default_terms_conditions" value="{{ old('default_terms_conditions', $company?->default_terms_conditions ?? '') }}">
                        <div class="mt-2 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 bg-slate-50 px-3 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <input type="text" value="untitled" readonly class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                    <select data-editor-file class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                        <option value="">File</option>
                                        <option value="clear">Clear</option>
                                        <option value="reset">Reset to default</option>
                                    </select>
                                    <select data-editor-format class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                        <option value="">Format</option>
                                        <option value="p">Paragraph</option>
                                        <option value="h1">Heading 1</option>
                                        <option value="h2">Heading 2</option>
                                        <option value="h3">Heading 3</option>
                                        <option value="blockquote">Quote</option>
                                    </select>
                                    <select data-editor-size class="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
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
                                        <input type="color" data-editor-color value="#000000" class="h-6 w-8 border-0 bg-transparent p-0">
                                    </label>
                                    <label class="flex h-10 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm">
                                        <span>Background</span>
                                        <input type="color" data-editor-background value="#ffffff" class="h-6 w-8 border-0 bg-transparent p-0">
                                    </label>
                                </div>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" data-editor-cmd="undo" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Undo</button>
                                    <button type="button" data-editor-cmd="redo" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Redo</button>
                                    <button type="button" data-editor-cmd="bold" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">B</button>
                                    <button type="button" data-editor-cmd="underline" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">U</button>
                                    <button type="button" data-editor-cmd="italic" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">I</button>
                                    <button type="button" data-editor-cmd="strikeThrough" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">S</button>
                                    <button type="button" data-editor-cmd="justifyLeft" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Left</button>
                                    <button type="button" data-editor-cmd="justifyCenter" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Center</button>
                                    <button type="button" data-editor-cmd="justifyRight" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Right</button>
                                    <button type="button" data-editor-cmd="justifyFull" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Justify</button>
                                    <button type="button" data-editor-cmd="insertUnorderedList" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">UL</button>
                                    <button type="button" data-editor-cmd="insertOrderedList" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">OL</button>
                                    <button type="button" data-editor-cmd="createLink" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Link</button>
                                    <button type="button" data-editor-cmd="unlink" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Unlink</button>
                                    <button type="button" data-editor-cmd="removeFormat" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">Code</button>
                                </div>
                            </div>
                            <div id="default_terms_conditions_editor"
                                class="min-h-[220px] px-4 py-3 text-sm leading-6 text-slate-800 outline-none"
                                contenteditable="true"
                                spellcheck="true"
                                data-placeholder="Enter the default quotation terms here. You can format text with the toolbar above."
                            ></div>
                        </div>
                        <x-input-error :messages="$errors->get('default_terms_conditions')" class="mt-2" />
                    </div>
                    <div class="flex justify-end">
                        <x-primary-button>Save Terms & Conditions</x-primary-button>
                    </div>
                </form>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const editor = document.getElementById('default_terms_conditions_editor');
                    const hiddenInput = document.getElementById('default_terms_conditions');

                    if (!editor || !hiddenInput) {
                        return;
                    }

                    const initialValue = hiddenInput.value || '';
                    editor.innerHTML = initialValue ? initialValue : '<p></p>';

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

                    document.querySelectorAll('[data-editor-cmd]').forEach(function (button) {
                        button.addEventListener('click', function () {
                            const command = button.getAttribute('data-editor-cmd');
                            if (command === 'createLink') {
                                const url = window.prompt('Enter link URL');
                                if (!url) return;
                                applyCommand(command, url);
                                return;
                            }

                            if (command === 'removeFormat') {
                                applyCommand(command);
                                editor.innerHTML = editor.innerHTML || '<p></p>';
                                syncEditor();
                                return;
                            }

                            applyCommand(command);
                        });
                    });

                    editor.closest('.rounded-2xl')?.querySelector('[data-editor-file]')?.addEventListener('change', function (event) {
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

                    editor.closest('.rounded-2xl')?.querySelector('[data-editor-format]')?.addEventListener('change', function (event) {
                        if (!event.target.value) return;
                        setBlockFormat(event.target.value);
                        event.target.value = '';
                    });

                    editor.closest('.rounded-2xl')?.querySelector('[data-editor-size]')?.addEventListener('change', function (event) {
                        if (!event.target.value) return;
                        applyCommand('fontSize', event.target.value);
                        event.target.value = '';
                    });

                    editor.closest('.rounded-2xl')?.querySelector('[data-editor-color]')?.addEventListener('input', function (event) {
                        applyCommand('foreColor', event.target.value);
                    });

                    editor.closest('.rounded-2xl')?.querySelector('[data-editor-background]')?.addEventListener('input', function (event) {
                        applyCommand('hiliteColor', event.target.value);
                    });

                    editor.closest('form')?.addEventListener('submit', syncEditor);

                    if (editor.innerHTML.trim() === '<p></p>') {
                        editor.innerHTML = '';
                    }
                });
            </script>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-lg font-bold text-slate-950">Unit Master</h4>
                            <p class="mt-1 text-sm text-slate-500">Manage reusable units for products, quotations, and BOQ rows.</p>
                        </div>
                        @if($isCompanyAdmin)
                            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-unit-create-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add Unit</button>
                        @endif
                    </div>
                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h5 class="text-base font-bold text-slate-950">Saved Units</h5>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Unit</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @forelse($units as $unit)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ $unit->name }}</td>
                                            <td class="px-4 py-3 text-right">
                                                <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700" data-unit-edit data-id="{{ $unit->id }}" data-name="{{ $unit->name }}"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg></button>
                                                <form method="POST" action="{{ route('settings.company.units.destroy', $unit) }}" class="inline unit-delete-form" data-delete-message="Delete this unit?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">No units added yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3">
                            {{ $units->links('pagination::tailwind') }}
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h4 class="text-lg font-bold text-slate-950">GST Master</h4>
                            <p class="mt-1 text-sm text-slate-500">Manage reusable GST percentages for BOQ products and quotations.</p>
                        </div>
                        @if($isCompanyAdmin)
                            <button type="button" onclick="window.dispatchEvent(new CustomEvent('open-gst-rate-create-modal'))" class="shrink-0 rounded-full bg-slate-950 px-5 py-2.5 text-sm font-bold text-white shadow-[0_8px_20px_rgba(15,23,42,0.18)] transition hover:bg-slate-800">Add GST Rate</button>
                        @endif
                    </div>
                    <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h5 class="text-base font-bold text-slate-950">Saved GST Rates</h5>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">GST</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 bg-white">
                                    @forelse($gstRates as $gstRate)
                                        <tr>
                                            <td class="px-4 py-3 text-sm font-semibold text-slate-900">{{ number_format((float) $gstRate->rate, 2) }}%</td>
                                            <td class="px-4 py-3 text-right">
                                                <button type="button" class="rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700" data-gst-rate-edit data-id="{{ $gstRate->id }}" data-rate="{{ $gstRate->rate }}"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg></button>
                                                <form method="POST" action="{{ route('settings.company.gst-rates.destroy', $gstRate) }}" class="inline gst-rate-delete-form" data-delete-message="Delete this GST rate?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600"><svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg></button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="px-4 py-8 text-center text-sm text-slate-500">No GST rates added yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="px-4 py-3">
                            {{ $gstRates->links('pagination::tailwind') }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                <h4 class="text-lg font-bold text-slate-950">MSME Details</h4>
                <p class="mt-1 text-sm text-slate-500">Add your MSME (Udyam) registration number and choose whether to print it on invoices and quotations.</p>

                <form method="POST" action="{{ route('settings.company.update') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <x-input-label for="msme_number" value="MSME Number" />
                            <x-text-input
                                id="msme_number"
                                name="msme_number"
                                class="mt-1 block w-full rounded-xl"
                                value="{{ old('msme_number', $company?->msme_number) }}"
                                placeholder="UDYAM-XX-00-0000000"
                                maxlength="50"
                            />
                            <x-input-error :messages="$errors->get('msme_number')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex items-start gap-3">
                                <input type="hidden" name="show_msme_number_in_invoice" value="0">
                                <input
                                    type="checkbox"
                                    id="show_msme_number_in_invoice"
                                    name="show_msme_number_in_invoice"
                                    value="1"
                                    @checked(old('show_msme_number_in_invoice', $company?->show_msme_number_in_invoice ?? false))
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                >
                                <div>
                                    <x-input-label for="show_msme_number_in_invoice" value="Show MSME Number in Invoice" class="cursor-pointer" />
                                    <p class="mt-1 text-sm text-slate-500">When enabled, the MSME number is printed in the Invoice Details section.</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex items-start gap-3">
                                <input type="hidden" name="show_msme_number_in_quotation" value="0">
                                <input
                                    type="checkbox"
                                    id="show_msme_number_in_quotation"
                                    name="show_msme_number_in_quotation"
                                    value="1"
                                    @checked(old('show_msme_number_in_quotation', $company?->show_msme_number_in_quotation ?? false))
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                                >
                                <div>
                                    <x-input-label for="show_msme_number_in_quotation" value="Show MSME Number in Quotation" class="cursor-pointer" />
                                    <p class="mt-1 text-sm text-slate-500">When enabled, the MSME number is printed in the Quotation Details section, including lead quotations and BOQs.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold hover:bg-indigo-500">
                            Save MSME Details
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </section>

        <hr class="hidden border-slate-200">

        <section id="payroll-settings" @class(['rounded-3xl border border-slate-200 bg-white p-6 shadow-sm', 'hidden' => $activeSettingsTab !== 'payroll'])>
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Payroll Settings</h3>
                    <p class="text-sm text-slate-500">Working days per week, PF toggle, and holiday calendar management.</p>
                </div>
            </div>

            @if($canManagePayrollSettings)
                <div class="mt-6 grid gap-6 lg:grid-cols-2">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Working Days Per Week</h4>
                        <p class="mt-1 text-sm text-slate-500">Choose the working schedule used in bulk attendance.</p>
                        <form method="POST" action="{{ route('settings.company.update') }}" class="mt-4 space-y-4">
                            @csrf
                            @php
                                $selectedWorkingDays = old('working_days', $company?->working_days ?? ['mon','tue','wed','thu','fri','sat']);
                                if (!is_array($selectedWorkingDays)) {
                                    $selectedWorkingDays = ['mon','tue','wed','thu','fri','sat'];
                                }
                                $dayOptions = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
                            @endphp
                            <div>
                                <x-input-label value="Select Working Days" />
                                <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
                                    @foreach($dayOptions as $key => $label)
                                        <label class="flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                            <input type="checkbox" name="working_days[]" value="{{ $key }}" @checked(in_array($key, $selectedWorkingDays, true)) class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <x-primary-button>Save Payroll Settings</x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50/80 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h4 class="text-lg font-bold text-slate-950">Holiday Calendar</h4>
                                <p class="mt-1 text-sm text-slate-500">Add, edit, or delete holidays in a popup form.</p>
                            </div>
                            <button type="button" id="open-holiday-modal" class="rounded-2xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white">Add Holiday</button>
                        </div>

                        <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500">Title</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200">
                                    @forelse($holidays as $holiday)
                                        <tr>
                                            <td class="px-4 py-3 text-sm text-slate-700">{{ $holiday->holiday_date->format('d M Y') }}</td>
                                            <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ $holiday->title }}</td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                <button
                                                    type="button"
                                                    class="mr-2 rounded-lg bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                                                    data-holiday-edit
                                                    data-holiday-id="{{ $holiday->id }}"
                                                    data-holiday-date="{{ $holiday->holiday_date->toDateString() }}"
                                                    data-holiday-title="{{ e($holiday->title) }}"
                                                    data-holiday-description="{{ e($holiday->description ?? '') }}"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                </button>
                                                <form action="{{ route('settings.company.holidays.destroy', $holiday) }}" method="POST" class="inline company-holiday-delete-form" data-delete-message="Delete this holiday?">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="rounded-lg bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-600">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No holidays added yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4 px-1">
                            {{ $holidays->links() }}
                        </div>
                    </div>
                </div>
            @else
                <div class="mt-6 rounded-3xl border border-amber-200 bg-amber-50 px-6 py-5 text-sm text-amber-900">
                    Payroll settings are restricted for this account. Working days and holiday management are hidden because you do not have company admin access.
                </div>
            @endif
        </section>

        <hr class="hidden border-slate-200">

        <script>
        document.addEventListener('DOMContentLoaded', function() {

            // Generic validation helper
            const validateForm = (formId, rules) => {
                const form = document.getElementById(formId);
                if (!form) return;

                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    let errors = [];

                    rules.forEach(rule => {
                        const input = form.querySelector(`[name="${rule.name}"]`);
                        const value = input.value.trim();

                        // 1. Required Validation
                        if (rule.required && !value) {
                            errors.push(`${rule.label} is required.`);
                        }
                        // 2. Min/Max Length Validation
                        else if (rule.minLen && value.length < rule.minLen) {
                            errors.push(`${rule.label} must be at least ${rule.minLen} characters.`);
                        }
                        // 3. Numeric Range Validation
                        else if (rule.type === 'number') {
                            const numVal = parseInt(value);
                            if (rule.min !== undefined && numVal < rule.min) {
                                errors.push(`${rule.label} must be at least ${rule.min}.`);
                            }
                            if (rule.max !== undefined && numVal > rule.max) {
                                errors.push(`${rule.label} cannot exceed ${rule.max}.`);
                            }
                        }
                    });

                    if (errors.length > 0) {
                        // Show SweetAlert Error
                        Swal.fire({
                            icon: 'error',
                            title: 'Check your inputs',
                            html: `<ul class="text-left list-disc list-inside">${errors.map(err => `<li>${err}</li>`).join('')}</ul>`,
                            confirmButtonColor: '#4f46e5' // Indigo-600
                        });
                    } else {
                        // Success: Show loading and submit
                        Swal.fire({
                            title: 'Saving...',
                            didOpen: () => { Swal.showLoading(); }
                        });
                        form.submit();
                    }
                });
            };

            // Apply rules to project numbering forms
            validateForm('project-settings-form', [
                { name: 'project_reference_prefix', label: 'Project Prefix', required: true, minLen: 2 },
                { name: 'project_reference_year', label: 'Year', required: true, type: 'number', min: 2000, max: 2100 },
                { name: 'project_reference_sequence', label: 'Starting Sequence', required: true, type: 'number', min: 1 }
            ]);

            // Apply rules to Challan Settings Form
            validateForm('challan-settings-form', [
                { name: 'challan_prefix', label: 'Challan Prefix', required: true, minLen: 2 },
                { name: 'challan_sequence', label: 'Challan Sequence', required: true, type: 'number', min: 1 }
            ]);
        });
        </script>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            (function () {
                function persistOrder(el, url, selector) {
                    const order = Array.from(el.querySelectorAll(selector)).map(tr => tr.dataset.id);
                    fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ order })
                    }).then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to save order');
                        }
                    }).catch(() => {
                        Swal.fire({
                            icon: 'error',
                            title: 'Could not save order',
                            text: 'Your changes were not saved. The page will reload to show the current order.',
                            confirmButtonText: 'OK'
                        }).then(() => window.location.reload());
                    });
                }

                // Fixed rows must keep their exact position, so a movable row can be
                // dropped next to a fixed row but never swap places with it (i.e. it
                // can't jump to the far side of a fixed row in one move).
                function restrictToMovableZone(evt) {
                    if (!evt.related || evt.related.dataset.reorderable !== '0') {
                        return true;
                    }

                    const rows = Array.from(evt.to.querySelectorAll('tr[data-id]'));
                    const draggedIndex = rows.indexOf(evt.dragged);
                    const relatedIndex = rows.indexOf(evt.related);

                    return Math.abs(draggedIndex - relatedIndex) <= 1;
                }

                // Pipeline Sortable
                const el = document.getElementById('status-sortable');
                if (el) {
                    new Sortable(el, {
                        animation: 150,
                        handle: '.cursor-move',
                        filter: '[data-reorderable="0"]',
                        onMove: restrictToMovableZone,
                        onEnd: function () {
                            persistOrder(el, '{{ route('settings.statuses.reorder') }}', 'tr[data-id]');
                        }
                    });
                }

                const projectStageSortable = document.getElementById('project-stage-sortable');
                if (projectStageSortable) {
                    new Sortable(projectStageSortable, {
                        animation: 150,
                        handle: '.cursor-move',
                        filter: '[data-reorderable="0"]',
                        onMove: restrictToMovableZone,
                        onEnd: function () {
                            persistOrder(projectStageSortable, '{{ route('settings.project-stages.reorder') }}', 'tr[data-id]');
                        }
                    });
                }

                const serviceStageSortable = document.getElementById('service-stage-sortable');
                if (serviceStageSortable) {
                    new Sortable(serviceStageSortable, {
                        animation: 150,
                        handle: '.cursor-move',
                        filter: '[data-reorderable="0"]',
                        onMove: restrictToMovableZone,
                        onEnd: function () {
                            persistOrder(serviceStageSortable, '{{ route('settings.service-stages.reorder') }}', 'tr[data-id]');
                        }
                    });
                }

                // Pipeline Modals
                const statusModal = document.getElementById('status-modal');
                const statusForm = document.getElementById('status-form');
                const statusMethod = statusForm?.querySelector('input[name="_method"]');
                const statusNameInput = document.getElementById('status_name');
                const statusColorInput = document.getElementById('status_color');

                window.addEventListener('open-status-modal', function() {
                    if (!statusModal || !statusForm) return;
                    statusForm.action = '{{ route('settings.statuses.store') }}';
                    if (statusMethod) statusMethod.value = 'POST';
                    if (statusNameInput) statusNameInput.value = '';
                    if (statusColorInput) statusColorInput.value = '#4f46e5';
                    statusModal.classList.remove('hidden');
                });

                window.addEventListener('open-status-edit', function(event) {
                    if (!statusModal || !statusForm) return;
                    const detail = event.detail || {};
                    statusForm.action = `{{ url('/settings/statuses') }}/${detail.id}`;
                    if (statusMethod) statusMethod.value = 'PATCH';
                    if (statusNameInput) statusNameInput.value = detail.name || '';
                    if (statusColorInput) statusColorInput.value = detail.color || '#4f46e5';
                    statusModal.classList.remove('hidden');
                });

                document.querySelectorAll('[data-status-modal-close]').forEach(btn => {
                    btn.addEventListener('click', () => statusModal?.classList.add('hidden'));
                });

                const projectStageModal = document.getElementById('project-stage-modal');
                const projectStageForm = document.getElementById('project-stage-form');
                const projectStageMethod = projectStageForm?.querySelector('input[name="_method"]');
                const projectStageNameInput = document.getElementById('project_stage_name');
                const projectStageColorInput = document.getElementById('project_stage_color');

                window.addEventListener('open-project-stage-modal', function() {
                    if (!projectStageModal || !projectStageForm) return;
                    projectStageForm.action = '{{ route('settings.project-stages.store') }}';
                    if (projectStageMethod) projectStageMethod.value = 'POST';
                    if (projectStageNameInput) projectStageNameInput.value = '';
                    if (projectStageColorInput) projectStageColorInput.value = '#4f46e5';
                    projectStageModal.classList.remove('hidden');
                });

                window.addEventListener('open-project-stage-edit', function(event) {
                    if (!projectStageModal || !projectStageForm) return;
                    const detail = event.detail || {};
                    projectStageForm.action = `{{ url('/settings/project-stages') }}/${detail.id}`;
                    if (projectStageMethod) projectStageMethod.value = 'PATCH';
                    if (projectStageNameInput) projectStageNameInput.value = detail.name || '';
                    if (projectStageColorInput) projectStageColorInput.value = detail.color || '#4f46e5';
                    projectStageModal.classList.remove('hidden');
                });

                document.querySelectorAll('[data-project-stage-modal-close]').forEach(btn => {
                    btn.addEventListener('click', () => projectStageModal?.classList.add('hidden'));
                });

                const serviceStageModal = document.getElementById('service-stage-modal');
                const serviceStageForm = document.getElementById('service-stage-form');
                const serviceStageMethod = serviceStageForm?.querySelector('input[name="_method"]');
                const serviceStageNameInput = document.getElementById('service_stage_name');
                const serviceStageColorInput = document.getElementById('service_stage_color');
                const serviceStageStatusInput = document.getElementById('service_stage_status');
                const serviceStageDescriptionInput = document.getElementById('service_stage_description');

                window.addEventListener('open-service-stage-modal', function() {
                    if (!serviceStageModal || !serviceStageForm) return;
                    serviceStageForm.action = '{{ route('settings.service-stages.store') }}';
                    if (serviceStageMethod) serviceStageMethod.value = 'POST';
                    if (serviceStageNameInput) serviceStageNameInput.value = '';
                    if (serviceStageColorInput) serviceStageColorInput.value = '#4f46e5';
                    if (serviceStageStatusInput) serviceStageStatusInput.value = 'open';
                    if (serviceStageDescriptionInput) serviceStageDescriptionInput.value = '';
                    serviceStageModal.classList.remove('hidden');
                });

                window.addEventListener('open-service-stage-edit', function(event) {
                    if (!serviceStageModal || !serviceStageForm) return;
                    const detail = event.detail || {};
                    serviceStageForm.action = `{{ url('/settings/service-stages') }}/${detail.id}`;
                    if (serviceStageMethod) serviceStageMethod.value = 'PATCH';
                    if (serviceStageNameInput) serviceStageNameInput.value = detail.name || '';
                    if (serviceStageColorInput) serviceStageColorInput.value = detail.color || '#4f46e5';
                    if (serviceStageStatusInput) serviceStageStatusInput.value = detail.mappedStatus || 'open';
                    if (serviceStageDescriptionInput) serviceStageDescriptionInput.value = detail.description || '';
                    serviceStageModal.classList.remove('hidden');
                });

                document.querySelectorAll('[data-service-stage-modal-close]').forEach(btn => {
                    btn.addEventListener('click', () => serviceStageModal?.classList.add('hidden'));
                });

                const companyDetailsForm = document.getElementById('company-details-form');
                const companyStateSelect = document.getElementById('state');
                const companyCitySelect = document.getElementById('city');
                const invoiceNumberingForm = document.getElementById('invoice-numbering-form');
                const quotationNumberingForm = document.getElementById('quotation-numbering-form');
                const projectSettingsForm = document.getElementById('project-settings-form');
                const challanSettingsForm = document.getElementById('challan-settings-form');
                const printBrandingForm = document.getElementById('print-branding-form');
                const leadSourceForm = document.getElementById('lead-source-form');
                const leadSourceSubmit = document.getElementById('lead-source-submit');
                const leadCustomFieldForm = document.getElementById('lead-custom-field-form');
                const leadCustomFieldSubmit = document.getElementById('lead-custom-field-submit');
                const leadCustomFieldTypeInput = document.getElementById('lead_custom_field_type');
                const leadCustomFieldOptionsWrapper = document.getElementById('lead-custom-field-options-wrapper');
                const leadCustomFieldOptionsInput = document.getElementById('lead_custom_field_options');
                const leadCustomFieldModal = document.getElementById('lead-custom-field-modal');
                const leadCustomFieldModalTitle = document.getElementById('lead-custom-field-modal-title');
                const leadCustomFieldMethod = document.getElementById('lead-custom-field-method');
                const leadCustomFieldLabelInput = document.getElementById('lead_custom_field_label');
                const leadCustomFieldPlaceholderInput = document.getElementById('lead_custom_field_placeholder');
                const leadCustomFieldHelpTextInput = document.getElementById('lead_custom_field_help_text');
                const leadCustomFieldRequiredInput = document.getElementById('lead_custom_field_required');
                const leadCustomFieldShowInFiltersInput = document.getElementById('lead_custom_field_show_in_filters');
                const leadCustomFieldShowInBoqQuotationInput = document.getElementById('lead_custom_field_show_in_boq_quotation');
                const projectCustomFieldForm = document.getElementById('project-custom-field-form');
                const projectCustomFieldSubmit = document.getElementById('project-custom-field-submit');
                const projectCustomFieldTypeInput = document.getElementById('project_custom_field_type');
                const projectCustomFieldOptionsWrapper = document.getElementById('project-custom-field-options-wrapper');
                const projectCustomFieldOptionsInput = document.getElementById('project_custom_field_options');
                const projectCustomFieldModal = document.getElementById('project-custom-field-modal');
                const projectCustomFieldModalTitle = document.getElementById('project-custom-field-modal-title');
                const projectCustomFieldMethod = document.getElementById('project-custom-field-method');
                const projectCustomFieldLabelInput = document.getElementById('project_custom_field_label');
                const projectCustomFieldPlaceholderInput = document.getElementById('project_custom_field_placeholder');
                const projectCustomFieldHelpTextInput = document.getElementById('project_custom_field_help_text');
                const projectCustomFieldRequiredInput = document.getElementById('project_custom_field_required');
                const projectCustomFieldShowInFiltersInput = document.getElementById('project_custom_field_show_in_filters');
                const existingLeadSources = @js(
                    collect(method_exists($leadSources, 'items') ? $leadSources->items() : $leadSources)
                        ->map(fn ($leadSource) => [
                            'key' => (string) ($leadSource['key'] ?? ''),
                            'name' => trim((string) ($leadSource['name'] ?? '')),
                        ])
                        ->values()
                );
                const existingTaskMasterNames = @js($taskMasterCollection->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->values());
                const existingLeadCustomFields = @js(collect($leadCustomFields)->map(fn ($field) => [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => trim((string) ($field['label'] ?? '')),
                ])->values());
                const existingProjectCustomFields = @js(collect($projectCustomFields ?? [])->map(fn ($field) => [
                    'key' => (string) ($field['key'] ?? ''),
                    'label' => trim((string) ($field['label'] ?? '')),
                ])->values());
                const existingCategoryNames = @js($categories->pluck('name')->map(fn ($name) => trim((string) $name))->filter()->values());
                const companyCityOptionsByState = @js($companyCityOptionsByState);

                function getSelectedCompanyStateName() {
                    if (!companyStateSelect) {
                        return '';
                    }

                    return String(companyStateSelect.value || '');
                }

                function populateCompanyCities() {
                    if (!companyStateSelect || !companyCitySelect) {
                        return;
                    }

                    const selectedCity = companyCitySelect.dataset.selectedCity || companyCitySelect.value || '';
                    const selectedStateName = getSelectedCompanyStateName();
                    const cities = companyCityOptionsByState[String(selectedStateName)] || [];

                    companyCitySelect.innerHTML = '<option value="">Select city</option>';

                    cities.forEach((city) => {
                        const option = document.createElement('option');
                        option.value = city.name;
                        option.textContent = city.name;

                        if (city.name === selectedCity) {
                            option.selected = true;
                        }

                        companyCitySelect.appendChild(option);
                    });

                    if (!cities.some((city) => city.name === selectedCity)) {
                        companyCitySelect.value = '';
                    }

                    companyCitySelect.disabled = cities.length === 0;
                    companyCitySelect.dataset.selectedCity = companyCitySelect.value || '';
                }

                if (companyStateSelect && companyCitySelect) {
                    companyCitySelect.dataset.selectedCity = @js(old('city', $company?->city));
                    populateCompanyCities();

                    companyStateSelect.addEventListener('change', () => {
                        companyCitySelect.dataset.selectedCity = '';
                        populateCompanyCities();
                    });

                    companyCitySelect.addEventListener('change', () => {
                        companyCitySelect.dataset.selectedCity = companyCitySelect.value || '';
                    });
                }

                if (window.jQuery && jQuery.fn && typeof jQuery.fn.validate === 'function' && window.Swal) {
                    const $ = window.jQuery;

                    $.validator.addMethod('gstPattern', function (value, element) {
                        if (!value) return true;
                        return /^[0-9]{2}[A-Z0-9]{13}$/.test(value.trim().toUpperCase());
                    }, 'Please enter a valid GST number.');

                    $.validator.addMethod('panPattern', function (value, element) {
                        if (!value) return true;
                        return /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(value.trim().toUpperCase());
                    }, 'Please enter a valid PAN number.');

                    $.validator.addMethod('strictEmail', function (value, element) {
                        if (!value) return true;
                        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
                    }, 'Please enter a valid email address.');

                    $.validator.addMethod('commaOrLines', function (value, element) {
                        return true;
                    }, '');

                    const validationRules = {
                        name: { required: true, minlength: 2 },
                        email: { required: true, strictEmail: true, maxlength: 191 },
                        phone_number: { required: true, digits: true, minlength: 10, maxlength: 10 },
                        gst_number: { required: false, gstPattern: true, minlength: 15, maxlength: 15 },
                        pan_number: { required: false, panPattern: true, minlength: 10, maxlength: 10 },
                        registration_number: { required: false, minlength: 2, maxlength: 100 },
                        registration_date: { required: false, dateISO: true },
                        address: { required: false, minlength: 5, maxlength: 1000 },
                        mail_username: { required: false, minlength: 2, maxlength: 191 },
                        mail_password: { required: false, minlength: 4, maxlength: 191 },
                        from_address: { required: false, email: true, maxlength: 191 },
                        to_address: { required: false, email: true, maxlength: 191 },
                        from_name: { required: false, minlength: 2, maxlength: 191 },
                        to_name: { required: false, minlength: 2, maxlength: 191 },
                        project_reference_prefix: { required: false, minlength: 1, maxlength: 50 },
                        project_reference_year: { required: false, digits: true, min: 2000, max: 2100 },
                        project_reference_sequence: { required: false, digits: true, min: 1 },
                        challan_prefix: { required: false, minlength: 1, maxlength: 50 },
                        challan_sequence: { required: false, digits: true, min: 1 },
                        quotation_prefix: { required: false, minlength: 1, maxlength: 50 },
                        quotation_sequence: { required: false, digits: true, min: 1 },
                        'print_settings[accent_color]': { required: false },
                        'print_settings[page_margin_top]': { required: false, digits: true, min: 0, max: 100 },
                        'print_settings[page_margin_right]': { required: false, digits: true, min: 0, max: 100 },
                        'print_settings[page_margin_bottom]': { required: false, digits: true, min: 0, max: 100 },
                        'print_settings[page_margin_left]': { required: false, digits: true, min: 0, max: 100 },
                        holiday_date: { required: true, dateISO: true },
                        title: { required: true, minlength: 2 },
                    };

                    const validationMessages = {
                        name: { required: 'This field is required.', minlength: 'Please enter at least 2 characters.' },
                        email: {
                            required: 'Company email is required.',
                            email: 'Please enter a valid email address.',
                            maxlength: 'Company email must not exceed 191 characters.',
                        },
                        phone_number: {
                            required: 'Phone number is required.',
                            digits: 'Phone number must contain only digits.',
                            minlength: 'Phone number must be exactly 10 digits.',
                            maxlength: 'Phone number must be exactly 10 digits.',
                        },
                        gst_number: {
                            gstPattern: 'Please enter a valid GST number.',
                            minlength: 'GST number must be 15 characters.',
                            maxlength: 'GST number must be 15 characters.',
                        },
                        pan_number: {
                            panPattern: 'Please enter a valid PAN number.',
                            minlength: 'PAN number must be 10 characters.',
                            maxlength: 'PAN number must be 10 characters.',
                        },
                        registration_date: { dateISO: 'Please enter a valid date.' },
                        from_address: { email: 'Please enter a valid email address.', maxlength: 'From address must not exceed 191 characters.' },
                        to_address: { email: 'Please enter a valid email address.', maxlength: 'To address must not exceed 191 characters.' },
                        project_reference_year: { digits: 'Year must be numeric.', min: 'Year must be between 2000 and 2100.', max: 'Year must be between 2000 and 2100.' },
                        project_reference_sequence: { digits: 'Sequence must be numeric.', min: 'Sequence must be at least 1.' },
                        challan_sequence: { digits: 'Sequence must be numeric.', min: 'Sequence must be at least 1.' },
                        quotation_sequence: { digits: 'Sequence must be numeric.', min: 'Sequence must be at least 1.' },
                        holiday_date: { required: 'Please select a holiday date.', dateISO: 'Please enter a valid date.' },
                        title: { required: 'Please enter a title.', minlength: 'Please enter at least 2 characters.' },
                    };

                    function showValidationError(message) {
                        Swal.fire({
                            title: 'Validation Error',
                            text: message || 'Please check the highlighted fields.',
                            icon: 'error',
                            confirmButtonColor: '#0f172a',
                        });
                    }

                function trimToMaxLength(field) {
                    if (!field) return;
                    field.addEventListener('input', function () {
                        const maxLength = Number(this.getAttribute('maxlength') || 0);
                        if (maxLength > 0 && this.value.length > maxLength) {
                            this.value = this.value.slice(0, maxLength);
                        }
                    });
                }

                function validateNumberingForm(form, label, prefixField, yearField, sequenceField) {
                    if (!form) {
                        return;
                    }

                    $(form).validate({
                        rules: {
                            [prefixField]: { required: true, minlength: 1, maxlength: 50 },
                            [yearField]: { required: true, digits: true, min: 2000, max: 2100 },
                            [sequenceField]: { required: true, digits: true, min: 1 },
                        },
                        messages: {
                            [prefixField]: {
                                required: `${label} prefix is required.`,
                                maxlength: `${label} prefix must not exceed 50 characters.`,
                            },
                            [yearField]: {
                                required: `${label} year is required.`,
                                digits: `${label} year must be numeric.`,
                                min: `${label} year must be between 2000 and 2100.`,
                                max: `${label} year must be between 2000 and 2100.`,
                            },
                            [sequenceField]: {
                                required: `${label} sequence is required.`,
                                digits: `${label} sequence must be numeric.`,
                                min: `${label} sequence must be at least 1.`,
                            },
                        },
                        errorClass: 'text-xs text-rose-600 mt-1 block',
                        errorPlacement: placeFieldError,
                        invalidHandler: function (event, validator) {
                            showValidationError(validator?.errorList?.[0]?.message);
                        }
                    });

                    form.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                }

                function placeFieldError(error, element) {
                    error.addClass('text-xs text-rose-600 mt-2 block');
                    error.insertAfter(element);
                }

                    function validateFileSizeField(field, showPopup = true) {
                        if (!field || !field.files || !field.files.length) return true;

                        const maxSize = Number(field.dataset.maxSize || 0);
                        if (!maxSize) return true;

                        const file = field.files[0];
                        if (file.size <= maxSize) return true;

                        const maxSizeLabel = field.dataset.maxSizeLabel || `${Math.round(maxSize / (1024 * 1024))} MB`;
                        const message = `${file.name} is too large. Please upload a file smaller than ${maxSizeLabel}.`;
                        const validator = window.jQuery ? window.jQuery(field.form).data('validator') : null;

                        if (validator) {
                            validator.showErrors({ [field.name]: message });
                        }

                        field.value = '';

                        if (showPopup) {
                            showValidationError(message);
                        }

                        return false;
                    }

                    if (printBrandingForm) {
                        $(printBrandingForm).validate({
                            rules: {
                                print_logo: { maxFileSize: true },
                            },
                            messages: {
                                print_logo: {
                                    maxFileSize: 'Please upload a print logo smaller than 50 MB.',
                                },
                            },
                            errorClass: 'text-xs text-rose-600 mt-2 block',
                            errorPlacement: placeFieldError,
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please check the selected print logo file.');
                            },
                            submitHandler: function (formEl) {
                                const fileFields = formEl.querySelectorAll('.js-file-size-validate');
                                for (const field of fileFields) {
                                    if (!validateFileSizeField(field, true)) {
                                        return false;
                                    }
                                }

                                formEl.submit();
                                return false;
                            }
                        });

                        printBrandingForm.querySelectorAll('.js-file-size-validate').forEach(function (field) {
                            field.addEventListener('change', function () {
                                validateFileSizeField(field, true);
                            });
                        });
                    }

                    if (companyDetailsForm) {
                        const companyRules = {
                            name: { required: true, minlength: 2, maxlength: 191 },
                            email: { required: true, strictEmail: true, maxlength: 191 },
                            phone_number: { required: true, digits: true, minlength: 10, maxlength: 10 },
                            gst_number: { required: false, gstPattern: true, minlength: 15, maxlength: 15 },
                            pan_number: { required: false, panPattern: true, minlength: 10, maxlength: 10 },
                            registration_number: { required: false, minlength: 2, maxlength: 100 },
                            registration_date: { required: false, dateISO: true },
                            address: { required: false, minlength: 5, maxlength: 1000 },
                        };

                        const companyMessages = {
                            name: {
                                required: 'Company name is required.',
                                minlength: 'Company name must be at least 2 characters.',
                                maxlength: 'Company name must not exceed 191 characters.',
                            },
                            email: {
                                required: 'Company email is required.',
                                strictEmail: 'Please enter a valid email address.',
                                maxlength: 'Company email must not exceed 191 characters.',
                            },
                            phone_number: {
                                required: 'Phone number is required.',
                                digits: 'Phone number must contain only digits.',
                                minlength: 'Phone number must be exactly 10 digits.',
                                maxlength: 'Phone number must be exactly 10 digits.',
                            },
                            gst_number: {
                                gstPattern: 'Please enter a valid GST number.',
                                minlength: 'GST number must be 15 characters.',
                                maxlength: 'GST number must be 15 characters.',
                            },
                            pan_number: {
                                panPattern: 'Please enter a valid PAN number.',
                                minlength: 'PAN number must be 10 characters.',
                                maxlength: 'PAN number must be 10 characters.',
                            },
                            registration_number: {
                                minlength: 'Registration number must be at least 2 characters.',
                                maxlength: 'Registration number must not exceed 100 characters.',
                            },
                            registration_date: { dateISO: 'Please enter a valid date.' },
                            address: {
                                minlength: 'Address must be at least 5 characters.',
                                maxlength: 'Address must not exceed 1000 characters.',
                            },
                        };

                        $(companyDetailsForm).validate({
                            rules: companyRules,
                            messages: companyMessages,
                            errorClass: 'text-xs text-rose-600 mt-1 block',
                            highlight: function (element) {
                                $(element).addClass('border-rose-400 ring-1 ring-rose-300');
                            },
                            unhighlight: function (element) {
                                $(element).removeClass('border-rose-400 ring-1 ring-rose-300');
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message);
                            }
                        });

                        const phoneField = companyDetailsForm.querySelector('#phone_number');
                        if (phoneField) {
                            phoneField.addEventListener('input', function () {
                                this.value = this.value.replace(/\D/g, '').slice(0, 10);
                            });

                            phoneField.addEventListener('keypress', function (event) {
                                const key = event.key;
                                if (!/[0-9]/.test(key) && !['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter'].includes(key)) {
                                    event.preventDefault();
                                }
                            });

                            phoneField.addEventListener('paste', function (event) {
                                const pasted = (event.clipboardData || window.clipboardData)?.getData('text') || '';
                                if (!/^\d*$/.test(pasted)) {
                                    event.preventDefault();
                                }
                            });
                        }

                        companyDetailsForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    validateNumberingForm(invoiceNumberingForm, 'Invoice', 'invoice_prefix', 'invoice_year', 'invoice_sequence');
                    validateNumberingForm(quotationNumberingForm, 'Quotation', 'quotation_prefix', 'quotation_year', 'quotation_sequence');
                    if (projectSettingsForm) {
                        $(projectSettingsForm).validate({
                            rules: {
                                project_reference_prefix: { required: false, maxlength: 50 },
                                project_reference_year: { required: false, digits: true, min: 2000, max: 2100 },
                                project_reference_sequence: { required: false, digits: true, min: 1 },
                            },
                            messages: {
                                project_reference_prefix: { maxlength: 'Prefix must not exceed 50 characters.' },
                                project_reference_year: {
                                    digits: 'Year must be numeric.',
                                    min: 'Year must be between 2000 and 2100.',
                                    max: 'Year must be between 2000 and 2100.',
                                },
                                project_reference_sequence: {
                                    digits: 'Sequence must be numeric.',
                                    min: 'Sequence must be at least 1.',
                                },
                            },
                            errorClass: 'text-xs text-rose-600 mt-1 block',
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message);
                            }
                        });
                        projectSettingsForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    const unitEditForm = document.getElementById('unit-edit-form');
                    const gstRateEditForm = document.getElementById('gst-rate-edit-form');

                    function bindMasterValidation(form, submitMessage, fieldName, maxValue) {
                        if (!form) {
                            return;
                        }

                        $(form).validate({
                            rules: {
                                [fieldName]: maxValue
                                    ? { required: true, number: true, min: 0, max: maxValue }
                                    : { required: true, minlength: 1, maxlength: 50 },
                            },
                            messages: {
                                [fieldName]: maxValue
                                    ? {
                                        required: 'GST rate is required.',
                                        number: 'GST rate must be a valid number.',
                                        min: 'GST rate cannot be negative.',
                                        max: 'GST rate must not exceed 100%.',
                                    }
                                    : {
                                        required: 'Unit name is required.',
                                        minlength: 'Unit name must be at least 1 character.',
                                        maxlength: 'Unit name must not exceed 50 characters.',
                                    },
                            },
                            errorClass: 'text-xs text-rose-600 mt-1 block',
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || (maxValue ? 'Please enter a valid GST rate.' : 'Please enter a valid unit name.'));
                            },
                            submitHandler: function (formEl) {
                                const submitButton = formEl.querySelector('button[type="submit"]');
                                if (submitButton) {
                                    submitButton.disabled = true;
                                    submitButton.textContent = submitMessage;
                                }
                                formEl.submit();
                                return false;
                            }
                        });

                        form.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    bindMasterValidation(unitEditForm, 'Updating...', 'name');
                    bindMasterValidation(gstRateEditForm, 'Updating...', 'rate', 100);

                    if (challanSettingsForm) {
                        $(challanSettingsForm).validate({
                            rules: {
                                challan_prefix: { required: false, maxlength: 50 },
                                challan_sequence: { required: false, digits: true, min: 1 },
                            },
                            messages: {
                                challan_prefix: { maxlength: 'Challan prefix must not exceed 50 characters.' },
                                challan_sequence: {
                                    digits: 'Sequence must be numeric.',
                                    min: 'Sequence must be at least 1.',
                                },
                            },
                            errorClass: 'text-xs text-rose-600 mt-1 block',
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message);
                            }
                        });
                        challanSettingsForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);

                        const challanPrefixInput = document.getElementById('challan_prefix');
                        const challanSequenceInput = document.getElementById('challan_sequence');
                        const challanSequenceNote = document.getElementById('challan-sequence-note');
                        let challanCheckTimer = null;
                        let challanCheckController = null;

                        function setChallanSequenceNote(message, tone) {
                            challanSequenceNote.textContent = message;
                            challanSequenceNote.classList.toggle('hidden', !message);
                            challanSequenceNote.classList.toggle('text-emerald-600', tone === 'ok');
                            challanSequenceNote.classList.toggle('text-amber-600', tone === 'warn');
                        }

                        async function checkChallanSequence() {
                            const sequence = parseInt(challanSequenceInput.value, 10);
                            if (!Number.isInteger(sequence) || sequence < 1) {
                                setChallanSequenceNote('', null);
                                return;
                            }

                            if (challanCheckController) {
                                challanCheckController.abort();
                            }
                            challanCheckController = new AbortController();

                            const params = new URLSearchParams({ prefix: challanPrefixInput.value.trim(), sequence: String(sequence) });

                            try {
                                const response = await fetch(`${challanSequenceInput.dataset.checkUrl}?${params}`, {
                                    headers: { 'Accept': 'application/json' },
                                    signal: challanCheckController.signal,
                                });
                                if (!response.ok) {
                                    setChallanSequenceNote('', null);
                                    return;
                                }

                                const result = await response.json();
                                if (result.available) {
                                    setChallanSequenceNote(`${result.number} is available. The next challan will use this number.`, 'ok');
                                } else {
                                    setChallanSequenceNote(`${result.number} is already used. The next challan will use ${result.next_number}.`, 'warn');
                                }
                            } catch (error) {
                                if (error.name !== 'AbortError') {
                                    setChallanSequenceNote('', null);
                                }
                            }
                        }

                        function scheduleChallanSequenceCheck() {
                            clearTimeout(challanCheckTimer);
                            challanCheckTimer = setTimeout(checkChallanSequence, 350);
                        }

                        if (challanPrefixInput && challanSequenceInput && challanSequenceNote) {
                            challanPrefixInput.addEventListener('input', scheduleChallanSequenceCheck);
                            challanSequenceInput.addEventListener('input', scheduleChallanSequenceCheck);
                            checkChallanSequence();
                        }
                    }

                    const contractorCreateForm = document.getElementById('contractor-create-form');
                    const contractorEditFormEl = document.getElementById('contractor-edit-form');

                    function initAddressAutocomplete(input, suggestionsContainer, onApply) {
                        if (!input || !suggestionsContainer) {
                            return;
                        }

                        const autocompleteUrl = input.dataset.autocompleteUrl || '';
                        let autocompleteTimer = null;
                        let autocompleteAbortController = null;
                        let suggestionsList = [];
                        let highlightedIndex = -1;
                        let suppressLookup = false;

                        function escapeHtml(value) {
                            return String(value).replace(/[&<>"']/g, function (char) {
                                return ({
                                    '&': '&amp;',
                                    '<': '&lt;',
                                    '>': '&gt;',
                                    '"': '&quot;',
                                    "'": '&#039;'
                                })[char];
                            });
                        }

                        function getSuggestionLabel(item) {
                            if (!item || typeof item !== 'object') {
                                return '';
                            }

                            if (typeof item.display_name === 'string' && item.display_name.trim() !== '') {
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
                                const normalized = String(value || '').trim();
                                return normalized !== '' && values.indexOf(value) === index;
                            });

                            return parts.join(', ');
                        }

                        function normalizeSuggestions(payload) {
                            if (Array.isArray(payload)) {
                                return payload;
                            }

                            if (Array.isArray(payload?.features)) {
                                return payload.features;
                            }

                            return [];
                        }

                        function hideSuggestions() {
                            suggestionsContainer.classList.add('hidden');
                            suggestionsContainer.innerHTML = '';
                            suggestionsList = [];
                            highlightedIndex = -1;
                        }

                        function renderSuggestions(results) {
                            suggestionsList = results;
                            highlightedIndex = -1;

                            if (!results.length) {
                                suggestionsContainer.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No matching locations found.</div>';
                                suggestionsContainer.classList.remove('hidden');
                                return;
                            }

                            suggestionsContainer.innerHTML = results.map((item, index) => {
                                const displayName = escapeHtml(getSuggestionLabel(item));
                                return `
                                    <button
                                        type="button"
                                        class="settings-address-suggestion-item flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
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
                            }).join('');

                            suggestionsContainer.classList.remove('hidden');
                        }

                        function setHighlightedSuggestion(index) {
                            if (!suggestionsList.length) {
                                return;
                            }

                            const items = suggestionsContainer.querySelectorAll('.settings-address-suggestion-item');
                            items.forEach((item, itemIndex) => {
                                const isActive = itemIndex === index;
                                item.classList.toggle('bg-slate-50', isActive);
                                item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                            });
                            highlightedIndex = index;
                        }

                        function applySuggestion(index) {
                            const selectedSuggestion = suggestionsList[index];
                            if (!selectedSuggestion) {
                                return;
                            }

                            suppressLookup = true;
                            input.value = getSuggestionLabel(selectedSuggestion);
                            hideSuggestions();
                            onApply?.(input);

                            window.setTimeout(function () {
                                suppressLookup = false;
                            }, 0);
                        }

                        async function fetchSuggestions(query) {
                            if (!autocompleteUrl) {
                                return;
                            }

                            if (autocompleteAbortController) {
                                autocompleteAbortController.abort();
                            }

                            autocompleteAbortController = new AbortController();

                            try {
                                const response = await fetch(`${autocompleteUrl}${encodeURIComponent(query)}`, {
                                    method: 'GET',
                                    headers: {
                                        'Accept': 'application/json',
                                    },
                                    signal: autocompleteAbortController.signal,
                                });

                                if (!response.ok) {
                                    hideSuggestions();
                                    return;
                                }

                                const results = await response.json();
                                renderSuggestions(normalizeSuggestions(results));
                            } catch (error) {
                                if (error.name !== 'AbortError') {
                                    hideSuggestions();
                                }
                            }
                        }

                        function queueLookup() {
                            if (suppressLookup) {
                                return;
                            }

                            const query = input.value.trim();

                            if (autocompleteTimer) {
                                window.clearTimeout(autocompleteTimer);
                            }

                            if (query.length < 3) {
                                hideSuggestions();
                                return;
                            }

                            autocompleteTimer = window.setTimeout(function () {
                                fetchSuggestions(query);
                            }, 350);
                        }

                        input.addEventListener('input', queueLookup);
                        input.addEventListener('keydown', function (event) {
                            if (suggestionsContainer.classList.contains('hidden') || !suggestionsList.length) {
                                return;
                            }

                            if (event.key === 'ArrowDown') {
                                event.preventDefault();
                                const nextIndex = highlightedIndex < suggestionsList.length - 1
                                    ? highlightedIndex + 1
                                    : 0;
                                setHighlightedSuggestion(nextIndex);
                                return;
                            }

                            if (event.key === 'ArrowUp') {
                                event.preventDefault();
                                const previousIndex = highlightedIndex > 0
                                    ? highlightedIndex - 1
                                    : suggestionsList.length - 1;
                                setHighlightedSuggestion(previousIndex);
                                return;
                            }

                            if (event.key === 'Enter' && highlightedIndex >= 0) {
                                event.preventDefault();
                                applySuggestion(highlightedIndex);
                                return;
                            }

                            if (event.key === 'Escape') {
                                hideSuggestions();
                            }
                        });

                        suggestionsContainer.addEventListener('mousedown', function (event) {
                            const button = event.target.closest('[data-address-index]');
                            if (!button) {
                                return;
                            }

                            event.preventDefault();
                            applySuggestion(Number(button.dataset.addressIndex));
                        });

                        document.addEventListener('click', function (event) {
                            if (event.target === input || suggestionsContainer.contains(event.target)) {
                                return;
                            }

                            hideSuggestions();
                        });
                    }

                    function bindContractorValidation(form, isCreate) {
                        if (!form) return;

                        const validateOpts = {
                            rules: {
                                name: {
                                    required: true,
                                    minlength: 2,
                                    maxlength: 191,
                                    normalizer: function (value) {
                                        return $.trim(value);
                                    },
                                },
                                phone_number: { required: true, digits: true, minlength: 10, maxlength: 10 },
                                email: {
                                    required: true,
                                    email: true,
                                    maxlength: 191,
                                    normalizer: function (value) {
                                        return $.trim(value);
                                    },
                                },
                                referral_name: {
                                    required: false,
                                    minlength: 2,
                                    maxlength: 191,
                                    normalizer: function (value) {
                                        return $.trim(value);
                                    },
                                },
                                address: {
                                    required: false,
                                    maxlength: 1000,
                                    normalizer: function (value) {
                                        return $.trim(value);
                                    },
                                },
                                notes: {
                                    required: false,
                                    maxlength: 2000,
                                    normalizer: function (value) {
                                        return $.trim(value);
                                    },
                                },
                            },
                            messages: {
                                name: {
                                    required: 'Contractor name is required.',
                                    minlength: 'Contractor name must be at least 2 characters.',
                                    maxlength: 'Contractor name must not exceed 191 characters.',
                                },
                                phone_number: {
                                    required: 'Contact number is required.',
                                    digits: 'Contact number must contain only digits.',
                                    minlength: 'Contact number must be exactly 10 digits.',
                                    maxlength: 'Contact number must be exactly 10 digits.',
                                },
                                email: {
                                    required: 'Email address is required.',
                                    email: 'Please enter a valid email address.',
                                    maxlength: 'Email must not exceed 191 characters.',
                                },
                                referral_name: {
                                    minlength: 'Referral name must be at least 2 characters.',
                                    maxlength: 'Referral name must not exceed 191 characters.',
                                },
                                address: { maxlength: 'Address must not exceed 1000 characters.' },
                                notes: { maxlength: 'Notes must not exceed 2000 characters.' },
                            },
                            errorClass: 'text-xs text-rose-600 mt-1 block',
                            highlight: function (element) {
                                $(element).addClass('border-rose-400 ring-1 ring-rose-300');
                            },
                            unhighlight: function (element) {
                                $(element).removeClass('border-rose-400 ring-1 ring-rose-300');
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please fix the highlighted errors in the contractor form.');
                            },
                        };

                        if (isCreate) {
                            validateOpts.submitHandler = function (formEl) {
                                const submitBtn = formEl.querySelector('#contractor-create-submit');
                                if (submitBtn) {
                                    submitBtn.disabled = true;
                                    submitBtn.textContent = 'Adding...';
                                }
                                formEl.submit();
                                return false;
                            };
                        }

                        $(form).validate(validateOpts);
                        form.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);

                        const phoneField = form.querySelector('[name="phone_number"]');

                        if (phoneField) {
                            phoneField.addEventListener('input', function () {
                                this.value = this.value.replace(/\D/g, '').slice(0, 10);
                            });
                        }
                    }

                    bindContractorValidation(contractorCreateForm, true);
                    bindContractorValidation(contractorEditFormEl, false);
                    initAddressAutocomplete(
                        document.getElementById('contractor_address'),
                        document.getElementById('contractor-address-suggestions'),
                        function (element) {
                            $(element).valid();
                        }
                    );
                    initAddressAutocomplete(
                        document.getElementById('edit_contractor_address'),
                        document.getElementById('edit-contractor-address-suggestions'),
                        function (element) {
                            $(element).valid();
                        }
                    );

                    const companyAddressInput = document.getElementById('address');
                    const companyAddressSuggestions = document.getElementById('company-address-suggestions');
                    const companyLatitudeInput = document.getElementById('latitude');
                    const companyLongitudeInput = document.getElementById('longitude');

                    initAddressAutocomplete(
                        companyAddressInput,
                        companyAddressSuggestions,
                        function (element) {
                            if (!companyLatitudeInput || !companyLongitudeInput) {
                                return;
                            }

                            const address = (element?.value || '').trim();
                            if (!address || !window.google || !google.maps || typeof google.maps.Geocoder !== 'function') {
                                companyLatitudeInput.value = '';
                                companyLongitudeInput.value = '';
                                return;
                            }

                            const geocoder = new google.maps.Geocoder();
                            geocoder.geocode({ address }, function (results, status) {
                                if (status === 'OK' && results && results[0] && results[0].geometry && results[0].geometry.location) {
                                    companyLatitudeInput.value = String(results[0].geometry.location.lat());
                                    companyLongitudeInput.value = String(results[0].geometry.location.lng());
                                    return;
                                }

                                companyLatitudeInput.value = '';
                                companyLongitudeInput.value = '';
                            });
                        }
                    );

                    if (window.google?.maps?.places && companyAddressInput && companyLatitudeInput && companyLongitudeInput) {
                        const autocomplete = new google.maps.places.Autocomplete(companyAddressInput, {
                            componentRestrictions: { country: ['in'] },
                            fields: ['formatted_address', 'geometry'],
                        });

                        autocomplete.addListener('place_changed', function () {
                            const place = autocomplete.getPlace();
                            if (!place?.geometry?.location) {
                                return;
                            }

                            companyAddressInput.value = place.formatted_address || companyAddressInput.value;
                            companyLatitudeInput.value = String(place.geometry.location.lat());
                            companyLongitudeInput.value = String(place.geometry.location.lng());
                        });
                    }

                    const inlineDeleteForms = [
                        ...document.querySelectorAll('.company-lead-source-delete-form'),
                        ...document.querySelectorAll('.company-lead-custom-field-delete-form'),
                        ...document.querySelectorAll('.company-category-delete-form'),
                        ...document.querySelectorAll('.company-holiday-delete-form'),
                        ...document.querySelectorAll('.company-project-stage-delete-form'),
                        ...document.querySelectorAll('.company-service-stage-delete-form'),
                        ...document.querySelectorAll('.company-status-delete-form'),
                        ...document.querySelectorAll('.unit-delete-form'),
                        ...document.querySelectorAll('.contractor-delete-form'),
                        ...document.querySelectorAll('.gst-rate-delete-form'),
                    ];

                    inlineDeleteForms.forEach(function (deleteForm) {
                        deleteForm.addEventListener('submit', function (event) {
                            if (deleteForm.dataset.confirmed === '1') {
                                return;
                            }

                            event.preventDefault();
                            const message = deleteForm.dataset.deleteMessage || 'Are you sure you want to delete this item?';

                            Swal.fire({
                                title: 'Confirm Delete',
                                text: message,
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, delete it',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#e11d48',
                                cancelButtonColor: '#475569',
                            }).then(function (result) {
                                if (result.isConfirmed) {
                                    deleteForm.dataset.confirmed = '1';
                                    deleteForm.submit();
                                }
                            });
                        });
                    });

                    const statusForm = document.getElementById('status-form');
                    if (statusForm) {
                        $(statusForm).validate({
                            rules: {
                                name: { required: true, minlength: 2, maxlength: 191 },
                                color: { required: true },
                            },
                            messages: {
                                name: {
                                    required: 'Please enter a stage name.',
                                    minlength: 'Stage name must be at least 2 characters.',
                                    maxlength: 'Stage name must not exceed 191 characters.',
                                },
                                color: { required: 'Please choose a color.' },
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please check the stage details.');
                            },
                        });
                    }

                    const projectStageForm = document.getElementById('project-stage-form');
                    if (projectStageForm) {
                        $(projectStageForm).validate({
                            rules: {
                                name: { required: true, minlength: 2, maxlength: 191 },
                                color: { required: true },
                            },
                            messages: {
                                name: {
                                    required: 'Please enter a stage name.',
                                    minlength: 'Stage name must be at least 2 characters.',
                                    maxlength: 'Stage name must not exceed 191 characters.',
                                },
                                color: { required: 'Please choose a color.' },
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please check the project stage details.');
                            },
                        });
                    }

                    const holidayForm = document.getElementById('holiday-form');
                    if (holidayForm) {
                        $(holidayForm).validate({
                            rules: {
                                holiday_date: { required: true, dateISO: true },
                                title: { required: true, minlength: 2, maxlength: 100 },
                                description: { maxlength: 100 },
                            },
                            messages: {
                                holiday_date: {
                                    required: 'Please select a holiday date.',
                                    dateISO: 'Please choose a valid date.',
                                },
                                title: {
                                    required: 'Please enter a holiday title.',
                                    minlength: 'Holiday title must be at least 2 characters.',
                                    maxlength: 'Holiday title must not exceed 100 characters.',
                                },
                                description: {
                                    maxlength: 'Description must not exceed 100 characters.',
                                },
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please check the holiday details.');
                            },
                        });
                        holidayForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    [statusForm, projectStageForm, holidayForm].forEach(function (formEl) {
                        if (!formEl) return;
                        formEl.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    });

                    if (leadSourceForm) {
                        $(leadSourceForm).validate({
                            rules: {
                                lead_source_name: { required: true, minlength: 2, maxlength: 191 },
                            },
                            messages: {
                                lead_source_name: {
                                    required: 'Please enter a lead source name.',
                                    minlength: 'Lead source name must be at least 2 characters.',
                                    maxlength: 'Lead source name must not exceed 191 characters.',
                                }
                            },
                            invalidHandler: function (event, validator) {
                                showValidationError(validator?.errorList?.[0]?.message || 'Please enter a valid lead source.');
                            },
                            submitHandler: function (formEl) {
                                const value = (formEl.querySelector('#lead_source_name')?.value || '').trim().toLowerCase();
                                const currentKey = formEl.querySelector('#lead_source_key')?.value;
                                const duplicate = existingLeadSources.some((source) => {
                                    if (currentKey && String(source.key || '') === String(currentKey)) {
                                        return false;
                                    }

                                    return String(source.name || '').trim().toLowerCase() === value;
                                });

                                if (duplicate) {
                                    showValidationError('This lead source already exists.');
                                    return false;
                                }

                                if (leadSourceSubmit) {
                                    leadSourceSubmit.disabled = true;
                                    leadSourceSubmit.textContent = 'Saving...';
                                }

                                formEl.submit();
                                return false;
                            }
                        });

                        leadSourceForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    if (leadCustomFieldForm) {
                        const supportedOptionsTypes = ['dropdown', 'radiobutton'];

                        function updateLeadCustomFieldOptionsState() {
                            if (!leadCustomFieldTypeInput || !leadCustomFieldOptionsWrapper || !leadCustomFieldOptionsInput) {
                                return;
                            }

                            const requiresOptions = supportedOptionsTypes.includes(leadCustomFieldTypeInput.value);
                            leadCustomFieldOptionsWrapper.classList.toggle('hidden', !requiresOptions);
                            leadCustomFieldOptionsInput.required = requiresOptions;
                        }

                        if (leadCustomFieldTypeInput) {
                            leadCustomFieldTypeInput.addEventListener('change', updateLeadCustomFieldOptionsState);
                        }

                        updateLeadCustomFieldOptionsState();

                        leadCustomFieldForm.addEventListener('submit', function (event) {
                            const labelValue = (leadCustomFieldLabelInput?.value || '').trim().toLowerCase();
                            const currentKey = leadCustomFieldForm.querySelector('#lead_custom_field_key')?.value;
                            const duplicate = existingLeadCustomFields.some((field) => {
                                if (currentKey && String(field.key || '') === String(currentKey)) {
                                    return false;
                                }

                                return String(field.label || '').trim().toLowerCase() === labelValue;
                            });

                            if (duplicate) {
                                event.preventDefault();
                                showValidationError('This field label already exists.');
                                return;
                            }

                            const selectedType = leadCustomFieldTypeInput?.value || 'text';
                            const requiresOptions = supportedOptionsTypes.includes(selectedType);
                            const optionsValue = (leadCustomFieldOptionsInput?.value || '').trim();

                            if (requiresOptions && !optionsValue) {
                                event.preventDefault();
                                showValidationError('Please provide at least one option for this field type.');
                                return;
                            }
                        });

                        leadCustomFieldForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }

                    if (projectCustomFieldForm) {
                        const supportedOptionsTypes = ['dropdown', 'radiobutton'];

                        function updateProjectCustomFieldOptionsState() {
                            if (!projectCustomFieldTypeInput || !projectCustomFieldOptionsWrapper || !projectCustomFieldOptionsInput) {
                                return;
                            }

                            const requiresOptions = supportedOptionsTypes.includes(projectCustomFieldTypeInput.value);
                            projectCustomFieldOptionsWrapper.classList.toggle('hidden', !requiresOptions);
                            projectCustomFieldOptionsInput.required = requiresOptions;
                        }

                        if (projectCustomFieldTypeInput) {
                            projectCustomFieldTypeInput.addEventListener('change', updateProjectCustomFieldOptionsState);
                        }

                        updateProjectCustomFieldOptionsState();

                        projectCustomFieldForm.addEventListener('submit', function (event) {
                            const labelValue = (projectCustomFieldLabelInput?.value || '').trim().toLowerCase();
                            const currentKey = projectCustomFieldForm.querySelector('#project_custom_field_key')?.value;
                            const duplicate = existingProjectCustomFields.some((field) => {
                                if (currentKey && String(field.key || '') === String(currentKey)) {
                                    return false;
                                }

                                return String(field.label || '').trim().toLowerCase() === labelValue;
                            });

                            if (duplicate) {
                                event.preventDefault();
                                showValidationError('This field label already exists.');
                                return;
                            }

                            const selectedType = projectCustomFieldTypeInput?.value || 'text';
                            const requiresOptions = supportedOptionsTypes.includes(selectedType);
                            const optionsValue = (projectCustomFieldOptionsInput?.value || '').trim();

                            if (requiresOptions && !optionsValue) {
                                event.preventDefault();
                                showValidationError('Please provide at least one option for this field type.');
                                return;
                            }
                        });

                        projectCustomFieldForm.querySelectorAll('input[maxlength], textarea[maxlength]').forEach(trimToMaxLength);
                    }
                }

                const leadSourceModal = document.getElementById('lead-source-modal');
                const leadSourceModalTitle = document.getElementById('lead-source-modal-title');
                const leadSourceMethod = document.getElementById('lead-source-method');
                const leadSourceMode = document.getElementById('lead_source_mode');
                const leadSourceKey = document.getElementById('lead_source_key');
                const leadSourceNameInput = document.getElementById('lead_source_name');
                const leadSourceReferralCheckbox = document.getElementById('show_referral_person_name');
                const taskMasterCreateForm = document.getElementById('task-master-create-form');
                const taskMasterCreateSubmit = document.getElementById('task-master-create-submit');
                const taskMasterModal = document.getElementById('task-master-modal');
                const taskMasterModalTitle = document.getElementById('task-master-modal-title');
                const taskMasterForm = document.getElementById('task-master-form');
                const taskMasterMethod = document.getElementById('task-master-method');
                const taskMasterIndex = document.getElementById('task_master_index');
                const taskMasterNameInput = document.getElementById('task_master_name_modal');
                const taskMasterSubmit = document.getElementById('task-master-submit');
                const modal = document.getElementById('category-edit-modal');
                const modalNameInput = document.getElementById('edit_category_name');
                const modalForm = document.getElementById('category-edit-form');
                const modalCloseButtons = document.querySelectorAll('[data-category-modal-close]');
                const categoryCreateModal = document.getElementById('category-create-modal');
                const categoryCreateForm = document.getElementById('category-create-form');
                const categoryCreateSubmit = document.getElementById('category-create-submit');
                const unitModal = document.getElementById('unit-edit-modal');
                const unitEditForm = document.getElementById('unit-edit-form');
                const unitEditName = document.getElementById('edit_unit_name');
                const unitCreateModal = document.getElementById('unit-create-modal');
                const unitCreateForm = document.getElementById('unit-create-form');
                const unitCreateSubmit = document.getElementById('unit-create-submit');
                const contractorModal = document.getElementById('contractor-edit-modal');
                const contractorEditForm = document.getElementById('contractor-edit-form');
                const contractorEditName = document.getElementById('edit_contractor_name');
                const contractorEditPhone = document.getElementById('edit_contractor_phone_number');
                const contractorEditEmail = document.getElementById('edit_contractor_email');
                const contractorEditReferral = document.getElementById('edit_contractor_referral_name');
                const contractorEditAddress = document.getElementById('edit_contractor_address');
                const contractorEditNotes = document.getElementById('edit_contractor_notes');
                const gstRateModal = document.getElementById('gst-rate-edit-modal');
                const gstRateEditForm = document.getElementById('gst-rate-edit-form');
                const gstRateEditValue = document.getElementById('edit_gst_rate');
                const gstRateCreateModal = document.getElementById('gst-rate-create-modal');
                const gstRateCreateForm = document.getElementById('gst-rate-create-form');
                const gstRateCreateSubmit = document.getElementById('gst-rate-create-submit');

                function openLeadSourceModal(payload) {
                    if (!leadSourceModal || !leadSourceForm || !leadSourceMethod || !leadSourceNameInput || !leadSourceMode || !leadSourceKey || !leadSourceReferralCheckbox) {
                        return;
                    }

                    const leadSourceIdentifier = payload?.key ?? payload?.index ?? null;
                    const isEdit = leadSourceIdentifier !== null && leadSourceIdentifier !== '';
                    leadSourceModalTitle.textContent = isEdit ? 'Edit Lead Source' : 'Add Lead Source';
                    leadSourceForm.action = isEdit ? `${leadSourceForm.dataset.updateBase}/${leadSourceIdentifier}` : leadSourceForm.dataset.storeAction;
                    leadSourceMethod.value = isEdit ? 'PATCH' : 'POST';
                    leadSourceMode.value = isEdit ? 'edit' : 'create';
                    leadSourceKey.value = isEdit ? String(leadSourceIdentifier) : '';
                    leadSourceNameInput.value = payload?.name || '';
                    leadSourceReferralCheckbox.checked = Boolean(payload?.showReferralPersonName);

                    if (leadSourceSubmit) {
                        leadSourceSubmit.disabled = false;
                        leadSourceSubmit.textContent = 'Save Lead Source';
                    }

                    leadSourceModal.classList.remove('hidden');
                    setTimeout(function () {
                        leadSourceNameInput.focus();
                    }, 50);
                }

                function openLeadCustomFieldModal(payload) {
                    if (!leadCustomFieldModal || !leadCustomFieldModalTitle || !leadCustomFieldForm || !leadCustomFieldMethod || !leadCustomFieldLabelInput || !leadCustomFieldTypeInput || !leadCustomFieldPlaceholderInput || !leadCustomFieldHelpTextInput || !leadCustomFieldRequiredInput || !leadCustomFieldShowInFiltersInput || !leadCustomFieldShowInBoqQuotationInput || !leadCustomFieldOptionsInput) {
                        return;
                    }

                    const isEdit = Boolean(payload && payload.key !== undefined && payload.key !== null);
                    leadCustomFieldModalTitle.textContent = isEdit ? 'Edit Lead Custom Field' : 'Add Lead Custom Field';
                    leadCustomFieldForm.action = isEdit ? `${leadCustomFieldForm.dataset.updateBase}/${payload.key}` : leadCustomFieldForm.dataset.storeAction;
                    leadCustomFieldMethod.value = isEdit ? 'PATCH' : 'POST';
                    leadCustomFieldForm.querySelector('#lead_custom_field_key').value = isEdit ? String(payload.key) : '';
                    leadCustomFieldLabelInput.value = payload?.label || '';
                    leadCustomFieldTypeInput.value = payload?.type || 'text';
                    leadCustomFieldRequiredInput.checked = Boolean(payload?.required);
                    leadCustomFieldShowInFiltersInput.checked = Boolean(payload?.showInFilters);
                    leadCustomFieldShowInBoqQuotationInput.checked = Boolean(payload?.showInBoqQuotation);
                    leadCustomFieldPlaceholderInput.value = payload?.placeholder || '';
                    leadCustomFieldHelpTextInput.value = payload?.helpText || '';
                    leadCustomFieldOptionsInput.value = payload?.options || '';

                    if (leadCustomFieldSubmit) {
                        leadCustomFieldSubmit.disabled = false;
                        leadCustomFieldSubmit.textContent = 'Save Field';
                    }

                    const event = new Event('change');
                    leadCustomFieldTypeInput.dispatchEvent(event);
                    leadCustomFieldModal.classList.remove('hidden');
                    setTimeout(function () {
                        leadCustomFieldLabelInput.focus();
                    }, 50);
                }

                function openProjectCustomFieldModal(payload) {
                    if (!projectCustomFieldModal || !projectCustomFieldModalTitle || !projectCustomFieldForm || !projectCustomFieldMethod || !projectCustomFieldLabelInput || !projectCustomFieldTypeInput || !projectCustomFieldPlaceholderInput || !projectCustomFieldHelpTextInput || !projectCustomFieldRequiredInput || !projectCustomFieldShowInFiltersInput || !projectCustomFieldOptionsInput) {
                        return;
                    }

                    const isEdit = Boolean(payload && payload.key !== undefined && payload.key !== null);
                    projectCustomFieldModalTitle.textContent = isEdit ? 'Edit Project Custom Field' : 'Add Project Custom Field';
                    projectCustomFieldForm.action = isEdit ? `${projectCustomFieldForm.dataset.updateBase}/${payload.key}` : projectCustomFieldForm.dataset.storeAction;
                    projectCustomFieldMethod.value = isEdit ? 'PATCH' : 'POST';
                    projectCustomFieldForm.querySelector('#project_custom_field_key').value = isEdit ? String(payload.key) : '';
                    projectCustomFieldLabelInput.value = payload?.label || '';
                    projectCustomFieldTypeInput.value = payload?.type || 'text';
                    projectCustomFieldRequiredInput.checked = Boolean(payload?.required);
                    projectCustomFieldShowInFiltersInput.checked = Boolean(payload?.showInFilters);
                    projectCustomFieldPlaceholderInput.value = payload?.placeholder || '';
                    projectCustomFieldHelpTextInput.value = payload?.helpText || '';
                    projectCustomFieldOptionsInput.value = payload?.options || '';

                    if (projectCustomFieldSubmit) {
                        projectCustomFieldSubmit.disabled = false;
                        projectCustomFieldSubmit.textContent = 'Save Field';
                    }

                    const event = new Event('change');
                    projectCustomFieldTypeInput.dispatchEvent(event);
                    projectCustomFieldModal.classList.remove('hidden');
                    setTimeout(function () {
                        projectCustomFieldLabelInput.focus();
                    }, 50);
                }

                function openCreatePopup(modalElement, focusSelector, submitButton, submitLabel) {
                    if (!modalElement || !submitButton) {
                        return;
                    }

                    submitButton.disabled = false;
                    submitButton.textContent = submitLabel;
                    modalElement.classList.remove('hidden');

                    window.setTimeout(function () {
                        const focusTarget = focusSelector ? modalElement.querySelector(focusSelector) : null;
                        focusTarget?.focus();
                    }, 50);
                }

                function closeCreatePopup(modalElement) {
                    modalElement?.classList.add('hidden');
                }

                function openCategoryCreateModal() {
                    openCreatePopup(categoryCreateModal, '#category_name', categoryCreateSubmit, 'Add Category');
                }

                function openUnitCreateModal() {
                    openCreatePopup(unitCreateModal, '#unit_name', unitCreateSubmit, 'Add Unit');
                }

                function openGstRateCreateModal() {
                    openCreatePopup(gstRateCreateModal, '#gst_rate', gstRateCreateSubmit, 'Add GST Rate');
                }

                window.addEventListener('open-lead-source-modal', function () {
                    openLeadSourceModal(null);
                });

                window.addEventListener('open-lead-source-edit', function (event) {
                    openLeadSourceModal(event.detail || null);
                });

                window.addEventListener('open-lead-custom-field-modal', function () {
                    openLeadCustomFieldModal(null);
                });

                window.addEventListener('open-lead-custom-field-edit', function (event) {
                    openLeadCustomFieldModal(event.detail || null);
                });

                window.addEventListener('open-project-custom-field-modal', function () {
                    openProjectCustomFieldModal(null);
                });

                window.addEventListener('open-project-custom-field-edit', function (event) {
                    openProjectCustomFieldModal(event.detail || null);
                });

                window.addEventListener('open-category-create-modal', function () {
                    openCategoryCreateModal();
                });

                window.addEventListener('open-unit-create-modal', function () {
                    openUnitCreateModal();
                });

                window.addEventListener('open-gst-rate-create-modal', function () {
                    openGstRateCreateModal();
                });

                document.querySelectorAll('[data-lead-source-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        leadSourceModal?.classList.add('hidden');
                    });
                });

                function openTaskMasterModal(payload) {
                    if (!taskMasterModal || !taskMasterModalTitle || !taskMasterForm || !taskMasterMethod || !taskMasterNameInput || !taskMasterIndex) {
                        return;
                    }

                    const isEdit = Boolean(payload && payload.index !== undefined && payload.index !== null);
                    taskMasterModalTitle.textContent = isEdit ? 'Edit Task' : 'Add Task';
                    taskMasterForm.action = isEdit ? `${taskMasterForm.dataset.updateBase}/${payload.index}` : taskMasterForm.dataset.storeAction;
                    taskMasterMethod.value = isEdit ? 'PATCH' : 'POST';
                    taskMasterIndex.value = isEdit ? String(payload.index) : '';
                    taskMasterNameInput.value = payload?.name || '';

                    if (taskMasterSubmit) {
                        taskMasterSubmit.disabled = false;
                        taskMasterSubmit.textContent = isEdit ? 'Update Task' : 'Save Task';
                    }

                    taskMasterModal.classList.remove('hidden');
                    setTimeout(function () {
                        taskMasterNameInput.focus();
                    }, 50);
                }

                async function submitTaskMasterForm(formEl) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const response = await fetch(formEl.action, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        body: new FormData(formEl),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const firstError = Object.values(data.errors || {}).flat().find(Boolean);
                        throw new Error(data.message || firstError || 'Unable to save task.');
                    }

                    return data;
                }

                function normalizeTaskMasterName(value) {
                    return String(value || '').trim().replace(/\s+/g, ' ').toLowerCase();
                }

                async function handleTaskMasterSubmit(formEl, submitButton, isEdit = false) {
                    const taskName = normalizeTaskMasterName(formEl.querySelector('[name="task_master_name"]')?.value);
                    const currentIndex = formEl.querySelector('[name="task_master_index"]')?.value;
                    const duplicate = existingTaskMasterNames.some((name, index) => {
                        if (isEdit && currentIndex !== '' && Number(currentIndex) === index) {
                            return false;
                        }

                        return normalizeTaskMasterName(name) === taskName;
                    });

                    if (duplicate) {
                        showValidationError('This task already exists.');
                        return;
                    }

                    if (submitButton) {
                        submitButton.disabled = true;
                        submitButton.textContent = isEdit ? 'Updating...' : 'Saving...';
                    }

                    try {
                        await submitTaskMasterForm(formEl);
                        taskMasterModal?.classList.add('hidden');
                        window.location.reload();
                    } catch (error) {
                        showValidationError(error?.message || 'Unable to save task.');
                    } finally {
                        if (submitButton) {
                            submitButton.disabled = false;
                            submitButton.textContent = isEdit ? 'Update Task' : 'Save Task';
                        }
                    }
                }

                window.addEventListener('open-task-master-edit', function (event) {
                    openTaskMasterModal(event.detail || null);
                });

                document.querySelectorAll('[data-task-master-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        openTaskMasterModal({
                            index: button.dataset.index,
                            name: button.dataset.name,
                        });
                    });
                });

                document.querySelectorAll('[data-task-master-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        taskMasterModal?.classList.add('hidden');
                    });
                });

                if (taskMasterForm) {
                    taskMasterForm.addEventListener('submit', function (event) {
                        event.preventDefault();
                        handleTaskMasterSubmit(taskMasterForm, taskMasterSubmit, taskMasterMethod?.value === 'PATCH');
                    });
                }

                if (taskMasterCreateForm) {
                    taskMasterCreateForm.addEventListener('submit', function (event) {
                        event.preventDefault();
                        handleTaskMasterSubmit(taskMasterCreateForm, taskMasterCreateSubmit, false);
                    });
                }

                document.querySelectorAll('.company-task-master-delete-form').forEach(function (form) {
                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        const confirmation = await Swal.fire({
                            icon: 'warning',
                            title: 'Delete task?',
                            text: form.dataset.deleteMessage || 'Are you sure you want to delete this task?',
                            showCancelButton: true,
                            confirmButtonColor: '#0f172a',
                            cancelButtonColor: '#94a3b8',
                            confirmButtonText: 'Delete',
                        });

                        if (!confirmation.isConfirmed) {
                            return;
                        }

                        const submitButton = form.querySelector('button[type="submit"]');
                        const originalLabel = submitButton?.innerHTML || '';

                        if (submitButton) {
                            submitButton.disabled = true;
                            submitButton.innerHTML = 'Deleting...';
                        }

                        try {
                            const response = await fetch(form.action, {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'Accept': 'application/json',
                                },
                                body: new FormData(form),
                            });

                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const firstError = Object.values(data.errors || {}).flat().find(Boolean);
                                throw new Error(data.message || firstError || 'Unable to delete task.');
                            }

                            window.location.reload();
                        } catch (error) {
                            showValidationError(error?.message || 'Unable to delete task.');
                        } finally {
                            if (submitButton) {
                                submitButton.disabled = false;
                                submitButton.innerHTML = originalLabel;
                            }
                        }
                    });
                });

                document.querySelectorAll('[data-lead-custom-field-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        leadCustomFieldModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-project-custom-field-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        projectCustomFieldModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-category-create-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        closeCreatePopup(categoryCreateModal);
                    });
                });

                document.querySelectorAll('[data-unit-create-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        closeCreatePopup(unitCreateModal);
                    });
                });

                document.querySelectorAll('[data-gst-rate-create-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        closeCreatePopup(gstRateCreateModal);
                    });
                });

                window.addEventListener('open-category-edit', function (event) {
                    if (!modal || !modalForm || !modalNameInput) {
                        return;
                    }

                    const detail = event.detail || {};
                    modalForm.action = `{{ url('/settings/categories') }}/${detail.id}`;
                    modalNameInput.value = detail.name || '';
                    modal.classList.remove('hidden');
                });

                modalCloseButtons.forEach(function (button) {
                    button.addEventListener('click', function () {
                        modal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-unit-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!unitModal || !unitEditForm || !unitEditName) {
                            return;
                        }

                        unitEditForm.action = `{{ url('/settings/company/units') }}/${button.dataset.id}`;
                        unitEditName.value = button.dataset.name || '';
                        unitModal.classList.remove('hidden');
                    });
                });

                document.querySelectorAll('[data-unit-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        unitModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-contractor-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!contractorModal || !contractorEditForm || !contractorEditName) {
                            return;
                        }

                        contractorEditForm.action = `{{ url('/settings/company/contractors') }}/${button.dataset.id}`;
                        contractorEditName.value = button.dataset.name || '';
                        contractorEditPhone.value = button.dataset.phoneNumber || '';
                        contractorEditEmail.value = button.dataset.email || '';
                        contractorEditReferral.value = button.dataset.referralName || '';
                        contractorEditAddress.value = button.dataset.address || '';
                        contractorEditNotes.value = button.dataset.notes || '';
                        contractorModal.classList.remove('hidden');
                    });
                });

                document.querySelectorAll('[data-contractor-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        contractorModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-gst-rate-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (!gstRateModal || !gstRateEditForm || !gstRateEditValue) {
                            return;
                        }

                        gstRateEditForm.action = `{{ url('/settings/company/gst-rates') }}/${button.dataset.id}`;
                        gstRateEditValue.value = button.dataset.rate || '';
                        gstRateModal.classList.remove('hidden');
                    });
                });

                document.querySelectorAll('[data-gst-rate-modal-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        gstRateModal?.classList.add('hidden');
                    });
                });

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape') {
                        leadSourceModal?.classList.add('hidden');
                        taskMasterModal?.classList.add('hidden');
                        modal?.classList.add('hidden');
                        unitModal?.classList.add('hidden');
                        contractorModal?.classList.add('hidden');
                        gstRateModal?.classList.add('hidden');
                        projectCustomFieldModal?.classList.add('hidden');
                        categoryCreateModal?.classList.add('hidden');
                        unitCreateModal?.classList.add('hidden');
                        gstRateCreateModal?.classList.add('hidden');
                    }
                });

                @if(old('settings_modal') === 'category')
                    openCategoryCreateModal();
                @endif

                @if(old('settings_modal') === 'unit')
                    openUnitCreateModal();
                @endif

                @if(old('settings_modal') === 'gst')
                    openGstRateCreateModal();
                @endif

                const holidayModal = document.getElementById('holiday-modal');
                const holidayForm = document.getElementById('holiday-form');
                const holidayMethod = document.getElementById('holiday-method');
                const holidayDate = document.getElementById('holiday_date');
                const holidayTitle = document.getElementById('holiday_title');
                const holidayDescription = document.getElementById('holiday_description');
                const openHolidayModalBtn = document.getElementById('open-holiday-modal');

                function openHolidayPopup(payload) {
                    if (!holidayModal || !holidayForm || !holidayMethod) return;
                    holidayForm.action = payload ? `{{ url('/settings/company/holidays') }}/${payload.id}` : `{{ route('settings.company.holidays.store') }}`;
                    holidayMethod.value = payload ? 'PATCH' : 'POST';
                    if (holidayDate) holidayDate.value = payload?.holiday_date || '';
                    if (holidayTitle) holidayTitle.value = payload?.title || '';
                    if (holidayDescription) holidayDescription.value = payload?.description || '';
                    holidayModal.classList.remove('hidden');
                }

                openHolidayModalBtn?.addEventListener('click', function () {
                    openHolidayPopup(null);
                });

                document.querySelectorAll('[data-holiday-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        holidayModal?.classList.add('hidden');
                    });
                });

                document.querySelectorAll('[data-holiday-edit]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        openHolidayPopup({
                            id: button.getAttribute('data-holiday-id'),
                            holiday_date: button.getAttribute('data-holiday-date'),
                            title: button.getAttribute('data-holiday-title'),
                            description: button.getAttribute('data-holiday-description'),
                        });
                    });
                });

                @if($errors->has('lead_source_name'))
                    openLeadSourceModal({
                        key: @json(old('lead_source_mode') === 'edit' ? old('lead_source_key') : null),
                        name: @json(old('lead_source_name')),
                        showReferralPersonName: @json(old('show_referral_person_name')),
                    });
                @endif

                @if($errors->has('label') || $errors->has('type') || $errors->has('options') || $errors->has('help_text'))
                    openLeadCustomFieldModal({
                        key: @json(old('lead_custom_field_key')),
                        label: @json(old('label')),
                        type: @json(old('type', 'text')),
                        required: @json(old('required')),
                        showInFilters: @json(old('show_in_filters')),
                        placeholder: @json(old('placeholder')),
                        helpText: @json(old('help_text')),
                        options: @json(old('options')),
                    });
                @endif

                @if($errors->has('project_label') || $errors->has('project_type') || $errors->has('project_options') || $errors->has('project_help_text'))
                    openProjectCustomFieldModal({
                        key: @json(old('project_custom_field_key')),
                        label: @json(old('project_label')),
                        type: @json(old('project_type', 'text')),
                        required: @json(old('project_required')),
                        showInFilters: @json(old('project_show_in_filters')),
                        placeholder: @json(old('project_placeholder')),
                        helpText: @json(old('project_help_text')),
                        options: @json(old('project_options')),
                    });
                @endif

                const pfToggle = document.getElementById('pf_enabled');
                const pfSwitch = pfToggle?.nextElementSibling;
                const pfDot = pfSwitch?.nextElementSibling?.querySelector('.pf-switch-dot');

                function syncPfToggle() {
                    if (!pfToggle || !pfSwitch) {
                        return;
                    }

                    pfSwitch.setAttribute('aria-checked', pfToggle.checked ? 'true' : 'false');

                    if (pfDot) {
                        pfDot.classList.toggle('bg-emerald-500', pfToggle.checked);
                        pfDot.classList.toggle('bg-slate-400', !pfToggle.checked);
                    }
                }

                if (pfToggle) {
                    syncPfToggle();
                    pfToggle.addEventListener('change', syncPfToggle);
                }
            })();
        </script>
    @endpush

    @if($isCompanyAdmin)
        <div id="status-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Lead Pipeline Stage</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-status-modal-close>✕</button>
                </div>
                <form id="status-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div>
                        <x-input-label for="status_name" value="Stage Name" />
                        <x-text-input id="status_name" name="name" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div>
                        <x-input-label for="status_color" value="Color" />
                        <div class="flex items-center gap-3">
                            <input type="color" id="status_color" name="color" value="#4f46e5" class="h-10 w-20 rounded-lg border border-slate-300">
                            <span class="text-xs text-slate-500">Pick a color for the Kanban board</span>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-status-modal-close>Cancel</button>
                        <x-primary-button>Save Stage</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="project-stage-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Project Pipeline Stage</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-project-stage-modal-close>&times;</button>
                </div>
                <form id="project-stage-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div>
                        <x-input-label for="project_stage_name" value="Stage Name" />
                        <x-text-input id="project_stage_name" name="name" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div>
                        <x-input-label for="project_stage_color" value="Color" />
                        <div class="flex items-center gap-3">
                            <input type="color" id="project_stage_color" name="color" value="#4f46e5" class="h-10 w-20 rounded-lg border border-slate-300">
                            <span class="text-xs text-slate-500">Pick a color for the project board</span>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-project-stage-modal-close>&times;</button>
                        <x-primary-button>Save Stage</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="service-stage-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Service Stage</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-service-stage-modal-close>&times;</button>
                </div>
                <form id="service-stage-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" name="_method" value="POST">
                    <div>
                        <x-input-label for="service_stage_name" value="Stage Name" />
                        <x-text-input id="service_stage_name" name="name" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div>
                        <x-input-label for="service_stage_color" value="Color" />
                        <div class="flex items-center gap-3">
                            <input type="color" id="service_stage_color" name="color" value="#4f46e5" class="h-10 w-20 rounded-lg border border-slate-300">
                            <span class="text-xs text-slate-500">Pick a color for the service kanban board</span>
                        </div>
                    </div>
                    <div>
                        <x-input-label for="service_stage_status" value="Ticket Status" />
                        <select id="service_stage_status" name="mapped_status" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                            @foreach($serviceStatusOptions as $statusKey => $statusLabel)
                                <option value="{{ $statusKey }}">{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-input-label for="service_stage_description" value="Description" />
                        <textarea id="service_stage_description" name="description" rows="3" maxlength="255" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Short note for this service lane"></textarea>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-service-stage-modal-close>Cancel</button>
                        <x-primary-button>Save Stage</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="lead-source-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 id="lead-source-modal-title" class="text-lg font-bold text-slate-950">Add Lead Source</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-lead-source-modal-close>&times;</button>
                </div>
                <form
                    id="lead-source-form"
                    method="POST"
                    action="{{ route('settings.company.lead-sources.store') }}"
                    class="mt-5 space-y-4"
                    data-store-action="{{ route('settings.company.lead-sources.store') }}"
                    data-update-base="{{ url('/settings/company/lead-sources') }}"
                >
                    @csrf
                    <input type="hidden" id="lead-source-method" name="_method" value="POST">
                    <input type="hidden" id="lead_source_mode" name="lead_source_mode" value="{{ old('lead_source_mode', 'create') }}">
                    <input type="hidden" id="lead_source_key" name="lead_source_key" value="{{ old('lead_source_key') }}">
                    <div>
                        <x-input-label for="lead_source_name" value="Lead Source Name" />
                        <x-text-input
                            id="lead_source_name"
                            name="lead_source_name"
                            maxlength="191"
                            class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                            value="{{ old('lead_source_name') }}"
                            placeholder="e.g. Website"
                            required
                        />
                        <x-input-error :messages="$errors->get('lead_source_name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="show_referral_person_name" class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <input
                                id="show_referral_person_name"
                                name="show_referral_person_name"
                                type="checkbox"
                                value="1"
                                class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                @checked(old('show_referral_person_name'))
                            >
                            <span>
                                <span class="block text-sm font-semibold text-slate-900">Show Referral Person Name field</span>
                                <span class="mt-1 block text-xs text-slate-500">If checked, this lead source will display the referral person name field in add and edit lead forms.</span>
                            </span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-lead-source-modal-close>Cancel</button>
                        <x-primary-button id="lead-source-submit">Save Lead Source</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="task-master-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 id="task-master-modal-title" class="text-lg font-bold text-slate-950">Add Task</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-task-master-modal-close>&times;</button>
                </div>
                <form
                    id="task-master-form"
                    method="POST"
                    action="{{ route('settings.company.task-masters.store') }}"
                    class="mt-5 space-y-4"
                    data-store-action="{{ route('settings.company.task-masters.store') }}"
                    data-update-base="{{ url('/settings/company/task-masters') }}"
                >
                    @csrf
                    <input type="hidden" id="task-master-method" name="_method" value="POST">
                    <input type="hidden" id="task_master_index" name="task_master_index" value="{{ old('task_master_index') }}">
                    <div>
                        <x-input-label for="task_master_name_modal" value="Task Name" />
                        <x-text-input
                            id="task_master_name_modal"
                            name="task_master_name"
                            maxlength="191"
                            class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                            value="{{ old('task_master_name') }}"
                            placeholder="e.g. Inspect camera feed"
                            required
                        />
                        <x-input-error :messages="$errors->get('task_master_name')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-task-master-modal-close>Cancel</button>
                        <x-primary-button id="task-master-submit">Save Task</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="lead-custom-field-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 id="lead-custom-field-modal-title" class="text-lg font-bold text-slate-950">Add Lead Custom Field</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-lead-custom-field-modal-close>&times;</button>
                </div>
                <form
                    id="lead-custom-field-form"
                    method="POST"
                    action="{{ route('settings.company.lead-custom-fields.store') }}"
                    class="mt-5 space-y-4"
                    data-store-action="{{ route('settings.company.lead-custom-fields.store') }}"
                    data-update-base="{{ url('/settings/company/lead-custom-fields') }}"
                >
                    @csrf
                    <input type="hidden" id="lead-custom-field-method" name="_method" value="POST">
                    <input type="hidden" id="lead_custom_field_key" name="lead_custom_field_key" value="">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input-label for="lead_custom_field_label" value="Field Label" />
                            <x-text-input
                                id="lead_custom_field_label"
                                name="label"
                                maxlength="191"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                                value="{{ old('label') }}"
                                placeholder="e.g. Site Requirement"
                                required
                            />
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="lead_custom_field_type" value="Field Type" />
                            <select
                                id="lead_custom_field_type"
                                name="type"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                @foreach(($company?->leadCustomFieldTypes() ?? []) as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" @selected(old('type', 'text') === $typeKey)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="lead_custom_field_placeholder" value="Placeholder" />
                            <x-text-input
                                id="lead_custom_field_placeholder"
                                name="placeholder"
                                maxlength="191"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                                value="{{ old('placeholder') }}"
                                placeholder="Shown inside text inputs"
                            />
                            <x-input-error :messages="$errors->get('placeholder')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="lead_custom_field_help_text" value="Help Text" />
                            <textarea
                                id="lead_custom_field_help_text"
                                name="help_text"
                                rows="2"
                                maxlength="500"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Short hint shown below the field"
                            >{{ old('help_text') }}</textarea>
                            <x-input-error :messages="$errors->get('help_text')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input
                                    id="lead_custom_field_required"
                                    name="required"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    @checked(old('required'))
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Required field</span>
                                    <span class="mt-1 block text-xs text-slate-500">Leads cannot be saved until this field is filled in.</span>
                                </span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input
                                    id="lead_custom_field_show_in_filters"
                                    name="show_in_filters"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    @checked(old('show_in_filters'))
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Show in Advanced Filters</span>
                                    <span class="mt-1 block text-xs text-slate-500">If enabled, this field appears in the lead advanced filter section.</span>
                                </span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input
                                    id="lead_custom_field_show_in_boq_quotation"
                                    name="show_in_boq_quotation"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    @checked(old('show_in_boq_quotation'))
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Show in BOQ/Quotation</span>
                                    <span class="mt-1 block text-xs text-slate-500">If enabled, this field appears when generating a Quotation or BOQ for this lead.</span>
                                </span>
                            </label>
                        </div>

                        <div id="lead-custom-field-options-wrapper" class="md:col-span-2">
                            <x-input-label for="lead_custom_field_options" value="Options" />
                            <textarea
                                id="lead_custom_field_options"
                                name="options"
                                rows="4"
                                maxlength="4000"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="One option per line for dropdown or radio button fields"
                            >{{ old('options') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Required for dropdown and radio button fields.</p>
                            <x-input-error :messages="$errors->get('options')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-lead-custom-field-modal-close>Cancel</button>
                        <x-primary-button id="lead-custom-field-submit">Save Field</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="project-custom-field-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 id="project-custom-field-modal-title" class="text-lg font-bold text-slate-950">Add Project Custom Field</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-project-custom-field-modal-close>&times;</button>
                </div>
                <form
                    id="project-custom-field-form"
                    method="POST"
                    action="{{ route('settings.company.project-custom-fields.store') }}"
                    class="mt-5 space-y-4"
                    data-store-action="{{ route('settings.company.project-custom-fields.store') }}"
                    data-update-base="{{ url('/settings/company/project-custom-fields') }}"
                >
                    @csrf
                    <input type="hidden" id="project-custom-field-method" name="_method" value="POST">
                    <input type="hidden" id="project_custom_field_key" name="project_custom_field_key" value="">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input-label for="project_custom_field_label" value="Field Label" />
                            <x-text-input
                                id="project_custom_field_label"
                                name="project_label"
                                maxlength="191"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                                value="{{ old('project_label') }}"
                                placeholder="e.g. Project Scope"
                                required
                            />
                            <x-input-error :messages="$errors->get('project_label')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="project_custom_field_type" value="Field Type" />
                            <select
                                id="project_custom_field_type"
                                name="project_type"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                required
                            >
                                @foreach(($company?->projectCustomFieldTypes() ?? []) as $typeKey => $typeLabel)
                                    <option value="{{ $typeKey }}" @selected(old('project_type', 'text') === $typeKey)>{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('project_type')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="project_custom_field_placeholder" value="Placeholder" />
                            <x-text-input
                                id="project_custom_field_placeholder"
                                name="project_placeholder"
                                maxlength="191"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                                value="{{ old('project_placeholder') }}"
                                placeholder="Shown inside text inputs"
                            />
                            <x-input-error :messages="$errors->get('project_placeholder')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <x-input-label for="project_custom_field_help_text" value="Help Text" />
                            <textarea
                                id="project_custom_field_help_text"
                                name="project_help_text"
                                rows="2"
                                maxlength="500"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Short hint shown below the field"
                            >{{ old('project_help_text') }}</textarea>
                            <x-input-error :messages="$errors->get('project_help_text')" class="mt-2" />
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input
                                    id="project_custom_field_required"
                                    name="project_required"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    @checked(old('project_required'))
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Required field</span>
                                    <span class="mt-1 block text-xs text-slate-500">Projects cannot be saved until this field is filled in.</span>
                                </span>
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <input
                                    id="project_custom_field_show_in_filters"
                                    name="project_show_in_filters"
                                    type="checkbox"
                                    value="1"
                                    class="mt-1 rounded border-slate-300 text-slate-900 focus:ring-slate-500"
                                    @checked(old('project_show_in_filters'))
                                >
                                <span>
                                    <span class="block text-sm font-semibold text-slate-900">Show in Advanced Filters</span>
                                    <span class="mt-1 block text-xs text-slate-500">If enabled, this field appears in the project filter section.</span>
                                </span>
                            </label>
                        </div>

                        <div id="project-custom-field-options-wrapper" class="md:col-span-2">
                            <x-input-label for="project_custom_field_options" value="Options" />
                            <textarea
                                id="project_custom_field_options"
                                name="project_options"
                                rows="4"
                                maxlength="4000"
                                class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="One option per line for dropdown or radio button fields"
                            >{{ old('project_options') }}</textarea>
                            <p class="mt-1 text-xs text-slate-500">Required for dropdown and radio button fields.</p>
                            <x-input-error :messages="$errors->get('project_options')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-project-custom-field-modal-close>Cancel</button>
                        <x-primary-button id="project-custom-field-submit">Save Field</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        @if($canManagePayrollSettings)
        <div id="holiday-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Holiday</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-holiday-close>✕</button>
                </div>
                <form id="holiday-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    <input type="hidden" id="holiday-method" name="_method" value="POST">
                    <div>
                        <x-input-label for="holiday_date" value="Holiday Date" />
                        <x-text-input
                            id="holiday_date"
                            name="holiday_date"
                            type="date"
                            class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3"
                            required
                            min="{{ date('Y-m-d') }}"
                        />
                    </div>
                    <div>
                        <x-input-label for="holiday_title" value="Holiday Title" />
                        <x-text-input id="holiday_title" name="title" maxlength="100" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div>
                        <x-input-label for="holiday_description" value="Description" />
                        <textarea id="holiday_description" name="description" rows="3" maxlength="100" class="mt-1 block w-full rounded-2xl border-slate-300"></textarea>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-holiday-close>Cancel</button>
                        <x-primary-button>Save Holiday</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        @endif

        <div id="category-create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Add Category</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-category-create-modal-close>&times;</button>
                </div>
                <form id="category-create-form" action="{{ route('settings.categories.store') }}" method="POST" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="settings_modal" value="category">
                    <div>
                        <x-input-label for="category_name" value="Category Name" />
                        <x-text-input id="category_name" name="name" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" placeholder="e.g. Residential, Commercial" required minlength="2" maxlength="191" value="{{ old('name') }}" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-category-create-modal-close>Cancel</button>
                        <x-primary-button id="category-create-submit">Add Category</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="category-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Edit Category</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-category-modal-close>✕</button>
                </div>
                <form id="category-edit-form" method="POST" class="mt-5 space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="edit_category_name" value="Category Name" />
                        <x-text-input id="edit_category_name" name="name" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-category-modal-close>Cancel</button>
                        <x-primary-button class="rounded-2xl bg-slate-900 px-5 py-2.5 text-sm font-semibold">Update Category</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="unit-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Edit Unit</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-unit-modal-close>&times;</button>
                </div>
                <form id="unit-edit-form" method="POST" class="mt-5 space-y-4" novalidate>
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="edit_unit_name" value="Unit Name" />
                        <x-text-input id="edit_unit_name" name="name" maxlength="50" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-unit-modal-close>Cancel</button>
                        <x-primary-button>Update Unit</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="unit-create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Add Unit</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-unit-create-modal-close>&times;</button>
                </div>
                <form id="unit-create-form" method="POST" action="{{ route('settings.company.units.store') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="settings_modal" value="unit">
                    <div>
                        <x-input-label for="unit_name" value="Unit Name" />
                        <x-text-input id="unit_name" name="name" maxlength="50" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" value="{{ old('name') }}" placeholder="Nos, Set, Sq.Ft, Mtr" />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-unit-create-modal-close>Cancel</button>
                        <x-primary-button id="unit-create-submit">Add Unit</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="contractor-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-2xl rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Edit Contractor</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-contractor-modal-close>&times;</button>
                </div>
                <form id="contractor-edit-form" method="POST" class="mt-5 space-y-4" novalidate>
                    @csrf
                    @method('PATCH')
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <x-input-label for="edit_contractor_name" value="Contractor Name" />
                            <x-text-input id="edit_contractor_name" name="name" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                        </div>
                        <div>
                            <x-input-label for="edit_contractor_phone_number" value="Contact Number" />
                            <x-text-input id="edit_contractor_phone_number" name="phone_number" inputmode="numeric" maxlength="10" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" />
                        </div>
                        <div>
                            <x-input-label for="edit_contractor_email" value="Email Address" />
                            <x-text-input id="edit_contractor_email" name="email" type="email" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" />
                        </div>
                        <div>
                            <x-input-label for="edit_contractor_referral_name" value="Referral Name" />
                            <x-text-input id="edit_contractor_referral_name" name="referral_name" maxlength="191" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="edit_contractor_address" value="Address" />
                            <div class="relative mt-1">
                                <textarea id="edit_contractor_address" name="address" rows="3" maxlength="1000" class="block w-full rounded-2xl border-slate-300" autocomplete="off" data-location-autocomplete="contractor-address-edit" data-autocomplete-url="{{ config('services.location_autocomplete.url') }}"></textarea>
                                <div
                                    id="edit-contractor-address-suggestions"
                                    class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                                    role="listbox"
                                    aria-label="Edit contractor address suggestions"
                                ></div>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="edit_contractor_notes" value="Notes" />
                            <textarea id="edit_contractor_notes" name="notes" rows="3" maxlength="2000" class="mt-1 block w-full rounded-2xl border-slate-300"></textarea>
                        </div>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-contractor-modal-close>Cancel</button>
                        <x-primary-button>Update Contractor</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="gst-rate-edit-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Edit GST Rate</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-gst-rate-modal-close>&times;</button>
                </div>
                <form id="gst-rate-edit-form" method="POST" class="mt-5 space-y-4" novalidate>
                    @csrf
                    @method('PATCH')
                    <div>
                        <x-input-label for="edit_gst_rate" value="GST (%)" />
                        <x-text-input id="edit_gst_rate" name="rate" type="number" min="0" max="100" step="0.01" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" required />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-gst-rate-modal-close>Cancel</button>
                        <x-primary-button>Update GST Rate</x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <div id="gst-rate-create-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl">
                <div class="flex items-center justify-between">
                    <h4 class="text-lg font-bold text-slate-950">Add GST Rate</h4>
                    <button type="button" class="text-slate-500 hover:text-slate-700" data-gst-rate-create-modal-close>&times;</button>
                </div>
                <form id="gst-rate-create-form" method="POST" action="{{ route('settings.company.gst-rates.store') }}" class="mt-5 space-y-4" novalidate>
                    @csrf
                    <input type="hidden" name="settings_modal" value="gst">
                    <div>
                        <x-input-label for="gst_rate" value="GST (%)" />
                        <x-text-input id="gst_rate" name="rate" type="number" min="0" max="100" step="0.01" maxlength="6" class="mt-1 block w-full rounded-2xl border-slate-300 bg-white px-4 py-3" value="{{ old('rate') }}" placeholder="18" />
                        <x-input-error :messages="$errors->get('rate')" class="mt-2" />
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700" data-gst-rate-create-modal-close>Cancel</button>
                        <x-primary-button id="gst-rate-create-submit">Add GST Rate</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</x-app-layout>
@if($canManagePayrollSettings)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.getElementById('holiday_date');
        const form = document.getElementById('holiday-form');

        if (!dateInput || !form) {
            return;
        }

        // Prevent manual typing of past dates
        dateInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Reset time to compare only dates

            if (selectedDate < today) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date',
                    text: 'You cannot select a past date for a holiday.',
                    confirmButtonColor: '#0f172a',
                });
                this.value = ''; // Clear the invalid input
            }
        });

        // Final check on form submit
        form.addEventListener('submit', function(e) {
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Check your details',
                    text: 'Please choose a current or future date.',
                });
            }
        });
    });
</script>
@endif
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const page = document.getElementById('print-preview-page');
        const content = document.getElementById('print-preview-content');
        const letterhead = document.getElementById('print-preview-letterhead');
        const status = document.getElementById('print-preview-status');
        const accentBadge = document.getElementById('print-preview-accent-badge');
        const marginTop = document.getElementById('print-preview-margin-top');
        const marginRight = document.getElementById('print-preview-margin-right');
        const marginBottom = document.getElementById('print-preview-margin-bottom');
        const marginLeft = document.getElementById('print-preview-margin-left');

        const accentInput = document.getElementById('print_settings[accent_color]');
        const titleInput = document.getElementById('print_settings[letterhead_title]');
        const subtitleInput = document.getElementById('print_settings[letterhead_subtitle]');
        const topInput = document.getElementById('print_settings[page_margin_top]');
        const rightInput = document.getElementById('print_settings[page_margin_right]');
        const bottomInput = document.getElementById('print_settings[page_margin_bottom]');
        const leftInput = document.getElementById('print_settings[page_margin_left]');
        const useLetterheadInput = document.getElementById('print_settings[use_letterhead]');
        const enablePrintSettingsInput = document.getElementById('enable_print_settings');
        const enablePrintSettingsValue = document.getElementById('enable_print_settings_value');
        const previewBrandBlock = document.getElementById('print-preview-brand-block');
        const previewTitle = document.getElementById('print-preview-title');
        const previewSubtitle = document.getElementById('print-preview-subtitle');
        const previewLogo = document.getElementById('print-preview-logo');
        const previewLogoFallback = document.getElementById('print-preview-logo-fallback');
        const letterheadFileInput = document.getElementById('letterhead_file');
        const previewLetterheadImage = document.getElementById('print-preview-letterhead-image');
        let hasLetterheadImage = !!(previewLetterheadImage && previewLetterheadImage.getAttribute('src'));

        if (!page || !content || !status || !accentBadge || !topInput || !rightInput || !bottomInput || !leftInput) {
            return;
        }

        letterheadFileInput?.addEventListener('change', function () {
            const file = letterheadFileInput.files && letterheadFileInput.files[0];
            if (!file || !previewLetterheadImage) {
                return;
            }

            const reader = new FileReader();
            reader.onload = function (event) {
                previewLetterheadImage.src = String(event.target?.result || '');
                hasLetterheadImage = true;
                syncPreview();
            };
            reader.readAsDataURL(file);
        });

        const defaultAccent = accentInput?.value || '#4f46e5';
        const fallbackTitle = @js($company?->name ?? 'Workshop Platform');
        const savedLetterheadTitle = @js($printSettings['letterhead_title'] ?? $company?->name ?? 'Workshop Platform');
        const savedLetterheadSubtitle = @js($printSettings['letterhead_subtitle'] ?? 'Official Document');
        const savedUseLetterhead = @js((bool) ($printSettings['use_letterhead'] ?? true));
        const mmToPx = 2.1;

        function parseMargin(input, fallback) {
            const value = Number.parseFloat(input?.value);
            return Number.isFinite(value) ? value : fallback;
        }

        function hexToRgba(hex, alpha) {
            const normalized = String(hex || '').replace('#', '').trim();
            if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
                return `rgba(79, 70, 229, ${alpha})`;
            }

            const r = Number.parseInt(normalized.slice(0, 2), 16);
            const g = Number.parseInt(normalized.slice(2, 4), 16);
            const b = Number.parseInt(normalized.slice(4, 6), 16);
            return `rgba(${r}, ${g}, ${b}, ${alpha})`;
        }

        function syncPreview() {
            const accent = accentInput?.value || defaultAccent;
            const title = titleInput ? ((titleInput.value || '').trim() || fallbackTitle) : savedLetterheadTitle;
            const subtitle = subtitleInput ? ((subtitleInput.value || '').trim() || 'Official Document') : savedLetterheadSubtitle;
            const top = parseMargin(topInput, 20);
            const right = parseMargin(rightInput, 20);
            const bottom = parseMargin(bottomInput, 20);
            const left = parseMargin(leftInput, 20);
            const letterheadEnabled = useLetterheadInput ? useLetterheadInput.checked : savedUseLetterhead;
            const printEnabled = enablePrintSettingsInput ? enablePrintSettingsInput.checked : true;

            if (enablePrintSettingsValue) {
                enablePrintSettingsValue.value = printEnabled ? '1' : '0';
            }

            content.style.padding = `${top * mmToPx}px ${right * mmToPx}px ${bottom * mmToPx}px ${left * mmToPx}px`;
            content.style.setProperty('--preview-accent-soft', hexToRgba(accent, 0.14));

            page.style.opacity = printEnabled ? '1' : '0.58';
            page.style.borderColor = printEnabled ? '#e2e8f0' : '#fcd34d';

            if (status) {
                status.textContent = printEnabled ? 'Enabled' : 'Disabled';
                status.className = printEnabled
                    ? 'rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700'
                    : 'rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700';
            }

            accentBadge.style.backgroundColor = accent;
            accentBadge.style.color = '#ffffff';

            if (previewTitle) previewTitle.textContent = title;
            if (previewSubtitle) previewSubtitle.textContent = subtitle;
            if (previewBrandBlock) {
                previewBrandBlock.classList.toggle('hidden', printEnabled);
            }

            const showLetterheadImage = printEnabled && letterheadEnabled && hasLetterheadImage;

            if (previewLetterheadImage) {
                previewLetterheadImage.classList.toggle('hidden', !showLetterheadImage);
            }

            if (letterhead) {
                letterhead.classList.toggle('hidden', !letterheadEnabled || showLetterheadImage);
            }

            if (previewLogo) {
                previewLogo.classList.toggle('hidden', !letterheadEnabled);
            }

            if (previewLogoFallback) {
                previewLogoFallback.classList.toggle('hidden', letterheadEnabled);
            }

            if (marginTop) marginTop.textContent = String(top);
            if (marginRight) marginRight.textContent = String(right);
            if (marginBottom) marginBottom.textContent = String(bottom);
            if (marginLeft) marginLeft.textContent = String(left);
        }

        [
            accentInput,
            titleInput,
            subtitleInput,
            topInput,
            rightInput,
            bottomInput,
            leftInput,
            useLetterheadInput,
            enablePrintSettingsInput,
        ].forEach(function (input) {
            if (!input) {
                return;
            }

            input.addEventListener('input', syncPreview);
            input.addEventListener('change', syncPreview);
        });

        enablePrintSettingsInput?.addEventListener('change', syncPreview);

        syncPreview();
    });
</script>
