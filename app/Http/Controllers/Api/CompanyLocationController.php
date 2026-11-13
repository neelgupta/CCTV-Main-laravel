<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyLocationController extends Controller
{
    public function states(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access state list.');

        $states = State::query()
            ->withCount('cities')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (State $state) => [
                'id' => $state->id,
                'name' => $state->name,
                'cities_count' => (int) ($state->cities_count ?? 0),
            ])
            ->values();

        return response()->json([
            'message' => 'State list retrieved successfully.',
            'data' => $states,
        ]);
    }

    public function cities(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access city list.');

        $validated = $request->validate([
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
        ], [
            'state_id.integer' => 'State id must be a valid number.',
            'state_id.exists' => 'Selected state is not available.',
        ]);

        $cities = City::query()
            ->when(isset($validated['state_id']), fn ($query) => $query->where('state_id', (int) $validated['state_id']))
            ->with('state:id,name')
            ->orderBy('name')
            ->get(['id', 'state_id', 'name'])
            ->map(fn (City $city) => [
                'id' => $city->id,
                'state_id' => $city->state_id,
                'name' => $city->name,
                'state' => $city->state ? [
                    'id' => $city->state->id,
                    'name' => $city->state->name,
                ] : null,
            ])
            ->values();

        return response()->json([
            'message' => 'City list retrieved successfully.',
            'data' => $cities,
        ]);
    }
}
