<x-app-layout>
    <div id="ticket-create-page">
    <x-slot name="header">
        <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">{{ __('Create Ticket') }}</h2>
            <p class="mt-1 text-sm text-slate-500">Describe the issue clearly so it can be handled faster.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-8xl py-6 sm:px-6 lg:px-8">
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.8fr)_24rem]">
        <div class="rounded-none border border-slate-200 bg-white p-8 shadow-sm lg:p-10">
            @php
                $prefill = $prefill ?? [];
                $prefillSource = $prefill['source'] ?? null;
                $prefillSourceLabel = $prefill['source_label'] ?? null;
                $prefilledContact = old('customer_contact_number', $prefill['customer_contact_number'] ?? '');
                $prefilledName = old('customer_name', $prefill['customer_name'] ?? '');
                $prefilledEmail = old('customer_email', $prefill['customer_email'] ?? '');
                $prefilledAddress = old('customer_address', $prefill['customer_address'] ?? '');
                $prefilledType = old('type', $prefill['type'] ?? '');
                $prefilledPriority = old('priority', $prefill['priority'] ?? '');
                $prefilledAssignedTo = old('assigned_to', $prefill['assigned_to'] ?? '');
                $prefilledVisitDate = old('visit_date', $prefill['visit_date'] ?? '');
                $prefilledSubject = old('subject', $prefill['subject'] ?? '');
                $prefilledDescription = old('description', $prefill['description'] ?? '');
                $prefilledProjectId = old('project_id', $prefill['project_id'] ?? '');
                $isAmcPrefill = $prefillSource === 'amc';
            @endphp

            @if($prefillSource === 'amc')
                <div class="mb-6 border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800">
                    This support ticket is prefilled from {{ $prefillSourceLabel }}. Update the issue details below before creating it.
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    {{ session('warning') }}
                </div>
            @endif

            <!-- Added ID for jQuery Validation -->
            <form id="ticketForm" method="POST" action="{{ route('tickets.store') }}" class="space-y-6" data-no-global-loader="1">
                @csrf
                @if($prefillSource === 'amc' && filled($prefill['amc_reference'] ?? null))
                    <input type="hidden" name="amc_source" value="{{ $prefill['amc_reference'] }}">
                @endif
                <input type="hidden" name="selected_customer_source_type" id="selected_customer_source_type" value="">
                <input type="hidden" name="selected_customer_source_id" id="selected_customer_source_id" value="">

                <div class="grid gap-4 lg:grid-cols-3">
                    <div class="relative lg:col-span-1">
                        <div class="flex items-center gap-1">
                            <x-input-label for="customer_name" value="Name" class="ml-1" />
                            <span class="text-red-600">*</span>
                        </div>
                        <div class="relative mt-1">
                            <x-text-input
                                id="customer_name"
                                name="customer_name"
                                placeholder="Search or enter client name"
                                :value="$prefilledName"
                                autocomplete="off"
                                class="block w-full pr-11 placeholder:text-slate-500"
                            />
                            <button
                                type="button"
                                id="customer_name_dropdown_toggle"
                                class="absolute inset-y-0 right-0 flex w-11 items-center justify-center text-slate-400 transition hover:text-slate-600"
                                aria-label="Show existing clients"
                            >
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                        <div
                            id="customer_name_search_results"
                            class="absolute z-20 mt-2 hidden w-full overflow-hidden rounded-none border border-slate-200 bg-white shadow-xl"
                        >
                            <div id="customer_name_search_heading" class="hidden border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Existing Clients
                            </div>
                            <div id="customer_name_refine_wrap" class="hidden border-b border-slate-100 px-3 py-2">
                                <input
                                    type="text"
                                    id="customer_name_refine_search"
                                    placeholder="Filter by mobile, email, or address"
                                    class="block w-full border-slate-300 px-3 py-2 text-sm text-slate-700 placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500"
                                    autocomplete="off"
                                />
                            </div>
                            <div id="customer_name_search_results_list" class="max-h-[410px] overflow-y-auto overscroll-contain p-2"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Search an existing client first, or continue with a new client name.</p>
                        <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                    </div>

                    <div class="relative">
                        <div class="flex items-center gap-1">
                            <x-input-label for="customer_contact_number" value="Contact Number" class="ml-1" />
                            <span class="text-red-600">*</span>
                        </div>
                        <div id="customer_contact_number_select_wrap" class="hidden">
                            <select
                                id="customer_contact_number_selector"
                                class="mt-1 block w-full border-slate-300 text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"
                            >
                                <option value="">Select contact number</option>
                            </select>
                        </div>
                        <x-text-input
                            id="customer_contact_number"
                            name="customer_contact_number"
                            type="tel"
                            inputmode="numeric"
                            maxlength="10"
                            placeholder="Enter contact number"
                            :value="$prefilledContact"
                            class="mt-1 block w-full placeholder:text-slate-500"
                        />
                        <div
                            id="customer_contact_search_results"
                            class="absolute z-20 mt-2 hidden w-full overflow-hidden rounded-none border border-slate-200 bg-white shadow-xl"
                        >
                            <div id="customer_contact_search_heading" class="border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Existing Clients
                            </div>
                            <div id="customer_contact_search_results_list" class="max-h-[410px] overflow-y-auto overscroll-contain p-2"></div>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">If the selected client has multiple numbers, choose the correct one here.</p>
                        <x-input-error :messages="$errors->get('customer_contact_number')" class="mt-2" />
                    </div>

                    <div class="relative">
                        <x-input-label for="customer_email" value="Email" class="ml-1" />
                        <x-text-input
                            id="customer_email"
                            name="customer_email"
                            type="email"
                            placeholder="Enter email address"
                            :value="$prefilledEmail"
                            class="mt-1 block w-full placeholder:text-slate-500"
                        />
                        <div
                            id="customer_email_search_results"
                            class="absolute z-20 mt-2 hidden w-full overflow-hidden rounded-none border border-slate-200 bg-white shadow-xl"
                        >
                            <div id="customer_email_search_heading" class="border-b border-slate-100 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Existing Clients
                            </div>
                            <div id="customer_email_search_results_list" class="max-h-[410px] overflow-y-auto overscroll-contain p-2"></div>
                        </div>
                        <x-input-error :messages="$errors->get('customer_email')" class="mt-2" />
                    </div>
                </div>

                <div id="customer_address_fields" class="space-y-3">
                    <div id="customer_address_select_wrap" class="hidden">
                        <x-input-label for="customer_address_selector" value="Select Address" class="ml-1" />
                        <select
                            id="customer_address_selector"
                            class="mt-1 block w-full border-slate-300 text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"
                        >
                            <option value="">Select client address</option>
                        </select>
                    </div>

                    <div>
                        <x-input-label for="customer_address" value="Address" class="ml-1" />
                        <textarea
                            id="customer_address"
                            name="customer_address"
                            rows="3"
                            placeholder="Enter customer address"
                            class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm placeholder:text-slate-500 transition-all"
                        >{{ $prefilledAddress }}</textarea>
                        <x-input-error :messages="$errors->get('customer_address')" class="mt-2" />
                    </div>
                </div>

                <div id="customer_project_select_wrap" class="{{ $prefilledProjectId ? '' : 'hidden' }}">
                    <x-input-label for="project_id" value="Project" class="ml-1" />
                    <select
                        id="project_id"
                        name="project_id"
                        class="mt-1 block w-full border-slate-300 text-slate-700 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm transition-all"
                    >
                        <option value="">Select project</option>
                        @if($prefilledProjectId)
                            <option value="{{ $prefilledProjectId }}" selected>Selected project</option>
                        @endif
                    </select>
                    <p id="customer_project_helper_text" class="mt-1 text-xs text-slate-500">Choose the client project if this ticket belongs to an existing project.</p>
                    <x-input-error :messages="$errors->get('project_id')" class="mt-2" />
                </div>

                <!-- Type + Priority -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-searchable-select
                            name="type"
                            id="type"
                            label="Type"
                            :options="collect($typeOptions)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])"
                            :selected="$prefilledType"
                            placeholder="Search type..."
                            empty-label="Select Type"
                        />
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                    </div>
                    <div>
                        <x-searchable-select
                            name="priority"
                            id="priority"
                            label="Priority"
                            :options="collect([
                                ['id' => 'low', 'label' => 'Low'],
                                ['id' => 'medium', 'label' => 'Medium'],
                                ['id' => 'high', 'label' => 'High'],
                                ['id' => 'urgent', 'label' => 'Urgent'],
                            ])"
                            :selected="$prefilledPriority"
                            placeholder="Search priority..."
                            empty-label="Select Priority"
                        />
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <div class="flex items-center gap-1">
                        <x-input-label for="visit_date" value="Visit Date" class="ml-1" />
                        <span class="text-red-600">*</span>
                    </div>
                    <x-text-input
                        id="visit_date"
                        name="visit_date"
                        type="date"
                        :min="now()->toDateString()"
                        :value="$prefilledVisitDate"
                        class="mt-1 block w-full"
                        required
                    />
                    <p class="mt-1 text-xs text-slate-500">Required. Choose today or a future date.</p>
                    <x-input-error :messages="$errors->get('visit_date')" class="mt-2" />
                </div>

                <!-- Assign To + Referenced By -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-ticket-assignee-select
                            name="assigned_to"
                            id="assigned_to"
                            label="Assign To"
                            visit-date-input-id="visit_date"
                            :selected="old('assigned_to', $prefill['assigned_to'] ?? [])"
                        />
                        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                    </div>

                    <div>
                        <x-searchable-select
                            name="referenced_by"
                            id="referenced_by"
                            label="Referenced By"
                            :options="$assignees->map(fn ($assignee) => [
                                'id' => $assignee->id,
                                'label' => $assignee->name . ($assignee->isCompanyAdmin() ? ' (Company Admin)' : ' (Staff)'),
                                'meta' => $assignee->email ?? '',
                            ])"
                            :selected="old('referenced_by')"
                            placeholder="Search referenced by..."
                            empty-label="Select Referenced By"
                        />
                        <x-input-error :messages="$errors->get('referenced_by')" class="mt-2" />
                    </div>
                </div>

                <!-- Subject -->
                <div>
                    <div class="flex items-center gap-1">
                        <x-input-label for="subject" value="Subject" class="ml-1" />
                        <span class="text-red-600">*</span>
                    </div>
                    <x-text-input
                        id="subject"
                        name="subject"
                        placeholder="Enter ticket subject"
                        :value="$prefilledSubject"
                        class="mt-1 block w-full placeholder:text-slate-500"
                        required
                    />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                </div>

                <!-- Description -->
                <div>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1">
                            <x-input-label for="description" value="Description" class="ml-1" />
                            <span class="text-red-600">*</span>
                        </div>
                        <x-speech-to-text target="description" />
                    </div>
                    <textarea id="description" name="description" rows="6"
                        placeholder="Please provide steps to reproduce the issue..."
                        class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm placeholder:text-slate-500 transition-all"
                        required>{{ $prefilledDescription }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('tickets.index') }}"
                       class="border border-slate-200 px-6 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Cancel
                    </a>
                    <x-primary-button id="submitBtn">
                        Create Ticket
                    </x-primary-button>
                </div>
            </form>
        </div>

        <aside id="customer_history_panel" class="border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Customer History</h3>
                </div>
                <span id="customer_history_count" class="hidden bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"></span>
            </div>

            <div id="customer_history_empty" class="mt-6 border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                Selected client/contact details will be show here.
            </div>

            <div id="customer_history_loading" class="mt-6 hidden border border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                Loading customer history...
            </div>

            <div id="customer_history_error" class="mt-6 hidden border border-rose-200 bg-rose-50 px-4 py-4 text-sm text-rose-700"></div>

            <div id="customer_history_content" class="mt-6 hidden space-y-6">
                <a
                    id="customer_history_profile_link"
                    href="#"
                    class="block border border-slate-200 bg-slate-50 p-4 transition hover:border-indigo-200 hover:bg-indigo-50/40"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div id="customer_history_name" class="text-base font-semibold text-slate-900"></div>
                            <div id="customer_history_source" class="mt-1 text-xs font-semibold uppercase tracking-wide text-indigo-600"></div>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Email</dt>
                            <dd id="customer_history_email" class="max-w-[12rem] text-right font-medium text-slate-900"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Phone</dt>
                            <dd id="customer_history_phone" class="max-w-[12rem] text-right font-medium text-slate-900"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Location</dt>
                            <dd id="customer_history_location" class="max-w-[12rem] text-right font-medium text-slate-900"></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-slate-500">Address</dt>
                            <dd id="customer_history_address" class="max-w-[12rem] whitespace-pre-line text-right font-medium text-slate-900"></dd>
                        </div>
                    </dl>
                </a>

                <section>
                    <div class="flex items-center justify-between gap-3">
                        <h4 class="text-sm font-semibold uppercase tracking-wide text-slate-600">Complain / Support History</h4>
                    </div>
                    <div id="customer_history_list" class="mt-3 space-y-3"></div>
                </section>
            </div>
        </aside>
        </div>
    </div>

    <!-- jQuery Validation Script -->
    @push('scripts')
    <style>
        /* Styling the jQuery Error Messages */
        .error { color: #e11d48; font-size: 0.875rem; margin-top: 0.25rem; font-weight: 500; }
        input.error, textarea.error, select.error { border-color: #e11d48 !important; }
        input.error:focus, textarea.error:focus { ring-color: #fb7185 !important; }
    </style>

    @if(filled(config('services.google_maps.key')))
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
    @endif

    <script>
        $(document).ready(function() {
            const emailCheckUrl = @json(route('tickets.customer.email-check'));
            const phoneCheckUrl = @json(route('tickets.customer.phone-check'));

            $("#ticketForm").validate({
                ignore: [],
                errorElement: "label",
                errorClass: "error",
                rules: {
                    customer_name: {
                        required: true,
                        minlength: 2,
                        maxlength: 191,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    customer_email: {
                        email: true,
                        maxlength: 191,
                        remote: {
                            url: emailCheckUrl,
                            type: "GET",
                            data: {
                                customer_email: function () {
                                    return $('#customer_email').val();
                                },
                                selected_customer_source_type: function () {
                                    return $('#selected_customer_source_type').val();
                                },
                                selected_customer_source_id: function () {
                                    return $('#selected_customer_source_id').val();
                                }
                            }
                        },
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    customer_contact_number: {
                        required: true,
                        maxlength: 30,
                        remote: {
                            url: phoneCheckUrl,
                            type: "GET",
                            data: {
                                customer_contact_number: function () {
                                    return $('#customer_contact_number').val();
                                },
                                selected_customer_source_type: function () {
                                    return $('#selected_customer_source_type').val();
                                },
                                selected_customer_source_id: function () {
                                    return $('#selected_customer_source_id').val();
                                }
                            }
                        },
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    customer_address: {
                        maxlength: 1000,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    type: { required: true },
                    subject: {
                        required: true,
                        minlength: 5,
                        maxlength: 191,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    priority: { required: true },
                    visit_date: {
                        required: true,
                        date: true
                    },
                    description: {
                        required: true,
                        maxlength: 5000,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    }
                },
                messages: {
                    customer_name: {
                        required: "Please enter customer name",
                        minlength: "Name must be at least 2 characters.",
                        maxlength: "Name may not be greater than 191 characters."
                    },
                    customer_email: {
                        email: "Please enter a valid email address.",
                        maxlength: "Email may not be greater than 191 characters.",
                        remote: "This email already exists for another customer."
                    },
                    customer_contact_number: {
                        required: "Phone number is required.",
                        maxlength: "Contact number may not be greater than 30 characters.",
                        remote: "This phone number already exists for another client."
                    },
                    customer_address: {
                        maxlength: "Address may not be greater than 1000 characters."
                    },
                    type: {
                        required: "Please select a type."
                    },
                    subject: {
                        required: "Please enter a subject.",
                        minlength: "Subject must be at least 5 characters long.",
                        maxlength: "Subject may not be greater than 191 characters."
                    },
                    priority: {
                        required: "Please select a priority level."
                    },
                    visit_date: {
                        required: "Please select a visit date.",
                        date: "Please enter a valid visit date."
                    },
                    description: {
                        required: "Please provide a detailed description.",
                        maxlength: "Description may not be greater than 5000 characters."
                    }
                },
                invalidHandler: function(event, validator) {
                    var errors = validator.numberOfInvalids();
                    if (errors) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Check Form',
                            text: 'Please fix the highlighted errors before submitting.',
                            confirmButtonColor: '#4f46e5',
                        });
                    }
                },
                errorPlacement: function(error, element) {
                    error.insertAfter(element);
                },
                submitHandler: function(form) {
                    const selectedSourceTypeValue = ($('#selected_customer_source_type').val() || '').trim();
                    const selectedSourceIdValue = ($('#selected_customer_source_id').val() || '').trim();

                    const continueSubmit = function () {
                        Swal.fire({
                            title: 'Creating Ticket...',
                            text: 'Please wait while we process your request.',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $('#submitBtn').attr('disabled', 'disabled').text('Processing...');
                        form.submit();
                    };

                    if (!selectedSourceTypeValue || !selectedSourceIdValue) {
                        if (@js($isAmcPrefill)) {
                            continueSubmit();
                            return;
                        }

                        Swal.fire({
                            icon: 'warning',
                            title: 'Create New Customer?',
                            text: 'No existing customer has been selected. When you submit, a new customer record may be created or an existing record may be matched automatically.',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, Continue',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#4f46e5',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                continueSubmit();
                            }
                        });
                        return;
                    }

                    continueSubmit();
                }
            });

            $("#customer_email").on("change blur", function () {
                $(this).valid();
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const nameInput = document.getElementById('customer_name');
            const nameDropdownToggle = document.getElementById('customer_name_dropdown_toggle');
            const nameResultsWrap = document.getElementById('customer_name_search_results');
            const nameResultsHeading = document.getElementById('customer_name_search_heading');
            const refineSearchWrap = document.getElementById('customer_name_refine_wrap');
            const refineSearchInput = document.getElementById('customer_name_refine_search');
            const nameResultsList = document.getElementById('customer_name_search_results_list');
            const contactInput = document.getElementById('customer_contact_number');
            const contactSelectWrap = document.getElementById('customer_contact_number_select_wrap');
            const contactSelect = document.getElementById('customer_contact_number_selector');
            const contactResultsWrap = document.getElementById('customer_contact_search_results');
            const contactResultsHeading = document.getElementById('customer_contact_search_heading');
            const contactResultsList = document.getElementById('customer_contact_search_results_list');
            const emailInput = document.getElementById('customer_email');
            const emailResultsWrap = document.getElementById('customer_email_search_results');
            const emailResultsHeading = document.getElementById('customer_email_search_heading');
            const emailResultsList = document.getElementById('customer_email_search_results_list');
            const addressInput = document.getElementById('customer_address');
            const addressSelectWrap = document.getElementById('customer_address_select_wrap');
            const addressSelect = document.getElementById('customer_address_selector');
            const projectSelectWrap = document.getElementById('customer_project_select_wrap');
            const projectSelect = document.getElementById('project_id');
            const projectHelperText = document.getElementById('customer_project_helper_text');
            const visitDateInput = document.getElementById('visit_date');
            const selectedCustomerSourceTypeInput = document.getElementById('selected_customer_source_type');
            const selectedCustomerSourceIdInput = document.getElementById('selected_customer_source_id');
            const historyPanel = document.getElementById('customer_history_panel');
            const historyEmpty = document.getElementById('customer_history_empty');
            const historyLoading = document.getElementById('customer_history_loading');
            const historyError = document.getElementById('customer_history_error');
            const historyContent = document.getElementById('customer_history_content');
            const historyCount = document.getElementById('customer_history_count');
            const historyProfileLink = document.getElementById('customer_history_profile_link');
            const historyName = document.getElementById('customer_history_name');
            const historySource = document.getElementById('customer_history_source');
            const historyEmail = document.getElementById('customer_history_email');
            const historyPhone = document.getElementById('customer_history_phone');
            const historyLocation = document.getElementById('customer_history_location');
            const historyAddress = document.getElementById('customer_history_address');
            const historyList = document.getElementById('customer_history_list');
            const prefilledAddress = @js($prefilledAddress);
            const isAmcPrefill = @js($isAmcPrefill);

            if (!nameInput || !nameDropdownToggle || !nameResultsWrap || !nameResultsHeading || !refineSearchWrap || !refineSearchInput || !nameResultsList || !contactInput || !contactSelectWrap || !contactSelect || !contactResultsWrap || !contactResultsHeading || !contactResultsList || !emailInput || !emailResultsWrap || !emailResultsHeading || !emailResultsList || !addressInput || !addressSelectWrap || !addressSelect || !projectSelectWrap || !projectSelect || !projectHelperText || !visitDateInput || !selectedCustomerSourceTypeInput || !selectedCustomerSourceIdInput || !historyPanel || !historyEmpty || !historyLoading || !historyError || !historyContent || !historyCount || !historyProfileLink || !historyName || !historySource || !historyEmail || !historyPhone || !historyLocation || !historyAddress || !historyList) {
                return;
            }

            const today = new Date();
            const todayText = today.toISOString().split('T')[0];
            visitDateInput.min = todayText;

            const lookupUrl = @json(route('tickets.customer.lookup'));
            const historyUrl = @json(route('tickets.customer.history'));
            let searchTimer = null;
            let contactSearchTimer = null;
            let emailSearchTimer = null;
            let searchRequestId = 0;
            let historyRequestId = 0;
            let selectedCustomerKey = null;
            let selectedCustomerVariants = [];
            let cachedResultsByField = {
                name: [],
                contact_number: [],
                email: [],
            };
            let currentResultQueryByField = {
                name: '',
                contact_number: '',
                email: '',
            };
            let refineTimer = null;
            let dropdownOpenedManually = false;
            let activeHistorySourceKey = null;

            function setAddressValue(value, triggerLookup = true) {
                addressInput.value = (value || '').toString();

                if (triggerLookup) {
                    addressInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
            }

            const lookupConfigs = {
                name: {
                    searchField: 'name',
                    input: nameInput,
                    wrap: nameResultsWrap,
                    heading: nameResultsHeading,
                    refineWrap: refineSearchWrap,
                    refineInput: refineSearchInput,
                    list: nameResultsList,
                    minLength: 2,
                    emptyText: 'No existing clients found.',
                    noMatchText: 'No matching records found for this mobile, email, or address.',
                    allowNewOption: true,
                },
                contact_number: {
                    searchField: 'contact_number',
                    input: contactInput,
                    wrap: contactResultsWrap,
                    heading: contactResultsHeading,
                    refineWrap: null,
                    refineInput: null,
                    list: contactResultsList,
                    minLength: 4,
                    emptyText: 'No existing clients found.',
                    noMatchText: 'No matching clients found for this phone number.',
                    allowNewOption: false,
                },
                email: {
                    searchField: 'email',
                    input: emailInput,
                    wrap: emailResultsWrap,
                    heading: emailResultsHeading,
                    refineWrap: null,
                    refineInput: null,
                    list: emailResultsList,
                    minLength: 3,
                    emptyText: 'No existing clients found.',
                    noMatchText: 'No matching clients found for this email address.',
                    allowNewOption: false,
                },
            };

            function phoneValue(rawValue) {
                return (rawValue || '').toString().replace(/\D/g, '').trim();
            }

            function hideLookupResults(field) {
                const config = lookupConfigs[field];

                if (!config) {
                    return;
                }

                config.wrap.classList.add('hidden');
                config.list.innerHTML = '';

                if (config.heading) {
                    config.heading.classList.add('hidden');
                }

                if (config.refineWrap) {
                    config.refineWrap.classList.add('hidden');
                }

                if (config.refineInput) {
                    config.refineInput.value = '';
                }
            }

            function hideAllLookupResults() {
                Object.keys(lookupConfigs).forEach(function (field) {
                    hideLookupResults(field);
                });
                dropdownOpenedManually = false;
            }

            function clearSelectedCustomerState() {
                selectedCustomerKey = null;
                selectedCustomerVariants = [];
                selectedCustomerSourceTypeInput.value = '';
                selectedCustomerSourceIdInput.value = '';
                activeHistorySourceKey = null;
                cachedResultsByField.name = [];
                cachedResultsByField.contact_number = [];
                cachedResultsByField.email = [];
                currentResultQueryByField.name = '';
                currentResultQueryByField.contact_number = '';
                currentResultQueryByField.email = '';
                contactSelect.innerHTML = '<option value="">Select contact number</option>';
                contactSelectWrap.classList.add('hidden');
                addressSelect.innerHTML = '<option value="">Select client address</option>';
                addressSelectWrap.classList.add('hidden');
                resetProjectOptions();
                resetHistoryPanel();
                hideAllLookupResults();
            }

            function clearFetchedFields(preserveName = true) {
                if (!preserveName) {
                    nameInput.value = '';
                }
                emailInput.value = '';
                addressInput.value = '';
                contactInput.value = '';
                clearSelectedCustomerState();
            }

            function resetProjectOptions() {
                projectSelect.innerHTML = '<option value="">Select project</option>';
                projectSelect.value = '';
                projectSelectWrap.classList.add('hidden');
                projectHelperText.textContent = 'Choose the client project if this ticket belongs to an existing project.';
            }

            function resetHistoryPanel() {
                historyEmpty.classList.remove('hidden');
                historyLoading.classList.add('hidden');
                historyError.classList.add('hidden');
                historyError.textContent = '';
                historyContent.classList.add('hidden');
                historyCount.classList.add('hidden');
                historyCount.textContent = '';
                historyList.innerHTML = '';
                historyName.textContent = '';
                historySource.textContent = '';
                historyProfileLink.href = '#';
                historyEmail.textContent = '-';
                historyPhone.textContent = '-';
                historyLocation.textContent = '-';
                historyAddress.textContent = '-';
            }

            function setHistoryLoading() {
                historyEmpty.classList.add('hidden');
                historyError.classList.add('hidden');
                historyError.textContent = '';
                historyContent.classList.add('hidden');
                historyCount.classList.add('hidden');
                historyLoading.classList.remove('hidden');
            }

            function setHistoryError(message) {
                historyEmpty.classList.add('hidden');
                historyLoading.classList.add('hidden');
                historyContent.classList.add('hidden');
                historyCount.classList.add('hidden');
                historyError.textContent = message;
                historyError.classList.remove('hidden');
            }

            function renderHistoryTicketCard(item) {
                const wrapper = document.createElement('a');
                wrapper.href = item.show_url || '#';
                wrapper.className = 'block border border-slate-200 bg-white p-4 transition hover:border-indigo-200 hover:bg-indigo-50/40';

                const top = document.createElement('div');
                top.className = 'flex items-start justify-between gap-3';

                const left = document.createElement('div');
                const number = document.createElement('div');
                number.className = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
                number.textContent = item.ticket_number || '-';
                const subject = document.createElement('div');
                subject.className = 'mt-1 text-sm font-semibold text-slate-900';
                subject.textContent = item.subject || 'Ticket';
                left.appendChild(number);
                left.appendChild(subject);

                const badge = document.createElement('span');
                badge.className = 'bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700';
                badge.textContent = `${item.type_label || item.type || '-'} / ${item.status_label || item.status || '-'}`;

                top.appendChild(left);
                top.appendChild(badge);

                const meta = document.createElement('div');
                meta.className = 'mt-3 space-y-1 text-xs text-slate-600';
                meta.innerHTML = [
                    item.created_at ? `Created: ${item.created_at}` : null,
                    item.requested_by ? `Requested By: ${item.requested_by}` : null,
                    item.assigned_to ? `Assigned To: ${item.assigned_to}` : null,
                    item.contact_number ? `Contact: ${item.contact_number}` : null,
                ].filter(Boolean).join('<br>');

                wrapper.appendChild(top);
                wrapper.appendChild(meta);

                return wrapper;
            }

            function renderHistoryPanel(payload) {
                const customer = payload && payload.customer ? payload.customer : {};
                const projects = Array.isArray(payload && payload.projects ? payload.projects : []) ? payload.projects : [];
                const history = Array.isArray(payload && payload.history ? payload.history : []) ? payload.history : [];

                historyLoading.classList.add('hidden');
                historyError.classList.add('hidden');
                historyEmpty.classList.add('hidden');
                historyContent.classList.remove('hidden');

                historyName.textContent = customer.name || nameInput.value || '-';
                historySource.textContent = customer.source_label || '-';
                historyProfileLink.href = customer.show_url || '#';
                historyEmail.textContent = customer.email || '-';
                historyPhone.textContent = Array.isArray(customer.phones) && customer.phones.length ? customer.phones.join(', ') : (customer.phone || '-');
                historyLocation.textContent = [customer.city, customer.state].filter(Boolean).join(', ') || '-';
                historyAddress.textContent = customer.address || '-';
                populateProjectOptions(projects, projectSelect.value);

                historyList.innerHTML = '';

                if (history.length) {
                    historyCount.textContent = `${history.length} history item${history.length === 1 ? '' : 's'}`;
                    historyCount.classList.remove('hidden');
                    history.forEach(function (item) {
                        historyList.appendChild(renderHistoryTicketCard(item));
                    });
                    return;
                }

                historyCount.textContent = '0 history';
                historyCount.classList.remove('hidden');

                const empty = document.createElement('div');
                empty.className = 'border border-dashed border-slate-200 bg-slate-50 px-4 py-5 text-sm text-slate-500';
                empty.textContent = 'No any complain/support history.';
                historyList.appendChild(empty);
            }

            async function loadCustomerHistory(result) {
                if (!result || !result.source_type || !result.source_id) {
                    activeHistorySourceKey = null;
                    resetHistoryPanel();
                    return;
                }

                activeHistorySourceKey = `${result.source_type}:${result.source_id}`;
                const requestId = ++historyRequestId;
                setHistoryLoading();

                try {
                    const params = new URLSearchParams({
                        source_type: result.source_type,
                        source_id: String(result.source_id),
                    });

                    const response = await fetch(`${historyUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load customer history.');
                    }

                    const data = await response.json();
                    if (requestId !== historyRequestId) {
                        return;
                    }

                    renderHistoryPanel(data);
                } catch (error) {
                    if (requestId !== historyRequestId) {
                        return;
                    }

                    setHistoryError('Unable to load customer history. Please try again.');
                }
            }

            function normalizeText(value) {
                return (value || '').toString().trim().replace(/\s+/g, ' ').toLowerCase();
            }

            function resultSearchText(result) {
                const parts = [
                    result && result.name ? result.name : '',
                    result && result.email ? result.email : '',
                    result && result.phone ? result.phone : '',
                    result && result.address ? result.address : '',
                ];

                const phones = Array.isArray(result && result.phones ? result.phones : [])
                    ? result.phones.map(function (phoneOption) {
                        return phoneOption && phoneOption.value ? phoneOption.value : '';
                    })
                    : [];

                const addresses = Array.isArray(result && result.addresses ? result.addresses : [])
                    ? result.addresses.map(function (addressOption) {
                        return addressOption && addressOption.value ? addressOption.value : '';
                    })
                    : [];

                return normalizeText(parts.concat(phones, addresses).join(' '));
            }

            function findMatchingVariant() {
                if (!selectedCustomerVariants.length) {
                    return null;
                }

                const currentPhone = phoneValue(contactInput.value).slice(0, 10);
                const currentAddress = normalizeText(addressInput.value);
                let bestVariant = null;
                let bestScore = 0;

                selectedCustomerVariants.forEach(function (variant) {
                    if (!variant) {
                        return;
                    }

                    let score = 0;
                    const phones = Array.isArray(variant.phones) ? variant.phones : [];
                    const addresses = Array.isArray(variant.addresses) ? variant.addresses : [];

                    if (currentPhone !== '') {
                        const hasExactPhone = phones.some(function (phoneOption) {
                            return phoneValue(phoneOption && phoneOption.value ? phoneOption.value : '') === currentPhone;
                        });

                        if (hasExactPhone) {
                            score += 100;
                        }
                    }

                    if (currentAddress !== '') {
                        const addressValues = addresses
                            .map(function (addressOption) {
                                return normalizeText(addressOption && addressOption.value ? addressOption.value : '');
                            })
                            .filter(Boolean);

                        if (addressValues.includes(currentAddress)) {
                            score += 80;
                        } else if (addressValues.some(function (value) {
                            return value.includes(currentAddress) || currentAddress.includes(value);
                        })) {
                            score += 40;
                        }
                    }

                    if (score > bestScore) {
                        bestScore = score;
                        bestVariant = variant;
                    }
                });

                return bestScore > 0 ? bestVariant : null;
            }

            function applyVariantDetails(variant) {
                if (!variant) {
                    return;
                }

                const variantSourceType = variant.source_type || '';
                const variantSourceId = variant.source_id ? String(variant.source_id) : '';
                const nextHistorySourceKey = variantSourceType && variantSourceId
                    ? `${variantSourceType}:${variantSourceId}`
                    : null;

                if (variantSourceType && variantSourceId) {
                    selectedCustomerSourceTypeInput.value = variantSourceType;
                    selectedCustomerSourceIdInput.value = variantSourceId;
                }

                if (variant.email) {
                    emailInput.value = variant.email;
                }

                const variantAddresses = Array.isArray(variant.addresses) ? variant.addresses : [];
                const currentAddress = normalizeText(addressInput.value);
                const hasCurrentVariantAddress = currentAddress !== '' && variantAddresses.some(function (addressOption) {
                    return normalizeText(addressOption && addressOption.value ? addressOption.value : '') === currentAddress;
                });
                const selectedAddress = hasCurrentVariantAddress
                    ? addressInput.value
                    : (variant.address || (variantAddresses[0] && variantAddresses[0].value ? variantAddresses[0].value : ''));

                if (selectedAddress) {
                    setAddressValue(selectedAddress, false);
                }

                if (nextHistorySourceKey && nextHistorySourceKey !== activeHistorySourceKey) {
                    loadCustomerHistory({
                        source_type: variantSourceType,
                        source_id: variantSourceId,
                    });
                }

                $('#ticketForm').validate().element('#customer_email');
                $('#ticketForm').validate().element('#customer_address');
                $('#ticketForm').validate().element('#customer_contact_number');
            }

            function populateAddressOptions(addresses, selectedValue) {
                const currentValue = (selectedValue || '').trim();
                const options = Array.isArray(addresses) ? addresses : [];

                addressSelect.innerHTML = '<option value="">Select client address</option>';

                let matched = false;
                options.forEach(function (option) {
                    if (!option || !option.value) {
                        return;
                    }

                    const isSelected = currentValue !== '' && option.value.trim() === currentValue;
                    if (isSelected) {
                        matched = true;
                    }

                    const item = document.createElement('option');
                    item.value = option.value;
                    item.textContent = option.label || option.value;
                    item.selected = isSelected;
                    addressSelect.appendChild(item);
                });

                if (currentValue !== '' && !matched) {
                    const currentItem = document.createElement('option');
                    currentItem.value = currentValue;
                    currentItem.textContent = 'Current ticket address';
                    currentItem.selected = true;
                    addressSelect.appendChild(currentItem);
                }

                addressSelectWrap.classList.toggle('hidden', options.length <= 1);
            }

            function populateContactOptions(phoneOptions, selectedValue) {
                const currentValue = phoneValue(selectedValue);
                const options = Array.isArray(phoneOptions) ? phoneOptions : [];

                contactSelect.innerHTML = '<option value="">Select contact number</option>';

                let selectedPhone = currentValue;
                options.forEach(function (option, index) {
                    const normalizedValue = phoneValue(option && option.value ? option.value : '');
                    if (!normalizedValue) {
                        return;
                    }

                    if (!selectedPhone && index === 0) {
                        selectedPhone = normalizedValue;
                    }

                    const item = document.createElement('option');
                    item.value = normalizedValue;
                    item.textContent = option.label || normalizedValue;
                    item.selected = normalizedValue === selectedPhone;
                    contactSelect.appendChild(item);
                });

                contactInput.value = selectedPhone || '';
                contactSelectWrap.classList.toggle('hidden', options.length <= 1);
            }

            function populateProjectOptions(projects, selectedValue = '') {
                const options = Array.isArray(projects) ? projects : [];
                const currentValue = (selectedValue || '').toString().trim();

                projectSelect.innerHTML = '<option value="">Select project</option>';

                let matched = false;
                options.forEach(function (project) {
                    if (!project || !project.id) {
                        return;
                    }

                    const option = document.createElement('option');
                    option.value = String(project.id);
                    option.textContent = project.label || project.reference_no || project.name || `Project #${project.id}`;
                    option.selected = currentValue !== '' && option.value === currentValue;

                    if (option.selected) {
                        matched = true;
                    }

                    projectSelect.appendChild(option);
                });

                if (currentValue !== '' && !matched) {
                    const currentOption = document.createElement('option');
                    currentOption.value = currentValue;
                    currentOption.textContent = 'Selected project';
                    currentOption.selected = true;
                    projectSelect.appendChild(currentOption);
                }

                projectSelectWrap.classList.toggle('hidden', options.length === 0 && currentValue === '');
                projectHelperText.textContent = options.length
                    ? 'Choose the client project if this ticket belongs to an existing project.'
                    : 'No existing project found for this client.';
            }

            function renderLookupResults(field, searchValue, results, showExistingHeading = false, refineValue = '') {
                const config = lookupConfigs[field];
                if (!config) {
                    return;
                }

                const query = (searchValue || '').trim();
                const refineQuery = normalizeText(refineValue);
                const resultsKey = field;

                if (!query && !showExistingHeading && field === 'name') {
                    hideLookupResults(field);
                    return;
                }

                config.list.innerHTML = '';
                cachedResultsByField[resultsKey] = Array.isArray(results) ? results : [];
                currentResultQueryByField[resultsKey] = query;

                if (config.heading) {
                    config.heading.classList.toggle('hidden', field === 'name' ? !showExistingHeading : false);
                }

                if (config.refineWrap) {
                    config.refineWrap.classList.toggle('hidden', cachedResultsByField[resultsKey].length <= 1);
                    if (cachedResultsByField[resultsKey].length > 1) {
                        config.refineInput.value = refineValue;
                    } else {
                        config.refineInput.value = '';
                    }
                }

                const filteredResults = refineQuery === ''
                    ? cachedResultsByField[resultsKey]
                    : cachedResultsByField[resultsKey].filter(function (result) {
                        return resultSearchText(result).includes(refineQuery);
                    });

                filteredResults.forEach(function (result) {
                    const index = cachedResultsByField[resultsKey].indexOf(result);
                    const option = document.createElement('button');
                    option.type = 'button';
                    option.dataset.index = String(index);
                    option.className = 'flex w-full flex-col px-4 py-3 text-left transition hover:bg-slate-50';

                    const metaParts = [];
                    if (Array.isArray(result.source_labels) && result.source_labels.length) {
                        metaParts.push(`Source: ${result.source_labels.join(', ')}`);
                    }
                    if (result.email) {
                        metaParts.push(`Email: ${result.email}`);
                    }
                    if (result.phone) {
                        metaParts.push(`Phone: ${result.phone}`);
                    }
                    if (result.address) {
                        metaParts.push(`Address: ${result.address}`);
                    }
                    const meta = metaParts.join(' | ');
                    const title = document.createElement('span');
                    title.className = 'text-sm font-semibold text-slate-900';
                    title.textContent = result.name || '';

                    const subtitle = document.createElement('span');
                    subtitle.className = 'mt-1 text-xs text-slate-500';
                    subtitle.textContent = meta || 'Existing client';

                    option.appendChild(title);
                    option.appendChild(subtitle);

                    config.list.appendChild(option);
                });

                if (field === 'name' && query && refineQuery === '') {
                    const newOption = document.createElement('button');
                    newOption.type = 'button';
                    newOption.dataset.newOption = '1';
                    newOption.className = 'mt-1 flex w-full flex-col border border-dashed border-slate-300 px-4 py-3 text-left transition hover:border-slate-400 hover:bg-slate-50';
                    const newOptionTitle = document.createElement('span');
                    newOptionTitle.className = 'text-sm font-semibold text-slate-900';
                    newOptionTitle.textContent = `Use "${query}" as a new client`;

                    const newOptionSubtitle = document.createElement('span');
                    newOptionSubtitle.className = 'mt-1 text-xs text-slate-500';
                    newOptionSubtitle.textContent = 'Fill the remaining details manually.';

                    newOption.appendChild(newOptionTitle);
                    newOption.appendChild(newOptionSubtitle);
                    config.list.appendChild(newOption);
                }

                if (!filteredResults.length && refineQuery !== '') {
                    const emptyFilteredState = document.createElement('div');
                    emptyFilteredState.className = 'px-4 py-3 text-sm text-slate-500';
                    emptyFilteredState.textContent = config.noMatchText;
                    config.list.appendChild(emptyFilteredState);
                }

                if (!cachedResultsByField[resultsKey].length && !query) {
                    const emptyState = document.createElement('div');
                    emptyState.className = 'px-4 py-3 text-sm text-slate-500';
                    emptyState.textContent = config.emptyText;
                    config.list.appendChild(emptyState);
                }

                config.wrap.classList.remove('hidden');
            }

            function applyCustomerSelection(result) {
                selectedCustomerKey = result.key || null;
                selectedCustomerVariants = Array.isArray(result.variants) ? result.variants : [];
                selectedCustomerSourceTypeInput.value = result.source_type || '';
                selectedCustomerSourceIdInput.value = result.source_id ? String(result.source_id) : '';
                nameInput.value = result.name || nameInput.value;
                emailInput.value = result.email || '';
                resetProjectOptions();
                populateContactOptions(result.phones || [], result.phone || '');

                const addressOptions = Array.isArray(result.addresses) ? result.addresses : [];
                const selectedAddress = result.address
                    || (addressOptions[0] && addressOptions[0].value ? addressOptions[0].value : '');

                populateAddressOptions(addressOptions, selectedAddress);
                setAddressValue(selectedAddress || '', false);
                applyVariantDetails(findMatchingVariant() || selectedCustomerVariants[0] || result);
                hideAllLookupResults();

                $('#ticketForm').validate().element('#customer_name');
                $('#ticketForm').validate().element('#customer_email');
                $('#ticketForm').validate().element('#customer_contact_number');
            }

            async function searchCustomerByField(field, searchValue, searchExistingOnly = false, refineValue = '') {
                const config = lookupConfigs[field];
                if (!config) {
                    return;
                }

                const value = (searchValue || '').trim();
                const refined = (refineValue || '').trim();
                if (value.length < config.minLength && !searchExistingOnly) {
                    hideLookupResults(field);
                    return;
                }

                const requestId = ++searchRequestId;

                try {
                    const params = new URLSearchParams();
                    params.set('search_field', config.searchField);
                    if (field === 'name' && value !== '') {
                        params.set('customer_name', value);
                    } else if (field === 'contact_number' && value !== '') {
                        params.set('contact_number', value);
                    } else if (field === 'email' && value !== '') {
                        params.set('customer_email', value);
                    }
                    if (refined !== '') {
                        params.set('refine_term', refined);
                    }
                    if (searchExistingOnly) {
                        params.set('search_clients', '1');
                    }

                    const response = await fetch(`${lookupUrl}?${params.toString()}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    if (requestId !== searchRequestId) {
                        return;
                    }

                    const currentInputValue = field === 'contact_number'
                        ? phoneValue(config.input.value).slice(0, 10)
                        : (config.input.value || '').trim();

                    if (!searchExistingOnly && currentInputValue !== value) {
                        return;
                    }

                    if (!data || data.mode !== 'search') {
                        hideLookupResults(field);
                        return;
                    }

                    renderLookupResults(field, value, data.results || [], searchExistingOnly && value === '', refined);
                } catch (e) {
                    hideLookupResults(field);
                }
            }

            function scheduleNameSearch() {
                if (searchTimer) {
                    clearTimeout(searchTimer);
                }

                const currentValue = (nameInput.value || '').trim();
                searchTimer = setTimeout(() => searchCustomerByField('name', currentValue), 250);
            }

            function scheduleContactSearch() {
                if (contactSearchTimer) {
                    clearTimeout(contactSearchTimer);
                }

                const currentValue = phoneValue(contactInput.value).slice(0, 10);
                contactSearchTimer = setTimeout(() => searchCustomerByField('contact_number', currentValue), 250);
            }

            function scheduleEmailSearch() {
                if (emailSearchTimer) {
                    clearTimeout(emailSearchTimer);
                }

                const currentValue = (emailInput.value || '').trim();
                emailSearchTimer = setTimeout(() => searchCustomerByField('email', currentValue), 250);
            }

            nameInput.addEventListener('input', function () {
                if (selectedCustomerKey) {
                    clearFetchedFields(true);
                }

                const currentValue = (this.value || '').trim();
                if (currentValue.length >= 2) {
                    dropdownOpenedManually = false;
                    scheduleNameSearch();
                } else {
                    hideLookupResults('name');
                }
            });

            nameInput.addEventListener('focus', function () {
                const currentValue = (this.value || '').trim();
                if (currentValue.length >= 2 && !nameResultsList.innerHTML.trim()) {
                    scheduleNameSearch();
                }
            });

            nameDropdownToggle.addEventListener('click', function () {
                const currentValue = (nameInput.value || '').trim();

                if (!nameResultsWrap.classList.contains('hidden') && currentValue === '' && dropdownOpenedManually) {
                    hideLookupResults('name');
                    return;
                }

                dropdownOpenedManually = true;
                searchCustomerByField('name', currentValue, currentValue === '');
                nameInput.focus();
            });

            contactInput.addEventListener('input', function () {
                this.value = phoneValue(this.value).slice(0, 10);
                const currentValue = (this.value || '').trim();
                if (currentValue.length >= 4) {
                    scheduleContactSearch();
                } else {
                    hideLookupResults('contact_number');
                }
                applyVariantDetails(findMatchingVariant());
                $('#ticketForm').validate().element('#customer_contact_number');
            });

            contactSelect.addEventListener('change', function () {
                contactInput.value = phoneValue(this.value).slice(0, 10);
                applyVariantDetails(findMatchingVariant());
                $('#ticketForm').validate().element('#customer_contact_number');
            });

            emailInput.addEventListener('input', function () {
                const currentValue = (this.value || '').trim();
                if (currentValue.length >= 3) {
                    scheduleEmailSearch();
                } else {
                    hideLookupResults('email');
                }
                $('#ticketForm').validate().element('#customer_email');
            });

            addressSelect.addEventListener('change', function () {
                setAddressValue(this.value || '', false);
                applyVariantDetails(findMatchingVariant());
                $('#ticketForm').validate().element('#customer_address');
            });

            addressInput.addEventListener('input', function () {
                applyVariantDetails(findMatchingVariant());
            });

            nameResultsList.addEventListener('click', function (event) {
                const button = event.target.closest('button');
                if (!button) {
                    return;
                }

                if (button.dataset.newOption === '1') {
                    clearFetchedFields(true);
                    hideLookupResults('name');
                    nameInput.focus();
                    return;
                }

                const resultIndex = Number(button.dataset.index);
                const currentResults = cachedResultsByField.name;
                if (Number.isNaN(resultIndex) || !currentResults[resultIndex]) {
                    return;
                }

                applyCustomerSelection(currentResults[resultIndex]);
            });

            contactResultsList.addEventListener('click', function (event) {
                const button = event.target.closest('button');
                if (!button) {
                    return;
                }

                const resultIndex = Number(button.dataset.index);
                const currentResults = cachedResultsByField.contact_number;
                if (Number.isNaN(resultIndex) || !currentResults[resultIndex]) {
                    return;
                }

                applyCustomerSelection(currentResults[resultIndex]);
            });

            emailResultsList.addEventListener('click', function (event) {
                const button = event.target.closest('button');
                if (!button) {
                    return;
                }

                const resultIndex = Number(button.dataset.index);
                const currentResults = cachedResultsByField.email;
                if (Number.isNaN(resultIndex) || !currentResults[resultIndex]) {
                    return;
                }

                applyCustomerSelection(currentResults[resultIndex]);
            });

            refineSearchInput.addEventListener('input', function () {
                const refineValue = this.value || '';

                if (refineTimer) {
                    clearTimeout(refineTimer);
                }

                if ((currentResultQueryByField.name || '').trim().length < 2) {
                    renderLookupResults('name', currentResultQueryByField.name, cachedResultsByField.name, !nameResultsHeading.classList.contains('hidden'), refineValue);
                    return;
                }

                refineTimer = setTimeout(function () {
                    searchCustomerByField('name', currentResultQueryByField.name, false, refineValue);
                }, 250);
            });

            document.addEventListener('click', function (event) {
                if (!nameResultsWrap.contains(event.target) && event.target !== nameInput && event.target !== nameDropdownToggle && !nameDropdownToggle.contains(event.target)) {
                    hideLookupResults('name');
                }

                if (!contactResultsWrap.contains(event.target) && event.target !== contactInput) {
                    hideLookupResults('contact_number');
                }

                if (!emailResultsWrap.contains(event.target) && event.target !== emailInput) {
                    hideLookupResults('email');
                }
            });

            if ((contactInput.value || '').trim() !== '') {
                contactInput.value = phoneValue(contactInput.value).slice(0, 10);
            }

            if ((nameInput.value || '').trim() !== '') {
                selectedCustomerVariants = [];
            }

            if (isAmcPrefill && prefilledAddress) {
                setAddressValue(prefilledAddress, false);
            }

            if (window.google?.maps?.places && addressInput) {
                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: ['in'] },
                    fields: ['formatted_address'],
                });

                autocomplete.addListener('place_changed', function () {
                    const place = autocomplete.getPlace();
                    if (!place || !place.formatted_address) {
                        return;
                    }

                    setAddressValue(place.formatted_address, true);
                });
            }

            resetHistoryPanel();

            emailInput.addEventListener('change', function () {
                $('#ticketForm').validate().element('#customer_email');
            });
        });
    </script>
    @endpush
    </div>
</x-app-layout>
