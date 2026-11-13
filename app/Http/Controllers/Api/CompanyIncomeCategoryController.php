<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IncomeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyIncomeCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can view income categories.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.categories.manage'), 403, 'You are not allowed to view income categories.');

        $companyId = (int) $user->company_id;

        $categories = IncomeCategory::query()
            ->where('company_id', $companyId)
            ->when(filled($request->query('status')), fn ($builder) => $builder->where('status', $request->query('status')))
            ->when(filled($request->query('search')), fn ($builder) => $builder->where('name', 'like', '%' . trim((string) $request->query('search')) . '%'))
            ->orderBy('name')
            ->get();

        return response()->json([
            'message' => 'Income categories fetched successfully.',
            'data' => [
                'categories' => $categories,
            ],
        ]);
    }
}
