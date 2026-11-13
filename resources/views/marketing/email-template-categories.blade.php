<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">Email Template Categories</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Add, edit, and remove categories without using popups.</p>
                </div>
                <a href="{{ route('marketing.email-template') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Templates</a>
            </div>
        </div>
    </x-slot>

    <div class="w-full py-4 sm:px-4 lg:px-6 space-y-4">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex justify-end">
                <form method="POST" action="{{ route('marketing.email-template.categories.store') }}" class="flex w-full max-w-xl flex-col gap-3 sm:flex-row">
                    @csrf
                    <input type="text" name="name" value="{{ old('name') }}" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Add category name">
                    <button type="submit" class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Add Category</button>
                </form>
            </div>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-hidden rounded-2xl">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Templates</th>
                            <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($categories as $category)
                            <tr>
                                <td class="px-4 py-4 text-sm font-semibold text-slate-900">{{ $category->name }}</td>
                                <td class="px-4 py-4 text-sm text-slate-600">{{ $category->templates_count }}</td>
                                <td class="px-4 py-4 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <form method="POST" action="{{ route('marketing.email-template.categories.update', $category) }}" class="inline-flex items-center gap-2">
                                            @csrf
                                            @method('PATCH')
                                            <input type="text" name="name" value="{{ old('name', $category->name) }}" class="w-44 rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900">
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50" title="Update">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('marketing.email-template.categories.destroy', $category) }}" data-swal-confirm="Delete this category? This action cannot be undone." data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel" data-swal-confirm-color="#DC2626" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-500 transition hover:bg-rose-100" title="Delete">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No categories added yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($categories->hasPages())
                <div class="flex justify-end border-t border-slate-200 px-4 py-4">
                    {{ $categories->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
