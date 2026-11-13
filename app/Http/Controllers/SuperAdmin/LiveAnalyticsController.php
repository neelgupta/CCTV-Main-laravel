<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveAnalyticsController extends Controller
{
    public function __construct(private AnalyticsService $analytics)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $filters = $this->resolveFilters($request);

        return view('super-admin.live-analytics', [
            'filters' => $filters,
            'companies' => Company::query()->orderBy('name')->get(['id', 'name']),
            'initialData' => $this->analytics->getDashboardData($filters),
        ]);
    }

    public function data(Request $request): JsonResponse
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return response()->json($this->analytics->getDashboardData($this->resolveFilters($request)));
    }

    private function resolveFilters(Request $request): array
    {
        $validated = $request->validate([
            'period' => ['nullable', 'in:today,weekly,monthly'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
        ]);

        return [
            'period' => $validated['period'] ?? 'weekly',
            'company_id' => isset($validated['company_id']) ? (int) $validated['company_id'] : null,
        ];
    }
}
