@php
    $selectedPermissions = collect(old('permissions', $role->permissions?->pluck('id')->all() ?? []))
        ->map(fn ($id) => (int) $id)
        ->all();
    $isEdit = $role->exists;
@endphp

<div class="grid grid-cols-1 gap-6">
    <div class="bg-white border border-gray-200 shadow-sm p-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
                <x-input-label for="role_name" value="Role Name" />
                <x-text-input
                    id="role_name"
                    name="name"
                    class="mt-1 block w-full"
                    :value="old('name', $role->name)"
                    required
                    minlength="3"
                    maxlength="50"
                    placeholder="Enter Role Name"
                />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>
            <div class="border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                @if($role->isCompanyAdmin())
                    Company Admin always has all permissions enabled.
                @else
                    Select the permissions this role should have. At least one permission is recommended.
                @endif
            </div>
        </div>
    </div>

    <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100">
            <h3 class="text-base font-semibold text-gray-900">Permission Options</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white tracking-wider w-1/3">Feature</th>
                        <th class="px-5 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white tracking-wider">Permissions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    @foreach($permissionMatrix as $feature => $capabilities)
                        <tr>
                            <td class="px-5 py-4 text-sm font-semibold text-gray-900 align-top">{{ $feature }}</td>
                            <td class="px-5 py-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($capabilities as $capability)
                                        <label class="flex items-center gap-2 text-sm text-gray-700 py-1">
                                            <input
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $capability['id'] }}"
                                                class="rounded border-gray-300 text-indigo-600"
                                                {{ in_array((int) $capability['id'], $selectedPermissions, true) || $role->isCompanyAdmin() ? 'checked' : '' }}
                                                {{ $role->isCompanyAdmin() ? 'disabled' : '' }}
                                            >
                                            <span>{{ $capability['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-4 border-t border-gray-100 flex items-center justify-end gap-3">
            <a href="{{ route('settings.roles.index') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                Cancel
            </a>
            <x-primary-button id="role-form-submit" type="submit">
                {{ $isEdit ? 'Update Role' : 'Create Role' }}
            </x-primary-button>
        </div>
    </div>
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (!window.jQuery || !window.jQuery.validator) {
                return;
            }

            const $ = window.jQuery;
            const $form = $('#role-form');
            const $submit = $('#role-form-submit');

            if (!$form.length) {
                return;
            }

            $.validator.addMethod('roleNameFormat', function (value, element) {
                return this.optional(element) || /^[A-Za-z][A-Za-z0-9\s&()_\-]*$/.test(value);
            }, 'Role name must start with a letter and may contain letters, numbers, spaces, &, parentheses, underscores, and hyphens.');

            $.validator.addMethod('permissionRequired', function () {
                if (@json($role->isCompanyAdmin())) {
                    return true;
                }

                return $form.find('input[name="permissions[]"]:checked').length > 0;
            }, 'Please select at least one permission.');

            $form.validate({
                ignore: [],
                errorElement: 'p',
                errorClass: 'mt-2 text-sm text-red-600',
                rules: {
                    name: {
                        required: true,
                        minlength: 3,
                        maxlength: 50,
                        roleNameFormat: true,
                        normalizer: function (value) {
                            return $.trim(value).replace(/\s+/g, ' ');
                        }
                    },
                    'permissions[]': {
                        permissionRequired: true
                    }
                },
                messages: {
                    name: {
                        required: 'Please enter role name.',
                        minlength: 'Role name must be at least 3 characters.',
                        maxlength: 'Role name may not be greater than 50 characters.'
                    }
                },
                errorPlacement: function (error, element) {
                    if (element.attr('name') === 'permissions[]') {
                        const $container = $form.find('table').first().parent();
                        const $existing = $('#permissions-client-error');
                        if ($existing.length) {
                            $existing.remove();
                        }
                        error.attr('id', 'permissions-client-error');
                        error.insertAfter($container);
                        return;
                    }

                    error.insertAfter(element);
                },
                highlight: function (element) {
                    $(element).addClass('border-red-500').removeClass('border-emerald-500');
                },
                unhighlight: function (element) {
                    $(element).removeClass('border-red-500').addClass('border-emerald-500');
                    if (element.name === 'permissions[]') {
                        $('#permissions-client-error').remove();
                    }
                },
                submitHandler: function (form) {
                    $submit.prop('disabled', true).text('{{ $isEdit ? 'Updating...' : 'Creating...' }}');

                    Swal.fire({
                        title: '{{ $isEdit ? 'Updating role...' : 'Creating role...' }}',
                        text: 'Please wait while we save the role details.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    form.submit();
                }
            });
        });
    </script>
@endpush
