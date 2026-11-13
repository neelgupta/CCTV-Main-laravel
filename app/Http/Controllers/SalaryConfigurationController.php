<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SalaryConfiguration;
use App\Models\StaffActivity;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;

class SalaryConfigurationController extends Controller
{
    public function store(Request $request)
    {
        $staff = $this->resolveStaffOrFail($request->input('staff_id'));
        $this->ensureCanAccessStaff($staff);

        $validated = $request->validate([
            'basic_salary' => 'required|numeric|min:0',
            'hra' => 'nullable|numeric|min:0',
            // 'allowances' => 'nullable|array',
            // 'allowances.*' => 'nullable|numeric|min:0',
            'allowances' => 'nullable|numeric|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $salary = SalaryConfiguration::updateOrCreate(
            ['staff_id' => $staff->id],
            [
                'basic_salary' => $validated['basic_salary'],
                'hra' => $validated['hra'] ?? 0,
                'allowances' => $validated['allowances'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]
        );

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Salary Configuration',
            'description' => 'Staff Salary Configuration',
            'occurred_at' => now(),
        ]);

        return redirect()->route('teams.staff.show', $staff)->with('success', 'Salary details updated successfully.');
    }

    public function show(string $id)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);

        $salary = SalaryConfiguration::where('staff_id', $staff->id)->first();
        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Salary View',
            'description' => 'Checks Staff Salary Details',
            'occurred_at' => now(),
        ]);

        return response()->json($salary);
    }

    private function resolveStaffOrFail(string|int|null $staffId): Staff
    {
        $staffId = Staff::decodeRouteKeyValue($staffId);

        return Staff::query()
            ->where('company_id', (int) Auth::user()->company_id)
            ->findOrFail($staffId);
    }

    private function ensureCanAccessStaff(Staff $staff): void
    {
        $user = Auth::user();
        if ($user->isCompanyAdmin()) {
            return;
        }

        if (!$user->hasPermission('teams.staff.self.manage') || (int) $staff->user_id !== (int) $user->id) {
            abort(403, 'You can manage only your own details.');
        }
    }
}
