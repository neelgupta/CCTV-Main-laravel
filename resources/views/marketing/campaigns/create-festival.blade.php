<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">Create Festival Campaign</h2>
                    <p class="mt-1 text-sm text-slate-500">Set up a festive outreach campaign with AI-generated banners and greetings.</p>
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
                <input type="hidden" name="type" value="festival">
                <input type="hidden" name="status" value="draft">

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Campaign Name</label>
                        <input type="text" name="name" value="{{ old('name', 'Festival Campaign') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Subject</label>
                        <input type="text" name="subject" value="{{ old('subject', 'Festive wishes from our team') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                    </div>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Festival</label>
                        <select name="festival_name" id="festivalSelect" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" onchange="populateFestivalDetails(this)">
                            <option value="">Select a festival</option>
                            @foreach($upcomingFestivals as $festival)
                                <option value="{{ $festival['festival_name'] }}" data-date="{{ $festival['date'] }}" data-description="{{ $festival['description'] ?? '' }}">{{ $festival['festival_name'] }} - {{ \Carbon\Carbon::parse($festival['date'])->format('d M Y') }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Festival Date</label>
                        <input type="date" name="festival_date" id="festivalDate" value="{{ old('festival_date') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Send Type</label>
                    <div class="mt-2 grid max-w-md grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700">
                            <input type="radio" name="send_type" value="immediate" @checked(old('send_type') === 'immediate') class="sr-only festival-send-type">
                            Send Now
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="send_type" value="scheduled" @checked(old('send_type', 'scheduled') === 'scheduled') class="sr-only festival-send-type">
                            Schedule
                        </label>
                    </div>
                </div>

                <div class="festivalScheduleField">
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schedule Date & Time</label>
                    <input type="datetime-local" name="scheduled_at" value="{{ old('scheduled_at') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Festival Description</label>
                    <textarea name="festival_description" id="festivalDescription" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" placeholder="Festival highlights and greeting context">{{ old('festival_description') }}</textarea>
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Greetings Text</label>
                    <input type="text" name="greetings_text" value="{{ old('greetings_text', 'Warm festive wishes from our team') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                </div>

                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WhatsApp Template</label>
                    <input type="text" name="whatsapp_template_name" value="{{ old('whatsapp_template_name') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" placeholder="festival_template">
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

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-cyan-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700">Create Festival Campaign</button>
                    <a href="{{ route('marketing.campaigns.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        function populateFestivalDetails(select) {
            const option = select.options[select.selectedIndex];
            if (option && option.dataset.date) {
                document.getElementById('festivalDate').value = option.dataset.date;
                document.getElementById('festivalDescription').value = option.dataset.description || '';
            }
        }

        document.querySelectorAll('.festival-send-type').forEach(el => {
            const syncScheduleFields = function() {
                const fields = document.querySelectorAll('.festivalScheduleField');
                fields.forEach(f => { f.style.display = this.value === 'scheduled' ? 'block' : 'none'; });
            };

            el.addEventListener('change', syncScheduleFields);
            if (el.checked) {
                syncScheduleFields.call(el);
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
