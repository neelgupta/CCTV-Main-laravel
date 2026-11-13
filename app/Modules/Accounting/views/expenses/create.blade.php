<x-app-layout>
    <x-slot name="header"><h2 class="text-2xl font-semibold text-slate-950">Create Expense</h2></x-slot>
    <div class="py-6">
        <form method="POST" action="{{ route('accounting.expenses.store') }}" enctype="multipart/form-data" class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur space-y-6">
            @csrf
            @include('accounting::expenses._form')
            <div class="flex gap-3">
                <a href="{{ route('accounting.expenses.index') }}" class=" border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Cancel</a>
                <button class=" bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white" >Save</button>
            </div>
        </form>
    </div>
</x-app-layout>
