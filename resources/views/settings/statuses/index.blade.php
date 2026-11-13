<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Lead Status Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-4">Add New Status</h3>
                    <form id="status-create-form" action="{{ route('settings.statuses.store') }}" method="POST" class="flex flex-col md:flex-row gap-4" novalidate>
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="name" value="Status Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                        </div>
                        <div class="w-32">
                            <x-input-label for="color" value="Color" />
                            <input id="color" type="color" name="color" class="mt-1 block w-full h-10 border-gray-300 rounded-md" value="#4f46e5" required>
                        </div>
                        <div class="flex items-end">
                            <x-primary-button id="status-create-submit" type="submit">Add Status</x-primary-button>
                        </div>
                    </form>
                </div>

                <div>
                    <h3 class="text-lg font-bold mb-4">Existing Statuses</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Color</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Order</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($statuses as $status)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $status->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            <div class="flex items-center">
                                                <div class="w-4 h-4 rounded-full mr-2" style="background-color: {{ $status->color }}"></div>
                                                {{ $status->color }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $status->order }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            (function () {
                const form = document.getElementById('status-create-form');
                const submit = document.getElementById('status-create-submit');
                const nameInput = document.getElementById('name');
                const colorInput = document.getElementById('color');

                if (!form || !submit) {
                    return;
                }

                form.addEventListener('submit', function (event) {
                    const name = (nameInput?.value || '').trim();
                    const color = (colorInput?.value || '').trim();

                    if (!name) {
                        event.preventDefault();
                        appAlert('Status Name is required.', 'Validation Error', 'error');
                        nameInput?.focus();
                        return;
                    }

                    if (!/^#[0-9A-Fa-f]{6}$/.test(color)) {
                        event.preventDefault();
                        appAlert('Please select a valid color.', 'Validation Error', 'error');
                        colorInput?.focus();
                        return;
                    }

                    submit.disabled = true;
                    submit.textContent = 'Adding...';
                });
            })();
        </script>
    @endpush
</x-app-layout>
