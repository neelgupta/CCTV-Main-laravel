<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">Create Normal Campaign</h2>
                    <p class="mt-1 text-sm text-slate-500">Set up a new email and WhatsApp outreach campaign.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('marketing.campaigns.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Campaigns</a>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="w-full py-4 sm:px-4 lg:px-6">
        @if($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ route('marketing.campaigns.store') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="type" value="normal">
                <input type="hidden" name="status" value="draft">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Campaign Name</label>
                        <input type="text" name="name" value="{{ old('name') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" required>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Subject</label>
                        <input type="text" name="subject" value="{{ old('subject') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Send Type</label>
                    <div class="mt-2 grid max-w-md grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700">
                            <input type="radio" name="send_type" value="immediate" @checked(old('send_type') === 'immediate') class="sr-only">
                            Send Now
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="send_type" value="scheduled" @checked(old('send_type', 'scheduled') === 'scheduled') class="sr-only">
                            Schedule
                        </label>
                    </div>
                </div>

                <div id="scheduleField">
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schedule Date & Time</label>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WhatsApp Template</label>
                    <input type="text" name="whatsapp_template_name" value="{{ old('whatsapp_template_name') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" placeholder="festival_offer_template">
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Recipient Clients</label>
                        <button type="button" data-select-all-clients class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">Select All</button>
                    </div>
                    <select name="client_ids[]" multiple data-client-select class="mt-2 block h-40 w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                        @foreach($clients as $client)
                            <option value="{{ $client->id }}" @selected(in_array($client->id, old('client_ids', [])))>{{ $client->name }} {{ $client->email ? '(' . $client->email . ')' : '' }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex items-center gap-4">
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="send_email" value="1" checked> Email
                    </label>
                    <label class="flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="send_whatsapp" value="1" checked> WhatsApp
                    </label>
                </div>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Create Campaign</button>
                    <a href="{{ route('marketing.campaigns.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.querySelectorAll('input[name="send_type"]').forEach(el => {
            const syncScheduleField = function() {
                const scheduleField = document.getElementById('scheduleField');
                if (scheduleField) {
                    scheduleField.style.display = this.value === 'scheduled' ? 'block' : 'none';
                }
            };

            el.addEventListener('change', syncScheduleField);
            if (el.checked) {
                syncScheduleField.call(el);
            }
        });

        document.querySelectorAll('[data-select-all-clients]').forEach(button => {
            button.addEventListener('click', () => {
                const select = button.closest('div').parentElement.querySelector('[data-client-select]');
                if (! select) return;
                const shouldSelectAll = Array.from(select.options).some(option => !option.selected);
                Array.from(select.options).forEach(option => { option.selected = shouldSelectAll; });
                button.textContent = shouldSelectAll ? 'Clear All' : 'Select All';
            });
        });
    </script>
</x-app-layout>
