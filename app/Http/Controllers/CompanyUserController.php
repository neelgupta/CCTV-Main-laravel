<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Models\CompanySubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Models\Staff;
use App\Services\MediaUploadService;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\StaffActivity;
use Illuminate\Support\Str;

class CompanyUserController extends Controller
{
    public function index()
    {
        $companyId = auth()->user()->company_id;
        $subscriptionService = app(SubscriptionLimitService::class);

        $users = User::query()
            ->where('company_id', $companyId)
            ->whereHas('roleRecord', function ($query) {
                $query->where('slug', '!=', 'client');
            })
            ->with([
                'roleRecord:id,name,slug',
                'staff.companySubscription.plan:id,name',
            ])
            ->orderBy('name')
            ->get();

        $roles = Role::query()
            ->where('company_id', $companyId)
            ->where('slug', '!=', 'client')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('settings.users.index', [
            'users' => $users,
            'roles' => $roles,
            'availableSubscriptionOptions' => $subscriptionService->availableSubscriptionOptions($companyId),
            'subscriptionUsage' => $subscriptionService->subscriptionUsageSummary($companyId),
        ]);
    }

    // public function store(Request $request)
    // {
    //     $companyId = auth()->user()->company_id;
    //     $request->validate([
    //         'name' => 'required|string|max:255',
    //         'email' => 'required|email|max:255|unique:users,email',
    //         'password' => 'required|string|min:8|confirmed',
    //         'role_id' => [
    //             'required',
    //             Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
    //         ],
    //     ]);

    //     $role = Role::query()->where('company_id', $companyId)->findOrFail($request->integer('role_id'));

    //     User::create([
    //         'company_id' => $companyId,
    //         'name' => $request->string('name')->toString(),
    //         'email' => $request->string('email')->toString(),
    //         'password' => Hash::make($request->string('password')->toString()),
    //         'role_id' => $role->id,
    //         'role' => $role->slug,
    //     ]);

