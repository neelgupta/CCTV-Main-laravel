<x-guest-layout>
    <div id="customer-support-error-page" class="min-h-screen bg-gradient-to-br from-slate-50 via-white to-rose-50 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl">
            <div class="overflow-hidden rounded-3xl border border-rose-200 bg-white shadow-xl">
                <div class="border-b border-rose-100 bg-rose-50 px-6 py-8 text-center">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-rose-700">QR Complaint Portal</p>
                    <h1 class="mt-2 text-3xl font-bold text-slate-900">{{ $title }}</h1>
                </div>

                <div class="px-6 py-8 text-center">
                    <p class="text-base text-slate-600">{{ $message }}</p>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
