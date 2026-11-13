<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyLeadController extends Controller
{
    /**
     * Get leads for the logged-in user's company.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 401);

        abort_unless(
            $user->company_id,
            403,
            'User is not associated with a company.'
        );

        $query = Lead::query()
            ->where('company_id', $user->company_id)
            ->with([
                'status:id,name',
                'category:id,name',
                'assignedTo:id,name,email',
                'state:id,name',
                'city:id,name',
            ])
            ->latest('id');

        // Search
        if ($request->filled('search')) {
            $search = trim($request->input('search'));

            $query->where(function ($q) use ($search) {
                $q->where('client_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('gst_number', 'like', "%{$search}%");
            });
        }

        // Status filter
        if ($request->filled('lead_status_id')) {
            $query->where(
                'lead_status_id',
                $request->input('lead_status_id')
            );
        }

        // Category filter
        if ($request->filled('lead_category_id')) {
            $query->where(
                'lead_category_id',
                $request->input('lead_category_id')
            );
        }

        // Assigned user filter
        if ($request->filled('assigned_to')) {
            $query->where(
                'assigned_to',
                $request->input('assigned_to')
            );
        }

        // Get all leads without pagination
        $leads = $query->get();

        return response()->json([
            'success' => true,
            'message' => 'Leads fetched successfully.',
            'data' => $leads,
            'total' => $leads->count(),
        ]);
    }
}