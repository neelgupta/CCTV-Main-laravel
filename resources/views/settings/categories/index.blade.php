<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manage Your Lead Categories') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="mb-8">
                    <h3 class="text-lg font-bold mb-4">Add New Category</h3>
                    <form id="category-create-form" action="{{ route('settings.categories.store') }}" method="POST" class="flex flex-col md:flex-row gap-4" novalidate>
                        @csrf
                        <div class="flex-1">
                            <x-input-label for="name" value="Category Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full" placeholder="e.g. Residential, Commercial" required />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div class="flex items-end">
                            <x-primary-button id="category-create-submit" type="submit">Add Category</x-primary-button>
                        </div>
                    </form>
                </div>

                <div>
                    <h3 class="text-lg font-bold mb-4">Your Categories</h3>
                    <p class="text-sm text-gray-500 mb-4">This list includes global categories set by the admin and categories you have created for your company.</p>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($categories as $category)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{{ $category->name }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            @if(is_null($category->company_id))
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Global</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-indigo-100 text-indigo-800">Company</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            @if(!is_null($category->company_id))
                                                <form action="{{ route('settings.categories.destroy', $category) }}" method="POST" class="category-delete-form" data-swal-confirm="Are you sure?" data-swal-title="Delete Category">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900 category-delete-submit">Delete</button>
                                                </form>
                                            @endif
                                        </td>
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
                const form = document.getElementById('category-create-form');
                const submit = document.getElementById('category-create-submit');
                const nameInput = document.getElementById('name');

                if (form && submit) {
                    form.addEventListener('submit', function (event) {
                        const name = (nameInput?.value || '').trim();
                        if (!name) {
                            event.preventDefault();
                            appAlert('Category Name is required.', 'Validation Error', 'error');
                            nameInput?.focus();
                            return;
                        }

                        submit.disabled = true;
                        submit.textContent = 'Adding...';
                    });
                }
            })();
        </script>
    @endpush
</x-app-layout>
