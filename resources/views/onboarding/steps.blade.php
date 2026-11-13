<x-guest-layout>
    @php
        $stepDefinitions = [
            1 => [
                'number' => '01',
                'name' => 'Company Info',
                'summary' => 'Add your core registration details so your business profile starts with clean, trustworthy data.',
            ],
            2 => [
                'number' => '02',
                'name' => 'Location & Brand',
                'summary' => 'Upload identity assets and set the location your team and customers will recognize.',
            ],
            4 => [
                'number' => '03',
                'name' => 'Plan & Billing',
                'summary' => 'Choose the package that fits your current team size and growth stage.',
            ],
        ];

        $currentStep = $stepDefinitions[$step] ?? $stepDefinitions[1];
        $selectedPlanId = old('subscription_plan_id', optional($company->activeSubscription)->subscription_plan_id);
        $selectedBillingCycle = old('billing_cycle', session('onboarding.billing_cycle', 'monthly'));
        $selectedSubscriptionMode = old('subscription_mode', session('onboarding.subscription_mode', 'paid'));
        $selectedLicenseCount = max(1, (int) old('license_count', session('onboarding.license_count', 1)));
        $planPricesData = $step == 4
            ? $plans->mapWithKeys(function ($plan) {
                $monthlyPrice = (float) ($plan->getRawOriginal('monthly_price') ?? 0);
                $yearlyPrice = (float) ($plan->getRawOriginal('yearly_price') ?? 0);

                return [
                    $plan->id => [
                        'monthly' => $monthlyPrice,
                        'yearly' => $yearlyPrice,
                        'annualReference' => $monthlyPrice * 12,
                        'savings' => max(0, ($monthlyPrice * 12) - $yearlyPrice),
                        'discountLabel' => $plan->yearlyDiscountLabel(),
                    ],
                ];
            })->toArray()
            : [];
        $averageYearlyDiscount = $step == 4
            ? $plans
                ->map(fn ($plan) => $plan->yearlyDiscountPercentage())
                ->filter(fn ($discount) => $discount !== null)
                ->avg()
            : null;
        $averageYearlyDiscountLabel = $averageYearlyDiscount !== null
            ? 'SAVE ~' . number_format(round($averageYearlyDiscount), 0) . '%'
            : null;

        $planBlueprints = [
            'starter' => [
                'summary' => 'A clean starting point for smaller teams that need structure without extra complexity.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'Guided onboarding included',
                ],
                'includes_title' => 'Starter includes:',
                'includes' => [
                    'Lead and project tracking',
                    'Team attendance visibility',
                    'Basic dashboards and follow-ups',
                    'Field scheduling foundation',
                ],
            ],
            'growth' => [
                'summary' => 'Designed for fast-moving operations that need better coordination across sales, projects, and service.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'Priority onboarding support',
                ],
                'includes_title' => 'Everything in Starter, plus:',
                'includes' => [
                    'Expanded team capacity',
                    'Higher operational throughput',
                    'More room for project and service scale',
                    'Best-fit plan for growing companies',
                ],
            ],
            'enterprise' => [
                'summary' => 'Built for larger field operations that need flexible scale, control, and premium support.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'High-scale operational support',
                ],
                'includes_title' => 'Everything in Growth, plus:',
                'includes' => [
                    'Unlimited scaling for field teams',
                    'Long-term operational headroom',
                    'Premium account flexibility',
                    'Best option for multi-team rollouts',
                ],
            ],
        ];

        $fallbackPlanBlueprints = [
            [
                'summary' => 'A practical setup for teams beginning to standardize operations.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'Guided onboarding included',
                ],
                'includes_title' => 'Plan includes:',
                'includes' => [
                    'Lead visibility',
                    'Project coordination',
                    'Attendance management',
                    'Customer follow-up structure',
                ],
            ],
            [
                'summary' => 'A balanced plan for companies scaling daily execution and collaboration.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'Priority onboarding support',
                ],
                'includes_title' => 'Plan includes:',
                'includes' => [
                    'Larger team capacity',
                    'Better operational throughput',
                    'Stronger collaboration support',
                    'More headroom for growth',
                ],
            ],
            [
                'summary' => 'A premium option for larger teams that need room to scale confidently.',
                'focus' => [
                    'Staff seats - %s',
                    'Storage - %s',
                    'High-scale operational support',
                ],
                'includes_title' => 'Plan includes:',
                'includes' => [
                    'Premium scale',
                    'Flexible capacity',
                    'Large-team readiness',
                    'Long-term platform fit',
                ],
            ],
        ];

        $formatPrice = function ($amount) {
            $amount = (float) $amount;
            $decimals = abs($amount - floor($amount)) > 0 ? 2 : 0;

            return number_format($amount, $decimals);
        };
    @endphp

    <div class="ww-auth-stage bg-gradient-to-b from-blue-50 via-blue-50/60 to-white">
        <div class="ww-auth-orb -left-12 top-10 h-72 w-72 bg-blue-300/45"></div>
        <div class="ww-auth-orb bottom-0 right-0 h-96 w-96 bg-cyan-300/30"></div>

        <div class="relative mx-auto flex min-h-screen max-w-5xl items-center px-4 py-8 sm:px-6 lg:px-8">
            <section class="ww-card w-full !p-6 sm:!p-8 lg:!p-10">
                @if (session('registration_success') || session('success'))
                    <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95" class="mb-8 rounded-3xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 p-6 shadow-sm sm:p-7">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-md shadow-emerald-600/20">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <div class="flex-1">
                                <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-emerald-800">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                    Registration Successful
                                </div>
                                <h2 class="mt-2 text-xl font-black text-slate-900 sm:text-2xl">
                                    Thank you for registering{{ $company?->name ? ', ' . $company->name : '' }}! 🎉
                                </h2>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                    {{ session('success') ?? 'Your company account and workspace have been registered successfully. Welcome to CCTV Workshop! Please choose your subscription plan to complete your setup.' }}
                                </p>
                            </div>
                            <button type="button" @click="show = false" class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-200/50 hover:text-slate-600 transition" title="Dismiss">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                @endif


                    @if ($step == 1)
                        <div class="mt-8 space-y-6">
                            <div>
                                <h3 class="text-2xl font-black tracking-tight text-slate-950">Company information</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">These details help personalize your account setup and keep your official records aligned from the start.</p>
                            </div>

                            <form action="{{ route('onboarding.step1') }}" method="POST" class="space-y-5 rounded-3xl border border-slate-200 bg-slate-50/60 p-5 sm:p-6">
                                @csrf

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="ww-field">
                                        <label for="gst_number" class="ww-label">GST number</label>
                                        <input id="gst_number" name="gst_number" type="text" value="{{ old('gst_number', $company->gst_number) }}" placeholder="22AAAAA0000A1Z5" class="ww-input {{ $errors->has('gst_number') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">Optional now, but useful for invoices and tax-ready records later.</p>
                                        <x-input-error :messages="$errors->get('gst_number')" class="ww-field-error" />
                                    </div>

                                    <div class="ww-field">
                                        <label for="pan_number" class="ww-label">PAN number</label>
                                        <input id="pan_number" name="pan_number" type="text" value="{{ old('pan_number', $company->pan_number) }}" placeholder="ABCDE1234F" class="ww-input {{ $errors->has('pan_number') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">Useful for official company records and verification flows.</p>
                                        <x-input-error :messages="$errors->get('pan_number')" class="ww-field-error" />
                                    </div>

                                    <div class="ww-field">
                                        <label for="registration_number" class="ww-label">Company registration number</label>
                                        <input id="registration_number" name="registration_number" type="text" value="{{ old('registration_number', $company->registration_number) }}" placeholder="Registration number" class="ww-input {{ $errors->has('registration_number') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">Optional, but recommended if your business is already formally registered.</p>
                                        <x-input-error :messages="$errors->get('registration_number')" class="ww-field-error" />
                                    </div>

                                    <div class="ww-field">
                                        <label for="registration_date" class="ww-label">Registration date</label>
                                        <input id="registration_date" name="registration_date" type="date" value="{{ old('registration_date', optional($company->registration_date)->format('Y-m-d')) }}" class="ww-input {{ $errors->has('registration_date') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">Helps keep your workspace profile complete for future reference.</p>
                                        <x-input-error :messages="$errors->get('registration_date')" class="ww-field-error" />
                                    </div>
                                </div>

                                <div class="flex justify-end border-t border-slate-200 pt-6">
                                    <button type="submit" class="ww-primary-btn">
                                        Continue to branding
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @elseif ($step == 2)
                        <div class="mt-8 space-y-6">
                            <div>
                                <h3 class="text-2xl font-black tracking-tight text-slate-950">Location and brand assets</h3>
                                <p class="mt-2 text-sm leading-7 text-slate-600">Set the company address, upload your logo, and choose the state and city that should anchor your workspace.</p>
                            </div>

                            <form id="locationForm" action="{{ route('onboarding.step2') }}" method="POST" enctype="multipart/form-data" class="space-y-5" x-data="locationPicker()" x-init="init()" novalidate>
                                @csrf

                                <div class="grid gap-5 ">
                                    <div class="ww-field">
                                        <label for="address" class="ww-label">Company address</label>
                                        <textarea id="address" name="address" rows="5" required class="ww-textarea {{ $errors->has('address') ? 'is-invalid' : '' }}">{{ old('address', $company->address) }}</textarea>
                                        <p class="ww-field-note">Use the address customers and staff should associate with your main business operations.</p>
                                        <p class="ww-field-error hidden" data-error-for="address"></p>
                                        <x-input-error :messages="$errors->get('address')" class="ww-field-error" />
                                    </div>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="ww-field">
                                        <label for="logo" class="ww-label">Company logo</label>
                                        <input id="logo" name="logo" type="file" accept="image/*" class="ww-input file:mr-4 file:rounded-full file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700 {{ $errors->has('logo') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">PNG, JPG, or WEBP up to 2 MB.</p>
                                        <x-input-error :messages="$errors->get('logo')" class="ww-field-error" />
                                    </div>

                                    <div class="ww-field">
                                        <label for="favicon" class="ww-label">Favicon</label>
                                        <input id="favicon" name="favicon" type="file" accept="image/*,.ico,.svg" class="ww-input file:mr-4 file:rounded-full file:border-0 file:bg-blue-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-blue-700 {{ $errors->has('favicon') ? 'is-invalid' : '' }}" />
                                        <p class="ww-field-note">ICO, SVG, PNG, or JPG up to 1 MB.</p>
                                        <x-input-error :messages="$errors->get('favicon')" class="ww-field-error" />
                                    </div>
                                </div>

                                <div class="grid gap-5 sm:grid-cols-2">
                                    <div class="ww-field">
                                        <label for="state" class="ww-label">State</label>
                                        <select id="state" name="state" required x-model="selectedState" @change="updateCities()" class="ww-select {{ $errors->has('state') ? 'is-invalid' : '' }}">
                                            <option value="">Select state</option>
                                            <template x-for="stateName in states" :key="stateName">
                                                <option :value="stateName" x-text="stateName"></option>
                                            </template>
                                        </select>
                                        <p class="ww-field-error hidden" data-error-for="state"></p>
                                        <x-input-error :messages="$errors->get('state')" class="ww-field-error" />
                                    </div>

                                    <div class="ww-field">
                                        <label for="city" class="ww-label">City</label>
                                        <select id="city" name="city" required x-model="selectedCity" class="ww-select {{ $errors->has('city') ? 'is-invalid' : '' }}">
                                            <option value="">Select city</option>
                                            <template x-for="cityName in availableCities" :key="cityName">
                                                <option :value="cityName" x-text="cityName"></option>
                                            </template>
                                        </select>
                                        <p class="ww-field-error hidden" data-error-for="city"></p>
                                        <x-input-error :messages="$errors->get('city')" class="ww-field-error" />
                                    </div>
                                </div>

                                <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                                    <button type="button" onclick="window.history.back()" class="ww-secondary-btn">Back</button>
                                    <button type="submit" class="ww-primary-btn">
                                        Continue to plan selection
                                        <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                        </svg>
                                    </button>
                                </div>
                            </form>
                        </div>
                    @elseif ($step == 4)
                        <div class="space-y-6">
                            <div class="mx-auto max-w-5xl border border-cyan-200 bg-white/95 px-4 py-6 shadow-[0_20px_70px_rgba(37,99,235,0.10)] sm:px-6">
                                <div class="text-center">
                                    <h3 class="text-2xl font-black text-slate-950 sm:text-3xl">Choose your subscription plan1</h3>
                                    <p class="mt-2 text-sm text-slate-500">Select the plan that best fits your business needs.</p>
                                </div>

                                <div class="mt-6 flex justify-center">
                                    <div class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 p-1 shadow-sm">
                                        <button type="button" style="background-color: #c5233e;" class="billing-cycle-option inline-flex min-w-[5.8rem] items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition {{ $selectedBillingCycle === 'monthly' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700' }}" data-cycle-option="monthly" data-billing-option="monthly">
                                            Monthly
                                        </button>
                                        <button type="button" class="billing-cycle-option inline-flex min-w-[5.8rem] items-center justify-center rounded-full px-5 py-2.5 text-sm font-semibold transition {{ $selectedBillingCycle === 'yearly' ? 'bg-blue-600 text-white shadow-sm' : 'bg-white text-slate-700' }}" data-cycle-option="yearly" data-billing-option="yearly">
                                            Yearly
                                        </button>
                                    </div>
                                </div>

                                <form action="{{ route('onboarding.complete') }}" id="subscriptionPlanForm" method="POST" class="mt-8 space-y-5" novalidate>
                                    @csrf
                                    <input type="hidden" name="billing_cycle" id="billing_cycle_input" value="{{ $selectedBillingCycle }}">
                                    <input type="hidden" name="subscription_mode" id="subscription_mode_input" value="{{ $selectedSubscriptionMode }}">
                                    <input type="hidden" name="license_count" id="license_count_input" value="{{ $selectedLicenseCount }}">

                                    <select id="plan-select" name="subscription_plan_id" class="sr-only" aria-hidden="true" tabindex="-1">
                                        <option value="" selected>Select a plan</option>
                                        @foreach ($plans as $plan)
                                            <option
                                                value="{{ $plan->id }}"
                                                @selected((string) $selectedPlanId === (string) $plan->id)
                                                data-monthly-price="{{ number_format((float) $plan->priceForCycle('monthly'), 0, '.', '') }}"
                                                data-yearly-price="{{ number_format((float) $plan->priceForCycle('yearly'), 0, '.', '') }}"
                                                data-yearly-discount-label="{{ $plan->yearlyDiscountLabel() ?? '' }}"
                                                data-name="{{ $plan->name }}"
                                                data-has-free-trial="{{ $plan->has_free_trial ? 1 : 0 }}"
                                                data-trial-eligible="{{ ($trialEligibilityByPlan[$plan->id]['eligible'] ?? false) ? 1 : 0 }}"
                                                data-trial-reason="{{ $trialEligibilityByPlan[$plan->id]['reason'] ?? '' }}"
                                            >{{ $plan->name }}</option>
                                        @endforeach
                                    </select>

                                    <div class="grid gap-4 lg:grid-cols-2">
                                        @foreach ($plans as $plan)
                                            @php
                                                $trialEligibility = $trialEligibilityByPlan[$plan->id] ?? ['available' => false, 'eligible' => false];
                                                $isSelected = (string) $selectedPlanId === (string) $plan->id;
                                                $yearlyDiscountLabel = $plan->yearlyDiscountLabel();
                                                $priceMonthly = (float) $plan->priceForCycle('monthly');
                                                $featureLabels = [
                                                    'Lead Management',
                                                    'Project Management',
                                                    'AMC Management',
                                                    'Client Management',
                                                    'Staff Management',
                                                ];
                                            @endphp

                                            <div
                                                role="button"
                                                tabindex="0"
                                                class="subscription-plan-card relative flex h-full cursor-pointer flex-col rounded-[28px] border border-cyan-200 bg-white text-left transition hover:border-blue-300"
                                                data-plan-card-id="{{ $plan->id }}"
                                                data-seat-count="{{ (string) $selectedPlanId === (string) $plan->id ? $selectedLicenseCount : 1 }}"
                                            >
                                                @if($plan->is_popular)
                                                    <span style="background-color: #c5233e;" class="absolute right-4 top-0 -translate-y-1/2 rounded-full bg-blue-600 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white shadow">
                                                        Most Popular
                                                    </span>
                                                @endif

                                                <div class="border-b border-cyan-100 px-5 pb-6 pt-8 text-center">
                                                    <div class="text-2xl font-black text-slate-950 sm:text-[1.75rem]">{{ $plan->name }}</div>
                                                    <div class="mt-6 text-3xl font-black text-slate-950 sm:text-4xl" data-plan-card-price="{{ $plan->id }}">
                                                        Rs.{{ number_format($priceMonthly, 0) }}
                                                    </div>
                                                    <div class="mt-1 text-xs text-slate-500">
                                                        <span data-plan-card-unit-price="{{ $plan->id }}">Rs.{{ number_format($priceMonthly, 0) }} / staff / month</span>
                                                        <span class="block text-[10px] text-slate-400 mt-0.5">+ {{ setting('subscription_gst_percentage', 18) }}% GST</span>
                                                    </div>

                                                    @php
                                                        $planNameLower = strtolower($plan->name ?? '');
                                                        $futurePrice = null;
                                                        if (str_contains($planNameLower, 'growth')) {
                                                            $futurePrice = 299;
                                                        } elseif (str_contains($planNameLower, 'enterprise')) {
                                                            $futurePrice = 599;
                                                        }
                                                    @endphp

                                                    @if(now()->format('Y-m-d') <= '2026-10-10' && $futurePrice)
                                                        <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-900 shadow-sm">
                                                            Current price is available till 10th Oct 2026. After 10th Oct 2026, {{ $plan->name }} plan price will be Rs. {{ $futurePrice }}.
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="flex-1 px-5 py-5">
                                                    <div class="space-y-2">
                                                        @foreach($featureLabels as $feature)
                                                            <div class="flex items-center gap-2 border border-cyan-200 bg-cyan-50/40 px-3 py-2 text-sm font-semibold text-slate-700">
                                                                <span style="background-color: #c5233e;" class="h-2.5 w-2.5 bg-blue-500"></span>
                                                                <span>{{ $feature }}</span>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    @if(! $plan->disable_staff_tracking)
                                                        <div style="background-color: #c5233e;" class="mt-3 rounded-2xl bg-[#284fda] px-4 py-3 text-white">
                                                            <div class="flex items-center justify-between gap-3">
                                                                <div class="flex items-center gap-2 text-sm font-semibold">
                                                                    <span class="h-2.5 w-2.5 bg-white"></span>
                                                                    <span>Staff Tracking</span>
                                                                </div>
                                                                <span class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white">
                                                                    Enterprise Only
                                                                </span>
                                                            </div>
                                                        </div>
                                                    @endif

                                                    <div class="mt-4 border-t border-dashed border-cyan-100 pt-4 text-center">
                                                        <div class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Select Staff</div>
                                                        <div class="mt-3 inline-flex items-center overflow-hidden rounded-full border border-cyan-200 bg-white shadow-sm">
                                                            <button type="button" class="seat-stepper-button inline-flex h-10 w-10 items-center justify-center border-r border-cyan-200 text-lg font-bold text-blue-600 transition hover:bg-blue-50" style="color: #c5233e;" data-seat-decrement data-seat-plan-id="{{ $plan->id }}">-</button>
                                                            <span class="min-w-[3.5rem] px-4 py-2 text-center text-base font-black text-slate-950" data-seat-value data-seat-plan-id="{{ $plan->id }}">{{ (string) $selectedPlanId === (string) $plan->id ? $selectedLicenseCount : 1 }}</span>
                                                            <button type="button"  class="seat-stepper-button inline-flex h-10 w-10 items-center justify-center border-l border-cyan-200 text-lg font-bold text-blue-600 transition hover:bg-blue-50" style="color: #c5233e;" data-seat-increment data-seat-plan-id="{{ $plan->id }}">+</button>
                                                        </div>
                                                    </div>

                                                    @if ($plan->has_free_trial)
                                                        <div
                                                            class="mt-4 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700"
                                                            data-plan-card-trial-status="{{ $plan->id }}"
                                                        >
                                                            {{ ($trialEligibility['eligible'] ?? false) ? '15-Day Free Trial Available' : 'Trial unavailable' }}
                                                        </div>
                                                    @endif
                                                </div>

                                                <div class="border-t border-cyan-100 px-5 py-5">
                                                    <span style="background-color: #c5233e;"
                                                        class="inline-flex w-full items-center justify-center rounded-full px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:opacity-95"
                                                        style="background: linear-gradient(90deg, #1d4ed8 0%, #0891b2 100%); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.18);"
                                                    >
                                                        Select {{ $plan->name }} &rarr;
                                                    </span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>

                                    <x-input-error :messages="$errors->get('subscription_plan_id')" class="ww-field-error" />
                                    <p id="subscription_plan_id_error" class="ww-field-error hidden"></p>

                                    <div id="trial-mode-panel" class="hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4">
                                        <label class="flex items-start gap-3 text-sm text-emerald-900">
                                            <input id="trial-mode-checkbox" name="use_free_trial" value="1" type="checkbox" @checked($selectedSubscriptionMode === 'trial') class="mt-1 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-400">
                                            <span>
                                                <span class="block font-semibold">Use the 15 Days free trial on the selected plan</span>
                                                <span id="trial-mode-hint" class="mt-1 block text-sm text-emerald-800/80"></span>
                                            </span>
                                        </label>
                                    </div>

                                    <div class="mt-2 flex flex-col gap-3 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                                        <button type="submit" formaction="{{ route('onboarding.skip') }}" formnovalidate class="ww-secondary-btn w-full justify-center sm:w-auto">Skip</button>
                                        <button style="background-color: #c5233e;"
                                            type="submit"
                                            class="inline-flex items-center justify-center gap-2 px-6 py-3 text-sm font-semibold text-white shadow-lg transition hover:opacity-95"
                                            style="background: linear-gradient(90deg, #1d4ed8 0%, #0f766e 100%); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.22);"
                                            form="subscriptionPlanForm"
                                        >
                                            <span id="onboarding-continue-label">Continue to payment</span>
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                            </form>
                            @endif

                    </section>
        </div>
    </div>

    @push('scripts')
        <script>
            function locationPicker() {
                const stateCityMap = @json(
                    $states->mapWithKeys(function ($state) {
                        return [
                            $state->name => $state->cities->pluck('name')->values(),
                        ];
                    })
                );

                return {
                    states: Object.keys(stateCityMap),
                    selectedState: @js(old('state', $company->state)),
                    selectedCity: @js(old('city', $company->city)),
                    availableCities: [],
                    init() {
                        this.updateCities();
                    },
                    updateCities() {
                        this.availableCities = stateCityMap[this.selectedState] || [];

                        if (!this.availableCities.includes(this.selectedCity)) {
                            this.selectedCity = '';
                        }
                    },
                };
            }

            document.addEventListener('DOMContentLoaded', () => {
                const locationForm = document.getElementById('locationForm');

                if (locationForm) {
                    const fields = ['address', 'state', 'city'];

                    const validateLocationField = (fieldName) => {
                        const field = locationForm.querySelector(`[name="${fieldName}"]`);
                        const errorNode = locationForm.querySelector(`[data-error-for="${fieldName}"]`);
                        const value = field ? field.value.trim() : '';
                        const message = value ? '' : `Please enter ${fieldName.replace('_', ' ')}.`;

                        if (!field || !errorNode) {
                            return true;
                        }

                        field.classList.remove('is-invalid', 'is-valid');

                        if (message) {
                            field.classList.add('is-invalid');
                            errorNode.textContent = message.replace('enter state.', 'select state.').replace('enter city.', 'select city.');
                            errorNode.classList.remove('hidden');
                            return false;
                        }

                        field.classList.add('is-valid');
                        errorNode.textContent = '';
                        errorNode.classList.add('hidden');
                        return true;
                    };

                    fields.forEach((fieldName) => {
                        const field = locationForm.querySelector(`[name="${fieldName}"]`);

                        if (!field) {
                            return;
                        }

                        ['change', 'blur', 'input'].forEach((eventName) => {
                            field.addEventListener(eventName, () => validateLocationField(fieldName));
                        });
                    });

                    locationForm.addEventListener('submit', (event) => {
                        let firstInvalid = null;

                        fields.forEach((fieldName) => {
                            const valid = validateLocationField(fieldName);

                            if (!valid && !firstInvalid) {
                                firstInvalid = locationForm.querySelector(`[name="${fieldName}"]`);
                            }
                        });

                        if (firstInvalid) {
                            event.preventDefault();
                            firstInvalid.focus();
                        }
                    });
                }

                const subscriptionPlanForm = document.getElementById('subscriptionPlanForm');

                if (!subscriptionPlanForm) {
                    return;
                }

                const billingInput = document.getElementById('billing_cycle_input');
                const trialModeInput = document.getElementById('subscription_mode_input');
                const licenseCountInput = document.getElementById('license_count_input');
                const billingButtons = Array.from(document.querySelectorAll('[data-cycle-option]'));
                const planSelect = document.getElementById('plan-select');
                const planCards = Array.from(document.querySelectorAll('[data-plan-card-id]'));
                const planPriceNodes = document.querySelectorAll('[data-plan-card-price]');
                const planUnitPriceNodes = document.querySelectorAll('[data-plan-card-unit-price]');
                const trialStatusNodes = document.querySelectorAll('[data-plan-card-trial-status]');
                const seatStepButtons = Array.from(document.querySelectorAll('[data-seat-decrement], [data-seat-increment]'));
                const seatValueNodes = Array.from(document.querySelectorAll('[data-seat-value]'));
                const planErrorNode = document.getElementById('subscription_plan_id_error');
                const trialPanel = document.getElementById('trial-mode-panel');
                const trialCheckbox = document.getElementById('trial-mode-checkbox');
                const trialHint = document.getElementById('trial-mode-hint');
                const prices = @json($planPricesData);
                const trialEligibility = @json($trialEligibilityByPlan ?? []);
                const planCounts = {};

                planCards.forEach((card) => {
                    const planId = card.dataset.planCardId;
                    const count = Math.max(1, Number(card.dataset.seatCount || 1));

                    if (planId) {
                        planCounts[planId] = Number.isFinite(count) && count > 0 ? count : 1;
                    }
                });

                const getStaffInput = (planId) => staffInputs.find((input) => String(input.dataset.staffInput) === String(planId));

                const getStaffCount = (planId) => {
                    const input = getStaffInput(planId);
                    const value = parseInt(input?.value, 10);

                    return Number.isFinite(value) && value >= 1 ? value : 1;
                };

                const setStaffCount = (planId, count) => {
                    const input = getStaffInput(planId);
                    const clamped = Math.max(1, Number.isFinite(count) ? Math.trunc(count) : 1);

                    if (input) {
                        input.value = clamped;
                    }

                    return clamped;
                };

                const syncLicenseCountInput = () => {
                    const radio = selectedPlan();

                    if (!licenseCountInput) {
                        return;
                    }

                    licenseCountInput.value = radio ? getStaffCount(radio.value) : 1;
                };

                const syncContinueButtonLabel = () => {
                    const label = document.getElementById('onboarding-continue-label');
                    const option = selectedPlan();
                    const planTrial = option ? (trialEligibility[option.value] || {}) : {};
                    const trialSelected = Boolean(trialCheckbox?.checked)
                        && Boolean(planTrial.eligible)
                        && billingInput.value === 'monthly';

                    if (label) {
                        label.textContent = trialSelected ? 'Activate 15-day free trial' : 'Continue to payment';
                    }
                };

                const formatCurrency = (value) => {
                    const amount = Number(value || 0);
                    const hasDecimals = Math.abs(amount - Math.trunc(amount)) > 0;

                    return new Intl.NumberFormat('en-IN', {
                        minimumFractionDigits: hasDecimals ? 2 : 0,
                        maximumFractionDigits: hasDecimals ? 2 : 0,
                    }).format(amount);
                };

                const selectedPlan = () => {
                    if (!planSelect || !planSelect.value) {
                        return null;
                    }

                    return planSelect.querySelector(`option[value="${planSelect.value}"]`);
                };

                const planCount = (planId) => {
                    const count = Number(planCounts[planId] || 1);
                    return Number.isFinite(count) && count > 0 ? Math.min(count, 999) : 1;
                };

                const syncSeatCount = () => {
                    const option = selectedPlan();
                    const selectedValue = option ? option.value : '';
                    const normalized = planCount(selectedValue);

                    if (licenseCountInput) {
                        licenseCountInput.value = normalized;
                    }

                    seatValueNodes.forEach((node) => {
                        const planId = node.dataset.seatPlanId;
                        node.textContent = planCount(planId);
                    });
                };

                const syncSelectedCards = () => {
                    const selectedValue = planSelect?.value || '';

                    planCards.forEach((card) => {
                        const isSelected = card.dataset.planCardId === selectedValue;

                        card.classList.toggle('border-blue-600', isSelected);
                        card.classList.toggle('ring-1', isSelected);
                        card.classList.toggle('ring-blue-200', isSelected);
                        card.classList.toggle('shadow-[0_18px_50px_rgba(244,63,94,0.12)]', isSelected);
                        card.classList.toggle('border-cyan-200', !isSelected);
                    });
                };

                const setPlanCount = (planId, nextCount) => {
                    if (!planId) {
                        return;
                    }

                    const normalized = Math.max(1, Math.min(999, Number(nextCount || 1)));
                    planCounts[planId] = Number.isFinite(normalized) ? normalized : 1;

                    const card = planCards.find((item) => item.dataset.planCardId === planId);
                    if (card) {
                        card.dataset.seatCount = String(planCounts[planId]);
                    }

                    syncSeatCount();
                    updatePlanPricing(billingInput.value || 'monthly');
                };

                const syncTrialPanel = () => {
                    const option = selectedPlan();

                    if (!trialPanel || !trialCheckbox || !trialHint || !trialModeInput) {
                        return;
                    }

                    if (!option) {
                        trialPanel.classList.add('hidden');
                        trialCheckbox.checked = false;
                        trialCheckbox.disabled = true;
                        trialModeInput.value = 'paid';
                        trialHint.textContent = '';
                        syncContinueButtonLabel();
                        return;
                    }

                    const planTrial = trialEligibility[option.value] || {};
                    const available = Boolean(planTrial.available);
                    const eligible = Boolean(planTrial.eligible);

                    if (billingInput.value === 'yearly' || !available) {
                        trialPanel.classList.add('hidden');
                        trialCheckbox.checked = false;
                        trialCheckbox.disabled = true;
                        trialModeInput.value = 'paid';
                        trialHint.textContent = '';
                        syncContinueButtonLabel();
                        return;
                    }

                    trialPanel.classList.remove('hidden');
                    trialCheckbox.disabled = !eligible;

                    if (!eligible) {
                        trialCheckbox.checked = false;
                    }

                    trialHint.textContent = eligible
                        ? 'This company can activate the free trial once on the selected plan.'
                        : (planTrial.reason || 'Free trial is not available on the selected plan.');

                    trialModeInput.value = trialCheckbox.checked && eligible ? 'trial' : 'paid';
                    syncContinueButtonLabel();
                };

                const updatePlanPricing = (cycle) => {
                    const activeCycle = cycle === 'yearly' ? 'yearly' : 'monthly';
                    billingInput.value = activeCycle;

                    billingButtons.forEach((button) => {
                        const isActive = button.dataset.billingOption === activeCycle;
                        button.classList.toggle('bg-blue-600', isActive);
                        button.classList.toggle('text-white', isActive);
                        button.classList.toggle('shadow-sm', isActive);
                        button.classList.toggle('bg-white', !isActive);
                        button.classList.toggle('text-slate-700', !isActive);
                    });

                    planPriceNodes.forEach((node) => {
                        const planId = node.dataset.planCardPrice;
                        const pricing = prices[planId];
                        const count = planCount(planId);

                        if (!pricing) {
                            return;
                        }

                        const currentAmount = (activeCycle === 'yearly' ? pricing.yearly : pricing.monthly) * count;
                        node.textContent = `Rs.${formatCurrency(currentAmount)}`;
                    });

                    planUnitPriceNodes.forEach((node) => {
                        const planId = node.dataset.planCardUnitPrice;
                        const pricing = prices[planId];

                        if (!pricing) {
                            return;
                        }

                        const currentAmount = activeCycle === 'yearly' ? pricing.yearly : pricing.monthly;
                        node.textContent = `Rs.${formatCurrency(currentAmount)} / staff / ${activeCycle}`;
                    });

                    trialStatusNodes.forEach((node) => {
                        node.classList.toggle('hidden', activeCycle === 'yearly');
                    });

                    syncTrialPanel();
                    syncSeatCount();
                };

                billingButtons.forEach((button) => {
                    button.addEventListener('click', () => updatePlanPricing(button.dataset.billingOption));
                });

                seatStepButtons.forEach((button) => {
                    button.addEventListener('click', (event) => {
                        event.preventDefault();
                        event.stopPropagation();

                        const card = button.closest('[data-plan-card-id]');
                        const planId = card?.dataset.planCardId;
                        if (!planId) {
                            return;
                        }

                        const step = button.hasAttribute('data-seat-increment') ? 1 : -1;
                        setPlanCount(planId, planCount(planId) + step);
                    });
                });

                seatValueNodes.forEach((node) => {
                    node.addEventListener('click', (event) => event.stopPropagation());
                });

                planCards.forEach((card) => {
                    card.addEventListener('click', () => {
                        if (!planSelect) {
                            return;
                        }

                        planSelect.value = card.dataset.planCardId || '';
                        syncSelectedCards();
                        syncTrialPanel();
                        syncSeatCount();

                        if (planErrorNode) {
                            planErrorNode.textContent = '';
                            planErrorNode.classList.add('hidden');
                        }
                    });

                    card.addEventListener('keydown', (event) => {
                        if (event.key !== 'Enter' && event.key !== ' ') {
                            return;
                        }

                        event.preventDefault();

                        if (!planSelect) {
                            return;
                        }

                        planSelect.value = card.dataset.planCardId || '';
                        syncSelectedCards();
                        syncTrialPanel();
                        syncSeatCount();
                    });
                });

                document.querySelectorAll('[data-staff-increment], [data-staff-decrement]').forEach((button) => {
                    button.addEventListener('click', () => {
                        const planId = button.dataset.planId;
                        const delta = button.hasAttribute('data-staff-increment') ? 1 : -1;
                        setStaffCount(planId, getStaffCount(planId) + delta);
                        updatePlanPricing(billingInput.value || 'monthly');
                        syncLicenseCountInput();
                    });
                });

                staffInputs.forEach((input) => {
                    input.addEventListener('input', () => {
                        const planId = input.dataset.staffInput;
                        const parsed = parseInt(input.value, 10);
                        setStaffCount(planId, Number.isFinite(parsed) ? parsed : 1);
                        updatePlanPricing(billingInput.value || 'monthly');
                        syncLicenseCountInput();
                    });

                    input.addEventListener('blur', () => {
                        const planId = input.dataset.staffInput;
                        setStaffCount(planId, getStaffCount(planId));
                        updatePlanPricing(billingInput.value || 'monthly');
                        syncLicenseCountInput();
                    });
                });

                if (trialCheckbox) {
                    trialCheckbox.addEventListener('change', () => {
                        const option = selectedPlan();
                        const planTrial = option ? (trialEligibility[option.value] || {}) : {};
                        const eligible = Boolean(planTrial.eligible);

                        trialModeInput.value = trialCheckbox.checked && eligible ? 'trial' : 'paid';
                        syncContinueButtonLabel();
                    });
                }

                subscriptionPlanForm.addEventListener('submit', (event) => {
                    const submitterAction = event.submitter?.getAttribute('formaction') || '';

                    if (submitterAction.includes('/onboarding/skip')) {
                        return;
                    }

                    if (!selectedPlan()) {
                        event.preventDefault();

                        if (planErrorNode) {
                            planErrorNode.textContent = 'Please select a subscription plan to continue.';
                            planErrorNode.classList.remove('hidden');
                        }

                        return;
                    }

                    const currentPlan = selectedPlan();
                    const currentTrial = currentPlan ? (trialEligibility[currentPlan.value] || {}) : {};
                    const canUseTrial = Boolean(currentTrial.eligible) && billingInput.value === 'monthly';
                    trialModeInput.value = trialCheckbox?.checked && canUseTrial ? 'trial' : 'paid';

                    syncLicenseCountInput();

                    if (!licenseCountInput || !Number.isFinite(parseInt(licenseCountInput.value, 10)) || parseInt(licenseCountInput.value, 10) < 1) {
                        event.preventDefault();

                        if (planErrorNode) {
                            planErrorNode.textContent = 'Please select a valid number of staff (minimum 1).';
                            planErrorNode.classList.remove('hidden');
                        }
                    }
                });

                syncSeatCount();
                updatePlanPricing(billingInput.value || 'monthly');
                syncSelectedCards();
                syncTrialPanel();
                syncLicenseCountInput();
            });
        </script>
    @endpush
</x-guest-layout>
