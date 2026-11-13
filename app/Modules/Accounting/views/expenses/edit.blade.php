<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-950">Edit Expense</h2></x-slot>
    <div class="py-6">
        <form method="POST" action="{{ route('accounting.expenses.update', $expense) }}" enctype="multipart/form-data" class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur space-y-6">
            @csrf
            @method('PATCH')
            @include('accounting::expenses._form')
            <div class="flex gap-3">
                <a href="{{ route('accounting.expenses.index') }}" class="rounded-2xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                <button class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14M5 12h14" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Update</span>
                </button>
            </div>
        </form>
    </div>
</x-app-layout>
