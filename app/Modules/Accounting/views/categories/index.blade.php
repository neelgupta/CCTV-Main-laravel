<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-slate-950">Categories</h2>
            <p class="mt-1 text-sm text-slate-500">Manage expense category masters.</p>
        </div>
    </x-slot>

    <div class="grid gap-6 py-6 xl:grid-cols-2">
      
        <div class="border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <h3 class="text-lg font-semibold text-slate-950">Expense Categories</h3>
            <form method="POST" action="{{ route('accounting.categories.expense.store') }}" class="mt-4 grid gap-3 md:grid-cols-4">
                @csrf
                <input name="name" placeholder="Category name" class=" border-slate-300 md:col-span-1">
                <input name="description" placeholder="Description" class=" border-slate-300 md:col-span-2">
                <select name="status" class=" border-slate-300"><option value="active">Active</option><option value="inactive">Inactive</option></select>
                <button class=" bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white md:col-span-4" style="background-color: #f77070;">Add Expense Category</button>
            </form>
            <div class="mt-5 overflow-hidden  border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50"><tr><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-white">Name</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-white">Status</th><th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-white">Action</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($expenseCategories as $category)
                            <tr>
                                <td class="px-4 py-3">
                                    <input form="expense-cat-{{ $category->id }}" name="name" value="{{ $category->name }}" class="w-full  border-slate-300">
                                    <input form="expense-cat-{{ $category->id }}" name="description" value="{{ $category->description }}" class="mt-2 w-full  border-slate-300">
                                </td>
                                <td class="px-4 py-3">
                                    <select form="expense-cat-{{ $category->id }}" name="status" class=" border-slate-300">
                                        <option value="active" @selected($category->status === 'active')>Active</option>
                                        <option value="inactive" @selected($category->status !== 'active')>Inactive</option>
                                    </select>
                                </td>
                                <td class="px-4 py-3">
                                    <form id="expense-cat-{{ $category->id }}" method="POST" action="{{ route('accounting.categories.expense.update', $category) }}">
                                        @csrf @method('PATCH')
                                        <button class="inline-flex items-center gap-1.5  bg-slate-950 px-3 py-1.5 text-xs font-semibold text-white">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('accounting.categories.expense.destroy', $category) }}" onsubmit="return confirm('Delete this category?')" class="mt-2">
                                        @csrf @method('DELETE')
                                        <button class="inline-flex items-center gap-1.5  border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M4 7h16" stroke-linecap="round"></path>
                                                <path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No expense categories yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
