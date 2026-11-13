<x-app-layout>
    <div class="py-10">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <div class="rounded-3xl bg-white p-8 shadow-sm ring-1 ring-slate-200">
                <h1 class="text-2xl font-bold text-slate-900">Client Portal</h1>
                <p class="mt-2 text-slate-600">Welcome, {{ $user?->name }}.</p>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs uppercase text-slate-500">Email</div>
                        <div class="mt-1 font-semibold">{{ $user?->email }}</div>
                    </div>
                    <div class="rounded-2xl bg-slate-50 p-4">
                        <div class="text-xs uppercase text-slate-500">Role</div>
                        <div class="mt-1 font-semibold">Client</div>
                    </div>
                </div>
                <form method="POST" action="{{ route('client.logout') }}" class="mt-6">
                    @csrf
                    <button class="rounded-xl bg-slate-900 px-4 py-2 text-white">Logout</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
