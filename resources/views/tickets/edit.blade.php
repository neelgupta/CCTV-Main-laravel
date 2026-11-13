<x-app-layout>
    <div id="ticket-edit-page">
    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">{{ __('Edit Ticket') }}</h2>
            <p class="mt-1 text-sm text-slate-500">Update the ticket details and keep the conversation on track.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl py-6 sm:px-6 lg:px-8">
        <div class="border border-slate-200 bg-white p-8 shadow-sm">
            <form id="ticketForm" method="POST" action="{{ route('tickets.update', $ticket) }}" class="space-y-6">
                @csrf
                @method('PATCH')
                <input type="hidden" name="project_id" value="{{ old('project_id', $ticket->project_id) }}">

                <!-- Contact Number -->
                <div>
                    <x-input-label for="customer_contact_number" value="Contact Number" class="ml-1" />
                    <x-text-input
                        id="customer_contact_number"
                        name="customer_contact_number"
                        type="tel"
                        inputmode="numeric"
                        maxlength="10"
                        placeholder="Enter contact number"
                        :value="old('customer_contact_number', $ticket->customer_contact_number)"
                        class="mt-1 block w-full placeholder:text-slate-300"
                    readonly/>
                    <x-input-error :messages="$errors->get('customer_contact_number')" class="mt-2" />
                    <div class="error-message"></div>
                </div>

                <!-- Name + Email -->
                @php($hasContactNumber = filled(old('customer_contact_number', $ticket->customer_contact_number)))
                <div id="customer_identity_fields" class="grid gap-4 md:grid-cols-2 {{ $hasContactNumber ? '' : 'hidden' }}">
                    <div>
                        <div class="flex items-center gap-1">
                            <x-input-label for="customer_name" value="Name" class="ml-1" />
                            <span class="text-red-600">*</span>
                        </div>
                        <x-text-input
                            id="customer_name"
                            name="customer_name"
                            placeholder="Enter name"
                            :value="old('customer_name', $ticket->customer_name)"
                            class="mt-1 block w-full placeholder:text-slate-300" readonly
                        />
                        <x-input-error :messages="$errors->get('customer_name')" class="mt-2" />
                        <div class="error-message"></div>
                    </div>

                    <div>
                        <div class="flex items-center gap-1">
                            <x-input-label for="customer_email" value="Email" class="ml-1" />
                        </div>
                        <x-text-input
                            id="customer_email"
                            name="customer_email"
                            type="email"
                            placeholder="Enter email address"
                            :value="old('customer_email', $ticket->customer_email)"
                            class="mt-1 block w-full placeholder:text-slate-300"
                        readonly/>
                        <x-input-error :messages="$errors->get('customer_email')" class="mt-2" />
                        <div class="error-message"></div>
                    </div>
                </div>

                <div id="customer_address_fields" class="space-y-3 {{ $hasContactNumber ? '' : 'hidden' }}">
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
                            class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm placeholder:text-slate-300 transition-all"
                        >{{ old('customer_address', $ticket->customer_address) }}</textarea>
                        <x-input-error :messages="$errors->get('customer_address')" class="mt-2" />
                        <div class="error-message"></div>
                    </div>
                </div>

                <div>
                    <x-input-label for="project_display" value="Project" class="ml-1" />
                    <x-text-input
                        id="project_display"
                        type="text"
                        :value="old('project_id', $ticket->project_id) && $ticket->project ? trim(implode(' - ', array_filter([$ticket->project->reference_no, $ticket->project->name]))) : 'No project linked'"
                        class="mt-1 block w-full placeholder:text-slate-300"
                        readonly
                    />
                </div>

                <!-- Type + Priority -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-searchable-select
                            name="type"
                            id="type"
                            label="Type"
                            :options="collect($typeOptions)->map(fn ($label, $value) => ['id' => $value, 'label' => $label])"
                            :selected="old('type', $ticket->type)"
                            placeholder="Search type..."
                            empty-label="Select Type"
                        />
                        <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        <div class="error-message"></div>
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
                            :selected="old('priority', $ticket->priority)"
                            placeholder="Search priority..."
                            empty-label="Select Priority"
                        />
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                        <div class="error-message"></div>
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
                        :value="old('visit_date', optional($ticket->visit_date)->toDateString())"
                        class="mt-1 block w-full"
                    />
                    <p class="mt-1 text-xs text-slate-500">Optional. If selected, choose today or a future date.</p>
                    <x-input-error :messages="$errors->get('visit_date')" class="mt-2" />
                    <div class="error-message"></div>
                </div>

                <!-- Assign To + Referenced By -->
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <x-ticket-assignee-select
                            name="assigned_to"
                            id="assigned_to"
                            label="Assign To"
                            visit-date-input-id="visit_date"
                            :selected="$selectedAssignedUserIds ?? ($ticket->assigned_to ? [$ticket->assigned_to] : [])"
                        />
                        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                        <div class="error-message"></div>
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
                            :selected="old('referenced_by', $ticket->referenced_by)"
                            placeholder="Search referenced by..."
                            empty-label="Select Referenced By"
                        />
                        <x-input-error :messages="$errors->get('referenced_by')" class="mt-2" />
                        <div class="error-message"></div>
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
                        :value="old('subject', $ticket->subject)"
                        class="mt-1 block w-full placeholder:text-slate-300"
                        required
                    />
                    <x-input-error :messages="$errors->get('subject')" class="mt-2" />
                    <div class="error-message"></div>
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
                        class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm placeholder:text-slate-300 transition-all"
                        required>{{ old('description', $ticket->description) }}</textarea>
                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    <div class="error-message"></div>
                </div>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <a href="{{ route('tickets.show', $ticket) }}"
                       class="border border-slate-200 px-6 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50 transition-all">
                        Cancel
                    </a>
                    <x-primary-button id="submitBtn">
                        Update Ticket
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <style>
        .error, .error-message .error { color: #e11d48; font-size: 0.875rem; margin-top: 0.25rem; font-weight: 500; }
        input.error, textarea.error, select.error { border-color: #e11d48 !important; }
        input.error:focus, textarea.error:focus { ring-color: #fb7185 !important; }
    </style>

    <script>
        $(document).ready(function() {
            $("#ticketForm").validate({
                rules: {
                    customer_name: {
                        normalizer: function(value) {
                            return $.trim(value);
                        },
                        required: {
                            depends: function () {
                                return ($('#customer_contact_number').val() || '').trim() !== '';
                            }
                        },
                        minlength: 2,
                        maxlength: 191,
                    },
                    customer_email: {
                        normalizer: function(value) {
                            return $.trim(value);
                        },
                        email: true,
                        maxlength: 191,
                    },
                    customer_address: {
                        normalizer: function(value) {
                            return $.trim(value);
                        },
                        maxlength: 1000,
                    },
                    type: { required: true },
                    subject: {
                        normalizer: function(value) {
                            return $.trim(value);
                        },
                        required: true,
                        minlength: 5,
                        maxlength: 191
                    },
                    priority: { required: true },
                    visit_date: {
                        date: true
                    },
                    description: {
                        normalizer: function(value) {
                            return $.trim(value);
                        },
                        required: true,
                        maxlength: 5000
                    }
                },
                messages: {
                    customer_name: {
                        required: "Please enter customer name",
                        minlength: "Name must be at least 2 characters long",
                        maxlength: "Name may not be greater than 191 characters",
                    },
                    customer_email: {
                        email: "Please enter a valid email address",
                        maxlength: "Email may not be greater than 191 characters",
                    },
                    customer_address: {
                        maxlength: "Address may not be greater than 1000 characters",
                    },
                    type: {
                        required: "Please select a type",
                    },
                    subject: {
                        required: "Please enter a subject",
                        minlength: "Subject should be at least 5 characters long",
                        maxlength: "Subject may not be greater than 191 characters"
                    },
                    priority: {
                        required: "Please select a priority",
                    },
                    description: {
                        required: "Please provide a detailed description",
                        maxlength: "Description may not be greater than 5000 characters"
                    }
                },
                errorPlacement: function(error, element) {
                    const errorContainer = element.closest('div').find('.error-message').first();

                    if (errorContainer.length) {
                        errorContainer.html(error);
                        return;
                    }

                    error.insertAfter(element);
                },
                submitHandler: function(form) {
                    $('#submitBtn').attr('disabled', 'disabled').text('Processing...');
                    form.submit();
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const contactInput = document.getElementById('customer_contact_number');
            const nameInput = document.getElementById('customer_name');
            const emailInput = document.getElementById('customer_email');
            const identityFields = document.getElementById('customer_identity_fields');
            const addressFields = document.getElementById('customer_address_fields');
            const addressInput = document.getElementById('customer_address');
            const addressSelectWrap = document.getElementById('customer_address_select_wrap');
            const addressSelect = document.getElementById('customer_address_selector');
            const visitDateInput = document.getElementById('visit_date');

            if (!contactInput || !nameInput || !emailInput || !identityFields || !addressFields || !addressInput || !addressSelectWrap || !addressSelect || !visitDateInput) return;

            const today = new Date();
            const todayText = today.toISOString().split('T')[0];
            visitDateInput.min = todayText;

            const lookupUrl = @json(route('tickets.customer.lookup'));
            let lookupTimer = null;
            let lastLookupValue = '';

            function setIdentityVisible(visible) {
                identityFields.classList.toggle('hidden', !visible);
                addressFields.classList.toggle('hidden', !visible);
                nameInput.required = visible;
                emailInput.required = false;
            }

            function clearCustomerFields() {
                nameInput.value = '';
                emailInput.value = '';
                addressInput.value = '';
                addressSelect.innerHTML = '<option value="">Select client address</option>';
                addressSelectWrap.classList.add('hidden');
            }

            function setAddressLocked(locked) {
                const hasValue = (addressInput.value || '').trim() !== '';
                const shouldLock = locked && hasValue;

                addressInput.readOnly = shouldLock;
                addressInput.classList.toggle('bg-slate-50', shouldLock);
                addressInput.classList.toggle('cursor-not-allowed', shouldLock);
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

            async function lookupCustomer(contactValue) {
                const value = (contactValue || '').trim();
                if (!value) {
                    lastLookupValue = '';
                    clearCustomerFields();
                    setIdentityVisible(false);
                    return;
                }

                lastLookupValue = value;

                try {
                    const response = await fetch(`${lookupUrl}?contact_number=${encodeURIComponent(value)}`, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });

                    if (!response.ok) {
                        return;
                    }

                    const data = await response.json();
                    if (((contactInput.value || '').trim()) !== value) {
                        return;
                    }

                    if (!data || !data.found) {
                        return;
                    }

                    if (!$.trim(nameInput.value)) {
                        nameInput.value = data.name || '';
                    }

                    if (!$.trim(emailInput.value)) {
                        emailInput.value = data.email || '';
                    }

                    const addressOptions = Array.isArray(data.addresses) ? data.addresses : [];
                    const selectedAddress = (addressInput.value || '').trim()
                        || data.address
                        || (addressOptions[0] && addressOptions[0].value ? addressOptions[0].value : '');
                    populateAddressOptions(addressOptions, selectedAddress);

                    if (!$.trim(addressInput.value)) {
                        addressInput.value = selectedAddress;
                    }

                    setAddressLocked(Boolean(data.locked));
                } catch (e) {
                    // Ignore lookup failures; manual entry still works.
                }
            }

            function scheduleLookup() {
                if (lookupTimer) {
                    clearTimeout(lookupTimer);
                }

                const currentValue = (contactInput.value || '').trim();
                lookupTimer = setTimeout(() => lookupCustomer(currentValue), 350);
            }

            contactInput.addEventListener('input', function () {

                // Allow only numbers
                this.value = this.value.replace(/\D/g, '');

                // Limit to 10 digits
                if (this.value.length > 10) {
                    this.value = this.value.slice(0, 10);
                }

                const currentValue = this.value.trim();

                // Show fields ONLY when exactly 10 digits
                setIdentityVisible(currentValue.length === 10);

                if (lastLookupValue && currentValue !== lastLookupValue) {
                    clearCustomerFields();
                }

                // Call API only when 10 digits complete
                if (currentValue.length === 10) {
                    scheduleLookup();
                }
            });

            contactInput.addEventListener('blur', function () {
                if (lookupTimer) {
                    clearTimeout(lookupTimer);
                }

                const currentValue = (contactInput.value || '').trim();
                    setIdentityVisible(currentValue.length === 10);

                    if (currentValue.length === 10) {
                        lookupCustomer(currentValue);
                    }
            });

            addressSelect.addEventListener('change', function () {
                addressInput.value = this.value || '';
                setAddressLocked(true);
                $('#ticketForm').validate().element('#customer_address');
            });

            setIdentityVisible(((contactInput.value || '').trim()).length === 10);

            if (((contactInput.value || '').trim()).length === 10) {
                lookupCustomer((contactInput.value || '').trim());
            }
        });
    </script>
    @endpush
    </div>
</x-app-layout>
