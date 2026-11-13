<?php

namespace App\Http\Controllers;

use App\Models\BoqProduct;
use App\Models\Company;
use App\Models\CompanyGstRate;
use Illuminate\Http\Request;

class CompanyGstRateController extends Controller
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
            'rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'unique:company_gst_rates,rate,NULL,id,company_id,' . $company->id,
            ],
        ]);

        CompanyGstRate::create([
            'company_id' => $company->id,
            'rate' => round((float) $data['rate'], 2),
        ]);

        return back()->with('success', 'GST rate added successfully.');
    }

    public function update(Request $request, CompanyGstRate $gstRate)
    {
        $company = $this->company();
        abort_unless($company && (int) $gstRate->company_id === (int) $company->id, 403);

        $data = $request->validate([
            'rate' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                'unique:company_gst_rates,rate,' . $gstRate->id . ',id,company_id,' . $company->id,
            ],
        ]);

        $gstRate->update([
            'rate' => round((float) $data['rate'], 2),
        ]);

        return back()->with('success', 'GST rate updated successfully.');
    }

    public function destroy(CompanyGstRate $gstRate)
    {
        $company = $this->company();
        abort_unless($company && (int) $gstRate->company_id === (int) $company->id, 403);

        $isUsedInProducts = BoqProduct::query()
            ->where('company_id', $company->id)
            ->where('gst', $gstRate->rate)
            ->exists();

        if ($isUsedInProducts) {
            return back()->withErrors([
                'gst_rate' => 'This GST rate is already used in BOQ products. Update those products first.',
            ]);
        }

        $gstRate->delete();

        return back()->with('success', 'GST rate deleted successfully.');
    }
}
