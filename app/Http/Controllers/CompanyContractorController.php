<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contractor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyContractorController extends Controller
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

        $data = $this->validatedData($request, $company);

        Contractor::create(array_merge($data, [
            'company_id' => $company->id,
        ]));

        return back()->with('success', 'Contractor added successfully.');
    }

    public function update(Request $request, Contractor $contractor)
    {
        $company = $this->company();
        abort_unless($company && (int) $contractor->company_id === (int) $company->id, 403);

        $data = $this->validatedData($request, $company, $contractor);

        $contractor->update($data);

        return back()->with('success', 'Contractor updated successfully.');
    }

    public function destroy(Contractor $contractor)
    {
        $company = $this->company();
        abort_unless($company && (int) $contractor->company_id === (int) $company->id, 403);

        $contractor->delete();

        return back()->with('success', 'Contractor deleted successfully.');
    }

    private function validatedData(Request $request, Company $company, ?Contractor $contractor = null): array
    {
        $contractorId = $contractor?->id;

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'min:2',
                'max:191',
                Rule::unique('contractors', 'name')
                    ->where(fn ($query) => $query->where('company_id', $company->id))
                    ->ignore($contractorId),
            ],
            'phone_number' => ['required', 'regex:/^\d{10}$/'],
            'email' => 'required|email|max:191',
            'referral_name' => 'nullable|string|min:2|max:191',
            'address' => 'nullable|string|max:1000',
            'notes' => 'nullable|string|max:2000',
        ]);

        $data['name'] = trim((string) $data['name']);
        $data['phone_number'] = preg_replace('/\D+/', '', (string) $data['phone_number']);
        $data['email'] = trim((string) $data['email']);
        $data['referral_name'] = filled($data['referral_name'] ?? null) ? trim((string) $data['referral_name']) : null;
        $data['address'] = filled($data['address'] ?? null) ? trim((string) $data['address']) : null;
        $data['notes'] = filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null;

        return $data;
    }
}
