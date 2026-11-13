<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLookupController extends Controller
{
    public function gstRates(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 401, 'Unauthenticated.');

        $companyId = (int) $user->company_id;

        $gstRates = CompanyGstRate::query()
            ->where('company_id', $companyId)
            ->orderBy('rate')
            ->get(['id', 'rate'])
            ->map(fn (CompanyGstRate $gstRate) => [
                'id' => $gstRate->id,
                'rate' => (float) $gstRate->rate,
                'label' => rtrim(rtrim(number_format((float) $gstRate->rate, 2, '.', ''), '0'), '.') . '%',
            ])
            ->values();

        return response()->json([
            'message' => 'GST rate list retrieved successfully.',
            'data' => $gstRates,
        ]);
    }

    public function units(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 401, 'Unauthenticated.');

        $companyId = (int) $user->company_id;

        $units = CompanyUnit::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (CompanyUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
            ])
            ->values();

        return response()->json([
            'message' => 'Unit list retrieved successfully.',
            'data' => $units,
        ]);
    }
}
