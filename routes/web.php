<?php

use App\Http\Controllers\CompanyCategoryController;
use App\Http\Controllers\CompanyMailSettingController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DailyStaffUpdateReportController;
use App\Http\Controllers\LetterheadSettingController;
use App\Http\Controllers\CompanyBoqController;
use App\Http\Controllers\CompanyContractorController;
use App\Http\Controllers\CompanyGstRateController;
use App\Http\Controllers\CompanyQuotationFormController;
use App\Http\Controllers\CompanyUnitController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\ClientAuthController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ClientQrController;
use App\Http\Controllers\CustomerQrComplaintController;
use App\Http\Controllers\CampaignBatchSettingsController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\AmcController;
use App\Http\Controllers\AmcPlanController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\LeadQuotationGeneratorController;
use App\Http\Controllers\LeadStatusController;
use App\Http\Controllers\MarketingEmailTemplateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectStageController;
use App\Http\Controllers\QuotationCustomFieldController;
use App\Http\Controllers\QuotationFormEntryController;
use App\Http\Controllers\QuotationFormSubmissionController;
use App\Http\Controllers\QuotationTemplateController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalaryConfigurationController;
use App\Http\Controllers\ServiceStageController;
use App\Http\Controllers\StaffAttendanceController;
use App\Http\Controllers\StaffBankDetailController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\StaffTaskController;
use App\Http\Controllers\StaffAssignedTaskController;
use App\Http\Controllers\StaffRouteTrackingController;
use App\Http\Controllers\StaffPayrollController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\ServiceCallController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\DashboardNotificationController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\PlatformIntegrationController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\SuperAdminController;
use App\Http\Controllers\SuperAdmin\CompanyAppUsageController;
use App\Http\Controllers\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\SuperAdmin\DatabaseBackupController;
use App\Http\Controllers\SuperAdmin\LiveAnalyticsController;
use App\Http\Controllers\SuperAdmin\InquiryController as SuperAdminInquiryController;
use App\Http\Controllers\Api\StaffTicketController as ApiStaffTicketController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;

use Illuminate\Support\Facades\Route;

Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('welcome');

Route::get('/service-worker.js', function (\Illuminate\Http\Request $request) {
    // Only permit actual browser ServiceWorker engine requests, block direct address bar visits
    $isServiceWorker = strcasecmp((string) $request->header('Service-Worker'), 'script') === 0
        || strcasecmp((string) $request->header('Sec-Fetch-Dest'), 'serviceworker') === 0;

    if (! $isServiceWorker) {
        abort(404);
    }

    $path = resource_path('service-worker.js');
    if (! file_exists($path)) {
        abort(404);
    }

    return response()->file($path, [
        'Content-Type' => 'application/javascript; charset=UTF-8',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Service-Worker-Allowed' => '/',
    ]);
});

// Route::get('/firebase-config.js', function () {
//     $firebase = config('services.firebase');

//     $payload = array_filter([
//         'apiKey' => $firebase['api_key'] ?? null,
//         'authDomain' => filled($firebase['project_id'] ?? null) ? ($firebase['project_id'] . '.firebaseapp.com') : null,
//         'projectId' => $firebase['project_id'] ?? null,
//         'messagingSenderId' => $firebase['messaging_sender_id'] ?? null,
//         'appId' => $firebase['app_id'] ?? null,
//     ], fn ($value) => filled($value));

//     $js = 'self.FIREBASE_CONFIG = ' . json_encode($payload ?: null, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';';

//     return response($js, 200, [
//         'Content-Type' => 'application/javascript; charset=UTF-8',
//         'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
//     ]);
// });

Route::get('/ticket-photos/{ticket}/{collection}/{index}', [ApiStaffTicketController::class, 'openPhoto'])
    ->whereNumber('index')
    ->name('ticket-photos.open');

Route::get('/ticket-signature/{ticket}', [ApiStaffTicketController::class, 'openSignature'])
    ->name('ticket-signature.open');

// Prevent static paths like /leads/create from being captured by UUID model routes.
// Supports both UUID and numeric IDs (for backward compatibility).
Route::pattern('lead', '([0-9a-fA-F\-]{36}|[0-9]+)');
Route::pattern('project', '([0-9a-fA-F\-]{36}|[0-9]+)');

Route::middleware('guest')->group(function () {
    Route::get('/client/login', [ClientAuthController::class, 'create'])->name('client.login');
    Route::post('/client/login', [ClientAuthController::class, 'store'])->name('client.login.store');
});

Route::get('/customer/support/{token}', [CustomerQrComplaintController::class, 'show'])
    ->name('customer.support.qr.show');
Route::post('/customer/support/{token}', [CustomerQrComplaintController::class, 'store'])
    ->name('customer.support.qr.store')
    ->middleware('throttle:20,1');

Route::middleware(['auth', 'role:client'])->group(function () {
    Route::get('/client/portal', [ClientAuthController::class, 'portal'])->name('client.portal');
    Route::post('/client/logout', [ClientAuthController::class, 'destroy'])->name('client.logout');
});

Route::middleware(['auth', 'active', 'subscription.access'])->group(function () {
    // Onboarding Routes
    Route::get('/onboarding', [OnboardingController::class, 'index'])->name('onboarding.index');
    Route::post('/onboarding/step-1', [OnboardingController::class, 'storeStep1'])->name('onboarding.step1');
    Route::post('/onboarding/step-2', [OnboardingController::class, 'storeStep2'])->name('onboarding.step2');
    Route::post('/onboarding/step-3', [OnboardingController::class, 'storeStep3'])->name('onboarding.step3');
    Route::get('/onboarding/step-4', [OnboardingController::class, 'step4'])->name('onboarding.step4');
    Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');
    Route::post('/onboarding/skip', [OnboardingController::class, 'skip'])->name('onboarding.skip');
    Route::get('/onboarding/payment', [OnboardingController::class, 'payment'])->name('onboarding.payment');
    Route::post('/onboarding/payment', [OnboardingController::class, 'finalizePayment'])->name('onboarding.payment.finalize');

    Route::middleware(['onboarding'])->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/dashboard/calendar', [DashboardController::class, 'calendar'])->name('dashboard.calendar');
        Route::get('/dashboard/calendar-events', [DashboardController::class, 'calendarEvents'])->name('dashboard.calendar-events.index');
        Route::post('/dashboard/calendar-events', [DashboardController::class, 'storeCalendarEvent'])->name('dashboard.calendar-events.store');
        Route::patch('/dashboard/calendar-events/{calendarEvent}', [DashboardController::class, 'updateCalendarEvent'])->name('dashboard.calendar-events.update');
        Route::delete('/dashboard/calendar-events/{calendarEvent}', [DashboardController::class, 'destroyCalendarEvent'])->name('dashboard.calendar-events.destroy');
        Route::get('/my-tasks', [StaffAssignedTaskController::class, 'index'])->name('staff.tasks.index');
        Route::get('/my-tasks/create', [StaffAssignedTaskController::class, 'create'])->name('staff.tasks.create');
        Route::post('/my-tasks', [StaffAssignedTaskController::class, 'store'])->name('staff.tasks.store');
        Route::get('/my-tasks/{task}/edit', [StaffAssignedTaskController::class, 'edit'])->name('staff.tasks.edit');
        Route::patch('/my-tasks/{task}', [StaffAssignedTaskController::class, 'update'])->name('staff.tasks.update');
        Route::delete('/my-tasks/{task}', [StaffAssignedTaskController::class, 'destroy'])->name('staff.tasks.destroy');
        Route::patch('/my-tasks/{task}/status', [StaffAssignedTaskController::class, 'updateStatus'])->name('staff.tasks.status.update');
    });

    Route::patch('/notifications/{dashboardNotification}/status', [DashboardNotificationController::class, 'updateStatus'])->name('notifications.status.update');
    Route::post('/notifications/mark-all-read', [DashboardNotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
});

