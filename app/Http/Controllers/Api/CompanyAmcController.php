<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAmcController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access AMC records.');

        $companyId = (int) $user->company_id;
        $perPage = max(1, min(50, (int) $request->integer('per_page', 15)));
        $search = trim((string) $request->query('search', ''));

        $query = Amc::query()
            ->where('company_id', $companyId)
            ->with([
                'client:id,name,email,mobile,phone,company_id',
                'project:id,name,uuid,company_id',
                'amcPlan:id,name,duration_months,price,company_id',
                'assignedStaff:id,name,contact_number,company_id',
                'assignedStaff.user:id,name,email',
                'visits:id,amc_id,visit_no,visit_date,status,technician',
            ])
            ->latest();

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->where('contract_no', 'like', $like)
                    ->orWhere('customer_name', 'like', $like)
                    ->orWhere('project_name', 'like', $like)
                    ->orWhere('mobile_number', 'like', $like)
                    ->orWhere('email', 'like', $like);
            });
        }

        $amcs = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'message' => 'AMC records retrieved successfully.',
            'data' => $amcs->items(),
            'meta' => [
                'current_page' => $amcs->currentPage(),
                'last_page' => $amcs->lastPage(),
                'per_page' => $amcs->perPage(),
                'total' => $amcs->total(),
            ],
        ]);
    }
}
