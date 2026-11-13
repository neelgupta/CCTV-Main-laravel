<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StaffLoginRequest;
use App\Models\User;
use App\Services\StaffAppActivityService;
use App\Support\StaffMobileTokenStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAuthController extends Controller
{
    public function __construct(
        private readonly StaffMobileTokenStore $tokenStore,
        private readonly StaffAppActivityService $activityService
    ) {
    }

    public function login(StaffLoginRequest $request): JsonResponse
    {
        $user = $request->authenticateEmployee();
        $token = $this->tokenStore->issue($user, $request->deviceName());
        $this->activityService->syncFromRequest($user, $request, 'login');

        return response()->json([
            'message' => 'Staff login successful.',
            'token_type' => 'Bearer',
            'access_token' => $token['plain_text_token'],
            'user' => $this->formatUser($user, $token['payload']),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tokenPayload = $request->attributes->get('staff_api_token');

        return response()->json([
            'user' => $this->formatUser($user, is_array($tokenPayload) ? $tokenPayload : null),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokenStore->forget((string) $request->bearerToken());

        return response()->json([
            'message' => 'Staff logout successful.',
        ]);
    }

    private function formatUser(User $user, ?array $tokenPayload = null): array
    {
        $user->loadMissing(['company', 'staff', 'roleRecord.permissions']);
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->normalizedRole(),
            'permissions' => $this->modulePermissions($user),
            'permission_keys' => $this->flatPermissionKeys($user),
            'profile_photo_url' => $user->profile_photo_url,
            'company' => [
                'id' => $user->company?->id,
                'name' => $user->company?->name,
                'company_code' => $user->company?->company_code,
                'logo_url' => $user->company?->logo_url,
                'enable_quotation_generator' => (bool) $user->company?->enable_quotation_generator,
                'quotation_enabled' => (bool) $user->company?->enable_quotation_generator,
                'invoice_enabled' => $this->isInvoiceEnabled($user),
                'invoice_prefix' => $user->company?->invoice_prefix,
                'invoice_year' => $user->company?->invoice_year,
                'quotation_prefix' => $user->company?->quotation_prefix,
                'quotation_year' => $user->company?->quotation_year,
                'manual_product_entry' => (bool) $user->company?->manual_product_entry,
            ],
            'staff' => [
                'id' => $user->staff?->id,
                'employee_id' => $user->staff?->employee_id,
                'department' => $user->staff?->department,
                'contact_number' => $user->staff?->contact_number,
                'join_date' => $user->staff?->join_date,
                'profile_photo_url' => $user->staff?->profile_photo_url,
            ],
            
            // 'token' => $tokenPayload ? [
            //     'name' => $tokenPayload['name'] ?? null,
            //     'issued_at' => $tokenPayload['issued_at'] ?? null,
            //     'last_used_at' => $tokenPayload['last_used_at'] ?? null,
            //     'expires_at' => $tokenPayload['expires_at'] ?? null,
            // ] : null,
        ];
    }

    private function isInvoiceEnabled(User $user): bool
    {
        $company = $user->company;

        if (! $company) {
            return false;
        }

        return filled(trim((string) $company->invoice_prefix))
            && filled($company->invoice_year)
            && (int) ($company->invoice_sequence ?? 0) > 0;
    }

    private function modulePermissions(User $user): array
    {
        return [
            'leads' => [
                'view' => $user->hasPermission('leads.view'),
                'create' => $user->hasPermission('leads.create'),
                'edit' => $user->hasPermission('leads.edit'),
                'delete' => $user->hasPermission('leads.delete'),
                'status_update' => $user->hasPermission('leads.status.update'),
                'comment' => $user->hasPermission('leads.comment'),
                'quotation_manage' => $user->hasPermission('leads.quotation.manage'),
                'survey_upload' => $user->hasPermission('leads.survey.upload'),
                'documents_upload' => $user->hasPermission('leads.documents.upload'),
                'summary_download' => $user->hasPermission('leads.summary.download'),
            ],
            'projects' => [
                'view' => $user->hasPermission('projects.view'),
                'create' => $user->hasPermission('projects.create'),
                'edit' => $user->hasPermission('projects.edit'),
                'delete' => $user->hasPermission('projects.delete'),
                'stage_update' => $user->hasPermission('projects.stage.update'),
                'assets_manage' => $user->hasPermission('projects.assets.manage'),
                'comment' => $user->hasPermission('projects.comment'),
            ],
            'amc' => [
                'view' => $user->hasPermission('amc.view'),
                'create' => $user->hasPermission('amc.create'),
                'edit' => $user->hasPermission('amc.edit'),
                'delete' => $user->hasPermission('amc.delete'),
            ],
            'support' => [
                'view' => $user->hasPermission('support.view'),
                'create' => $user->hasPermission('support.create'),
                'edit' => $user->hasPermission('support.edit'),
                'delete' => $user->hasPermission('support.delete'),
            ],
            'tasks' => [
                'view' => $user->hasPermission('tasks.view'),
                'manage' => $user->hasPermission('tasks.manage'),
            ],
            'accounting' => [
                // 'dashboard_view' => $user->hasPermission('accounting.dashboard.view'),
                'incomes_view' => $user->hasPermission('accounting.incomes.view'),
                'incomes_create' => $user->hasPermission('accounting.incomes.create'),
                'incomes_edit' => $user->hasPermission('accounting.incomes.edit'),
                'incomes_delete' => $user->hasPermission('accounting.incomes.delete'),
                'expenses_view' => $user->hasPermission('accounting.expenses.view'),
                'expenses_create' => $user->hasPermission('accounting.expenses.create'),
                'expenses_edit' => $user->hasPermission('accounting.expenses.edit'),
                'expenses_delete' => $user->hasPermission('accounting.expenses.delete'),
                'categories_manage' => $user->hasPermission('accounting.categories.manage'),
                // 'reports_view' => $user->hasPermission('accounting.reports.view'),
            ],
        ];
    }

    private function flatPermissionKeys(User $user): array
    {
        return [
            'leads.view' => $user->hasPermission('leads.view'),
            'leads.create' => $user->hasPermission('leads.create'),
            'leads.edit' => $user->hasPermission('leads.edit'),
            'leads.delete' => $user->hasPermission('leads.delete'),
            'leads.status.update' => $user->hasPermission('leads.status.update'),
            'leads.comment' => $user->hasPermission('leads.comment'),
            'leads.quotation.manage' => $user->hasPermission('leads.quotation.manage'),
            'leads.survey.upload' => $user->hasPermission('leads.survey.upload'),
            'leads.documents.upload' => $user->hasPermission('leads.documents.upload'),
            'leads.summary.download' => $user->hasPermission('leads.summary.download'),
            'projects.view' => $user->hasPermission('projects.view'),
            'projects.create' => $user->hasPermission('projects.create'),
            'projects.edit' => $user->hasPermission('projects.edit'),
            'projects.delete' => $user->hasPermission('projects.delete'),
            'projects.stage.update' => $user->hasPermission('projects.stage.update'),
            'projects.assets.manage' => $user->hasPermission('projects.assets.manage'),
            'projects.comment' => $user->hasPermission('projects.comment'),
            'amc.view' => $user->hasPermission('amc.view'),
            'amc.create' => $user->hasPermission('amc.create'),
            'amc.edit' => $user->hasPermission('amc.edit'),
            'amc.delete' => $user->hasPermission('amc.delete'),
            'support.view' => $user->hasPermission('support.view'),
            'support.create' => $user->hasPermission('support.create'),
            'support.edit' => $user->hasPermission('support.edit'),
            'support.delete' => $user->hasPermission('support.delete'),
            'tasks.view' => $user->hasPermission('tasks.view'),
            'tasks.manage' => $user->hasPermission('tasks.manage'),
            // 'accounting.dashboard.view' => $user->hasPermission('accounting.dashboard.view'),
            'accounting.incomes.view' => $user->hasPermission('accounting.incomes.view'),
            'accounting.incomes.create' => $user->hasPermission('accounting.incomes.create'),
            'accounting.incomes.edit' => $user->hasPermission('accounting.incomes.edit'),
            'accounting.incomes.delete' => $user->hasPermission('accounting.incomes.delete'),
            'accounting.expenses.view' => $user->hasPermission('accounting.expenses.view'),
            'accounting.expenses.create' => $user->hasPermission('accounting.expenses.create'),
            'accounting.expenses.edit' => $user->hasPermission('accounting.expenses.edit'),
            'accounting.expenses.delete' => $user->hasPermission('accounting.expenses.delete'),
            'accounting.categories.manage' => $user->hasPermission('accounting.categories.manage'),
            // 'accounting.reports.view' => $user->hasPermission('accounting.reports.view'),
        ];
    }
}
