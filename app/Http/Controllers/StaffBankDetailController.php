<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StaffBankDetails;
use App\Models\StaffActivity;
use App\Models\Staff;
use Illuminate\Support\Facades\Auth;

class StaffBankDetailController extends Controller
{
    public function store(Request $request)
    {
        $staff = $this->resolveStaffOrFail($request->input('staff_id'));
        $this->ensureCanAccessStaff($staff);

        $validated = $request->validate([
            'bank_name' => 'required|string|min:2|max:191',
            'account_number' => 'required|string|min:9|max:18',
            'ifsc' => 'required|string|size:11',
            'branch' => 'nullable|string|min:2|max:191',
        ]);

        $validated['bank_name'] = trim((string) $validated['bank_name']);
        $validated['account_number'] = trim((string) $validated['account_number']);
        $validated['ifsc'] = strtoupper(trim((string) $validated['ifsc']));
        $validated['branch'] = filled($validated['branch'] ?? null)
            ? trim((string) $validated['branch'])
            : null;

        $bankDetail = StaffBankDetails::updateOrCreate(
            ['staff_id' => $staff->id],
            $validated
        );

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Bank Details',
            'description' => 'Staff Bank Details Updated',
            'occurred_at' => now(),
        ]);

        return redirect()->route('teams.staff.show', $staff)->with('success', 'Bank details updated successfully.');
    }

    public function show(string $id)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);

        $bankDetail = StaffBankDetails::where('staff_id', $staff->id)->first();
        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Bank Details',
            'description' => 'Staff Bank Details Showed',
            'occurred_at' => now(),
        ]);

        return response()->json($bankDetail);
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
