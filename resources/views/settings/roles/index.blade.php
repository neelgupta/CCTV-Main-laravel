<x-app-layout>
    @php
        $authUser = auth()->user();
        $canCreateRole = $authUser && ($authUser->hasPermission('settings.roles.create') || $authUser->hasPermission('settings.roles.manage'));
        $canEditRole = $authUser && ($authUser->hasPermission('settings.roles.edit') || $authUser->hasPermission('settings.roles.manage'));
        $canDeleteRole = $authUser && ($authUser->hasPermission('settings.roles.delete') || $authUser->hasPermission('settings.roles.manage'));
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Role Management</h2>
                <p class="text-sm text-slate-500 mt-1">Manage company roles, permissions, and access controls.</p>
            </div>
            @if($canCreateRole)
                <a href="{{ route('settings.roles.create') }}" class="inline-flex items-center justify-center border border-transparent bg-gray-800 px-4 py-3 text-sm font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 sm:py-2">
                    Create Role
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100">
                    <h3 class="text-base font-semibold text-gray-900">All Roles</h3>
                </div>

                @if($roles->isEmpty())
                    <p class="p-5 text-sm text-gray-500">No roles available for this company yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white tracking-wider">Role Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white tracking-wider">Permissions</th>
                                    <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase text-white tracking-wider">Assigned Users</th>
                                    <th class="px-6 py-3 text-center text-xs font-semibold text-gray-600 uppercase text-white tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white">
                                @foreach($roles as $role)
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4 text-sm font-semibold text-gray-900">{{ $role->name }}</td>
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            {{ $role->isCompanyAdmin() ? 'All Permissions' : $role->permissions->count() . ' selected' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-700">{{ $role->users_count }}</td>
                                        <td class="px-6 py-4">
                                            <div class="flex items-center justify-center gap-2">
                                                @if($canEditRole)
                                                    <a
                                                        href="{{ route('settings.roles.edit', $role) }}"
                                                        class="inline-flex items-center justify-center border border-indigo-200 bg-indigo-50 p-2 text-indigo-700 transition hover:bg-indigo-100"
                                                        title="Edit Role"
                                                    >
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>

                                                    </a>
                                                @endif

                                                @if($canDeleteRole)
                                                    <form method="POST" action="{{ route('settings.roles.destroy', $role) }}" class="role-delete-form">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button
                                                            type="submit"
                                                            class="inline-flex items-center justify-center border border-red-200 bg-red-50 p-2 text-red-700 transition hover:bg-red-100"
                                                            title="Delete Role"
                                                            data-role-name="{{ $role->name }}"
                                                            {{ $role->isCompanyAdmin() || $role->users_count > 0 ? 'disabled' : '' }}
                                                        >
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                                <path d="M3 6h18" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M19 6l-1 14a1 1 0 01-1 1H7a1 1 0 01-1-1L5 6" stroke-linecap="round" stroke-linejoin="round"/>
                                                                <path d="M10 11v6M14 11v6" stroke-linecap="round" stroke-linejoin="round"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                            @if($role->isCompanyAdmin())
                                                <p class="mt-2 text-center text-xs text-slate-500">Default role cannot be deleted.</p>
                                            @elseif($role->users_count > 0)
                                                <p class="mt-2 text-center text-xs text-slate-500">Remove assigned users before deleting.</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.role-delete-form').forEach(function (form) {
                    form.addEventListener('submit', async function (event) {
                        event.preventDefault();

                        const button = form.querySelector('button[type="submit"]');
                        if (!button || button.disabled) {
                            return;
                        }

                        const roleName = button.dataset.roleName || 'this role';
                        const ok = await window.appConfirm(`Do you want to delete ${roleName}? This action will soft delete the role.`, {
                            title: 'Delete Role?',
                            confirmText: 'Yes, delete it',
                            cancelText: 'Cancel',
                            icon: 'warning',
                            confirmButtonColor: '#dc2626'
                        });

                        if (!ok) {
                            return;
                        }

                        button.disabled = true;
                        form.submit();
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
