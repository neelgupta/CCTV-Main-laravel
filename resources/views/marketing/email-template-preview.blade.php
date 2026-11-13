<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">{{ $template->name }}</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Preview the saved email template on a dedicated page.</p>
                </div>
                <a href="{{ route('marketing.email-template.edit', $template) }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Editor</a>
            </div>
        </div>
    </x-slot>

    <div class="w-full py-4 sm:px-4 lg:px-6">
        <div class="mx-auto max-w-5xl rounded-3xl border border-slate-200 bg-slate-100 p-5 shadow-sm">
            <div class="mx-auto max-w-3xl rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-6 py-5">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Subject</div>
                    <div class="mt-2 text-lg font-semibold text-slate-950">{{ $template->subject ?: 'No subject' }}</div>
                    @if($template->preheader)
                        <div class="mt-2 text-sm text-slate-500">{{ $template->preheader }}</div>
                    @endif
                </div>
                <div class="p-6">
                    {!! $template->html_content !!}
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