Route::middleware(['auth', 'active', 'subscription.access', 'setup.access'])->group(function () {
    Route::get('/super-admin', [SuperAdminController::class, 'dashboard'])->name('super-admin.dashboard');
    Route::get('/superadmin/live-analytics', [LiveAnalyticsController::class, 'index'])->name('superadmin.live-analytics');
    Route::get('/superadmin/live-analytics/data', [LiveAnalyticsController::class, 'data'])->name('superadmin.live-analytics.data');
    Route::get('/admin/locations', [SuperAdminController::class, 'locations'])->name('super-admin.locations');
    Route::get('/admin/settings', [SuperAdminSettingsController::class, 'index'])->name('super-admin.settings.index');
    Route::patch('/admin/settings', [SuperAdminSettingsController::class, 'update'])->name('super-admin.settings.update');
    Route::post('/admin/locations/states', [SuperAdminController::class, 'storeState'])->name('super-admin.states.store');
    Route::patch('/admin/locations/states/{state}', [SuperAdminController::class, 'updateState'])->name('super-admin.states.update');
    Route::post('/admin/locations/cities', [SuperAdminController::class, 'storeCity'])->name('super-admin.cities.store');
    Route::delete('/admin/locations/cities/{city}', [SuperAdminController::class, 'destroyCity'])->name('super-admin.cities.destroy');
    Route::get('/admin/subscriptions', [SuperAdminController::class, 'subscriptions'])->name('super-admin.subscriptions');
    Route::post('/super-admin/plans', [SuperAdminController::class, 'storePlan'])->name('super-admin.plans.store');
    Route::patch('/super-admin/plans/{plan}', [SuperAdminController::class, 'updatePlan'])->name('super-admin.plans.update');
    Route::delete('/super-admin/plans/{plan}', [SuperAdminController::class, 'destroyPlan'])->name('super-admin.plans.destroy');
    Route::post('/admin/subscriptions/assign', [SuperAdminController::class, 'assignSubscription'])->name('super-admin.subscriptions.assign');
    Route::get('/admin/companies', [SuperAdminController::class, 'companies'])->name('super-admin.companies');
    Route::get('/admin/companies/create', [SuperAdminController::class, 'createCompany'])->name('super-admin.companies.create');
    Route::post('/admin/companies/payment-order', [SuperAdminController::class, 'createPaymentOrder'])->name('super-admin.companies.payment-order');
    Route::post('/admin/companies', [SuperAdminController::class, 'storeCompany'])->name('super-admin.companies.store');
    Route::get('/admin/inactive-companies', [SuperAdminController::class, 'inactiveCompanies'])->name('super-admin.companies.inactive');
    Route::get('/admin/company-app-usage', [CompanyAppUsageController::class, 'index'])->name('super-admin.company-app-usage.index');
    Route::get('/admin/company-app-usage/{company}', [CompanyAppUsageController::class, 'show'])->name('super-admin.company-app-usage.show');
    Route::patch('/super-admin/companies/{company}/status', [SuperAdminController::class, 'toggleCompanyStatus'])->name('super-admin.companies.status');
    Route::patch('/super-admin/companies/{company}/password', [SuperAdminController::class, 'updateCompanyPassword'])->name('super-admin.companies.password');
    Route::patch('/super-admin/companies/{company}/storage', [SuperAdminController::class, 'updateCompanyStorageAddon'])->name('super-admin.companies.storage');
    Route::patch('/super-admin/companies/{company}/appointment-module', [SuperAdminController::class, 'toggleAppointmentModule'])->name('super-admin.companies.appointment-module');
    Route::patch('/super-admin/companies/{company}/create-company-from-lead', [SuperAdminController::class, 'toggleCreateCompanyFromLead'])->name('super-admin.companies.create-company-from-lead');
    Route::post('/admin/companies/{company}/impersonate', [SuperAdminController::class, 'impersonateCompany'])->name('super-admin.companies.impersonate');
    Route::post('/super-admin/return', [SuperAdminController::class, 'stopImpersonation'])->name('super-admin.return');
    Route::get('/admin/subscription-payments', [SuperAdminController::class, 'payments'])->name('super-admin.payments');
    Route::get('/admin/subscription-payments/{company}/invoices/download', [SuperAdminController::class, 'downloadPaymentInvoices'])->name('super-admin.payments.invoices.download');
    Route::get('/admin/subscription-payments/{company}', [SuperAdminController::class, 'paymentHistory'])->name('super-admin.payments.history');

    // Super Admin Inquiry / Demo Requests management
    Route::get('/admin/inquiries', [SuperAdminInquiryController::class, 'index'])->name('super-admin.inquiries.index');
    Route::post('/admin/inquiries/sync-all', [SuperAdminInquiryController::class, 'syncAll'])->name('super-admin.inquiries.sync-all');
    Route::post('/admin/inquiries/{inquiry}/sync-lead', [SuperAdminInquiryController::class, 'syncToLead'])->name('super-admin.inquiries.sync-lead');
    Route::patch('/admin/inquiries/{inquiry}/status', [SuperAdminInquiryController::class, 'updateStatus'])->name('super-admin.inquiries.status');
    Route::patch('/admin/inquiries/{inquiry}/notes', [SuperAdminInquiryController::class, 'updateNotes'])->name('super-admin.inquiries.notes');
    Route::delete('/admin/inquiries/{inquiry}', [SuperAdminInquiryController::class, 'destroy'])->name('super-admin.inquiries.destroy');
    Route::get('/admin/inquiries/export', [SuperAdminInquiryController::class, 'export'])->name('super-admin.inquiries.export');

    // Platform-level integration (OAuth app) configuration
    Route::get('/admin/integrations', [PlatformIntegrationController::class, 'index'])->name('super-admin.integrations');
    Route::post('/admin/integrations/google', [PlatformIntegrationController::class, 'updateGoogle'])->name('super-admin.integrations.google.update');

    Route::prefix('/admin/database-backups')->name('super-admin.database-backups.')->group(function () {
        Route::get('/', [DatabaseBackupController::class, 'index'])
            ->name('index')
            ->middleware('permission:database.backup.view');

        Route::post('/', [DatabaseBackupController::class, 'store'])
            ->name('store')
            ->middleware('permission:database.backup.manage');

        Route::post('/google-drive', [DatabaseBackupController::class, 'storeGoogleDrive'])
            ->name('google-drive.store')
            ->middleware('permission:database.backup.manage');

        Route::get('/{filename}/download', [DatabaseBackupController::class, 'download'])
            ->name('download')
            ->middleware('permission:database.backup.view');

        Route::delete('/{filename}', [DatabaseBackupController::class, 'destroy'])
            ->name('destroy')
            ->middleware('permission:database.backup.manage');
    });

    Route::resource('leads', LeadController::class)->only(['create', 'store'])->middleware(['permission:leads.create']);
    Route::resource('leads', LeadController::class)->only(['edit', 'update'])->middleware(['permission:leads.edit']);
    Route::resource('leads', LeadController::class)->only(['destroy'])->middleware(['permission:leads.delete']);
    Route::resource('leads', LeadController::class)->only(['index', 'show'])->middleware(['permission:leads.view']);
    Route::get('/leads/export', [LeadController::class, 'export'])->name('leads.export')->middleware(['permission:leads.view']);
    Route::post('/leads/import', [LeadController::class, 'import'])->name('leads.import')->middleware(['permission:leads.create']);
    Route::get('/leads/import/sample', [LeadController::class, 'downloadImportSample'])->name('leads.import.sample')->middleware(['permission:leads.create']);

    // Slot-wise Appointments module
    Route::get('/appointments', [\App\Http\Controllers\AppointmentController::class, 'index'])->name('appointments.index');
    Route::get('/appointments/calendar-events', [\App\Http\Controllers\AppointmentController::class, 'calendarEvents'])->name('appointments.calendar-events');
    Route::get('/appointments/slots-availability', [\App\Http\Controllers\AppointmentController::class, 'getSlotsAvailability'])->name('appointments.slots-availability');
    Route::post('/appointments', [\App\Http\Controllers\AppointmentController::class, 'store'])->name('appointments.store');
    Route::patch('/appointments/{appointment}/status', [\App\Http\Controllers\AppointmentController::class, 'updateStatus'])->name('appointments.status.update');
    Route::patch('/appointments/{appointment}/lead-status', [\App\Http\Controllers\AppointmentController::class, 'updateLeadStatus'])->name('appointments.lead-status.update');
    Route::patch('/appointments/agendas', [\App\Http\Controllers\AppointmentController::class, 'updateAgendas'])->name('appointments.agendas.update');
    Route::patch('/appointments/{appointment}/reschedule', [\App\Http\Controllers\AppointmentController::class, 'reschedule'])->name('appointments.reschedule');
    Route::delete('/appointments/{appointment}', [\App\Http\Controllers\AppointmentController::class, 'destroy'])->name('appointments.destroy');

    Route::resource('projects', ProjectController::class)->only(['create', 'store'])->middleware(['permission:projects.create']);
    Route::resource('projects', ProjectController::class)->only(['edit', 'update'])->middleware(['permission:projects.edit']);
    Route::resource('projects', ProjectController::class)->only(['destroy'])->middleware(['permission:projects.delete']);
    Route::resource('projects', ProjectController::class)->only(['index', 'show'])->middleware(['permission:projects.view']);
    Route::resource('task-management', StaffTaskController::class)
        ->parameters(['task-management' => 'task_management'])
        ->middleware(['auth', 'active', 'subscription.access', 'setup.access']);
    Route::patch('/task-management/{task_management}/reminder-status', [StaffTaskController::class, 'updateReminderStatus'])
        ->name('task-management.reminder-status.update')
        ->middleware(['auth', 'active', 'subscription.access', 'setup.access']);
    Route::patch('/task-management/{task_management}/blocker', [StaffTaskController::class, 'updateBlocker'])
        ->name('task-management.blocker.update')
        ->middleware(['auth', 'active', 'subscription.access', 'setup.access']);
    Route::delete('/task-management/{task_management}/blocker', [StaffTaskController::class, 'clearBlocker'])
        ->name('task-management.blocker.clear')
        ->middleware(['auth', 'active', 'subscription.access', 'setup.access']);
    Route::redirect('/reports', '/reports/leads')->name('reports.index')->middleware(['permission:reports.view']);
    Route::get('/reports/leads', [ReportsController::class, 'leads'])->name('reports.leads')->middleware(['permission:reports.view']);
    Route::get('/reports/leads/export', [ReportsController::class, 'exportLeads'])->name('reports.leads.export')->middleware(['permission:reports.view']);
    Route::get('/reports/projects', [ReportsController::class, 'projects'])->name('reports.projects')->middleware(['permission:reports.view']);
    Route::get('/reports/amc', [ReportsController::class, 'amc'])->name('reports.amc')->middleware(['permission:reports.view']);
    Route::get('/reports/payments', [ReportsController::class, 'payments'])->name('reports.payments')->middleware(['permission:reports.view']);
    Route::patch('/reports/payments/{ticketInvoice}/received', [ReportsController::class, 'togglePaymentReceived'])->name('reports.payments.received.update')->middleware(['permission:reports.view']);
    Route::get('/reports/users', [ReportsController::class, 'users'])->name('reports.users')->middleware(['permission:reports.view']);
    Route::get('/reports/services', [ReportsController::class, 'services'])->name('reports.services')->middleware(['permission:reports.view']);
    Route::get('/projects/export', [ProjectController::class, 'export'])->name('projects.export')->middleware(['permission:projects.view']);
    Route::post('/projects/import', [ProjectController::class, 'import'])->name('projects.import')->middleware(['permission:projects.create']);
    Route::get('/projects/import/sample', [ProjectController::class, 'downloadImportSample'])->name('projects.import.sample')->middleware(['permission:projects.create']);
    Route::get('/amcs', [AmcController::class, 'index'])->name('amcs.index')->middleware('permission:amc.view');
    Route::get('/amcs/export', [AmcController::class, 'export'])->name('amcs.export')->middleware('permission:amc.view');
    Route::post('/amcs/import', [AmcController::class, 'import'])->name('amcs.import')->middleware('permission:amc.create');
    Route::get('/amcs/import/sample', [AmcController::class, 'downloadImportSample'])->name('amcs.import.sample')->middleware('permission:amc.create');
    Route::get('/amcs/create', [AmcController::class, 'create'])->name('amcs.create')->middleware('permission:amc.create');
    Route::post('/amcs', [AmcController::class, 'store'])->name('amcs.store')->middleware('permission:amc.create');
    Route::get('/amcs/{amc}/edit', [AmcController::class, 'edit'])->name('amcs.edit')->middleware('permission:amc.edit');
    Route::delete('/amcs/{amc}', [AmcController::class, 'destroy'])->name('amcs.destroy')->middleware('permission:amc.delete');
    Route::get('/amc-plans', [AmcPlanController::class, 'index'])->name('amc-plans.index')->middleware('permission:amc.edit,projects.edit');
    Route::post('/amc-plans', [AmcPlanController::class, 'store'])->name('amc-plans.store')->middleware('permission:amc.edit,projects.edit');
    Route::patch('/amc-plans/{amcPlan}', [AmcPlanController::class, 'update'])->name('amc-plans.update')->middleware('permission:amc.edit,projects.edit');
    Route::delete('/amc-plans/{amcPlan}', [AmcPlanController::class, 'destroy'])->name('amc-plans.destroy')->middleware('permission:amc.edit,projects.edit');
    Route::get('/amcs/{amc}/receipt', [AmcController::class, 'receipt'])->name('amcs.receipt')->middleware('permission:amc.view');
    Route::get('/amcs/{amc}/schedule', [AmcController::class, 'schedule'])->name('amcs.schedule')->middleware('permission:amc.view');
    Route::get('/amcs/{amc}', [AmcController::class, 'show'])->name('amcs.show')->middleware('permission:amc.view');
    Route::get('/amcs/{amc}/visits/{visit}/invoice/download', [AmcController::class, 'downloadVisitInvoice'])->name('amcs.visits.invoice.download')->middleware('permission:amc.view');
    Route::patch('/amcs/{amc}', [AmcController::class, 'update'])->name('amcs.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/visits/{visit}/technician', [AmcController::class, 'updateVisitTechnician'])->name('amcs.visits.technician.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/assigned', [AmcController::class, 'updateAssignedTechnician'])->name('amcs.assigned.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/on-call-support', [AmcController::class, 'updateOnCallSupportDetails'])->name('amcs.on-call-support.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/payment', [AmcController::class, 'updatePaymentDetails'])->name('amcs.payment.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/visits/{visit}', [AmcController::class, 'updateVisitStatus'])->name('amcs.visits.update')->middleware('permission:amc.edit');

    Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage.update')->middleware('permission:projects.stage.update');
    Route::post('/projects/{project}/completed-amc', [ProjectController::class, 'storeCompletedStageAmc'])->name('projects.completed-amc.store')->middleware('permission:projects.edit');
    Route::post('/projects/{project}/drawings', [ProjectController::class, 'storeDrawing'])->name('projects.drawings.store')->middleware('permission:projects.assets.manage');
    Route::patch('/projects/{project}/drawings/accept', [ProjectController::class, 'acceptDrawings'])->name('projects.drawings.acceptMany')->middleware('permission:projects.assets.manage');
    Route::patch('/projects/{project}/drawings/{drawing}/accept', [ProjectController::class, 'acceptDrawing'])->name('projects.drawings.accept')->middleware('permission:projects.assets.manage');
    Route::get('/projects/{project}/drawings/{drawing}/view', [ProjectController::class, 'viewDrawing'])->name('projects.drawings.view')->middleware('permission:projects.assets.manage');
    Route::get('/projects/{project}/drawings/download', [ProjectController::class, 'downloadDrawings'])->name('projects.drawings.download')->middleware('permission:projects.assets.manage');
    Route::post('/projects/{project}/materials', [ProjectController::class, 'storeMaterial'])->name('projects.materials.store')->middleware('permission:projects.assets.manage');
    Route::get('/projects/{project}/materials/{material}/view', [ProjectController::class, 'viewMaterial'])->name('projects.materials.view')->middleware('permission:projects.assets.manage');
    Route::patch('/projects/{project}/materials/{material}/qa', [ProjectController::class, 'updateMaterialQa'])->name('projects.materials.qa.update')->middleware('permission:projects.assets.manage');
    Route::post('/projects/{project}/productions', [ProjectController::class, 'storeProduction'])->name('projects.productions.store')->middleware('permission:projects.assets.manage');
    Route::patch('/projects/{project}/productions/{production}/qa', [ProjectController::class, 'updateProductionQa'])->name('projects.productions.qa.update')->middleware('permission:projects.assets.manage');
    Route::get('/projects/{project}/productions/{production}/view', [ProjectController::class, 'viewProduction'])->name('projects.productions.view')->middleware('permission:projects.assets.manage');
    Route::post('/projects/{project}/comment', [ProjectController::class, 'addComment'])->name('projects.comment')->middleware('permission:projects.comment');
    Route::patch('/projects/{project}/contractor', [ProjectController::class, 'updateContractor'])->name('projects.contractor.update')->middleware('permission:projects.edit');

    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update')->middleware('permission:leads.status.update');
    Route::get('/leads/staff/{user}/leads', [LeadController::class, 'getStaffLeads'])->name('leads.staff-leads')->middleware('permission:leads.view');
    Route::post('/leads/transfer', [LeadController::class, 'transferLeads'])->name('leads.transfer')->middleware('permission:leads.edit');
    Route::patch('/leads/{lead}/assigned', [LeadController::class, 'updateAssignedUsers'])->name('leads.assigned.update')->middleware('permission:leads.edit');
    Route::post('/leads/{lead}/comment', [LeadController::class, 'addComment'])->name('leads.comment')->middleware('permission:leads.comment');
    Route::get('/leads/{lead}/latest-comments', [LeadController::class, 'latestComments'])->name('leads.latest-comments');
    Route::post('/leads/{lead}/dashboard-action', [LeadController::class, 'dashboardAction'])->name('leads.dashboard.action');
    Route::get('/leads/{lead}/follow-up/details', [DashboardController::class, 'leadFollowUpDetails'])->name('leads.follow-up.details');
    Route::get('/leads/{lead}/follow-up/history', [DashboardController::class, 'leadFollowUpHistory'])->name('leads.follow-up.history');
    Route::post('/leads/{lead}/follow-up/save', [DashboardController::class, 'saveLeadFollowUp'])->name('leads.follow-up.save');
    Route::post('/leads/{lead}/follow-up/reschedule', [LeadController::class, 'rescheduleFollowUpWithRemark'])->name('leads.follow-up.reschedule')->middleware('permission:leads.comment');
    Route::post('/leads/{lead}/contact', [LeadController::class, 'logContact'])->name('leads.contact.log')->middleware('permission:leads.comment');
    Route::post('/leads/{lead}/calendar-event', [LeadController::class, 'addLeadCalendarEvent'])->name('leads.calendar.store')->middleware('permission:leads.comment');
    Route::post('/leads/{lead}/quotation', [LeadController::class, 'uploadQuotation'])->name('leads.quotation')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/quotation/generate', [LeadQuotationGeneratorController::class, 'create'])->name('leads.quotation.generate')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/quotation/preview', [LeadQuotationGeneratorController::class, 'preview'])->name('leads.quotation.preview')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/quotation/generate', [LeadQuotationGeneratorController::class, 'store'])->name('leads.quotation.generate.store')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/quotation/{quotation}/edit', [LeadQuotationGeneratorController::class, 'editQuotation'])->name('leads.quotation.edit')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/quotation/{quotation}/edit', [LeadQuotationGeneratorController::class, 'updateQuotation'])->name('leads.quotation.update')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/quotation/{quotation}/clone', [LeadQuotationGeneratorController::class, 'cloneQuotation'])->name('leads.quotation.clone')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/quotation/{quotation}/save-template', [QuotationTemplateController::class, 'storeFromQuotation'])->name('leads.quotation.save-template')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/boq/generate', [LeadQuotationGeneratorController::class, 'createBoq'])->name('leads.boq.generate')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/boq/preview', [LeadQuotationGeneratorController::class, 'previewBoq'])->name('leads.boq.preview')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/boq/generate', [LeadQuotationGeneratorController::class, 'storeBoq'])->name('leads.boq.generate.store')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/boq/{quotation}/edit', [LeadQuotationGeneratorController::class, 'updateBoq'])->name('leads.boq.update')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/boq/{quotation}/edit', [LeadQuotationGeneratorController::class, 'editBoq'])->name('leads.boq.edit')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/boq/{quotation}/edit', [LeadQuotationGeneratorController::class, 'updateBoq'])->name('leads.boq.update')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/boq/{quotation}/view', [LeadQuotationGeneratorController::class, 'viewBoq'])->name('leads.boq.view')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/boq/{quotation}/print', [LeadQuotationGeneratorController::class, 'printBoq'])->name('leads.boq.print')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/boq/{quotation}/download', [LeadQuotationGeneratorController::class, 'downloadBoq'])->name('leads.boq.download')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/quotation/{quotation}/view-dynamic', [LeadQuotationGeneratorController::class, 'show'])->name('leads.quotation.view-dynamic')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/quotation/{quotation}/download-pdf', [LeadQuotationGeneratorController::class, 'downloadQuotation'])->name('leads.quotation.download-pdf')->middleware('permission:leads.quotation.manage');
    Route::patch('/leads/{lead}/quotation/{quotation}', [LeadController::class, 'decideQuotation'])->name('leads.quotation.decision')->middleware('permission:leads.quotation.manage');
    Route::get('/leads/{lead}/quotation/{quotation}/download', [LeadController::class, 'downloadQuotation'])->name('leads.quotation.download')->middleware('permission:leads.quotation.manage');
    Route::post('/leads/{lead}/survey', [LeadController::class, 'uploadSurvey'])->name('leads.survey')->middleware('permission:leads.survey.upload');
    Route::post('/leads/{lead}/documents', [LeadController::class, 'uploadDocument'])->name('leads.documents.store')->middleware('permission:leads.documents.upload');
    Route::post('/leads/{lead}/materials', [LeadController::class, 'storeMaterial'])->name('leads.materials.store')->middleware('permission:leads.edit');
    Route::post('/leads/{lead}/materials/products', [LeadController::class, 'storeMaterialProduct'])->name('leads.materials.products.store')->middleware('permission:leads.edit');
    Route::patch('/leads/{lead}/materials/{material}', [LeadController::class, 'updateMaterial'])->name('leads.materials.update')->middleware('permission:leads.edit');
    Route::delete('/leads/{lead}/materials/{material}', [LeadController::class, 'destroyMaterial'])->name('leads.materials.destroy')->middleware('permission:leads.edit');
    Route::get('/leads/{lead}/print', [LeadController::class, 'printQuotation'])->name('leads.print')->middleware('permission:leads.summary.download');
    Route::get('/leads/{lead}/summary/download', [LeadController::class, 'downloadLeadSummary'])->name('leads.summary.download')->middleware('permission:leads.summary.download');

    Route::post('/quotation-form/{form}', [QuotationFormSubmissionController::class, 'store'])->name('quotation-form.store')->middleware('permission:leads.quotation.manage');

    Route::post('/leads/{lead}/convert-to-company', [LeadController::class, 'convertToCompany'])->name('leads.convert-company');

    Route::resource('leads', LeadController::class);
    Route::resource('projects', ProjectController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/amcs', [AmcController::class, 'index'])->name('amcs.index')->middleware('permission:amc.view');
    Route::get('/amcs/export', [AmcController::class, 'export'])->name('amcs.export')->middleware('permission:amc.view');
    Route::post('/amcs/import', [AmcController::class, 'import'])->name('amcs.import')->middleware('permission:amc.create');
    Route::get('/amcs/import/sample', [AmcController::class, 'downloadImportSample'])->name('amcs.import.sample')->middleware('permission:amc.create');
    Route::get('/amcs/create', [AmcController::class, 'create'])->name('amcs.create')->middleware('permission:amc.create');
    Route::post('/amcs', [AmcController::class, 'store'])->name('amcs.store')->middleware('permission:amc.create');
    Route::get('/amcs/{amc}/edit', [AmcController::class, 'edit'])->name('amcs.edit')->middleware('permission:amc.edit');
    Route::delete('/amcs/{amc}', [AmcController::class, 'destroy'])->name('amcs.destroy')->middleware('permission:amc.delete');
    Route::get('/amc-plans', [AmcPlanController::class, 'index'])->name('amc-plans.index')->middleware('permission:amc.edit,projects.edit');
    Route::post('/amc-plans', [AmcPlanController::class, 'store'])->name('amc-plans.store')->middleware('permission:amc.edit,projects.edit');
    Route::patch('/amc-plans/{amcPlan}', [AmcPlanController::class, 'update'])->name('amc-plans.update')->middleware('permission:amc.edit,projects.edit');
    Route::delete('/amc-plans/{amcPlan}', [AmcPlanController::class, 'destroy'])->name('amc-plans.destroy')->middleware('permission:amc.edit,projects.edit');
    Route::get('/amcs/{amc}/receipt', [AmcController::class, 'receipt'])->name('amcs.receipt')->middleware('permission:amc.view');
    Route::get('/amcs/{amc}/schedule', [AmcController::class, 'schedule'])->name('amcs.schedule')->middleware('permission:amc.view');
    Route::get('/amcs/{amc}', [AmcController::class, 'show'])->name('amcs.show')->middleware('permission:amc.view');
    Route::patch('/amcs/{amc}', [AmcController::class, 'update'])->name('amcs.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/payment', [AmcController::class, 'updatePaymentDetails'])->name('amcs.payment.update')->middleware('permission:amc.edit');
    Route::patch('/amcs/{amc}/visits/{visit}', [AmcController::class, 'updateVisitStatus'])->name('amcs.visits.update')->middleware('permission:amc.edit');
    Route::get('/services', [ServiceController::class, 'index'])->name('services.index')->middleware('permission:service.view');
    Route::prefix('service-call')->name('service-call.')->group(function () {
        Route::get('/invoices', [ServiceCallController::class, 'invoices'])->name('invoices.index')->middleware('permission:support.view,tickets.view,tickets.reply,tickets.manage');
        Route::get('/invoices/create', [ServiceCallController::class, 'createInvoice'])->name('invoices.create')->middleware('permission:tickets.reply,tickets.manage');
        Route::get('/invoices/export', [ServiceCallController::class, 'invoiceExport'])->name('invoices.export')->middleware('permission:support.view,tickets.view,tickets.reply,tickets.manage');
        Route::get('/quotations', [ServiceCallController::class, 'quotations'])->name('quotations.index')->middleware('permission:leads.quotation.manage,support.view,tickets.view,tickets.reply,tickets.manage');
        Route::get('/quotations/create', [ServiceCallController::class, 'createQuotation'])->name('quotations.create')->middleware('permission:tickets.reply,tickets.manage');
        Route::get('/quotations/export', [ServiceCallController::class, 'quotationExport'])->name('quotations.export')->middleware('permission:leads.quotation.manage,support.view,tickets.view,tickets.reply,tickets.manage');
        Route::post('/billing-document', [ServiceCallController::class, 'saveClientBillingDocument'])->name('billing-document.save')->middleware('permission:tickets.reply,tickets.manage');
        Route::put('/billing-document/{document}', [ServiceCallController::class, 'updateClientBillingDocument'])->name('billing-document.update')->middleware('permission:tickets.reply,tickets.manage');
        Route::get('/billing-document/{document}/edit', [ServiceCallController::class, 'editClientBillingDocument'])->name('billing-document.edit')->middleware('permission:tickets.reply,tickets.manage');
        Route::get('/billing-document/{document}', [ServiceCallController::class, 'showClientBillingDocument'])->name('billing-document.show')->middleware('permission:support.view,tickets.view,tickets.reply,tickets.manage');
        Route::get('/billing-document/{document}/download', [ServiceCallController::class, 'downloadClientBillingDocument'])->name('billing-document.download')->middleware('permission:support.view,tickets.view,tickets.reply,tickets.manage');
        Route::post('/billing-document/{document}/save-template', [QuotationTemplateController::class, 'storeFromBillingDocument'])->name('billing-document.save-template')->middleware('permission:tickets.reply,tickets.manage,leads.quotation.manage');
    });
    Route::patch('/projects/{project}/stage', [ProjectController::class, 'updateStage'])->name('projects.stage.update');
    Route::post('/projects/{project}/completed-amc', [ProjectController::class, 'storeCompletedStageAmc'])->name('projects.completed-amc.store');
    Route::patch('/projects/{project}/drawings/{drawing}/accept', [ProjectController::class, 'acceptDrawing'])->name('projects.drawings.accept');
    Route::patch('/projects/{project}/materials/{material}', [ProjectController::class, 'updateMaterial'])->name('projects.materials.update');
    Route::delete('/projects/{project}/materials/{material}', [ProjectController::class, 'destroyMaterial'])->name('projects.materials.destroy');
    Route::patch('/projects/{project}/productions/{production}', [ProjectController::class, 'updateProduction'])->name('projects.productions.update');
    Route::delete('/projects/{project}/productions/{production}', [ProjectController::class, 'destroyProduction'])->name('projects.productions.destroy');
    Route::get('/projects/{project}/productions/{production}/view', [ProjectController::class, 'viewProduction'])->name('projects.productions.view');
    Route::get('/projects/{project}/productions/print', [ProjectController::class, 'printProductionSummary'])->name('projects.productions.print');
    Route::post('/projects/{project}/dispatch', [ProjectController::class, 'dispatchToDelivery'])->name('projects.dispatch');
    Route::post('/projects/{project}/challans', [ProjectController::class, 'storeChallan'])->name('projects.challans.store');
    Route::patch('/projects/{project}/challans/{challan}', [ProjectController::class, 'updateChallan'])->name('projects.challans.update');
    Route::get('/projects/{project}/challans/{challan}/print', [ProjectController::class, 'printChallan'])->name('projects.challans.print');
    Route::patch('/projects/{project}/installation', [ProjectController::class, 'updateInstallation'])->name('projects.installation.update');
    Route::post('/projects/{project}/installation/complete', [ProjectController::class, 'completeInstallation'])->name('projects.installation.complete');
    Route::get('/projects/{project}/completed/print', [ProjectController::class, 'printCompletionSummary'])->name('projects.completed.print');
    Route::post('/projects/{project}/comment', [ProjectController::class, 'addComment'])->name('projects.comment');
    Route::patch('/leads/{lead}/status', [LeadController::class, 'updateStatus'])->name('leads.status.update');
    Route::post('/leads/{lead}/comment', [LeadController::class, 'addComment'])->name('leads.comment');
    Route::post('/leads/{lead}/quotation', [LeadController::class, 'uploadQuotation'])->name('leads.quotation');
    Route::post('/leads/{lead}/survey', [LeadController::class, 'uploadSurvey'])->name('leads.survey');
    Route::get('/leads/{lead}/print', [LeadController::class, 'printQuotation'])->name('leads.print');

    // Staff CRUD
    Route::prefix('teams')->name('teams.')->group(function () {
        Route::get('/staff', [StaffController::class, 'index'])->name('staff.index')->middleware('permission:teams.staff.view,teams.staff.self.manage');
        Route::get('/staff/export', [StaffController::class, 'export'])->name('staff.export')->middleware('permission:teams.staff.view');
        Route::post('/staff/import', [StaffController::class, 'import'])->name('staff.import')->middleware('permission:teams.staff.create');
        Route::get('/staff/import/sample', [StaffController::class, 'downloadImportSample'])->name('staff.import.sample')->middleware('permission:teams.staff.create');
        Route::get('/staff/route-tracking/data', [StaffRouteTrackingController::class, 'data'])->name('staff.route-tracking.data');
        Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create')->middleware('permission:teams.staff.create');
        Route::post('/staff', [StaffController::class, 'store'])->name('staff.store')->middleware('permission:teams.staff.create');
        Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show')->middleware('permission:teams.staff.view,teams.staff.self.manage');
        Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit')->middleware('permission:teams.staff.edit,teams.staff.self.manage');
        Route::patch('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update')->middleware('permission:teams.staff.edit,teams.staff.self.manage');
        Route::patch('/staff/{staff}/invoices/{ticketInvoice}/received-by-admin', [StaffController::class, 'updateReceivedByAdmin'])->name('staff.invoices.received-by-admin.update')->middleware('permission:teams.staff.view,teams.staff.self.manage');
        Route::patch('/staff/{staff}/reactivate-subscription', [StaffController::class, 'reactivateSubscription'])->name('staff.reactivate-subscription')->middleware('permission:teams.staff.edit');
        Route::delete('/staff/{staff}', [StaffController::class, 'destroy'])->name('staff.destroy')->middleware('permission:teams.staff.delete');

        // Staff Document Upload
        Route::post('/staff/{staff}/documents', [StaffController::class, 'uploadDocument'])->name('staff.documents.upload')->middleware('permission:teams.staff.documents.manage,teams.staff.self.manage');
        Route::get('/staff/{staff}/documents/{document}/download', [StaffController::class, 'downloadDocument'])->name('staff.documents.download')->middleware('permission:teams.staff.documents.manage,teams.staff.self.manage');

        // Salary Configuration
        Route::get('/salary/{staff}', [SalaryConfigurationController::class, 'show'])->name('salary.show')->middleware('permission:teams.staff.salary.view,teams.staff.self.manage');
        Route::post('/salary', [SalaryConfigurationController::class, 'store'])->name('salary.store')->middleware('permission:teams.staff.salary.manage,teams.staff.self.manage');

        // Monthly Payroll
        Route::get('/payroll', [StaffPayrollController::class, 'index'])->name('payroll.index')->middleware('permission:teams.staff.salary.view');
        Route::get('/payroll/{month}', [StaffPayrollController::class, 'show'])->name('payroll.show')->middleware('permission:teams.staff.salary.view');
        Route::post('/payroll/{month}', [StaffPayrollController::class, 'store'])->name('payroll.store')->middleware('permission:teams.staff.salary.manage');

        // Salary Advance
        Route::get('/advance', [StaffPayrollController::class, 'advanceIndex'])->name('payroll.advance.index')->middleware('permission:teams.staff.salary.advance.view');
        Route::post('/advance', [StaffPayrollController::class, 'advanceStore'])->name('payroll.advance.store')->middleware('permission:teams.staff.salary.advance.manage');
        Route::get('/advance/{advance}/edit', [StaffPayrollController::class, 'advanceEdit'])->name('payroll.advance.edit')->middleware('permission:teams.staff.salary.advance.manage');
        Route::patch('/advance/{advance}', [StaffPayrollController::class, 'advanceUpdate'])->name('payroll.advance.update')->middleware('permission:teams.staff.salary.advance.manage');
        Route::delete('/advance/{advance}', [StaffPayrollController::class, 'advanceDestroy'])->name('payroll.advance.destroy')->middleware('permission:teams.staff.salary.advance.manage');

        // Legacy routes kept for older links/views
        Route::get('/advance/create', [StaffPayrollController::class, 'advanceIndex'])->name('staff.advance.create')->middleware('permission:teams.staff.salary.advance.view');
        Route::post('/advance/create', [StaffPayrollController::class, 'advanceStore'])->name('staff.advance.store')->middleware('permission:teams.staff.salary.advance.manage');
        Route::post('/salary/status', [StaffController::class, 'updateSalaryStatus'])->name('staff.salary.status')->middleware('permission:teams.staff.salary.manage');

        // Bank Details
        Route::get('/bank/{staff}', [StaffBankDetailController::class, 'show'])->name('bank.show')->middleware('permission:teams.staff.bank.view,teams.staff.self.manage');
        Route::post('/bank', [StaffBankDetailController::class, 'store'])->name('bank.store')->middleware('permission:teams.staff.bank.manage,teams.staff.self.manage');

        // Attendance
        Route::post('/attendance', [StaffAttendanceController::class, 'store'])->name('attendance.store')->middleware('permission:teams.staff.attendance.manage');
        Route::get('/attendance/report/{staff}/{month}/{year}', [StaffAttendanceController::class, 'report'])->name('attendance.report')->middleware('permission:teams.staff.attendance.view,teams.staff.self.manage');

    });

    Route::prefix('team/attendance')->name('attendance.')->group(function () {
        Route::post('/individual', [StaffAttendanceController::class, 'markIndividual'])->name('individual')->middleware('permission:teams.staff.attendance.manage');
        Route::post('/bulk', [StaffAttendanceController::class, 'markBulk'])->name('bulk')->middleware('permission:teams.staff.attendance.bulk.manage');
        Route::get('/bulk/export', [StaffAttendanceController::class, 'exportBulkExcel'])->name('bulk.export')->middleware('permission:teams.staff.attendance.bulk.manage');
        Route::post('/check/{staff}', [StaffAttendanceController::class, 'checkInOut'])->name('check')->middleware('permission:teams.staff.attendance.manage,teams.staff.self.manage');
        Route::patch('/correct/{staffId}/{date}', [StaffAttendanceController::class, 'correctAttendance'])->name('correct')->middleware('permission:teams.staff.attendance.manage');
        Route::get('/calendar/{staffId}/{month}/{year}', [StaffAttendanceController::class, 'monthlyCalendar'])->name('calendar')->middleware('permission:teams.staff.attendance.view,teams.staff.self.manage');
        Route::get('/export/{staffId}/{month}/{year}', [StaffAttendanceController::class, 'exportCsv'])->name('export')->middleware('permission:teams.staff.attendance.view,teams.staff.self.manage');
    });

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::middleware('role:company_admin')->group(function () {
            Route::get('/', function () {
                return redirect()->route('settings.company.index');
            })->name('index');
            Route::get('/company', [CompanySettingsController::class, 'index'])->name('company.index');
            Route::get('/company/challan-sequence-check', [CompanySettingsController::class, 'checkChallanSequence'])->name('company.challan-sequence.check');
            Route::get('/daily-update-history', [DailyStaffUpdateReportController::class, 'index'])->name('daily-staff-update.history.index');
            Route::post('/daily-update-history/generate', [DailyStaffUpdateReportController::class, 'store'])->name('daily-staff-update.generate');
            Route::get('/daily-update-history/date/{date}', [DailyStaffUpdateReportController::class, 'calendar'])
                ->where('date', '\d{4}-\d{2}-\d{2}')
                ->name('daily-staff-update.calendar');
            Route::get('/daily-update-history/{report}', [DailyStaffUpdateReportController::class, 'show'])->name('daily-staff-update.show');
            Route::get('/daily-update-history/{report}/download', [DailyStaffUpdateReportController::class, 'download'])->name('daily-staff-update.download');
            Route::delete('/daily-update-history/{report}', [DailyStaffUpdateReportController::class, 'destroy'])->name('daily-staff-update.destroy');
            Route::get('/pdf-letterhead', [LetterheadSettingController::class, 'index'])->name('letterhead.index');
            Route::get('/pdf-letterhead/file', [LetterheadSettingController::class, 'file'])->name('letterhead.file');
            Route::match(['get', 'post'], '/pdf-letterhead/preview', [LetterheadSettingController::class, 'preview'])->name('letterhead.preview');
            Route::post('/pdf-letterhead', [LetterheadSettingController::class, 'update'])->name('letterhead.update');
            Route::get('/company/asset/{type}', [CompanySettingsController::class, 'asset'])->name('company.asset');
            Route::get('/company/refresh-tax-context', [CompanySettingsController::class, 'refreshQuotationTaxContext'])->name('company.refresh-tax-context');
            Route::post('/company', [CompanySettingsController::class, 'update'])->name('company.update');
            Route::get('/company/letterhead-format', [CompanySettingsController::class, 'letterheadFormatBuilder'])->name('company.letterhead-format.builder');
            Route::patch('/company/letterhead-field-positions', [CompanySettingsController::class, 'updateLetterheadFieldPositions'])->name('company.letterhead-field-positions.update');
            Route::post('/company/mail-settings', [CompanyMailSettingController::class, 'store'])->name('company.mail-settings.store');
            Route::put('/company/mail-settings', [CompanyMailSettingController::class, 'update'])->name('company.mail-settings.update');
            Route::post('/company/mail-settings/test', [CompanyMailSettingController::class, 'test'])->name('company.mail-settings.test');
            Route::post('/company/lead-sources', [CompanySettingsController::class, 'storeLeadSource'])->name('company.lead-sources.store');
            Route::patch('/company/lead-sources/{key}', [CompanySettingsController::class, 'updateLeadSource'])->name('company.lead-sources.update');
            Route::delete('/company/lead-sources/{key}', [CompanySettingsController::class, 'destroyLeadSource'])->name('company.lead-sources.destroy');
            Route::post('/company/lead-custom-fields', [CompanySettingsController::class, 'storeLeadCustomField'])->name('company.lead-custom-fields.store');
            Route::patch('/company/lead-custom-fields/{key}', [CompanySettingsController::class, 'updateLeadCustomField'])->name('company.lead-custom-fields.update');
            Route::delete('/company/lead-custom-fields/{key}', [CompanySettingsController::class, 'destroyLeadCustomField'])->name('company.lead-custom-fields.destroy');
            Route::post('/company/project-custom-fields', [CompanySettingsController::class, 'storeProjectCustomField'])->name('company.project-custom-fields.store');
            Route::patch('/company/project-custom-fields/{key}', [CompanySettingsController::class, 'updateProjectCustomField'])->name('company.project-custom-fields.update');
            Route::delete('/company/project-custom-fields/{key}', [CompanySettingsController::class, 'destroyProjectCustomField'])->name('company.project-custom-fields.destroy');
            Route::post('/company/task-masters', [CompanySettingsController::class, 'storeTaskMaster'])->name('company.task-masters.store');
            Route::patch('/company/task-masters/{index}', [CompanySettingsController::class, 'updateTaskMaster'])->whereNumber('index')->name('company.task-masters.update');
            Route::delete('/company/task-masters/{index}', [CompanySettingsController::class, 'destroyTaskMaster'])->whereNumber('index')->name('company.task-masters.destroy');
            Route::post('/company/holidays', [CompanySettingsController::class, 'storeHoliday'])->name('company.holidays.store');
            Route::patch('/company/holidays/{holiday}', [CompanySettingsController::class, 'updateHoliday'])->name('company.holidays.update');
            Route::delete('/company/holidays/{holiday}', [CompanySettingsController::class, 'destroyHoliday'])->name('company.holidays.destroy');
            Route::post('/company/units', [CompanyUnitController::class, 'store'])->name('company.units.store');
            Route::patch('/company/units/{unit}', [CompanyUnitController::class, 'update'])->name('company.units.update');
            Route::delete('/company/units/{unit}', [CompanyUnitController::class, 'destroy'])->name('company.units.destroy');
            Route::post('/company/contractors', [CompanyContractorController::class, 'store'])->name('company.contractors.store');
            Route::patch('/company/contractors/{contractor}', [CompanyContractorController::class, 'update'])->name('company.contractors.update');
            Route::delete('/company/contractors/{contractor}', [CompanyContractorController::class, 'destroy'])->name('company.contractors.destroy');
            Route::post('/company/gst-rates', [CompanyGstRateController::class, 'store'])->name('company.gst-rates.store');
            Route::patch('/company/gst-rates/{gstRate}', [CompanyGstRateController::class, 'update'])->name('company.gst-rates.update');
            Route::delete('/company/gst-rates/{gstRate}', [CompanyGstRateController::class, 'destroy'])->name('company.gst-rates.destroy');
        });

        Route::group(['middleware' => ['permission:leads.quotation.manage,tickets.reply,tickets.manage,settings.quotation_form.manage']], function () {
            Route::get('/quotation-templates', [QuotationTemplateController::class, 'index'])->name('quotation-templates.index');
            Route::get('/quotation-templates/{quotationTemplate}', [QuotationTemplateController::class, 'show'])->name('quotation-templates.show');
            Route::post('/quotation-templates', [QuotationTemplateController::class, 'store'])->name('quotation-templates.store');
            Route::patch('/quotation-templates/{quotationTemplate}', [QuotationTemplateController::class, 'update'])->name('quotation-templates.update');
            Route::delete('/quotation-templates/{quotationTemplate}', [QuotationTemplateController::class, 'destroy'])->name('quotation-templates.destroy');
        });

        // Lead Statuses (Pipeline)
        Route::get('/statuses', [LeadStatusController::class, 'index'])->name('statuses.index');
        Route::post('/statuses', [LeadStatusController::class, 'store'])->name('statuses.store');
        Route::patch('/statuses/{status}', [LeadStatusController::class, 'update'])->name('statuses.update');
        Route::delete('/statuses/{status}', [LeadStatusController::class, 'destroy'])->name('statuses.destroy');
        Route::post('/statuses/reorder', [LeadStatusController::class, 'reorder'])->name('statuses.reorder');
        Route::get('/project-stages', [ProjectStageController::class, 'index'])->name('project-stages.index');
        Route::post('/project-stages', [ProjectStageController::class, 'store'])->name('project-stages.store');
        Route::patch('/project-stages/{stage}', [ProjectStageController::class, 'update'])->name('project-stages.update');
        Route::delete('/project-stages/{stage}', [ProjectStageController::class, 'destroy'])->name('project-stages.destroy');
        Route::post('/project-stages/reorder', [ProjectStageController::class, 'reorder'])->name('project-stages.reorder');
        Route::get('/service-stages', [ServiceStageController::class, 'index'])->name('service-stages.index')->middleware('permission:service.stages.manage');
        Route::post('/service-stages', [ServiceStageController::class, 'store'])->name('service-stages.store')->middleware('permission:service.stages.manage');
        Route::patch('/service-stages/{stage}', [ServiceStageController::class, 'update'])->name('service-stages.update')->middleware('permission:service.stages.manage');
        Route::delete('/service-stages/{stage}', [ServiceStageController::class, 'destroy'])->name('service-stages.destroy')->middleware('permission:service.stages.manage');
        Route::post('/service-stages/reorder', [ServiceStageController::class, 'reorder'])->name('service-stages.reorder')->middleware('permission:service.stages.manage');
        Route::get('/categories', [CompanyCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [CompanyCategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}', [CompanyCategoryController::class, 'update'])->name('categories.update');
        Route::delete('/categories/{category}', [CompanyCategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/campaign-batch', [CampaignBatchSettingsController::class, 'index'])->name('campaign.batch.index');
        Route::post('/campaign-batch', [CampaignBatchSettingsController::class, 'update'])->name('campaign.batch.update');

        Route::get('/quotation-form', [CompanyQuotationFormController::class, 'edit'])->name('quotation-form.edit');
        Route::post('/quotation-form', [CompanyQuotationFormController::class, 'update'])->name('quotation-form.update');
        Route::delete('/quotation-form', [CompanyQuotationFormController::class, 'reset'])->name('quotation-form.reset');

        Route::get('/boq', [CompanyBoqController::class, 'index'])->name('boq.index')->middleware('permission:boq.categories.manage,boq.products.manage');
        Route::get('/boq/products/export', [CompanyBoqController::class, 'exportProducts'])->name('boq.products.export')->middleware('permission:boq.products.manage');
        Route::post('/boq/products/import', [CompanyBoqController::class, 'importProducts'])->name('boq.products.import')->middleware('permission:boq.products.manage');
        Route::get('/boq/products/import/sample', [CompanyBoqController::class, 'downloadProductImportSample'])->name('boq.products.import.sample')->middleware('permission:boq.products.manage');
        Route::post('/boq/categories', [CompanyBoqController::class, 'storeCategory'])->name('boq.categories.store')->middleware('permission:boq.categories.manage');
        Route::patch('/boq/categories/{category}', [CompanyBoqController::class, 'updateCategory'])->name('boq.categories.update')->middleware('permission:boq.categories.manage');
        Route::delete('/boq/categories/{category}', [CompanyBoqController::class, 'destroyCategory'])->name('boq.categories.destroy')->middleware('permission:boq.categories.manage');
        Route::post('/boq/products', [CompanyBoqController::class, 'storeProduct'])->name('boq.products.store')->middleware('permission:boq.products.manage');
        Route::get('/boq/products/{product}/image', [CompanyBoqController::class, 'productImage'])->name('boq.products.image')->middleware('permission:boq.products.manage,boq.categories.manage');
        Route::patch('/boq/products/{product}', [CompanyBoqController::class, 'updateProduct'])->name('boq.products.update')->middleware('permission:boq.products.manage');
        Route::delete('/boq/products/{product}', [CompanyBoqController::class, 'destroyProduct'])->name('boq.products.destroy')->middleware('permission:boq.products.manage');
        Route::redirect('/services', '/services');

        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        Route::get('/users', [CompanyUserController::class, 'index'])->name('users.index');
        Route::post('/users', [CompanyUserController::class, 'store'])->name('users.store');
        Route::patch('/users/{user}/role', [CompanyUserController::class, 'updateRole'])->name('users.role.update');
        Route::patch('/users/{user}/toggle-status', [CompanyUserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::get('/gallery', [GalleryController::class, 'index'])->name('gallery.index')->middleware('permission:gallery.view');
        Route::get('/gallery/{mediaFile}/open', [GalleryController::class, 'open'])->name('gallery.open')->middleware('permission:gallery.view');
        Route::delete('/gallery/{mediaFile}', [GalleryController::class, 'destroy'])->name('gallery.destroy')->middleware('permission:gallery.view');
        Route::get('/clients', [ClientController::class, 'index'])->name('clients.index')->middleware('permission:clients.view');
        Route::get('/clients/export', [ClientController::class, 'export'])->name('clients.export')->middleware('permission:clients.view');
        Route::post('/clients/import', [ClientController::class, 'import'])->name('clients.import')->middleware('permission:clients.view');
        Route::get('/clients/import/sample', [ClientController::class, 'downloadImportSample'])->name('clients.import.sample')->middleware('permission:clients.view');
        Route::get('/clients/create', [ClientController::class, 'create'])->name('clients.create')->middleware('permission:clients.view');
        Route::post('/clients', [ClientController::class, 'store'])->name('clients.store')->middleware('permission:clients.view');
        Route::post('/clients/contacts/{lead}/add', [ClientController::class, 'addLeadContact'])->name('clients.contacts.add')->middleware('permission:clients.view');
        Route::post('/clients/duplicates/merge', [ClientController::class, 'mergeDuplicate'])->name('clients.duplicates.merge')->middleware('permission:clients.view');
        Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show')->middleware('permission:clients.view');
        Route::get('/clients/{client}/edit', [ClientController::class, 'edit'])->name('clients.edit')->middleware('permission:clients.view');
        Route::patch('/clients/{client}', [ClientController::class, 'update'])->name('clients.update')->middleware('permission:clients.view');
        Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy')->middleware('permission:clients.view');
        Route::post('/clients/{client}/qr/generate', [ClientQrController::class, 'generate'])->name('clients.qr.generate')->middleware('permission:clients.view');
        Route::post('/clients/{client}/qr/regenerate', [ClientQrController::class, 'regenerate'])->name('clients.qr.regenerate')->middleware('permission:clients.view');
        Route::get('/clients/{client}/qr/print', [ClientQrController::class, 'print'])->name('clients.qr.print')->middleware('permission:clients.view');
        Route::post('/users/check-email', [CompanyUserController::class, 'checkEmail'])->name('users.check-email');
        Route::post('/clients/check-availability', [ClientController::class, 'checkAvailability'])->name('clients.check-availability');
      });

    Route::prefix('tickets')->name('tickets.')->group(function () {
        Route::get('/', [TicketController::class, 'index'])->name('index')->middleware('permission:tickets.view,tickets.create');
        Route::get('/export', [TicketController::class, 'export'])->name('export');
        Route::get('/create', [TicketController::class, 'create'])->name('create')->middleware('permission:tickets.create');
        Route::post('/amc/{amc}', [TicketController::class, 'storeFromAmc'])->name('amc.store')->middleware('permission:tickets.create');
        Route::get('/customer/lookup', [TicketController::class, 'lookupCustomer'])->name('customer.lookup')->middleware('permission:tickets.create,tickets.reply,tickets.manage');
        Route::get('/customer/history', [TicketController::class, 'customerHistory'])->name('customer.history')->middleware('permission:tickets.create,tickets.reply,tickets.manage');
        Route::get('/customer/email-check', [TicketController::class, 'checkCustomerEmail'])->name('customer.email-check')->middleware('permission:tickets.create,tickets.reply,tickets.manage');
        Route::get('/customer/phone-check', [TicketController::class, 'checkCustomerPhone'])->name('customer.phone-check')->middleware('permission:tickets.create,tickets.reply,tickets.manage');
        Route::get('/visit-date-workloads', [TicketController::class, 'visitDateWorkloads'])->name('visit-date-workloads')->middleware('permission:tickets.create,tickets.reply,tickets.manage');
        Route::post('/', [TicketController::class, 'store'])->name('store')->middleware('permission:tickets.create');
        Route::get('/{ticket}/edit', [TicketController::class, 'edit'])->name('edit')->middleware('permission:tickets.reply,tickets.manage');
        Route::patch('/{ticket}', [TicketController::class, 'update'])->name('update')->middleware('permission:tickets.reply,tickets.manage');
        Route::patch('/{ticket}/service-report', [TicketController::class, 'updateServiceReport'])->name('service-report.update')->middleware('permission:tickets.reply,tickets.manage');
        Route::patch('/{ticket}/assigned-technician', [TicketController::class, 'updateAssignedTechnician'])->name('assigned-technician.update')->middleware('permission:tickets.reply,tickets.manage');
        Route::get('/{ticket}/invoices/{ticketInvoice}', [TicketController::class, 'viewInvoice'])->name('invoices.view')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::get('/{ticket}/invoices/{ticketInvoice}/print', [TicketController::class, 'printInvoice'])->name('invoices.print')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::get('/{ticket}/invoices/{ticketInvoice}/download', [TicketController::class, 'downloadInvoice'])->name('invoices.download')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::get('/{ticket}/quotations/{ticketQuote}/download', [TicketController::class, 'downloadQuotation'])->name('quotations.download')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::get('/{ticket}/quotations/{ticketQuote}', [TicketController::class, 'viewQuotation'])->name('quotations.view')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::get('/{ticket}/quotations/{ticketQuote}/print', [TicketController::class, 'printQuotation'])->name('quotations.print')->middleware('permission:tickets.view,tickets.reply,tickets.manage');
        Route::post('/{ticket}/billing-document', [TicketController::class, 'saveBillingDocument'])->name('billing-document.save')->middleware('permission:tickets.reply,tickets.manage');
        Route::delete('/{ticket}', [TicketController::class, 'destroy'])->name('destroy')->middleware('permission:tickets.manage');
        Route::get('/{ticket}', [TicketController::class, 'show'])->name('show')->middleware('permission:tickets.view,tickets.create,tickets.reply,tickets.manage');
        Route::post('/{ticket}/tasks', [TicketController::class, 'storeTask'])->name('tasks.store')->middleware('permission:tickets.reply,tickets.manage');
        Route::post('/{ticket}/reply', [TicketController::class, 'reply'])->name('reply')->middleware('permission:tickets.reply,tickets.manage');
        Route::patch('/{ticket}/status', [TicketController::class, 'updateStatus'])->name('status.update')->middleware('permission:tickets.reply,tickets.manage');
        Route::post('/{ticket}/repeat-complaint', [TicketController::class, 'repeatComplaint'])->name('repeat-complaint')->middleware('permission:tickets.reply,tickets.manage');
        Route::patch('/{ticket}/board-stage', [TicketController::class, 'updateBoardStage'])->name('board-stage.update')->middleware('permission:tickets.reply,tickets.manage');
    });

    Route::get('/subscription', [SubscriptionController::class, 'show'])->name('subscription.show')->middleware('permission:subscription.view');
    Route::get('/subscription/{subscription}/invoice', [SubscriptionController::class, 'invoice'])->name('subscription.invoice')->middleware('permission:subscription.view');
    Route::post('/subscription/create-order', [SubscriptionController::class, 'createOrder'])->name('subscription.create-order')->middleware('permission:subscription.view');
    Route::post('/subscription/change-plan', [SubscriptionController::class, 'changePlan'])->name('subscription.change')->middleware('permission:subscription.view');
    Route::post('/subscription/process', [SubscriptionController::class, 'processChange'])->name('subscription.process')->middleware('permission:subscription.view');
    Route::post('/subscription/recurring/complete', [SubscriptionController::class, 'completeRecurring'])->name('subscription.recurring.complete')->middleware('permission:subscription.view');
    Route::post('/subscription/{subscription}/cancel-auto-renew', [SubscriptionController::class, 'cancelAutoRenew'])->name('subscription.cancel-auto-renew')->middleware('permission:subscription.view');

    Route::get('/company/asset/{type}', [CompanySettingsController::class, 'asset'])->name('company.asset');

    // Integration module
    Route::middleware('permission:integrations.view')->prefix('integrations')->name('integrations.')->group(function () {
        Route::get('/', [IntegrationController::class, 'index'])->name('index');

        // Admin: configure / toggle / test integrations
        Route::post('/google-drive', [IntegrationController::class, 'saveGoogleDrive'])->name('google-drive.save');
        Route::post('/google-drive/test', [IntegrationController::class, 'testGoogleDrive'])->name('google-drive.test');
        Route::post('/email-smtp', [IntegrationController::class, 'saveEmailSmtp'])->name('email-smtp.save');
        Route::post('/email-smtp/test', [IntegrationController::class, 'testEmailSmtp'])->name('email-smtp.test');
        Route::post('/toggle/{type}', [IntegrationController::class, 'toggleIntegration'])->name('toggle');

        // Admin: one-click Auto Connect via platform Google OAuth (drive | gmail)
        Route::get('/oauth/google/{service}/redirect', [GoogleOAuthController::class, 'redirect'])->name('oauth.google.redirect');
        Route::get('/oauth/google/callback', [GoogleOAuthController::class, 'callback'])->name('oauth.google.callback');
        Route::post('/drive/disconnect', [GoogleOAuthController::class, 'disconnectDrive'])->name('drive.disconnect');

        // Company-wide notification settings (admin manages; applies to all users)
        Route::put('/notifications', [IntegrationController::class, 'updateNotificationMatrix'])->name('notifications.update');
    });

    Route::prefix('marketing')->name('marketing.')->group(function () {
        Route::get('/campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/create/normal', [CampaignController::class, 'createNormal'])->name('campaigns.create.normal');
        Route::get('/campaigns/create/festival', [CampaignController::class, 'createFestival'])->name('campaigns.create.festival');
        Route::get('/campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
        Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::patch('/campaigns/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::post('/campaigns/{campaign}/send', [CampaignController::class, 'sendNow'])->name('campaigns.send');
        Route::post('/campaigns/{campaign}/banner', [CampaignController::class, 'uploadBanner'])->name('campaigns.banner.upload');
        Route::post('/campaigns/banner/generate', [CampaignController::class, 'generateBanner'])->name('campaigns.banner.generate');
        Route::delete('/campaigns/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');
        Route::post('/campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate'])->name('campaigns.duplicate');
        Route::patch('/campaigns/{campaign}/status', [CampaignController::class, 'toggleStatus'])->name('campaigns.status');
        Route::get('/email-template', [MarketingEmailTemplateController::class, 'index'])->name('email-template');
        Route::get('/whatsapp', [MarketingEmailTemplateController::class, 'whatsappPage'])->name('whatsapp');
        Route::get('/email-template/add', [MarketingEmailTemplateController::class, 'index'])->name('email-template.add');
        Route::get('/email-template/{marketingEmailTemplate}/edit', [MarketingEmailTemplateController::class, 'index'])->name('email-template.edit');
        Route::get('/email-template/{marketingEmailTemplate}/preview', [MarketingEmailTemplateController::class, 'preview'])->name('email-template.preview');
        Route::get('/email-template-categories', [MarketingEmailTemplateController::class, 'categoriesPage'])->name('email-template.categories.page');
        Route::get('/email-template-sender-config', [MarketingEmailTemplateController::class, 'senderConfigPage'])->name('email-template.sender-config.page');
        Route::post('/email-template', [MarketingEmailTemplateController::class, 'store'])->name('email-template.store');
        Route::patch('/email-template/{marketingEmailTemplate}', [MarketingEmailTemplateController::class, 'update'])->name('email-template.update');
        Route::delete('/email-template/{marketingEmailTemplate}', [MarketingEmailTemplateController::class, 'destroy'])->name('email-template.destroy');
        Route::post('/email-template/files', [MarketingEmailTemplateController::class, 'uploadFile'])->name('email-template.files.store');
        Route::delete('/email-template/files/{mediaFile}', [MarketingEmailTemplateController::class, 'destroyFile'])->name('email-template.files.destroy');
        Route::post('/email-template/categories', [MarketingEmailTemplateController::class, 'storeCategory'])->name('email-template.categories.store');
        Route::patch('/email-template/categories/{marketingEmailTemplateCategory}', [MarketingEmailTemplateController::class, 'updateCategory'])->name('email-template.categories.update');
        Route::delete('/email-template/categories/{marketingEmailTemplateCategory}', [MarketingEmailTemplateController::class, 'destroyCategory'])->name('email-template.categories.destroy');
        Route::post('/email-template/sender-config', [MarketingEmailTemplateController::class, 'saveSenderConfig'])->name('email-template.sender-config.save');
        Route::post('/email-template/sender-groups', [MarketingEmailTemplateController::class, 'storeSenderGroup'])->name('email-template.sender-groups.store');
        Route::patch('/email-template/sender-groups/{marketingEmailSenderGroup}', [MarketingEmailTemplateController::class, 'updateSenderGroup'])->name('email-template.sender-groups.update');
        Route::delete('/email-template/sender-groups/{marketingEmailSenderGroup}', [MarketingEmailTemplateController::class, 'destroySenderGroup'])->name('email-template.sender-groups.destroy');
        Route::post('/email-template/wati-config', [MarketingEmailTemplateController::class, 'saveWatiConfig'])->name('email-template.wati-config.save');
    });
});

Route::middleware(['auth', 'active', 'subscription.access', 'setup.access', 'permission:settings.quotation_form.manage'])->group(function () {
    Route::get('/quotation-form', [QuotationFormEntryController::class, 'index'])->name('quotation-form.entry');
    Route::post('/quotation-form/override', [QuotationFormEntryController::class, 'createOverride'])->name('quotation-form.override.create');
    Route::get('/quotation-form/custom-fields', [QuotationCustomFieldController::class, 'index'])->name('quotation-form.custom-fields');
    Route::post('/quotation-form/custom-fields', [QuotationCustomFieldController::class, 'store'])->name('quotation-form.custom-fields.store');
});

Route::middleware(['auth', 'active', 'subscription.access', 'setup.access'])->group(function () {
    Route::get('/profile/photo', [ProfileController::class, 'photo'])->name('profile.photo');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::put('/profile/notification-preference', [PushNotificationController::class, 'updatePreference'])->name('profile.notification-preference.update');
    Route::post('/profile/push-devices', [PushNotificationController::class, 'storeToken'])->name('profile.push-devices.store');
    Route::delete('/profile/push-devices', [PushNotificationController::class, 'destroyToken'])->name('profile.push-devices.destroy');

});

require __DIR__.'/auth.php';
require __DIR__.'/debug.php';
