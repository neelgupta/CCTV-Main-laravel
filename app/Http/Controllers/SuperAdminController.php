<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\LeadStatus;
use App\Models\Permission;
use App\Models\ProjectStage;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Expense;
use App\Models\Income;
use App\Models\State;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\CompanyAdminSeatService;
use App\Services\CompanyAppUsageService;
use App\Services\RazorpayPlanSyncService;
use App\Services\RazorpayService;
use App\Services\SubscriptionLimitService;
use App\Support\SubscriptionInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SuperAdminController extends Controller
{
    private const MAX_BULK_INVOICES = 300;

    public function dashboard()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $companies = Company::query()->with(['users', 'activeSubscription.plan'])->latest()->get();
        $plans = SubscriptionPlan::query()->latest()->get();
        $subscriptions = CompanySubscription::query()->with(['company', 'plan'])->latest()->get();
        $appUsageService = app(CompanyAppUsageService::class);
        $today = now()->startOfDay();

        $recentPayments = $subscriptions
            ->where('amount_paid', '>', 0)
            ->take(6)
            ->values();

        $todaySchedule = collect()
            ->merge(
                $companies
                    ->filter(fn (Company $company) => optional($company->created_at)?->isSameDay($today))
                    ->take(5)
                    ->map(function (Company $company) {
                        return [
                            'title' => $company->name,
                            'meta' => 'New company registration',
                            'time' => optional($company->created_at)?->format('g:i A') ?: 'Today',
                            'color' => '#2563eb',
                            'url' => route('super-admin.companies'),
                        ];
                    })
            )
            ->merge(
                $subscriptions
                    ->filter(fn (CompanySubscription $subscription) => optional($subscription->starts_at)?->isSameDay($today))
                    ->take(5)
                    ->map(function (CompanySubscription $subscription) {
                        return [
                            'title' => $subscription->company?->name ?? 'Unknown Company',
                            'meta' => ($subscription->plan?->name ?? 'Subscription') . ' started',
                            'time' => 'Starts today',
                            'color' => '#059669',
                            'url' => route('super-admin.subscriptions'),
                        ];
                    })
            )
            ->values();

        return view('super-admin.dashboard', [
            'stats' => [
                'companies' => $companies->count(),
                'activeCompanies' => $companies->where('is_active', true)->count(),
                'plans' => $plans->count(),
                'activeSubscriptions' => $subscriptions->where('status', 'active')->count(),
                'payments' => $subscriptions->where('amount_paid', '>', 0)->count(),
                'trialSubscriptions' => $subscriptions->where('status', 'trialing')->count(),
                'monthlyRevenue' => (float) $subscriptions
                    ->filter(fn (CompanySubscription $subscription) => optional($subscription->created_at)?->format('Y-m') === now()->format('Y-m'))
                    ->sum('amount_paid'),
            ],
            'recentCompanies' => $companies->take(6),
            'recentSubscriptions' => $subscriptions->take(6),
            'recentPayments' => $recentPayments,
            'todaySchedule' => $todaySchedule,
            'companyAppUsageStats' => $appUsageService->dashboardSummary(),
            'companyAppUsageHighlights' => $appUsageService->companyQuery([
                'app_usage_status' => 'using_app',
                'sort' => 'last_active_desc',
            ])
                ->orderByDesc('last_app_activity')
                ->limit(5)
                ->get(),
        ]);
    }

    public function locations()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('super-admin.locations', [
            'states' => State::query()->withCount('cities')->orderBy('name')->paginate(10)->withQueryString(),
            'stateOptions' => State::query()->orderBy('name')->get(),
            'cities' => City::query()->with('state')->latest()->paginate(10)->withQueryString(),
            'activeCityOptions' => City::query()
                ->select('id', 'name', 'state_id')
                ->orderBy('name')
                ->get(),
            'deletedCities' => City::onlyTrashed()->with('state')->latest('deleted_at')->take(8)->get(),
        ]);
    }

    public function storeState(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                    function ($attribute, $value, $fail) {
                        $exists = State::query()
                            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $value))])
                            ->exists();

                        if ($exists) {
                            $fail('State already exists.');
                        }
                    },
                ],
            ],
            [
                'name.required' => 'Please enter a state name.',
                'name.min' => 'State name must be at least 2 characters.',
                'name.max' => 'State name may not be greater than 100 characters.',
            ]
        )->validateWithBag('stateForm');

        State::create([
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'State added successfully.');
    }

    public function storeCity(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $existingCity = null;

        $validated = Validator::make(
            $request->all(),
            [
                'state_id' => ['required', 'exists:states,id'],
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                    function ($attribute, $value, $fail) use ($request, &$existingCity) {
                        $existingCity = City::withTrashed()
                            ->where('state_id', $request->integer('state_id'))
                            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $value))])
                            ->first();

                        if ($existingCity && $existingCity->deleted_at === null) {
                            $fail('City already exists for the selected state.');
                        }
                    },
                ],
            ],
            [
                'state_id.required' => 'Please select a state.',
                'state_id.exists' => 'Selected state is invalid.',
                'name.required' => 'Please enter a city name.',
                'name.min' => 'City name must be at least 2 characters.',
                'name.max' => 'City name may not be greater than 100 characters.',
            ]
        )->validateWithBag('cityForm');

        if ($existingCity && $existingCity->deleted_at !== null) {
            $existingCity->restore();
            $existingCity->update([
                'state_id' => $validated['state_id'],
                'name' => trim($validated['name']),
            ]);

            return back()->with('success', 'Deleted city restored successfully.');
        }

        City::create([
            'state_id' => $validated['state_id'],
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'City added successfully.');
    }

    public function updateState(Request $request, State $state)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'name' => [
                    'required',
                    'string',
                    'min:2',
                    'max:100',
                    function ($attribute, $value, $fail) use ($state) {
                        $exists = State::query()
                            ->where('id', '!=', $state->id)
                            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $value))])
                            ->exists();

                        if ($exists) {
                            $fail('State already exists.');
                        }
                    },
                ],
                'editing_state_id' => ['nullable', 'integer'],
            ],
            [
                'name.required' => 'Please enter a state name.',
                'name.min' => 'State name must be at least 2 characters.',
                'name.max' => 'State name may not be greater than 100 characters.',
            ]
        )->validateWithBag('stateUpdateForm');

        $state->update([
            'name' => trim($validated['name']),
        ]);

        return back()->with('success', 'State updated successfully.');
    }

    public function destroyCity(City $city)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $city->delete();

        return back()->with('success', 'City deleted successfully.');
    }

    public function subscriptions(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'company_id' => $request->filled('company_id') ? $request->integer('company_id') : null,
            'subscription_plan_id' => $request->filled('subscription_plan_id') ? $request->integer('subscription_plan_id') : null,
            'status' => $request->string('status')->trim()->toString() ?: null,
            'sort' => $request->string('sort')->trim()->toString() ?: 'latest',
        ];

        $perPage = (int) $request->query('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50, 100], true) ? $perPage : 10;

        $subscriptionsQuery = CompanySubscription::query()
            ->with(['company', 'plan'])
            ->when(filled($filters['search'] ?? null), function ($query) use ($filters): void {
                $search = trim((string) $filters['search']);
                $query->where(function ($builder) use ($search): void {
                    $builder->whereHas('company', function ($companyQuery) use ($search): void {
                        $companyQuery->where('name', 'like', '%' . $search . '%')
                            ->orWhere('company_code', 'like', '%' . $search . '%');
                    })->orWhereHas('plan', function ($planQuery) use ($search): void {
                        $planQuery->where('name', 'like', '%' . $search . '%');
                    })->orWhere('status', 'like', '%' . $search . '%');
                });
            })
            ->when($filters['company_id'], fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->when($filters['subscription_plan_id'], fn ($query, $planId) => $query->where('subscription_plan_id', $planId))
            ->when(filled($filters['status'] ?? null), fn ($query) => $query->where('status', $filters['status']))
            ->when(($filters['sort'] ?? 'latest') === 'oldest', fn ($query) => $query->oldest(), fn ($query) => $query->latest());

        $activePlans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->latest()
            ->get();

        return view('super-admin.subscriptions', [
            'plans' => SubscriptionPlan::query()->withCount('companySubscriptions')->latest()->get(),
            'activePlans' => $activePlans,
            'companies' => Company::query()->orderBy('name')->get(),
            'companySubscriptions' => $subscriptionsQuery->paginate($perPage)->withQueryString(),
            'filters' => $filters,
            'perPageOptions' => [10, 25, 50, 100],
            'razorpayMode' => app(RazorpayService::class)->mode(),
            'gstRate' => (float) setting('subscription_gst_percentage', 18),
        ]);
    }

    public function storePlan(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|min:2|max:191',
                'monthly_price' => 'required|numeric|min:0|max:999999.99',
                'yearly_price' => 'required|numeric|min:0|max:999999.99',
                'is_active' => 'nullable|boolean',
                'is_popular' => 'nullable|boolean',
                'has_free_trial' => 'nullable|boolean',
                'disable_staff_tracking' => 'nullable|boolean',
            ] + $this->razorpayPlanLinkRules(),
            [
                'name.required' => 'Please enter a plan name.',
                'name.min' => 'Plan name must be at least 2 characters.',
                'name.max' => 'Plan name may not be greater than 191 characters.',
                'monthly_price.required' => 'Please enter a monthly price.',
                'yearly_price.required' => 'Please enter a yearly price.',
            ] + $this->razorpayPlanLinkMessages()
        )->after(fn ($validator) => $this->validateRazorpayPlanLinks($validator, $request))
            ->validateWithBag('planForm');

        $baseSlug = Str::slug($validated['name']);
        if ($baseSlug === '') {
            $baseSlug = 'plan';
        }
        $slug = Str::limit($baseSlug, 186, '') . '-' . Str::lower(Str::random(4));

        $plan = SubscriptionPlan::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'price' => $validated['monthly_price'],
            'monthly_price' => $validated['monthly_price'],
            'yearly_price' => $validated['yearly_price'],
            'billing_cycle' => 'monthly',
            'description' => null,
            'is_active' => $request->boolean('is_active'),
            'is_popular' => $request->boolean('is_popular'),
            'has_free_trial' => $request->boolean('has_free_trial'),
            'disable_staff_tracking' => $request->boolean('disable_staff_tracking'),
        ]);

        $notices = $this->linkRazorpayPlans($plan, $request);

        return back()
            ->with('success', 'Subscription plan created successfully.')
            ->with('warning', $notices !== [] ? implode(' ', $notices) : null);
    }

    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|min:2|max:191',
                'monthly_price' => 'required|numeric|min:0|max:999999.99',
                'yearly_price' => 'required|numeric|min:0|max:999999.99',
                'is_active' => 'nullable|boolean',
                'is_popular' => 'nullable|boolean',
                'has_free_trial' => 'nullable|boolean',
                'disable_staff_tracking' => 'nullable|boolean',
            ] + $this->razorpayPlanLinkRules(),
            [
                'name.required' => 'Please enter a plan name.',
                'name.min' => 'Plan name must be at least 2 characters.',
                'name.max' => 'Plan name may not be greater than 191 characters.',
                'monthly_price.required' => 'Please enter a monthly price.',
                'yearly_price.required' => 'Please enter a yearly price.',
            ] + $this->razorpayPlanLinkMessages()
        )->after(fn ($validator) => $this->validateRazorpayPlanLinks($validator, $request, $plan))
            ->validateWithBag('planEditForm');

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $plan->is_active;

        $plan->update([
            'name' => $validated['name'],
            'price' => $validated['monthly_price'],
            'monthly_price' => $validated['monthly_price'],
            'yearly_price' => $validated['yearly_price'],
            'is_active' => $isActive,
            'is_popular' => $request->boolean('is_popular'),
            'has_free_trial' => $request->boolean('has_free_trial'),
            'disable_staff_tracking' => $request->boolean('disable_staff_tracking'),
        ]);

        $notices = $this->linkRazorpayPlans($plan, $request);

        return back()
            ->with('success', 'Subscription plan updated successfully.')
            ->with('warning', $notices !== [] ? implode(' ', $notices) : null);
    }

    private function razorpayPlanLinkRules(): array
    {
        return [
            'razorpay_plan_id_monthly' => ['nullable', 'string', 'max:100', 'regex:/^plan_[A-Za-z0-9]+$/'],
            'razorpay_plan_id_yearly' => ['nullable', 'string', 'max:100', 'regex:/^plan_[A-Za-z0-9]+$/'],
        ];
    }

    private function razorpayPlanLinkMessages(): array
    {
        return [
            'razorpay_plan_id_monthly.regex' => 'Razorpay plan IDs look like plan_XXXXXXXXXXXXXX.',
            'razorpay_plan_id_yearly.regex' => 'Razorpay plan IDs look like plan_XXXXXXXXXXXXXX.',
        ];
    }

    /**
     * Checks newly pasted Razorpay plan ids against the submitted prices (period, currency and
     * amount incl. GST). An id that is already linked is not re-checked here: if it no longer
     * matches the price, linkRazorpayPlans() unlinks it instead of blocking the price change.
     */
    private function validateRazorpayPlanLinks($validator, Request $request, ?SubscriptionPlan $plan = null): void
    {
        $planSync = app(RazorpayPlanSyncService::class);

        foreach (RazorpayPlanSyncService::CYCLES as $billingCycle) {
            $field = 'razorpay_plan_id_' . $billingCycle;
            $priceField = $billingCycle . '_price';
            $razorpayPlanId = trim((string) $request->input($field));

            if ($razorpayPlanId === '' || $validator->errors()->has($field) || $validator->errors()->has($priceField)) {
                continue;
            }

            if ($plan && $this->linkedRazorpayPlan($plan, $billingCycle, $razorpayPlanId)) {
                continue;
            }

            $error = $planSync->validateManualPlan($razorpayPlanId, $billingCycle, (float) $request->input($priceField));

            if ($error !== null) {
                $validator->errors()->add($field, $error);
            }
        }
    }

    /**
     * Saves the Razorpay plan links and returns notices for links removed because they no
     * longer match the plan price. Only cycles whose field was submitted are touched, so the
     * quick Activate/Deactivate form (which does not send these fields) never changes a link.
     *
     * @return array<int, string>
     */
    private function linkRazorpayPlans(SubscriptionPlan $plan, Request $request): array
    {
        $planSync = app(RazorpayPlanSyncService::class);
        $plan = $plan->fresh();
        $planIdsByCycle = [];
        $notices = [];

        foreach (RazorpayPlanSyncService::CYCLES as $billingCycle) {
            $field = 'razorpay_plan_id_' . $billingCycle;

            if (! $request->has($field)) {
                continue;
            }

            $razorpayPlanId = trim((string) $request->input($field));
            $linked = $razorpayPlanId !== '' ? $this->linkedRazorpayPlan($plan, $billingCycle, $razorpayPlanId) : null;
            $expectedPaise = $planSync->amountPaiseFor($plan, $billingCycle);

            if ($linked && (int) ($linked['amount'] ?? 0) !== $expectedPaise) {
                $razorpayPlanId = '';
                $notices[] = sprintf(
                    '%s Razorpay plan %s was unlinked: it charges Rs. %s per license, but %s now costs Rs. %s including GST. A matching plan will be created automatically at the next auto-renew purchase, or you can link a new one.',
                    ucfirst($billingCycle),
                    $linked['id'],
                    number_format(((int) ($linked['amount'] ?? 0)) / 100, 2),
                    $plan->name,
                    number_format($expectedPaise / 100, 2)
                );
            }

            $planIdsByCycle[$billingCycle] = $razorpayPlanId;
        }

        if ($planIdsByCycle !== []) {
            $planSync->linkManualPlans($plan, $planIdsByCycle);
        }

        return $notices;
    }

    /**
     * The stored manual link for this cycle in the current mode, when it is the given id.
     */
    private function linkedRazorpayPlan(SubscriptionPlan $plan, string $billingCycle, string $razorpayPlanId): ?array
    {
        $stored = $plan->razorpayPlanFor(app(RazorpayService::class)->mode(), $billingCycle);

        return $stored && ($stored['source'] ?? null) === RazorpayPlanSyncService::SOURCE_MANUAL && $stored['id'] === $razorpayPlanId
            ? $stored
            : null;
    }

    public function destroyPlan(SubscriptionPlan $plan)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        if ($plan->companySubscriptions()->exists()) {
            return back()->with('error', 'This plan is currently assigned to one or more companies. Remove those subscriptions or mark the plan inactive before deleting it.');
        }

        $plan->delete();

        return back()->with('success', 'Subscription plan deleted successfully.');
    }

    public function assignSubscription(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $today = now()->startOfDay()->toDateString();

        $validated = Validator::make(
            $request->all(),
            [
                'company_id' => ['required', 'exists:companies,id'],
                'subscription_plan_id' => [
                    'required',
                    Rule::exists('subscription_plans', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
                'status' => ['required', Rule::in(['active', 'trialing', 'pending', 'expired', 'cancelled'])],
                'user_count' => ['required', 'integer', 'min:1', 'max:100000'],
                'amount_paid' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'starts_at' => ['required', 'date', 'after_or_equal:' . $today],
                'trial_ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'payment_method' => ['nullable', 'string', 'min:2', 'max:50'],
                'billing_reference' => ['nullable', 'string', 'min:2', 'max:191'],
                'notes' => ['nullable', 'string', 'min:2', 'max:2000'],
            ],
            [
                'company_id.required' => 'Please select a company.',
                'subscription_plan_id.required' => 'Please select a plan.',
                'status.required' => 'Please select a status.',
                'user_count.required' => 'Please enter the number of users.',
                'user_count.min' => 'User count must be at least 1.',
                'amount_paid.required' => 'Please enter the amount paid.',
                'starts_at.required' => 'Please select a start date.',
                'starts_at.after_or_equal' => 'Start date must be today or a future date.',
                'trial_ends_at.after_or_equal' => 'Trial end date must be the same as or after the start date.',
                'ends_at.after_or_equal' => 'End date must be the same as or after the start date.',
                'payment_method.min' => 'Payment method must be at least 2 characters.',
                'payment_method.max' => 'Payment method may not be greater than 50 characters.',
                'billing_reference.min' => 'Billing reference must be at least 2 characters.',
                'billing_reference.max' => 'Billing reference may not be greater than 191 characters.',
                'notes.min' => 'Notes must be at least 2 characters.',
                'notes.max' => 'Notes may not be greater than 2000 characters.',
            ]
        )->validateWithBag('subscriptionForm');

        $selectedPlan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->findOrFail($validated['subscription_plan_id']);

        $userCount = (int) $validated['user_count'];
        $validated['amount_paid'] = round((float) $selectedPlan->price * $userCount, 2);
        $usageBaseline = app(SubscriptionLimitService::class)->snapshotUsageBaseline((int) $validated['company_id']);
        $licensePackage = CompanySubscription::generateLicensePackage($userCount);

        $subscription = CompanySubscription::create([
            'company_id' => $validated['company_id'],
            'subscription_plan_id' => $validated['subscription_plan_id'],
            'status' => $validated['status'],
            'is_trial' => $validated['status'] === 'trialing',
            'starts_at' => $validated['starts_at'],
            'trial_ends_at' => $validated['trial_ends_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'amount_paid' => $validated['amount_paid'],
            'payment_method' => $validated['payment_method'] ?: null,
            'billing_reference' => $validated['billing_reference'] ?: null,
            'subscriber_email' => Company::query()->whereKey($validated['company_id'])->value('email'),
            'license_key' => $licensePackage['license_key'],
            'license_count' => $licensePackage['license_count'],
            'license_keys' => $licensePackage['license_keys'],
            'notes' => $validated['notes'] ?: null,
            'auto_renew' => false,
            'razorpay_order_id' => $request->filled('billing_reference') ? 'order_' . Str::lower(Str::random(10)) : null,
            'razorpay_payment_id' => ($validated['amount_paid'] ?? 0) > 0 ? 'pay_' . Str::lower(Str::random(10)) : null,
            'usage_baseline' => $usageBaseline,
        ]);

        app(CompanyAdminSeatService::class)->assignSeatIfNeeded((int) $validated['company_id'], $subscription);

        return back()->with('success', 'Company subscription saved successfully.');
    }

    public function companies(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return $this->companyDirectory($request, true);
    }

    public function createCompany()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $states = State::query()->orderBy('name')->get();
        $cities = City::query()->orderBy('name')->get();
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('name')->get();
        $razorpayService = app(RazorpayService::class);
        $gstRate = (float) setting('subscription_gst_percentage', 18);

        return view('super-admin.companies-create', [
            'states' => $states,
            'cities' => $cities,
            'plans' => $plans,
            'razorpayKey' => $razorpayService->keyId(),
            'razorpayEnabled' => $razorpayService->enabled(),
            'gstRate' => $gstRate,
        ]);
    }

    public function createPaymentOrder(Request $request, RazorpayService $razorpayService)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone_number' => ['required', 'string', 'max:20'],
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'amount_paid' => ['required', 'numeric', 'min:0'],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $amount = (float) $validated['amount_paid'];
        $reference = 'SUB-' . strtoupper(Str::random(8));
        $billingCycle = $validated['billing_cycle'];
        $companyName = trim((string) $validated['company_name']);
        $gstRate = (float) setting('subscription_gst_percentage', 18);

        $order = $this->buildRazorpayOrderPayload($razorpayService, $amount, $reference, $companyName, $plan->name, $billingCycle, $gstRate);

        return response()->json([
            'order_id' => $order['id'],
            'amount' => $order['amount'],
            'currency' => $order['currency'] ?? 'INR',
            'key' => $razorpayService->keyId(),
            'reference' => $reference,
            'demo' => $order['demo'] ?? false,
            'company_name' => $companyName,
            'plan_name' => $plan->name,
            'gst_rate' => $gstRate,
        ]);
    }

    private function buildRazorpayOrderPayload(RazorpayService $razorpayService, float $amount, string $reference, string $companyName, string $planName, string $billingCycle, float $gstRate = 0.0): array
    {
        if (! $razorpayService->enabled()) {
            return [
                'id' => 'order_' . Str::lower(Str::random(10)),
                'amount' => (int) round($amount * 100),
                'currency' => 'INR',
                'receipt' => $reference,
                'demo' => true,
            ];
        }

        try {
            return $razorpayService->createOrder(
                (int) round($amount * 100),
                $reference,
                [
                    'company_name' => $companyName,
                    'plan_name' => $planName,
                    'billing_cycle' => $billingCycle,
                    'gst_rate' => $gstRate . '%',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Razorpay order creation failed, falling back to demo mode.', [
                'error' => $e->getMessage(),
                'reference' => $reference,
            ]);

            return [
                'id' => 'order_' . Str::lower(Str::random(10)),
                'amount' => (int) round($amount * 100),
                'currency' => 'INR',
                'receipt' => $reference,
                'demo' => true,
            ];
        }
    }

    public function storeCompany(Request $request, RazorpayService $razorpayService)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $enableSubscription = $request->boolean('enable_subscription');

        $input = $request->all();
        if (isset($input['phone_number'])) {
            $input['phone_number'] = preg_replace('/\D+/', '', (string) $input['phone_number']);
        }
        if (isset($input['email'])) {
            $input['email'] = Str::lower(trim((string) $input['email']));
        }
        if (isset($input['company_name'])) {
            $input['company_name'] = trim((string) $input['company_name']);
        }

        if (($input['payment_method'] ?? '') === 'Other' && filled($input['payment_method_custom'] ?? null)) {
            $input['payment_method'] = trim((string) $input['payment_method_custom']);
        }

        $rules = [
            'company_name' => [
                'required',
                'string',
                'min:2',
                'max:191',
                'regex:/^(?=.*[A-Za-z])[A-Za-z0-9&.,()\'\\-\\s]+$/',
            ],
            'admin_name' => ['nullable', 'string', 'min:2', 'max:191'],
            'email' => [
                'required',
                'string',
                'email',
                'lowercase',
                'max:191',
                Rule::unique('companies', 'email'),
                function ($attribute, $value, $fail) {
                    $normalizedEmail = Str::lower(trim((string) $value));
                    $existingUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                        ->first();

                    if ($existingUser && ! $existingUser->isClient()) {
                        $fail('This company email is already registered. Please sign in to your existing account or contact support.');
                    }
                },
            ],
            'phone_number' => [
                'required',
                'digits:10',
                Rule::unique('companies', 'mobile_number'),
                Rule::unique('companies', 'phone_number'),
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'business_type' => ['nullable', 'string', 'max:50'],
            'enable_subscription' => ['nullable', 'boolean'],
            'razorpay_order_id' => ['nullable', 'string', 'max:191'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:191'],
            'razorpay_signature' => ['nullable', 'string', 'max:255'],
        ];

        if ($enableSubscription) {
            $rules = array_merge($rules, [
                'subscription_plan_id' => [
                    'required',
                    Rule::exists('subscription_plans', 'id')->where(fn ($query) => $query->where('is_active', true)),
                ],
                'billing_cycle' => ['required', Rule::in(['monthly', 'yearly'])],
                'subscription_status' => ['required', Rule::in(['active', 'trialing', 'pending', 'expired', 'cancelled'])],
                'user_count' => ['required', 'integer', 'min:1', 'max:100000'],
                'amount_paid' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
                'payment_method' => ['nullable', 'string', 'max:50'],
                'billing_reference' => ['nullable', 'string', 'max:191'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]);
        }

        $messages = [
            'company_name.regex' => 'The company name must contain at least one letter and may only include letters, numbers, spaces, and basic punctuation.',
            'email.unique' => 'This company email is already registered. Please sign in to your existing account or contact support.',
            'phone_number.unique' => 'This company phone number is already registered. Please sign in to your existing account or contact support.',
            'phone_number.digits' => 'The phone number must be exactly 10 digits.',
            'subscription_plan_id.required' => 'Please select a subscription plan.',
            'subscription_status.required' => 'Please select a subscription status.',
            'user_count.required' => 'Please enter the number of users/seats.',
            'amount_paid.required' => 'Please enter the amount paid.',
            'starts_at.required' => 'Please specify a start date.',
            'ends_at.after_or_equal' => 'End date must be on or after start date.',
        ];

        $validated = Validator::make($input, $rules, $messages)->validate();

        try {
            DB::transaction(function () use ($validated, $enableSubscription, $request, $razorpayService) {
                $cleanedPhone = preg_replace('/\D+/', '', (string) $validated['phone_number']);
                $cleanedEmail = Str::lower(trim((string) $validated['email']));
                $companyName = trim((string) $validated['company_name']);

                $company = Company::create([
                    'name' => $companyName,
                    'email' => $cleanedEmail,
                    'mobile_number' => $cleanedPhone,
                    'phone_number' => $cleanedPhone,
                    'address' => filled($validated['address'] ?? null) ? trim((string) $validated['address']) : null,
                    'city' => filled($validated['city'] ?? null) ? trim((string) $validated['city']) : null,
                    'state' => filled($validated['state'] ?? null) ? trim((string) $validated['state']) : null,
                    'business_type' => filled($validated['business_type'] ?? null) ? trim((string) $validated['business_type']) : 'CCTV',
                    'is_active' => true,
                    'is_onboarded' => true,
                    'onboarding_step' => 4,
                    'allow_growth_staff_tracking' => false,
                    'allow_appointment_module' => false,
                    'allow_create_company_from_lead' => false,
                ]);

                $companyAdminRole = Role::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => 'company_admin'],
                    ['name' => 'Company Admin']
                );

                $employeeRole = Role::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => 'employee'],
                    ['name' => 'Employee']
                );

                if ($companyAdminRole->permissions()->count() === 0) {
                    $companyAdminRole->permissions()->sync(Permission::query()->pluck('id')->all());
                }

                if ($employeeRole->permissions()->count() === 0) {
                    $employeePermissionKeys = [
                        'dashboard.view',
                        'leads.view', 'leads.create', 'leads.edit', 'leads.status.update', 'leads.comment',
                        'leads.quotation.manage', 'leads.survey.upload', 'leads.documents.upload', 'leads.summary.download',
                        'projects.view', 'projects.create', 'projects.edit', 'projects.stage.update', 'projects.assets.manage', 'projects.comment',
                        'support.view', 'support.create', 'support.edit',
                        'service.view',
                        'subscription.view', 'integrations.view', 'clients.view', 'amc.view', 'gallery.view',
                    ];
                    $employeeRole->permissions()->sync(
                        Permission::query()->whereIn('key', $employeePermissionKeys)->pluck('id')->all()
                    );
                }

                $adminName = filled($validated['admin_name'] ?? null)
                    ? trim((string) $validated['admin_name'])
                    : $companyName . ' Admin';

                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$cleanedEmail])
                    ->first();

                if ($existingUser) {
                    $existingUser->update([
                        'company_id' => $company->id,
                        'name' => $adminName,
                        'role' => 'company_admin',
                        'role_id' => $companyAdminRole->id,
                        'password' => Hash::make($validated['password']),
                        'is_active' => true,
                        'email_verified_at' => $existingUser->email_verified_at ?? now(),
                    ]);
                    $user = $existingUser;
                } else {
                    $user = User::create([
                        'company_id' => $company->id,
                        'name' => $adminName,
                        'email' => $cleanedEmail,
                        'email_verified_at' => now(),
                        'password' => Hash::make($validated['password']),
                        'role' => 'company_admin',
                        'role_id' => $companyAdminRole->id,
                        'is_active' => true,
                    ]);
                }

                Staff::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                    ],
                    [
                        'employee_id' => Staff::generateEmployeeId($company->id),
                        'name' => $user->name,
                        'contact_number' => $cleanedPhone,
                        'join_date' => now()->toDateString(),
                        'role_id' => $companyAdminRole->id,
                    ]
                );

                // Default pipeline stages
                LeadStatus::ensureNewLead($company->id);
                LeadStatus::ensureProposalSent($company->id);
                LeadStatus::ensureWon($company->id);
                LeadStatus::ensureLoss($company->id);
                LeadStatus::normalizeSystemStages($company->id);

                ProjectStage::ensureDefaults($company->id);

                if ($enableSubscription) {
                    $userCount = max(1, (int) $validated['user_count']);
                    $licensePackage = CompanySubscription::generateLicensePackage($userCount);
                    $usageBaseline = app(SubscriptionLimitService::class)->snapshotUsageBaseline($company->id);

                    $status = $validated['subscription_status'];
                    $isTrial = $status === 'trialing';
                    $amountPaid = round((float) $validated['amount_paid'], 2);

                    $razorpayPaymentId = $request->input('razorpay_payment_id');
                    $razorpayOrderId = $request->input('razorpay_order_id');
                    $razorpaySignature = $request->input('razorpay_signature');

                    if ($amountPaid > 0 && filled($razorpayPaymentId) && $razorpayService->enabled() && ($razorpaySignature !== 'demo-signature') && filled($razorpaySignature) && filled($razorpayOrderId)) {
                        $isValid = $razorpayService->verifySignature((string) $razorpayOrderId, (string) $razorpayPaymentId, (string) $razorpaySignature);
                        if (! $isValid) {
                            throw new \RuntimeException('Payment signature verification failed. Please try again.');
                        }
                    }

                    $paymentMethod = filled($razorpayPaymentId)
                        ? 'Razorpay'
                        : (filled($validated['payment_method'] ?? null)
                            ? trim((string) $validated['payment_method'])
                            : ($isTrial ? 'Free Trial' : 'Manual'));

                    if (filled($razorpayPaymentId)) {
                        $status = 'active';
                        $isTrial = false;
                    }

                    $billingReference = filled($validated['billing_reference'] ?? null)
                        ? trim((string) $validated['billing_reference'])
                        : ($razorpayPaymentId ?: ('SUB-' . strtoupper(Str::random(8))));
                    $startsAt = $validated['starts_at'];
                    $endsAt = filled($validated['ends_at'] ?? null) ? $validated['ends_at'] : null;

                    $subscription = CompanySubscription::create([
                        'company_id' => $company->id,
                        'subscription_plan_id' => $validated['subscription_plan_id'],
                        'billing_cycle' => $validated['billing_cycle'],
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'trial_ends_at' => $isTrial ? $endsAt : null,
                        'status' => $status,
                        'is_trial' => $isTrial,
                        'auto_renew' => false,
                        'amount_paid' => $amountPaid,
                        'payment_method' => $paymentMethod,
                        'billing_reference' => $billingReference,
                        'subscriber_email' => $company->email,
                        'license_key' => $licensePackage['license_key'],
                        'license_count' => $licensePackage['license_count'],
                        'license_keys' => $licensePackage['license_keys'],
                        'notes' => filled($validated['notes'] ?? null) ? trim((string) $validated['notes']) : null,
                        'razorpay_order_id' => $razorpayOrderId ?: (filled($billingReference) ? 'order_' . Str::lower(Str::random(10)) : null),
                        'razorpay_payment_id' => $razorpayPaymentId ?: ($amountPaid > 0 ? 'pay_' . Str::lower(Str::random(10)) : null),
                        'usage_baseline' => $usageBaseline,
                    ]);

                    app(CompanyAdminSeatService::class)->assignSeatIfNeeded($company->id, $subscription, $user);
                }

                try {
                    event(new Registered($user));
                } catch (\Throwable $e) {
                    Log::warning('Registration email event failed: ' . $e->getMessage());
                }
            });
        } catch (\RuntimeException $re) {
            return back()
                ->withErrors(['amount_paid' => $re->getMessage()])
                ->withInput();
        } catch (\Illuminate\Database\QueryException $exception) {
            return back()
                ->withErrors(['email' => 'Failed to register company. A duplicate record was encountered.'])
                ->withInput();
        }

        return redirect()
            ->route('super-admin.companies')
            ->with('success', 'Company "' . $validated['company_name'] . '" registered successfully with administrator and payment options configured.');
    }

    public function inactiveCompanies(Request $request)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return $this->companyDirectory($request, false);
    }

    private function companyDirectory(Request $request, bool $active)
    {
        $routeName = $active ? 'super-admin.companies' : 'super-admin.companies.inactive';

        if ($request->filled('delete')) {
            $companyId = (int) $request->query('delete');
            $company = Company::query()->find($companyId);

            if (! $company) {
                return redirect()
                ->route($routeName, $request->except('delete'))
                ->with('error', 'Company not found.');
            }

            try {
                DB::transaction(function () use ($company): void {
                    Income::query()->where('company_id', $company->id)->delete();
                    Expense::query()->where('company_id', $company->id)->delete();
                    $company->delete();
                });
            } catch (\Illuminate\Database\QueryException $e) {
                report($e);

                return redirect()
                    ->route($routeName, $request->except('delete'))
                    ->with('error', 'Company could not be deleted because related records still reference it.');
            }

            return redirect()
                ->route($routeName, $request->except('delete'))
                ->with('success', 'Company deleted successfully.');
        }

        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'plan_id' => $request->input('plan_id'),
            'onboarding_status' => $request->input('onboarding_status'),
            'sort' => $request->input('sort', 'latest'),
        ];

        $perPage = (int) $request->input('per_page', 10);
        $perPageOptions = [10, 25, 50, 100];
        if (! in_array($perPage, $perPageOptions, true)) {
            $perPage = 10;
        }

        $query = Company::query()
            ->where('is_active', $active)
            ->with(['users.roleRecord', 'activeSubscriptions.plan', 'activeSubscription.plan', 'latestSubscription.plan']);

        if ($filters['search'] !== '') {
            $searchTerm = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($searchTerm): void {
                $q->where('name', 'like', $searchTerm)
                    ->orWhere('company_code', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm)
                    ->orWhere('phone_number', 'like', $searchTerm)
                    ->orWhere('mobile_number', 'like', $searchTerm)
                    ->orWhere('city', 'like', $searchTerm)
                    ->orWhere('state', 'like', $searchTerm);
            });
        }

        if (! empty($filters['plan_id'])) {
            $query->whereHas('activeSubscriptions', function ($subQuery) use ($filters): void {
                $subQuery->where('subscription_plan_id', $filters['plan_id']);
            });
        }

        if ($filters['onboarding_status'] !== null && $filters['onboarding_status'] !== '') {
            if ($filters['onboarding_status'] === 'onboarded' || $filters['onboarding_status'] === '1') {
                $query->where('is_onboarded', true);
            } elseif ($filters['onboarding_status'] === 'pending' || $filters['onboarding_status'] === '0') {
                $query->where('is_onboarded', false);
            }
        }

        match ($filters['sort']) {
            'oldest' => $query->orderBy('created_at', 'asc'),
            'name_asc' => $query->orderBy('name', 'asc'),
            'name_desc' => $query->orderBy('name', 'desc'),
            default => $query->orderByDesc('created_at'),
        };

        $companies = $query
            ->paginate($perPage)
            ->withQueryString();

        $plans = SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $subscriptionLimitService = app(SubscriptionLimitService::class);
        $companySubscriptionPlans = $companies->getCollection()->mapWithKeys(function (Company $company) use ($subscriptionLimitService): array {
            return [
                $company->id => $subscriptionLimitService->activeSubscriptionsWithUsage((int) $company->id),
            ];
        });

        return view('super-admin.companies', [
            'companies' => $companies,
            'companySubscriptionPlans' => $companySubscriptionPlans,
            'inactivePage' => ! $active,
            'filters' => $filters,
            'plans' => $plans,
            'perPageOptions' => $perPageOptions,
        ]);
    }

    public function toggleCompanyStatus(Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $company->update([
            'is_active' => ! $company->is_active,
        ]);

        return back()->with('success', 'Company status updated successfully.');
    }

    public function toggleAppointmentModule(Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $company->update([
            'allow_appointment_module' => ! $company->allow_appointment_module,
        ]);

        $statusText = $company->allow_appointment_module ? 'allowed' : 'disabled';

        return back()->with('success', "Appointment module {$statusText} for {$company->name} successfully.");
    }

    public function toggleCreateCompanyFromLead(Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $company->update([
            'allow_create_company_from_lead' => ! $company->allow_create_company_from_lead,
        ]);

        $statusText = $company->allow_create_company_from_lead ? 'allowed' : 'disabled';

        return back()->with('success', "Create company from lead {$statusText} for {$company->name} successfully.");
    }

    public function updateCompanyPassword(Request $request, Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'password_company_id' => ['nullable', 'integer', Rule::in([$company->id])],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
                ],
            ],
            [
                'password.required' => 'Please enter a new password.',
                'password.confirmed' => 'Password confirmation does not match.',
                'password_company_id.in' => 'Selected company is invalid.',
            ]
        )->validateWithBag('companyPasswordForm');

        $targetUser = $company->users()
            ->where(function ($query) {
                $query->whereIn('role', ['company_admin', 'companyadmin'])
                    ->orWhereHas('roleRecord', function ($roleQuery) {
                        $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                    });
            })
            ->first();

        if (! $targetUser) {
            return back()
                ->withInput($request->except(['password', 'password_confirmation']))
                ->with('error', 'Company admin user not found for this company.');
        }

        $targetUser->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => Str::random(60),
        ])->save();

        return back()->with('success', 'Company password updated successfully.');
    }

    public function updateCompanyStorageAddon(Request $request, Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $validated = Validator::make(
            $request->all(),
            [
                'storage_company_id' => ['nullable', 'integer', Rule::in([$company->id])],
                'storage_addon_mb' => ['required', 'integer', 'min:0', 'max:999999'],
            ],
            [
                'storage_addon_mb.required' => 'Please enter extra storage in MB.',
                'storage_addon_mb.integer' => 'Extra storage must be a whole number.',
                'storage_addon_mb.min' => 'Extra storage cannot be negative.',
                'storage_addon_mb.max' => 'Extra storage may not be greater than 999999 MB.',
                'storage_company_id.in' => 'Selected company is invalid.',
            ]
        )->validateWithBag('companyStorageForm');

        $currentAddonMb = (int) ($company->storage_addon_mb ?? 0);
        $company->update([
            'storage_addon_mb' => $currentAddonMb + (int) $validated['storage_addon_mb'],
        ]);

        return redirect()
            ->route('super-admin.companies')
            ->with('success', 'Company storage add-on updated successfully.');
    }

    public function impersonateCompany(Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $targetUser = $company->users()
            ->where(function ($query) {
                $query->whereIn('role', ['company_admin', 'companyadmin'])
                    ->orWhereHas('roleRecord', function ($roleQuery) {
                        $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                    });
            })
            ->first();

        if (! $targetUser) {
            return redirect()
                ->route('super-admin.companies')
                ->with('error', "Company admin user not found for {$company->name}. Assign a company admin before logging in as this company.");
        }

        session([
            'super_admin_impersonator_id' => auth()->id(),
        ]);

        Auth::login($targetUser);
        request()->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Logged in as company admin successfully.');
    }

    public function stopImpersonation(Request $request)
    {
        $impersonatorId = $request->session()->pull('super_admin_impersonator_id');

        abort_unless($impersonatorId, 403);

        $superAdmin = User::query()->findOrFail($impersonatorId);
        abort_unless($superAdmin->isSuperAdmin(), 403);

        Auth::login($superAdmin);
        $request->session()->regenerate();

        return redirect()->route('super-admin.dashboard')->with('success', 'Returned to super admin successfully.');
    }

    public function payments()
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        return view('super-admin.payments', [
            'subscriptions' => CompanySubscription::query()
                ->with(['company', 'plan'])
                ->latest()
                ->get(),
        ]);
    }

    public function paymentHistory(Request $request, Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $filters = $this->subscriptionPaymentFilters($request);

        return view('super-admin.payment-history', [
            'company' => $company,
            'filters' => $filters,
            'subscriptions' => $this->subscriptionPaymentsQuery($filters, $company)
                ->with('plan')
                ->latest()
                ->get(),
        ]);
    }

    public function downloadPaymentInvoices(Request $request, Company $company)
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);

        $filters = $this->subscriptionPaymentFilters($request, true);

        $range = Carbon::parse($filters['from'])->format('d-m-Y') . ' to ' . Carbon::parse($filters['to'])->format('d-m-Y');
        $query = $this->subscriptionPaymentsQuery($filters, $company)
            ->whereRaw('LOWER(status) != ?', ['trialing']);

        $count = (clone $query)->count();

        if ($count === 0) {
            return back()->with('error', "No invoices found between {$range}.");
        }

        if ($count > self::MAX_BULK_INVOICES) {
            return back()->with('error', "{$count} invoices found between {$range}. Please select a shorter date range (max " . self::MAX_BULK_INVOICES . ' invoices per download).');
        }

        $invoices = $query->with(['company', 'plan'])
            ->oldest()
            ->get()
            ->map(fn (CompanySubscription $subscription) => SubscriptionInvoice::data($subscription));

        $fileName = 'subscription-invoices-' . Str::slug($company->name) . '-'
            . $filters['from'] . '-to-' . $filters['to'] . '.pdf';

        return Pdf::loadView('subscription.invoices-pdf', ['invoices' => $invoices])
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'dpi' => 96,
            ])
            ->download($fileName);
    }

    /**
     * @return array{from: ?string, to: ?string}
     */
    private function subscriptionPaymentFilters(Request $request, bool $requireRange = false): array
    {
        $rule = $requireRange ? 'required' : 'nullable';

        $validated = $request->validate([
            'from' => [$rule, 'date_format:Y-m-d'],
            'to' => [$rule, 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'from.required' => 'Select a From date to download invoices.',
            'to.required' => 'Select a To date to download invoices.',
            'to.after_or_equal' => 'The To date must be on or after the From date.',
        ]);

        return [
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ];
    }

    /**
     * Filters on the invoice date (created_at), which is the date printed on the invoice.
     */
    private function subscriptionPaymentsQuery(array $filters, Company $company)
    {
        return CompanySubscription::query()
            ->where('company_id', $company->id)
            ->when($filters['from'], fn ($query, $from) => $query->whereDate('created_at', '>=', $from))
            ->when($filters['to'], fn ($query, $to) => $query->whereDate('created_at', '<=', $to));
    }

}
