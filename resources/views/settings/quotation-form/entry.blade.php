<x-app-layout>
    <x-slot name="header">
        <div id="quotation-form-entry-page" class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Quotation Form') }}
            </h2>
            <span class="inline-flex items-center px-3 py-1 rounded-none text-xs font-semibold bg-gray-100 text-gray-700 uppercase tracking-widest">
                Using Default
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="rounded-none border border-green-200 bg-green-50 p-4 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="rounded-none border border-red-200 bg-red-50 p-4 text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm rounded-none p-6 border border-gray-100">
                @if($defaultForm)
                    <h3 class="text-lg font-bold text-gray-900">Default form is active for your company</h3>
                    <p class="text-sm text-gray-600 mt-2">
                        You are currently using the super admin default quotation form.
                        If you want to change fields for your company, create an override (it will start by copying the default).
                    </p>

                    <div class="mt-6 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('quotation-form.override.create') }}">
                            @csrf
                            <x-primary-button>Create Company Override</x-primary-button>
                        </form>
                    </div>
                @else
                    <h3 class="text-lg font-bold text-gray-900">Default form not configured</h3>
                    <p class="text-sm text-gray-600 mt-2">
                        Super admin has not configured the quotation form yet. Please ask super admin to create it first.
                    </p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
