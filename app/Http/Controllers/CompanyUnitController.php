<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyUnit;
use App\Models\BoqProduct;
use Illuminate\Http\Request;

class CompanyUnitController extends Controller
{
    private function company(): ?Company
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        return $user->company ?? $user->roleRecord?->company;
    }

    public function store(Request $request)
    {
        $company = $this->company();
        abort_unless($company, 403);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:50',
                'unique:company_units,name,NULL,id,company_id,' . $company->id,
            ],
        ]);

        CompanyUnit::create([
            'company_id' => $company->id,
            'name' => trim($data['name']),
        ]);

        return back()->with('success', 'Unit added successfully.');
    }

    public function update(Request $request, CompanyUnit $unit)
    {
        $company = $this->company();
        abort_unless($company && (int) $unit->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:1',
                'max:50',
                'unique:company_units,name,' . $unit->id . ',id,company_id,' . $company->id,
            ],
        ]);

        $unit->update([
            'name' => trim($data['name']),
        ]);

        return back()->with('success', 'Unit updated successfully.');
    }

    public function destroy(CompanyUnit $unit)
    {
        $company = $this->company();
        abort_unless($company && (int) $unit->company_id === (int) $company->id, 403);

        $isUsedInProducts = BoqProduct::query()
            ->where('company_id', $company->id)
            ->where('unit', $unit->name)
            ->exists();

        if ($isUsedInProducts) {
            return back()->withErrors([
                'unit' => 'This unit is already used in BOQ products. Update those products first.',
            ]);
        }

        $unit->delete();

        return back()->with('success', 'Unit deleted successfully.');
    }
}
