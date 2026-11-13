<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageClients = $authUser && ($authUser->isCompanyAdmin() || $authUser->hasPermission('settings.users.manage'));
        $clientFilterWrapperId = 'client-filter-wrapper-' . $activeTab;
    @endphp

    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Clients</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Separate active clients from lead contacts so project and AMC customers stay clean and contacts remain easy to review.
                    </p>
                </div>

                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center sm:justify-end">
                    <button type="button" id="toggle-client-filters"
                        aria-expanded="false"
                        aria-controls="{{ $clientFilterWrapperId }}"
                        class="inline-flex w-full items-center justify-center border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                        title="Toggle filters" aria-label="Toggle client filters">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 01.53 1.28l-5.78 5.78v4.19a.75.75 0 01-1.14.64l-2.5-1.67a.75.75 0 01-.34-.64v-2.52L2.22 5.28A.75.75 0 012 4.75z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    <a href="{{ route('settings.clients.export', request()->query()) }}"
                       class="inline-flex w-full items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">
                        Export
                    </a>

                    @if($canManageClients)
                        <button type="button" id="open-client-import-modal"
                            class="inline-flex items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-800 shadow-sm transition hover:bg-cyan-100">
                            Import
                        </button>

                        @if($activeTab === 'contacts')
                            <a href="{{ route('leads.create') }}" class="inline-flex items-center justify-center bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500">
                                + Add New Contact
                            </a>
                        @else
                            <a href="{{ route('settings.clients.create') }}" class="inline-flex items-center justify-center bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-cyan-500">
                                + Add New Client
                            </a>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    @if($canManageClients)
        <div id="client-import-modal"
            class="{{ $errors->has('client_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6"
            aria-hidden="{{ $errors->has('client_import_file') ? 'false' : 'true' }}"
            data-modal-open="{{ $errors->has('client_import_file') ? 'true' : 'false' }}">
            <div class="absolute inset-0" data-close-client-import-modal></div>
            <div class="relative z-10 w-full max-w-lg border border-slate-200 bg-white p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Import Clients</p>
                        <h3 class="mt-1 text-xl font-extrabold text-slate-950">Upload your client file</h3>
                        <p class="mt-2 text-sm text-slate-500">Download the sample sheet, add your client data, then upload it here.</p>
                    </div>
                    <button type="button" data-close-client-import-modal
                        class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close import modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="mt-5 border border-slate-200 bg-slate-50 px-4 py-3 text-xs text-slate-600">
                    First row will contain the field names. Required columns: <span class="font-semibold">name, email, phone, address</span>.
                </div>

                @if ($errors->has('client_import_file'))
                    <div class="mt-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        @foreach ($errors->get('client_import_file') as $message)
                            <div>{{ $message }}</div>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('settings.clients.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="client_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose file</label>
                        <input id="client_import_file" type="file" name="client_import_file" accept=".csv,.xls,.xlsx"
                            class="block w-full border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                        <p class="mt-2 text-xs text-slate-500">Accepted formats: CSV, XLS, XLSX. Maximum size: 10 MB.</p>
                    </div>

                    <div class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <a href="{{ route('settings.clients.import.sample') }}"
                            class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                            Download Sample
                        </a>
                        <button type="submit"
                            class="inline-flex items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-800 shadow-sm transition hover:bg-cyan-100">
                            Upload File
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <div class="py-4 sm:py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ collect($errors->all())->first() }}
                </div>
            @endif

            @if ($errors->has('client_import_file'))
                <div class="mb-3 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    @foreach ($errors->get('client_import_file') as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                </div>
            @endif


            <div class="mb-5 overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 pt-4 sm:px-6 sm:pt-5">
                    <div class="w-full overflow-x-auto pb-1 touch-pan-x [-webkit-overflow-scrolling:touch]">
                        <nav class="-mb-px flex w-max min-w-full flex-nowrap gap-3 pr-2 sm:gap-8">
                            <a href="{{ route('settings.clients.index', ['tab' => 'clients']) }}"
                               class="{{ $activeTab === 'clients' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }} inline-flex shrink-0 items-center whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm">
                                <span>Clients</span>
                                <span class="{{ $activeTab === 'clients' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500' }} ml-3 px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $clientsCount }}
                                </span>
                            </a>
                            <a href="{{ route('settings.clients.index', ['tab' => 'contacts']) }}"
                               class="{{ $activeTab === 'contacts' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }} inline-flex shrink-0 items-center whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm">
                                <span>Contacts</span>
                                <span class="{{ $activeTab === 'contacts' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500' }} ml-3 px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $contactsCount }}
                                </span>
                            </a>
                            <a href="{{ route('settings.clients.index', ['tab' => 'duplicates']) }}"
                               class="{{ $activeTab === 'duplicates' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }} inline-flex shrink-0 items-center whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm">
                                <span>Duplicates</span>
                                <span class="{{ $activeTab === 'duplicates' ? 'bg-indigo-50 text-indigo-600' : 'bg-slate-100 text-slate-500' }} ml-3 px-2.5 py-0.5 text-xs font-semibold">
                                    {{ $duplicatesCount }}
                                </span>
                            </a>
                        </nav>
                    </div>
                </div>
            </div>

            @if($activeTab === 'clients')
                <div id="client-filter-wrapper-clients" class="hidden mb-5 border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="GET" action="{{ route('settings.clients.index') }}" class="grid gap-4 lg:grid-cols-5">
                        <input type="hidden" name="tab" value="clients">

                        <div class="lg:col-span-2">
                            <label for="search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search All Fields</label>
                            <input id="search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="Client, contact, GST, PAN, city, state, source..."
                                class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        </div>

                        <div>
                            <label for="source" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Source</label>
                            <select id="source" name="source" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Sources</option>
                                <option value="project" @selected($filters['source'] === 'project')>Project</option>
                                <option value="amc" @selected($filters['source'] === 'amc')>AMC</option>
                                <option value="direct" @selected($filters['source'] === 'direct')>Direct Client</option>
                            </select>
                        </div>

                        <div>
                            <label for="state_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
                            <select id="state_id" name="state_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All States</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}" @selected((string) $filters['state_id'] === (string) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="city_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
                            <select id="city_id" name="city_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Cities</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" @selected((string) $filters['city_id'] === (string) $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end gap-3 lg:col-span-5">
                            <button type="submit" class="inline-flex items-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                            <a href="{{ route('settings.clients.index', ['tab' => 'clients']) }}" class="inline-flex items-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <h3 class="text-base font-semibold text-slate-900">Project and AMC Clients</h3>
                        <p class="mt-1 text-sm text-slate-500">Only clients connected to projects or AMC records are shown here.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Client</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Source</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Contact</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Address</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($clients as $client)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-slate-900">{{ $client->name }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                @if($client->user)
                                                    Linked user: {{ $client->user->name }}
                                                @else
                                                    Client record
                                                @endif
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <div class="flex flex-wrap gap-2">
                                                @forelse($client->source_labels as $label)
                                                    <span class="inline-flex items-center bg-cyan-50 px-2.5 py-1 text-xs font-medium text-cyan-700">{{ $label }}</span>
                                                @empty
                                                    <span class="text-xs text-slate-400">-</span>
                                                @endforelse
                                            </div>
                                            <div class="mt-2 text-xs text-slate-500">
                                                {{ $client->projects_count }} Project{{ $client->projects_count === 1 ? '' : 's' }} • {{ $client->amcs_count }} AMC{{ $client->amcs_count === 1 ? '' : 's' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <div>{{ $client->email ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $client->mobile ?: ($client->phone ?: '-') }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <div>{{ $client->address ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ $client->city?->name ?: 'No city' }}{{ $client->state?->name ? ', ' . $client->state->name : '' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center justify-end gap-4">
                                                <a href="{{ route('settings.clients.show', $client) }}" class="text-sky-600 hover:text-sky-700 transition-colors inline-flex items-center">
                                                    <svg class="w-6 h-6" viewBox="0 0 1024 1024" xmlns="http://www.w3.org/2000/svg" fill="currentColor">
                                                        <path d="M360.08448 352.17408h276.48v40.96h-276.48zM360.08448 458.61376h276.48v40.96h-276.48zM360.08448 567.00416h122.88v40.96h-122.88z" />
                                                        <path d="M472.90368 747.52H293.52448V276.48h409.6v223.09376a20.48 20.48 0 0 0 40.96 0V276.48c0-22.58432-18.37568-40.96-40.96-40.96h-409.6c-22.58432 0-40.96 18.37568-40.96 40.96v471.04c0 22.58432 18.37568 40.96 40.96 40.96h179.3792a20.48 20.48 0 0 0 0-40.96z" />
                                                        <path d="M765.57312 745.984l-54.7328-55.76192a106.73664 106.73664 0 0 0 16.78336-57.43616c0-59.28448-48.2304-107.52-107.52-107.52s-107.52 48.23552-107.52 107.52 48.2304 107.52 107.52 107.52c23.36256 0 44.94336-7.57248 62.59712-20.2752l53.632 54.64576a20.4288 20.4288 0 0 0 14.6176 6.13376 20.48 20.48 0 0 0 14.62272-34.82624z m-145.46944-46.63808c-36.70016 0-66.56-29.85984-66.56-66.56s29.85984-66.56 66.56-66.56 66.56 29.85984 66.56 66.56-29.85472 66.56-66.56 66.56z" />
                                                    </svg>
                                                </a>

                                                @if($canManageClients)
                                                    <a href="{{ route('settings.clients.edit', $client) }}" class="text-indigo-600 hover:text-indigo-700 transition-colors inline-flex items-center">
                                                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                            <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/>
                                                        </svg>
                                                    </a>

                                                    <form method="POST" action="{{ route('settings.clients.destroy', $client) }}" class="inline-flex items-center js-client-delete-form" data-no-global-loader="1" data-client-name="{{ $client->name }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-rose-600 hover:text-rose-700 transition-colors leading-none flex items-center">
                                                            <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                                                                <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-10 text-center text-sm text-slate-500">No project or AMC clients found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($clients->hasPages())
                        <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
                            {{ $clients->appends(array_merge(request()->query(), ['tab' => 'clients']))->links() }}
                        </div>
                    @endif
                </div>
            @elseif($activeTab === 'contacts')
                <div id="client-filter-wrapper-contacts" class="hidden mb-5 border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="GET" action="{{ route('settings.clients.index') }}" class="grid gap-4 lg:grid-cols-5">
                        <input type="hidden" name="tab" value="contacts">

                        <div class="lg:col-span-2">
                            <label for="contact_search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search All Fields</label>
                            <input id="contact_search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="Contact, source, city, state, assigned user..."
                                class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        </div>

                        <div>
                            <label for="contact_source" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Source</label>
                            <select id="contact_source" name="contact_source" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Sources</option>
                                <option value="lead" @selected($filters['contact_source'] === 'lead')>Lead</option>
                                <option value="client" @selected($filters['contact_source'] === 'client')>Client Record</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact_state_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
                            <select id="contact_state_id" name="state_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All States</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}" @selected((string) $filters['state_id'] === (string) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="contact_city_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
                            <select id="contact_city_id" name="city_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Cities</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" @selected((string) $filters['city_id'] === (string) $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end gap-3 lg:col-span-5">
                            <button type="submit" class="inline-flex items-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                            <a href="{{ route('settings.clients.index', ['tab' => 'contacts']) }}" class="inline-flex items-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                        <h3 class="text-base font-semibold text-slate-900">Lead Contacts</h3>
                        <p class="mt-1 text-sm text-slate-500">This tab shows lead-client details only. Add them into clients only when you want to convert or keep them as customer records.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Contact</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Lead Info</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Address</th>
                                    <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($contacts as $lead)
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-5 py-4">
                                            <div class="font-semibold text-slate-900">{{ $lead->contact_type === 'client' ? $lead->name : $lead->client_name }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $lead->email ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $lead->mobile ?: ($lead->phone ?: '-') }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <div class="flex flex-wrap gap-2">
                                                @if($lead->contact_type === 'client')
                                                    <span class="inline-flex items-center bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                                                        Client record
                                                    </span>
                                                @elseif($lead->status)
                                                    <span class="inline-flex items-center px-2.5 py-1 text-xs font-medium text-white" style="background-color: {{ $lead->status->color ?: '#0f172a' }}">
                                                        {{ $lead->status->name }}
                                                    </span>
                                                @endif
                                                @if($lead->matched_client)
                                                    <span class="inline-flex items-center bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                        Added to clients
                                                    </span>
                                                @endif
                                                @if($lead->contact_type !== 'client' && $lead->contact_source_label)
                                                    <span class="inline-flex items-center bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-700">
                                                        {{ $lead->contact_source_label }}
                                                    </span>
                                                @endif
                                            </div>
                                            <div class="mt-2 text-xs text-slate-500">
                                                @if($lead->contact_type === 'client')
                                                    Linked user: {{ $lead->user?->name ?: 'Not linked' }}
                                                @else
                                                    Assigned to: {{ $lead->assignedTo?->name ?: 'Unassigned' }}
                                                @endif
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                Created: {{ optional($lead->created_at)->format('d M Y') ?: '-' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">
                                            <div>{{ $lead->address ?: '-' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                {{ $lead->city?->name ?: 'No city' }}{{ $lead->state?->name ? ', ' . $lead->state->name : '' }}
                                            </div>
                                        </td>
                                        <td class="px-5 py-4">
                                            <div class="flex items-center justify-end gap-3">
                                                @if($lead->contact_type === 'client')
                                                    <a href="{{ route('settings.clients.show', $lead) }}" class="inline-flex items-center border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                                        View Client
                                                    </a>
                                                @else
                                                    <a href="{{ route('leads.show', $lead) }}" class="inline-flex items-center border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                                        View Lead
                                                    </a>

                                                    @if($canManageClients && !$lead->matched_client)
                                                        <form method="POST" action="{{ route('settings.clients.contacts.add', $lead) }}" class="inline-flex">
                                                            @csrf
                                                            <button type="submit" class="inline-flex items-center bg-cyan-600 px-3 py-2 text-xs font-semibold text-white transition hover:bg-cyan-500">
                                                                Add To Clients
                                                            </button>
                                                        </form>
                                                    @endif
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-10 text-center text-sm text-slate-500">No lead contacts found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if ($contacts->hasPages())
                        <div class="border-t border-slate-200 bg-slate-50 px-5 py-4">
                            {{ $contacts->appends(array_merge(request()->query(), ['tab' => 'contacts']))->links() }}
                        </div>
                    @endif
                </div>
            @else
                <div id="client-filter-wrapper-duplicates" class="hidden mb-5 border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="GET" action="{{ route('settings.clients.index') }}" class="grid gap-4 lg:grid-cols-5">
                        <input type="hidden" name="tab" value="duplicates">

                        <div class="lg:col-span-2">
                            <label for="duplicate_search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search All Fields</label>
                            <input id="duplicate_search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="Mobile number, client, contact, city, state..."
                                class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        </div>

                        <div>
                            <label for="duplicate_source" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Source</label>
                            <select id="duplicate_source" name="contact_source" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Sources</option>
                                <option value="lead" @selected($filters['contact_source'] === 'lead')>Contact</option>
                                <option value="client" @selected($filters['contact_source'] === 'client')>Client</option>
                            </select>
                        </div>

                        <div>
                            <label for="duplicate_state_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">State</label>
                            <select id="duplicate_state_id" name="state_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All States</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}" @selected((string) $filters['state_id'] === (string) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="duplicate_city_id" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">City</label>
                            <select id="duplicate_city_id" name="city_id" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                                <option value="">All Cities</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city->id }}" @selected((string) $filters['city_id'] === (string) $city->id)>{{ $city->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="flex items-end gap-3 lg:col-span-5">
                            <button type="submit" class="inline-flex items-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                            <a href="{{ route('settings.clients.index', ['tab' => 'duplicates']) }}" class="inline-flex items-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                        </div>
                    </form>
                </div>

                <div class="space-y-5">
                    @forelse($duplicates as $group)
                        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-200 bg-slate-50 px-5 py-4">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <h3 class="text-base font-semibold text-slate-900">Duplicate Mobile Number: {{ $group->phone }}</h3>
                                        <p class="mt-1 text-sm text-slate-500">{{ $group->count }} client/contact records share this number. Merge into the record you want to keep.</p>
                                    </div>
                                    <span class="inline-flex items-center bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                                        {{ $group->count }} records
                                    </span>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200 text-sm">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Record</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Details</th>
                                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Address</th>
                                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-white text-slate-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        @foreach($group->records as $record)
                                            <tr class="hover:bg-slate-50/70">
                                                <td class="px-5 py-4">
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <div class="font-semibold text-slate-900">{{ $record->duplicate_name ?: '-' }}</div>
                                                        <span class="inline-flex items-center {{ $record->duplicate_type === 'client' ? 'bg-cyan-50 text-cyan-700' : 'bg-sky-50 text-sky-700' }} px-2.5 py-1 text-xs font-medium">
                                                            {{ $record->duplicate_source_label }}
                                                        </span>
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $record->email ?: '-' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">{{ $record->mobile ?: ($record->phone ?: '-') }}</div>
                                                </td>
                                                <td class="px-5 py-4 text-slate-600">
                                                    <div class="text-xs text-slate-500">
                                                        {{ $record->duplicate_type === 'client' ? 'Client record' : 'Contact record' }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500">Created: {{ optional($record->created_at)->format('d M Y') ?: '-' }}</div>
                                                </td>
                                                <td class="px-5 py-4 text-slate-600">
                                                    <div>{{ $record->address ?: '-' }}</div>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        {{ $record->city?->name ?: 'No city' }}{{ $record->state?->name ? ', ' . $record->state->name : '' }}
                                                    </div>
                                                </td>
                                                <td class="px-5 py-4">
                                                    <div class="flex items-center justify-end gap-3">
                                                        @if($canManageClients)
                                                            <form method="POST" action="{{ route('settings.clients.duplicates.merge') }}" class="inline-flex js-client-merge-form" data-record-name="{{ $record->duplicate_name ?: 'this record' }}" data-record-phone="{{ $group->phone }}">
                                                                @csrf
                                                                <input type="hidden" name="target_type" value="{{ $record->duplicate_type }}">
                                                                <input type="hidden" name="target_id" value="{{ $record->id }}">
                                                                <input type="hidden" name="search" value="{{ $filters['search'] }}">
                                                                <input type="hidden" name="contact_source" value="{{ $filters['contact_source'] }}">
                                                                <input type="hidden" name="state_id" value="{{ $filters['state_id'] }}">
                                                                <input type="hidden" name="city_id" value="{{ $filters['city_id'] }}">
                                                                <button type="submit" class="inline-flex items-center bg-amber-500 px-3 py-2 text-xs font-semibold text-white transition hover:bg-amber-400">
                                                                    Merge Here
                                                                </button>
                                                            </form>
                                                        @endif

                                                        @if($record->duplicate_type === 'client')
                                                            <a href="{{ route('settings.clients.show', $record) }}" class="inline-flex items-center border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                                                View Client
                                                            </a>
                                                        @else
                                                            <a href="{{ route('leads.show', $record) }}" class="inline-flex items-center border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-50">
                                                                View Lead
                                                            </a>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @empty
                        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                            <div class="px-5 py-10 text-center text-sm text-slate-500">
                                No duplicate clients or contacts found for the selected filters.
                            </div>
                        </div>
                    @endforelse

                    @if ($duplicates->hasPages())
                        <div class="border border-slate-200 bg-slate-50 px-5 py-4">
                            {{ $duplicates->appends(array_merge(request()->query(), ['tab' => 'duplicates']))->links() }}
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterToggle = document.getElementById('toggle-client-filters');
            const filterWrapper = filterToggle ? document.getElementById(filterToggle.getAttribute('aria-controls') || '') : null;
            const filterForm = filterWrapper?.querySelector('form');

            if (filterToggle && filterWrapper && filterForm) {
                const hasActiveFilters = Array.from(filterForm.querySelectorAll('input, select, textarea'))
                    .some((field) => {
                        if (field.type === 'hidden') {
                            return false;
                        }

                        return String(field.value ?? '').trim() !== '';
                    });

                const syncToggle = (open) => {
                    filterWrapper.classList.toggle('hidden', !open);
                    filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };

                syncToggle(hasActiveFilters);

                filterToggle.addEventListener('click', function () {
                    syncToggle(filterWrapper.classList.contains('hidden'));
                });
            }

            const importModal = document.getElementById('client-import-modal');
            const importOpenButton = document.getElementById('open-client-import-modal');

            if (importModal && importOpenButton) {
                const closeButtons = importModal.querySelectorAll('[data-close-client-import-modal]');

                function openImportModal() {
                    importModal.classList.remove('hidden');
                    importModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                }

                function closeImportModal() {
                    importModal.classList.add('hidden');
                    importModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                }

                importOpenButton.addEventListener('click', openImportModal);
                closeButtons.forEach((button) => button.addEventListener('click', closeImportModal));

                document.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && !importModal.classList.contains('hidden')) {
                        closeImportModal();
                    }
                });

                if (importModal.dataset.modalOpen === 'true') {
                    openImportModal();
                }
            }

            document.querySelectorAll('.js-client-delete-form').forEach((form) => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const clientName = form.dataset.clientName || 'this client';

                    const result = await Swal.fire({
                        title: 'Delete Client?',
                        html: `This will permanently remove <strong>${clientName}</strong> and allow the same email or phone to be reused.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Delete',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#7c2d12',
                        cancelButtonColor: '#64748b',
                    });

                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });

            document.querySelectorAll('.js-client-merge-form').forEach((form) => {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const recordName = form.dataset.recordName || 'this record';
                    const recordPhone = form.dataset.recordPhone || 'the selected mobile number';

                    const result = await Swal.fire({
                        title: 'Merge Duplicate Records?',
                        html: `This will keep <strong>${recordName}</strong> as the main profile and move duplicate lead, project, AMC, and support details for <strong>${recordPhone}</strong> into it.`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Merge',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#d97706',
                        cancelButtonColor: '#64748b',
                    });

                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            });
        });
    </script>
    @endpush
</x-app-layout>
