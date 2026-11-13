<?php

namespace App\Http\Controllers;

use App\Models\AmcPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AmcPlanController extends Controller
{
    public function index()
    {
        $this->ensureCompanyAdmin();

        return view('amc-plans.index', [
            'plans' => AmcPlan::query()
                ->withCount('amcs')
                ->orderBy('duration_months')
                ->orderBy('visit_count')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->ensureCompanyAdmin();

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:191',
                Rule::unique('amc_plans', 'name')->where(fn ($query) => $query->where('company_id', (int) $user->company_id)),
            ],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'service_mode' => ['required', Rule::in(array_keys(AmcPlan::SERVICE_MODE_OPTIONS))],
            'visit_count' => ['required', 'integer', 'min:0', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['service_mode'] === 'on_call') {
            $validated['visit_count'] = 0;
        } elseif ($validated['visit_count'] < 1) {
            return back()
                ->withErrors(['visit_count' => 'Scheduled and hybrid plans must have at least 1 visit.'])
                ->withInput();
        }

        AmcPlan::create([
            'name' => $validated['name'],
            'duration_months' => $validated['duration_months'],
            'visit_count' => $validated['visit_count'],
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'includes_on_call_support' => in_array($validated['service_mode'], ['on_call', 'hybrid'], true),
            'service_mode' => $validated['service_mode'],
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return redirect()
            ->route('amc-plans.index')
            ->with('success', 'AMC plan created successfully.');
    }

    public function update(Request $request, AmcPlan $amcPlan)
    {
        $user = $this->ensureCompanyAdmin();
        abort_unless((int) $amcPlan->company_id === (int) $user->company_id, 403);

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:191',
                Rule::unique('amc_plans', 'name')
                    ->ignore($amcPlan->id)
                    ->where(fn ($query) => $query->where('company_id', (int) $user->company_id)),
            ],
            'duration_months' => ['required', 'integer', 'min:1', 'max:120'],
            'service_mode' => ['required', Rule::in(array_keys(AmcPlan::SERVICE_MODE_OPTIONS))],
            'visit_count' => ['required', 'integer', 'min:0', 'max:120'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999999.99'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($validated['service_mode'] === 'on_call') {
            $validated['visit_count'] = 0;
        } elseif ($validated['visit_count'] < 1) {
            return back()
                ->withErrors(['visit_count' => 'Scheduled and hybrid plans must have at least 1 visit.'])
                ->withInput();
        }

        $amcPlan->update([
            'name' => $validated['name'],
            'duration_months' => $validated['duration_months'],
            'visit_count' => $validated['visit_count'],
            'price' => $validated['price'],
            'description' => $validated['description'] ?? null,
            'includes_on_call_support' => in_array($validated['service_mode'], ['on_call', 'hybrid'], true),
            'service_mode' => $validated['service_mode'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return redirect()
            ->route('amc-plans.index')
            ->with('success', 'AMC plan updated successfully.');
    }

    public function destroy(AmcPlan $amcPlan)
    {
        $user = $this->ensureCompanyAdmin();
        abort_unless((int) $amcPlan->company_id === (int) $user->company_id, 403);

        $amcPlan->delete();

        return redirect()
            ->route('amc-plans.index')
            ->with('success', 'AMC plan deleted successfully.');
    }

    private function ensureCompanyAdmin()
    {
        $user = auth()->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admin can manage AMC plans.');

        return $user;
    }
}
