<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\State;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InquiryApiController extends Controller
{
    /**
     * Store a new inquiry / demo request from an external landing page.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        // Normalize input keys so flexible field names are accepted
        $data = [
            'name' => $request->input('name') ?? $request->input('your_name') ?? $request->input('full_name'),
            'phone' => $request->input('phone') ?? $request->input('mobile') ?? $request->input('mobile_number') ?? $request->input('phone_number'),
            'email' => $request->input('email') ?? $request->input('email_id'),
            'company_name' => $request->input('company_name') ?? $request->input('company'),
            'state' => $request->input('state') ?? $request->input('state_name'),
            'city' => $request->input('city') ?? $request->input('city_name'),
            'message' => $request->input('message') ?? $request->input('notes') ?? $request->input('remarks'),
            'source' => $request->input('source') ?? 'landing_page_demo',
        ];

        // Resolve state if state ID was sent instead of name
        if (!empty($data['state']) && is_numeric($data['state'])) {
            $stateModel = State::find($data['state']);
            if ($stateModel) {
                $data['state'] = $stateModel->name;
            }
        }

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'string', 'max:255'],
        ], [
            'name.required' => 'Please provide your name.',
            'phone.required' => 'Please provide your mobile number.',
            'email.email' => 'Please provide a valid email address.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        $inquiry = Inquiry::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
            'company_name' => $validated['company_name'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'message' => $validated['message'] ?? null,
            'source' => $validated['source'] ?? 'landing_page_demo',
            'status' => Inquiry::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Auto-create / fetch lead into target company (e.g. Sourcecube CCTV Pro One)
        $lead = null;
        $targetCompany = null;
        try {
            $syncService = app(\App\Services\InquiryLeadSyncService::class);
            $targetCompany = $syncService->resolveTargetCompany(
                $request->input('company_code') ?? $request->input('target_company_code')
            );
            if ($targetCompany) {
                $lead = $syncService->createLeadFromInquiry($inquiry, $targetCompany);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('InquiryApiController: Failed to auto-create lead from inquiry: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you! Your demo request has been received. Our team will contact you shortly.',
            'data' => [
                'id' => $inquiry->id,
                'lead_id' => $lead?->id,
                'assigned_to' => $lead?->assigned_to,
                'assigned_user_name' => $lead?->assignedTo?->name,
                'assigned_user_email' => $lead?->assignedTo?->email,
                'target_company' => $targetCompany?->name,
                'company_code' => $targetCompany?->company_code,
                'name' => $inquiry->name,
                'phone' => $inquiry->phone,
                'email' => $inquiry->email,
                'company_name' => $inquiry->company_name,
                'state' => $inquiry->state,
                'status' => $inquiry->status,
                'created_at' => $inquiry->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Get list of states for dropdowns in external forms.
     *
     * @return JsonResponse
     */
    public function states(): JsonResponse
    {
        $states = State::query()->orderBy('name')->pluck('name');

        if ($states->isEmpty()) {
            // Default Indian states list if database states table is empty
            $states = collect([
                'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh',
                'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka',
                'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram',
                'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu',
                'Telangana', 'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal',
                'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
                'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $states,
        ]);
    }
}

