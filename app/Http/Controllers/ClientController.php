<?php

namespace App\Http\Controllers;

use App\Exports\ClientExport;
use App\Exports\ContactExport;
use App\Exports\ClientImportSampleExport;
use App\Imports\ClientImport;
use App\Models\Amc;
use App\Models\City;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\State;
use App\Models\User;
use App\Services\ClientDuplicateMergeService;
use App\Services\ClientRelationSyncService;
use App\Services\ClientOutstandingAmountService;
use App\Services\ClientSyncService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ClientController extends Controller
{
    public function export(Request $request)
    {
        $companyId = (int) auth()->user()->company_id;
        $tab = in_array($request->string('tab')->toString(), ['clients', 'contacts', 'duplicates'], true)
            ? $request->string('tab')->toString()
            : 'clients';

        if ($tab === 'contacts') {
            return Excel::download(
                new ContactExport($this->contactItems($companyId, $this->clientFilters($request))),
                'contacts-' . now()->format('Y-m-d-His') . '.xlsx'
            );
        }

        $projectClientNames = $this->projectClientNames($companyId);
        $clients = $this->applyClientFilters(
            $this->baseClientsQuery($companyId, $projectClientNames),
            $this->clientFilters($request),
            $projectClientNames,
            $companyId
        )
            ->with(['state:id,name', 'city:id,name'])
            ->get();

        return Excel::download(
            new ClientExport($clients),
            'clients-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'client_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'client_import_file.required' => 'Please choose a client import file.',
            'client_import_file.mimes' => 'Client import file must be CSV, XLS, or XLSX.',
        ]);

        Excel::import(
            new ClientImport(auth()->user()),
            $request->file('client_import_file')
        );

        return redirect()
            ->route('settings.clients.index')
            ->with('success', 'Clients imported successfully.');
    }

    public function downloadImportSample()
    {
        return Excel::download(
            new ClientImportSampleExport(),
            'client-import-sample.xlsx'
        );
    }

    public function index(Request $request)
    {
        $companyId = (int) auth()->user()->company_id;
        $activeTab = in_array($request->string('tab')->toString(), ['clients', 'contacts', 'duplicates'], true)
            ? $request->string('tab')->toString()
            : 'clients';
        $filters = $this->clientFilters($request);

        $projectClientNames = $this->projectClientNames($companyId);

        $clientsQuery = $this->applyClientFilters(
            $this->baseClientsQuery($companyId, $projectClientNames),
            $filters,
            $projectClientNames,
            $companyId
        );

        $clientsCount = (clone $clientsQuery)->count();

        $clients = $clientsQuery
            ->paginate(10, ['*'], 'clients_page')
            ->withQueryString();

        $clientProjectCounts = Project::query()
            ->selectRaw('client_name, COUNT(*) as aggregate')
            ->where('company_id', $companyId)
            ->whereIn('client_name', $clients->getCollection()->pluck('name')->filter()->all())
            ->groupBy('client_name')
            ->pluck('aggregate', 'client_name');

        $clients->getCollection()->transform(function (Client $client) use ($projectClientNames, $clientProjectCounts) {
            $sources = [];

            if ($client->is_direct_client) {
                $sources[] = 'Direct Client';
            }

            if ($projectClientNames->contains($client->name)) {
                $sources[] = 'Project';
            }

            if ((int) $client->amcs_count > 0) {
                $sources[] = 'AMC';
            }

            $client->source_labels = $sources;
            $client->projects_count = (int) ($clientProjectCounts[$client->name] ?? 0);

            return $client;
        });

        $states = State::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $cities = City::query()
            ->orderBy('name')
            ->get(['id', 'name', 'state_id']);

        $contactsQuery = Lead::query()
            ->with(['status:id,name,color', 'assignedTo:id,name', 'state:id,name', 'city:id,name,state_id'])
            ->where('company_id', $companyId)
            ->whereDoesntHave('project')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.company_id', 'leads.company_id')
                    ->where(function ($matchQuery) {
                        $matchQuery
                            ->where(function ($nameQuery) {
                                $nameQuery->whereNotNull('leads.client_name')
                                    ->whereRaw('TRIM(clients.name) = TRIM(leads.client_name)');
                            })
                            ->orWhere(function ($emailQuery) {
                                $emailQuery->whereNotNull('leads.email')
                                    ->whereNotNull('clients.email')
                                    ->whereRaw('LOWER(clients.email) = LOWER(leads.email)');
                            })
                            ->orWhere(function ($mobileQuery) {
                                $mobileQuery->whereNotNull('leads.mobile')
                                    ->where(function ($clientPhoneQuery) {
                                        $clientPhoneQuery->whereColumn('clients.mobile', 'leads.mobile')
                                            ->orWhereColumn('clients.phone', 'leads.mobile');
                                    });
                            });
                            // Leads now store a single contact number in `mobile`.
                            // Matching against `leads.phone` causes SQL errors on updated schemas.
                    });
            })
            ->latest();

        $contactItems = $this->contactItems($companyId, $filters);

        $contactsCount = $contactItems->count();

        $contactsPage = LengthAwarePaginator::resolveCurrentPage('contacts_page');
        $contactsPerPage = 10;
        $contacts = new LengthAwarePaginator(
            $contactItems->forPage($contactsPage, $contactsPerPage)->values(),
            $contactsCount,
            $contactsPerPage,
            $contactsPage,
            [
                'path' => $request->url(),
                'pageName' => 'contacts_page',
            ]
        );

        $contacts->appends($request->query());

        $duplicateItems = $this->buildDuplicateItems($contactItems, $filters);
        $duplicatesCount = $duplicateItems->count();

        $duplicatesPage = LengthAwarePaginator::resolveCurrentPage('duplicates_page');
        $duplicatesPerPage = 10;
        $duplicates = new LengthAwarePaginator(
            $duplicateItems->forPage($duplicatesPage, $duplicatesPerPage)->values(),
            $duplicatesCount,
            $duplicatesPerPage,
            $duplicatesPage,
            [
                'path' => $request->url(),
                'pageName' => 'duplicates_page',
            ]
        );

        $duplicates->appends($request->query());

        return view('clients.index', compact(
            'activeTab',
            'clients',
            'clientsCount',
            'contacts',
            'contactsCount',
            'duplicates',
            'duplicatesCount',
            'filters',
            'states',
            'cities'
        ));
    }

    public function create()
    {
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('clients.create', compact('states'));
    }

    public function show(Client $client)
    {
        abort_unless((int) $client->company_id === (int) auth()->user()->company_id, 403);

        $client->ensureQrToken();

        $client->load(['user:id,name,email', 'state:id,name', 'city:id,name,state_id', 'company:id,name,logo,print_logo']);

        $projects = Project::query()
            ->where('company_id', $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_name', $client->name);
            })
            ->latest()
            ->get();

        $leads = Lead::query()
            ->where('company_id', $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_name', $client->name);
                if ($client->email) {
                    $query->orWhere('email', $client->email);
                }
                if ($client->mobile) {
                    $query->orWhere('mobile', $client->mobile);
                }
            })
            ->latest()
            ->get();

        $amcs = Amc::query()
            ->where('company_id', $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_id', $client->id)
                    ->orWhere('customer_name', $client->name);

                if ($client->email) {
                    $query->orWhere('email', $client->email);
                }

                if ($client->mobile) {
                    $query->orWhere('mobile_number', $client->mobile);
                }

                if ($client->phone) {
                    $query->orWhere('mobile_number', $client->phone);
                }
            })
            ->with(['project:id,name,uuid', 'supportTicket:id,ticket_number,subject,status,company_id,generated_from_amc_id'])
            ->latest()
            ->get();

        $supportTickets = $this->clientSupportTickets($client);
        $ledgerService = app(ClientOutstandingAmountService::class);
        $ledgerEntries = $ledgerService->ledgerEntries($client);
        $ledgerSummary = $ledgerService->ledgerSummary($client);

        $users = User::query()
            ->where('company_id', $client->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('clients.show', compact('client', 'projects', 'leads', 'amcs', 'supportTickets', 'ledgerEntries', 'ledgerSummary', 'users'));
    }

    public function edit(Client $client)
    {
        abort_unless((int) $client->company_id === (int) auth()->user()->company_id, 403);

        $client->load(['state:id,name', 'city:id,name,state_id']);
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('clients.edit', compact('client', 'states'));
    }

    public function store(Request $request, ClientSyncService $sync)
    {
        $companyId = auth()->user()->company_id;
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:191',
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:191',
            ],
            'phone' => 'nullable|string|max:20',
            'gst_number' => 'nullable|string|max:20|regex:/^[0-9A-Z]{15}$/',
            'pan_number' => 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'state_id' => 'required|exists:states,id',
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $request->input('state_id'))),
            ],
            'address' => 'nullable|string|min:5|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ], [
            'name.required' => 'Client name is required.',
            'name.min' => 'Client name must be at least 2 characters.',
            'name.max' => 'Client name may not be greater than 191 characters.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email may not be greater than 191 characters.',
            'phone.max' => 'Phone number may not be greater than 20 characters.',
            'gst_number.max' => 'GST number may not be greater than 20 characters.',
            'gst_number.regex' => 'Please enter a valid GST number.',
            'pan_number.max' => 'PAN number may not be greater than 20 characters.',
            'pan_number.regex' => 'Please enter a valid PAN number.',
            'address.min' => 'Address must be at least 5 characters.',
            'address.max' => 'Address may not be greater than 1000 characters.',
        ]);

        $validated['name'] = trim((string) $validated['name']);
        $validated['email'] = filled($validated['email'] ?? null)
            ? strtolower(trim((string) $validated['email']))
            : null;
        $validated['phone'] = filled($validated['phone'] ?? null)
            ? $this->normalizeClientPhone($validated['phone'])
            : null;
        $validated['gst_number'] = strtoupper(trim((string) ($validated['gst_number'] ?? '')));
        $validated['pan_number'] = strtoupper(trim((string) ($validated['pan_number'] ?? '')));
        $validated['state_id'] = filled($validated['state_id'] ?? null) ? (int) $validated['state_id'] : null;
        $validated['city_id'] = filled($validated['city_id'] ?? null) ? (int) $validated['city_id'] : null;
        $validated['address'] = filled($validated['address'] ?? null)
            ? trim((string) $validated['address'])
            : null;
        $validated['latitude'] = filled($validated['latitude'] ?? null) ? (float) $validated['latitude'] : null;
        $validated['longitude'] = filled($validated['longitude'] ?? null) ? (float) $validated['longitude'] : null;

        $duplicateMessages = $this->duplicateClientMessages($companyId, $validated['email'] ?? null, $validated['phone'] ?? null);
        if ($duplicateMessages) {
            return back()->withErrors($duplicateMessages)->withInput();
        }

        try {
            $client = $sync->upsert(array_merge($validated, [
                'company_id' => $companyId,
                'mobile' => $validated['phone'] ?? null,
                'is_direct_client' => true,
            ]));
        } catch (QueryException $exception) {
            if ($this->isEmailConflictException($exception)) {
                return back()
                    ->withErrors(['email' => 'This email exists. Contact admin to get more details.'])
                    ->withInput();
            }

            throw $exception;
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors())->withInput();
        }

        try {
            $sync->sendPasswordSetup($client);
        } catch (\Throwable $exception) {
            report($exception);
        }

        return redirect()->route('settings.clients.index')->with('success', 'Client saved.');
    }

    private function projectClientNames(int $companyId): Collection
    {
        return Project::query()
            ->where('company_id', $companyId)
            ->whereNotNull('client_name')
            ->orderBy('client_name')
            ->pluck('client_name')
            ->filter(fn ($name) => filled($name))
            ->map(fn ($name) => trim((string) $name))
            ->unique()
            ->values();
    }

    private function baseClientsQuery(int $companyId, Collection $projectClientNames)
    {
        return Client::query()
            ->with(['user:id,name,email', 'state:id,name', 'city:id,name,state_id'])
            ->withCount('amcs')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($projectClientNames) {
                $query->where('is_direct_client', true);
                if ($projectClientNames->isNotEmpty()) {
                    $query->orWhereIn('name', $projectClientNames->all());
                }
                $query->orWhereHas('amcs');
            })
            ->latest();
    }

    private function clientFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'source' => ['nullable', 'string', Rule::in(['project', 'amc', 'direct'])],
            'contact_source' => ['nullable', 'string', Rule::in(['lead', 'client'])],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
        ]);

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'source' => $validated['source'] ?? null,
            'contact_source' => $validated['contact_source'] ?? null,
            'state_id' => isset($validated['state_id']) ? (int) $validated['state_id'] : null,
            'city_id' => isset($validated['city_id']) ? (int) $validated['city_id'] : null,
        ];
    }

    private function applyClientFilters($query, array $filters, Collection $projectClientNames, int $companyId)
    {
        $search = $filters['search'] ?? null;
        $source = $filters['source'] ?? null;
        $stateId = $filters['state_id'] ?? null;
        $cityId = $filters['city_id'] ?? null;

        return $query
            ->when($source === 'direct', fn ($builder) => $builder->where('is_direct_client', true))
            ->when($source === 'project', fn ($builder) => $projectClientNames->isNotEmpty() ? $builder->whereIn('name', $projectClientNames->all()) : $builder->whereRaw('1 = 0'))
            ->when($source === 'amc', fn ($builder) => $builder->whereHas('amcs'))
            ->when($stateId, fn ($builder) => $builder->where('state_id', $stateId))
            ->when($cityId, fn ($builder) => $builder->where('city_id', $cityId))
            ->when($search, function ($builder) use ($search, $companyId) {
                $like = '%' . $search . '%';
                $normalizedSearch = strtolower($search);

                $builder->where(function ($searchScope) use ($like, $normalizedSearch, $companyId) {
                    $searchScope->where(function ($searchQuery) use ($like, $normalizedSearch) {
                        $searchQuery->where('name', 'like', $like)
                            ->orWhere('email', 'like', $like)
                            ->orWhere('mobile', 'like', $like)
                            ->orWhere('phone', 'like', $like)
                            ->orWhere('gst_number', 'like', $like)
                            ->orWhere('pan_number', 'like', $like)
                            ->orWhere('address', 'like', $like)
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like)->orWhere('email', 'like', $like))
                            ->orWhereHas('state', fn ($stateQuery) => $stateQuery->where('name', 'like', $like))
                            ->orWhereHas('city', fn ($cityQuery) => $cityQuery->where('name', 'like', $like))
                            ->orWhereHas('amcs', fn ($amcQuery) => $amcQuery->where('contract_no', 'like', $like)->orWhere('project_name', 'like', $like));

                        if (str_contains($normalizedSearch, 'direct')) {
                            $searchQuery->orWhere('is_direct_client', true);
                        }

                        if (str_contains($normalizedSearch, 'amc')) {
                            $searchQuery->orWhereHas('amcs');
                        }
                    })->orWhereExists(function ($projectQuery) use ($companyId, $like) {
                        $projectQuery->select(DB::raw(1))
                            ->from('projects')
                            ->whereColumn('projects.client_name', 'clients.name')
                            ->where('projects.company_id', $companyId)
                            ->where(function ($projectFieldsQuery) use ($like) {
                                $projectFieldsQuery->where('projects.name', 'like', $like)
                                    ->orWhere('projects.client_name', 'like', $like);
                            });
                    });

                    if (str_contains($normalizedSearch, 'project')) {
                        $searchScope->orWhereExists(function ($projectQuery) use ($companyId) {
                            $projectQuery->select(DB::raw(1))
                                ->from('projects')
                                ->whereColumn('projects.client_name', 'clients.name')
                                ->where('projects.company_id', $companyId);
                        });
                    }
                });
            });
    }

    private function applyContactFilters(Collection $items, array $filters): Collection
    {
        $search = mb_strtolower(trim((string) ($filters['search'] ?? '')));
        $contactSource = $filters['contact_source'] ?? null;
        $stateId = $filters['state_id'] ?? null;
        $cityId = $filters['city_id'] ?? null;

        return $items
            ->filter(function ($item) use ($search, $contactSource, $stateId, $cityId) {
                if ($contactSource === 'lead' && $item->contact_type !== 'lead') {
                    return false;
                }

                if ($contactSource === 'client' && $item->contact_type !== 'client') {
                    return false;
                }

                if ($stateId && (int) ($item->state_id ?? 0) !== (int) $stateId) {
                    return false;
                }

                if ($cityId && (int) ($item->city_id ?? 0) !== (int) $cityId) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                $haystacks = [
                    $item->contact_type === 'client' ? $item->name : $item->client_name,
                    $item->email ?? null,
                    $item->mobile ?? null,
                    $item->phone ?? null,
                    $item->address ?? null,
                    $item->state?->name ?? null,
                    $item->city?->name ?? null,
                    $item->contact_source_label ?? null,
                    $item->status?->name ?? null,
                    $item->assignedTo?->name ?? null,
                    $item->user?->name ?? null,
                ];

                return collect($haystacks)
                    ->filter(fn ($value) => filled($value))
                    ->contains(fn ($value) => str_contains(mb_strtolower((string) $value), $search));
            })
            ->values();
    }

    private function contactItems(int $companyId, array $filters): Collection
    {
        $projectClientNames = $this->projectClientNames($companyId);
        $projectClientNameLookup = $projectClientNames
            ->mapWithKeys(fn ($name) => [mb_strtolower(trim((string) $name)) => true]);

        $clientIdentityPool = Client::query()
            ->where('company_id', $companyId)
            ->get(['id', 'name', 'email', 'mobile', 'phone', 'address', 'latitude', 'longitude']);

        $contactLeadItems = Lead::query()
            ->with(['status:id,name,color', 'assignedTo:id,name', 'state:id,name', 'city:id,name,state_id'])
            ->where('company_id', $companyId)
            ->whereDoesntHave('project')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('clients')
                    ->whereColumn('clients.company_id', 'leads.company_id')
                    ->where(function ($matchQuery) {
                        $matchQuery
                            ->where(function ($nameQuery) {
                                $nameQuery->whereNotNull('leads.client_name')
                                    ->whereRaw('TRIM(clients.name) = TRIM(leads.client_name)');
                            })
                            ->orWhere(function ($emailQuery) {
                                $emailQuery->whereNotNull('leads.email')
                                    ->whereNotNull('clients.email')
                                    ->whereRaw('LOWER(clients.email) = LOWER(leads.email)');
                            })
                            ->orWhere(function ($mobileQuery) {
                                $mobileQuery->whereNotNull('leads.mobile')
                                    ->where(function ($clientPhoneQuery) {
                                        $clientPhoneQuery->whereColumn('clients.mobile', 'leads.mobile')
                                            ->orWhereColumn('clients.phone', 'leads.mobile');
                                    });
                            });
                    });
            })
            ->latest()
            ->get()
            ->reject(fn (Lead $lead) => $this->leadMatchesExistingClient($lead, $clientIdentityPool))
            ->map(function (Lead $lead) {
                $lead->contact_type = 'lead';
                $lead->matched_client = null;
                $lead->contact_source_label = filled($lead->source)
                    ? str($lead->source)->replace('_', ' ')->title()->toString()
                    : 'Lead';

                return $lead;
            });

        $contactClientItems = Client::query()
            ->with(['user:id,name,email', 'state:id,name', 'city:id,name,state_id'])
            ->withCount('amcs')
            ->where('company_id', $companyId)
            ->where('is_direct_client', false)
            ->whereDoesntHave('amcs')
            ->latest()
            ->get()
            ->filter(function (Client $client) use ($projectClientNameLookup) {
                $normalizedName = mb_strtolower(trim((string) $client->name));

                return $normalizedName === '' || ! $projectClientNameLookup->has($normalizedName);
            })
            ->map(function (Client $client) {
                $client->contact_type = 'client';
                $client->matched_client = false;
                $client->contact_source_label = 'Client Record';

                return $client;
            });

        return $this->applyContactFilters(
            $contactLeadItems
                ->concat($contactClientItems)
                ->sortByDesc(function ($item) {
                    return optional($item->created_at)?->getTimestamp() ?? 0;
                })
                ->values(),
            $filters
        );
    }

    public function addLeadContact(Request $request, Lead $lead, ClientSyncService $sync)
    {
        abort_unless((int) $lead->company_id === (int) auth()->user()->company_id, 403);

        try {
            $client = $sync->upsert([
                'company_id' => (int) $lead->company_id,
                'name' => $lead->client_name,
                'email' => $lead->email,
                'mobile' => $lead->mobile,
                'phone' => $lead->mobile ?: $lead->phone,
                'gst_number' => $lead->gst_number,
                'pan_number' => $lead->pan_number,
                'state_id' => $lead->state_id,
                'city_id' => $lead->city_id,
                'address' => $lead->address,
                'is_direct_client' => true,
            ]);

            $sync->sendPasswordSetup($client);
        } catch (QueryException $exception) {
            if ($this->isEmailConflictException($exception)) {
                return redirect()
                    ->route('settings.clients.index', ['tab' => 'contacts'])
                    ->withErrors(['email' => 'This email exists. Contact admin to get more details.']);
            }

            throw $exception;
        } catch (ValidationException $exception) {
            return redirect()
                ->route('settings.clients.index', ['tab' => 'contacts'])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('settings.clients.index', ['tab' => 'contacts'])
            ->with('success', 'Lead contact added to clients successfully.');
    }

    public function update(Request $request, Client $client, ClientRelationSyncService $relationSync)
    {
        abort_unless((int) $client->company_id === (int) auth()->user()->company_id, 403);
        $validated = $request->validate([
            'name' => 'required|string|min:2|max:191',
            'email' => ['nullable', 'email:rfc,dns', 'max:191'],
            'phone' => 'nullable|string|max:20',
            'gst_number' => 'nullable|string|max:20|regex:/^[0-9A-Z]{15}$/',
            'pan_number' => 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'state_id' => 'required|exists:states,id',
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $request->input('state_id'))),
            ],
            'address' => 'nullable|string|min:5|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);

        $validated['gst_number'] = strtoupper(trim((string) ($validated['gst_number'] ?? '')));
        $validated['pan_number'] = strtoupper(trim((string) ($validated['pan_number'] ?? '')));
        $validated['name'] = trim((string) $validated['name']);
        $validated['email'] = filled($validated['email'] ?? null)
            ? strtolower(trim((string) $validated['email']))
            : null;
        $validated['phone'] = filled($validated['phone'] ?? null)
            ? trim((string) $validated['phone'])
            : null;
        $validated['state_id'] = filled($validated['state_id'] ?? null) ? (int) $validated['state_id'] : null;
        $validated['city_id'] = filled($validated['city_id'] ?? null) ? (int) $validated['city_id'] : null;
        $validated['address'] = filled($validated['address'] ?? null)
            ? trim((string) $validated['address'])
            : null;
        $validated['latitude'] = filled($validated['latitude'] ?? null) ? (float) $validated['latitude'] : null;
        $validated['longitude'] = filled($validated['longitude'] ?? null) ? (float) $validated['longitude'] : null;

        $duplicateMessages = $this->duplicateClientMessages(
            (int) $client->company_id,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
            (int) $client->id
        );

        if ($duplicateMessages) {
            return back()->withErrors($duplicateMessages)->withInput();
        }

        $previous = $relationSync->snapshotClient($client);
        $nextPhone = $validated['phone'] ?? null;

        $client->fill([
            'name' => $validated['name'],
            'email' => $validated['email'] ?? null,
            'mobile' => $nextPhone,
            'phone' => $nextPhone,
            'gst_number' => $validated['gst_number'] ?? null,
            'pan_number' => $validated['pan_number'] ?? null,
            'state_id' => $validated['state_id'] ?? null,
            'city_id' => $validated['city_id'] ?? null,
            'address' => $validated['address'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);
        $client->save();
        $relationSync->syncClientRecord($client->fresh(), $previous);

        return redirect()->route('settings.clients.show', $client)->with('success', 'Client updated successfully.');
    }
    public function checkAvailability(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $email = filled($request->email) ? strtolower(trim((string) $request->email)) : null;
        $phone = $this->normalizeClientPhone($request->input('phone'));

        $duplicateMessages = $this->duplicateClientMessages($companyId, $email, $phone);

        return response()->json([
            'emailExists' => array_key_exists('email', $duplicateMessages),
            'phoneExists' => array_key_exists('phone', $duplicateMessages),
            'messages' => $duplicateMessages,
        ]);
    }

    public function destroy(Request $request, Client $client)
    {
        abort_unless((int) $client->company_id === (int) auth()->user()->company_id, 403);

        $clientEmail = filled($client->email) ? strtolower(trim((string) $client->email)) : null;

        DB::transaction(function () use ($client, $clientEmail) {
            $linkedUser = $client->user;
            $emailLinkedUser = $clientEmail
                ? User::query()
                    ->where('company_id', $client->company_id)
                    ->whereRaw('LOWER(email) = ?', [$clientEmail])
                    ->where(function ($query) {
                        $query->where('role', 'client')
                            ->orWhereHas('roleRecord', fn ($roleQuery) => $roleQuery->where('slug', 'client'));
                    })
                    ->first()
                : null;

            $client->delete();

            if ($linkedUser && $linkedUser->hasRole('client')) {
                $linkedUser->delete();
            }

            if (
                $emailLinkedUser
                && (! $linkedUser || (int) $emailLinkedUser->id !== (int) $linkedUser->id)
                && $emailLinkedUser->hasRole('client')
            ) {
                $emailLinkedUser->delete();
            }
        });

        return back()->with('success', 'Client permanently deleted successfully.');
    }

    public function mergeDuplicate(Request $request, ClientDuplicateMergeService $mergeService)
    {
        $companyId = (int) auth()->user()->company_id;

        $validated = $request->validate([
            'target_type' => ['required', 'string', Rule::in(['client', 'lead'])],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            $client = $mergeService->mergeIntoTarget(
                $companyId,
                (string) $validated['target_type'],
                (int) $validated['target_id']
            );
        } catch (ValidationException $exception) {
            return redirect()
                ->route('settings.clients.index', [
                    'tab' => 'duplicates',
                    'search' => $request->input('search'),
                    'contact_source' => $request->input('contact_source'),
                    'state_id' => $request->input('state_id'),
                    'city_id' => $request->input('city_id'),
                ])
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('settings.clients.show', $client)
            ->with('success', 'Duplicate clients and contacts merged successfully.');
    }

    private function duplicateClientMessages(int $companyId, ?string $email, ?string $phone, ?int $ignoreClientId = null): array
    {
        $messages = [];
        $ignoredUserId = $ignoreClientId
            ? Client::query()->whereKey($ignoreClientId)->value('user_id')
            : null;

        if ($email) {
            $company = Company::query()->find($companyId);

            if ($company && $company->isInternalStaffOrAdminEmail($email)) {
                $messages['email'] = 'This email belongs to a company administrator or staff member and cannot be assigned to a client.';
            } else {
                $emailClientQuery = Client::query()
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(email) = ?', [$email]);

                if ($ignoreClientId) {
                    $emailClientQuery->whereKeyNot($ignoreClientId);
                }

                $emailExists = $emailClientQuery->exists();

                if (! $emailExists) {
                    $emailUserQuery = User::query()
                        ->where('company_id', $companyId)
                        ->whereRaw('LOWER(email) = ?', [$email]);

                    if ($ignoredUserId) {
                        $emailUserQuery->whereKeyNot($ignoredUserId);
                    }

                    $emailExists = $emailUserQuery->exists();
                }

                if ($emailExists) {
                    $messages['email'] = 'This email already exists. Open the Duplicates tab to review and merge matching records.';
                }
            }
        }

        if ($phone) {
            $phoneExists = Client::query()
                ->where('company_id', $companyId)
                ->when($ignoreClientId, fn ($query) => $query->whereKeyNot($ignoreClientId))
                ->get(['id', 'mobile', 'phone'])
                ->contains(function (Client $client) use ($phone) {
                    return $this->duplicatePhoneValue([$client->mobile, $client->phone]) === $phone;
                });

            if ($phoneExists) {
                $messages['phone'] = 'This phone number already exists. Open the Duplicates tab to review and merge matching records.';
            }
        }

        return $messages;
    }

    private function normalizeClientPhone(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value));

        return filled($digits) ? $digits : null;
    }

    private function buildDuplicateItems(Collection $items, array $filters): Collection
    {
        $duplicateCandidates = $this->applyContactFilters($items, $filters)
            ->map(function ($item) {
                $item->duplicate_type = $item->contact_type === 'client' ? 'client' : 'lead';
                $item->duplicate_name = $item->contact_type === 'client' ? $item->name : $item->client_name;
                $item->duplicate_phone = $this->duplicatePhoneValue([$item->mobile, $item->phone]);
                $item->duplicate_source_label = $item->contact_type === 'client'
                    ? 'Client'
                    : 'Contact';

                return $item;
            });

        return $duplicateCandidates
            ->filter(fn ($item) => filled($item->duplicate_phone))
            ->groupBy(fn ($item) => $item->duplicate_phone)
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group, string $phone) {
                $sortedRecords = $group
                    ->sortBy([
                        fn ($item) => $item->duplicate_type === 'client' ? 0 : 1,
                        fn ($item) => $item->duplicate_name ?? '',
                    ])
                    ->values();

                return (object) [
                    'phone' => $phone,
                    'records' => $sortedRecords,
                    'count' => $sortedRecords->count(),
                ];
            })
            ->values()
            ->sortByDesc('count')
            ->values();
    }

    private function duplicatePhoneValue(array $values): ?string
    {
        foreach ($values as $value) {
            if (! filled($value)) {
                continue;
            }

            $trimmed = trim((string) $value);
            $digits = preg_replace('/\D+/', '', $trimmed);

            if ($digits !== null && $digits !== '') {
                return $digits;
            }

            if ($trimmed !== '') {
                return strtolower($trimmed);
            }
        }

        return null;
    }

    private function leadMatchesExistingClient(Lead $lead, Collection $clients): bool
    {
        $leadName = mb_strtolower(trim((string) ($lead->client_name ?? '')));
        $leadEmail = filled($lead->email) ? mb_strtolower(trim((string) $lead->email)) : null;
        $leadMobile = $this->duplicatePhoneValue([$lead->mobile, $lead->phone]);

        return $clients->contains(function (Client $client) use ($leadName, $leadEmail, $leadMobile) {
            $clientName = mb_strtolower(trim((string) ($client->name ?? '')));
            $clientEmail = filled($client->email) ? mb_strtolower(trim((string) $client->email)) : null;
            $clientMobile = $this->duplicatePhoneValue([$client->mobile, $client->phone]);

            if ($leadMobile && $clientMobile && $leadMobile === $clientMobile) {
                return true;
            }

            if ($leadEmail && $clientEmail && $leadEmail === $clientEmail) {
                return true;
            }

            return $leadName !== '' && $clientName !== '' && $leadName === $clientName;
        });
    }

    private function isEmailConflictException(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'email')
            && (str_contains($message, 'duplicate') || str_contains($message, 'already been taken') || str_contains($message, 'unique'));
    }

    private function clientSupportTickets(Client $client): Collection
    {
        $name = trim((string) $client->name);
        $email = filled($client->email) ? Str::lower(trim((string) $client->email)) : null;
        $phones = collect([$client->mobile, $client->phone])
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $address = $this->normalizeClientAddressForMatch($client->address);

        return Ticket::query()
            ->where('company_id', (int) $client->company_id)
            ->whereIn('type', ['complain', 'support'])
            ->where(function ($query) use ($client, $name, $email, $phones, $address) {
                $hasCondition = false;

                if ($email) {
                    $query->whereRaw('LOWER(customer_email) = ?', [$email]);
                    $hasCondition = true;
                }

                foreach ($phones as $phone) {
                    if ($hasCondition) {
                        $query->orWhere('customer_contact_number', 'like', '%' . $phone . '%');
                    } else {
                        $query->where('customer_contact_number', 'like', '%' . $phone . '%');
                        $hasCondition = true;
                    }
                }

                if ($address) {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                    } else {
                        $query->whereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                        $hasCondition = true;
                    }
                }

                if ($name !== '') {
                    if ($hasCondition) {
                        $query->where('customer_name', $name);
                    } else {
                        $query->where('customer_name', $name);
                        $hasCondition = true;
                    }
                }

                $query->orWhereHas('generatedFromAmc', function ($amcQuery) use ($client, $name) {
                    $amcQuery->where('client_id', (int) $client->id);

                    if ($name !== '') {
                        $amcQuery->orWhere('customer_name', $name);
                    }
                });

                $query->orWhereHas('amc', function ($amcQuery) use ($client, $name) {
                    $amcQuery->where('client_id', (int) $client->id);

                    if ($name !== '') {
                        $amcQuery->orWhere('customer_name', $name);
                    }
                });

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->with([
                'assignedUser:id,name',
                'generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
                'amc:id,contract_no,project_name,customer_name,client_id,project_id,support_ticket_id',
            ])
            ->latest()
            ->get();
    }

    private function normalizeClientAddressForMatch(?string $address): ?string
    {
        $value = trim((string) $address);

        if ($value === '') {
            return null;
        }

        return Str::lower((string) preg_replace('/\s+/', ' ', $value));
    }
}