    //     return back()->with('success', 'User created successfully.');
    // }
    public function store(Request $request)
    {
        $companyId = auth()->user()->company_id;
        $subscriptionService = app(SubscriptionLimitService::class);
        $availableSubscriptionOptions = collect($subscriptionService->availableSubscriptionOptions($companyId));
        $availableSubscriptionIds = $availableSubscriptionOptions->pluck('company_subscription_id')->values()->all();

        if ($availableSubscriptionIds === []) {
            return back()
                ->withInput()
                ->withErrors([
                    'company_subscription_id' => 'No active plan seats are available. Please purchase a subscription before creating staff.',
                ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:2|max:191',
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email'),
            ],
            'password' => 'required|string|min:8|confirmed',
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'contact_number' => 'required|digits:10',
            'join_date' => 'required|date|before_or_equal:today',
            'department' => 'nullable|string|min:2|max:255',
            'profile_photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'company_subscription_id' => [
                'required',
                Rule::in($availableSubscriptionIds),
            ],
        ], [
            'name.required' => 'Full name is required.',
            'name.min' => 'Full name must be at least 2 characters.',
            'name.max' => 'Full name may not be greater than 191 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email may not be greater than 191 characters.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'role_id.required' => 'Please select a role.',
            'contact_number.required' => 'Contact number is required.',
            'contact_number.digits' => 'Contact number must be exactly 10 digits.',
            'join_date.required' => 'Joining date is required.',
            'join_date.date' => 'Please enter a valid joining date.',
            'join_date.before_or_equal' => 'Joining date cannot be in the future.',
            'department.min' => 'Department must be at least 2 characters.',
            'department.max' => 'Department may not be greater than 191 characters.',
            'profile_photo.image' => 'Profile image must be an image file.',
            'profile_photo.mimes' => 'Profile image must be a JPG, JPEG, PNG, or WEBP file.',
            'profile_photo.max' => 'Profile image size must not exceed 2 MB.',
            'company_subscription_id.required' => 'Please select a plan.',
            'company_subscription_id.in' => 'The selected plan is invalid for this company.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();
        $validated['name'] = Str::limit(trim((string) $validated['name']), 191, '');
        $validated['email'] = Str::limit(strtolower(trim((string) $validated['email'])), 191, '');
        $validated['contact_number'] = trim((string) $validated['contact_number']);
        $validated['join_date'] = trim((string) $validated['join_date']);
        $validated['department'] = filled($validated['department'] ?? null)
            ? Str::limit(trim((string) $validated['department']), 191, '')
            : null;

        $subscriptionLimits = app(SubscriptionLimitService::class);
        $subscriptionLimits->ensureWithinLimit($companyId, 'users', 1, 'email');

        $selectedSubscriptionId = (int) $validated['company_subscription_id'];

        DB::transaction(function () use ($request, $companyId, $validated, $subscriptionService, $selectedSubscriptionId) {
            $role = Role::query()->where('company_id', $companyId)->findOrFail($validated['role_id']);
            $photoPath = null;
            $selectedSubscription = CompanySubscription::query()
                ->where('company_id', $companyId)
                ->where('id', $selectedSubscriptionId)
                ->whereIn('status', ['active', 'trialing'])
                ->firstOrFail();
            $selectedLicenseKey = $subscriptionService->nextAvailableLicenseKey($companyId, $selectedSubscription->id);

            if (! $selectedLicenseKey) {
                throw ValidationException::withMessages([
                    'company_subscription_id' => 'The selected plan has no remaining seats.',
                ]);
            }

            if ($request->hasFile('profile_photo')) {
                $photoPath = app(MediaUploadService::class)->store($request->file('profile_photo'), 'company_'.$companyId.'/profile_photos', 'public');
            }

            $employeeId = Staff::generateEmployeeId($companyId);

            // 2. Create the User (Login Credentials)
            $user = User::create([
                'company_id' => $companyId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($validated['password']),
                'role_id' => $role->id,
                'role' => $role->slug,
            ]);

            // 3. Create the Staff Profile (Personal/Job Info)
            $staff = Staff::create([
                'user_id' => $user->id, // This fixes your SQL error
                'company_id' => $companyId,
                'company_subscription_id' => $selectedSubscription->id,
                // 'employee_id' => $request->employee_id,
                'employee_id' => $employeeId,
                'name' => $validated['name'],
                'contact_number' => $validated['contact_number'],
                'join_date' => $validated['join_date'],
                'role_id' => $role->id,
                'department' => $validated['department'],
                'profile_photo' => $photoPath,
                'license_key' => $selectedLicenseKey,
            ]);

            StaffActivity::create([
                'staff_id' => $staff->id,
                'type' => 'Staff Created',
                'description' => 'New Staff Added',
                'occurred_at' => now(),
            ]);
        });

        return back()->with('success', 'User and Staff Profile created successfully.');
    }
    public function checkEmail(Request $request)
    {
        $email = Str::limit(strtolower(trim((string) $request->input('email'))), 191, '');

        $exists = User::where('email', $email)
            ->exists();

        return response()->json(!$exists);
    }

    public function updateRole(Request $request, User $user)
    {
        $companyId = auth()->user()->company_id;
        if ((int) $user->company_id !== (int) $companyId) {
            abort(403);
        }

        $request->validate([
            'role_id' => [
                'required',
                Rule::exists('roles', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
        ]);

        $role = Role::query()->where('company_id', $companyId)->findOrFail($request->integer('role_id'));
        $user->update([
            'role_id' => $role->id,
            'role' => $role->slug,
        ]);

        return back()->with('success', 'User role updated.');
    }

    public function toggleStatus(User $user){
        $companyId = auth()->user()->company_id;

        if ((int) $user->company_id !== (int) $companyId) {
            abort(403);
        }

        // Explicitly toggle and save
        $user->is_active = !$user->is_active;
        $user->save(); // This bypasses fillable checks if you haven't updated the model yet

        return back()->with('success', 'User status updated to ' . ($user->is_active ? 'Active' : 'Inactive'));
    }
}
