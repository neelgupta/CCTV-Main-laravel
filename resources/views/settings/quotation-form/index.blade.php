<x-app-layout>
    <x-slot name="header">
        <div id="quotation-form-page" class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Quotation Form (Company)') }}
            </h2>
            <span class="inline-flex items-center px-3 py-1 rounded-none text-xs font-semibold {{ $override ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }} uppercase tracking-widest">
                {{ $override ? 'Override' : 'Default' }}
            </span>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="rounded-none border border-green-200 bg-green-50 p-4 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @include('quotation-form._builder', [
                'action' => route('settings.quotation-form.update'),
                'schema' => $schema,
                'submitButtonText' => $submitButtonText,
                'showReset' => true,
                'resetAction' => route('settings.quotation-form.reset'),
                'isOverrideActive' => (bool) $override,
            ])
        </div>
    </div>
</x-app-layout>
