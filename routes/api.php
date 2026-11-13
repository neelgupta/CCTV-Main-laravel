<?php

use App\Http\Controllers\Api\StaffAuthController;
use App\Http\Controllers\Api\StaffAttendanceController;
use App\Http\Controllers\Api\StaffProductController;
use App\Http\Controllers\Api\StaffTicketController;
use App\Http\Controllers\Api\CampaignAnalyticsController;
use App\Http\Controllers\Api\CampaignApiController;
use App\Http\Controllers\Api\CampaignTrackingController;
use App\Http\Controllers\Api\CompanyAmcController;
use App\Http\Controllers\Api\CompanyAmcDetailController;
use App\Http\Controllers\Api\CompanyAmcStoreController;
use App\Http\Controllers\Api\CompanyAmcPlanController;
use App\Http\Controllers\Api\CompanyExpenseCategoryController;
use App\Http\Controllers\Api\CompanyExpenseController;
use App\Http\Controllers\Api\CompanyClientStoreController;
use App\Http\Controllers\Api\CompanyClientController;
use App\Http\Controllers\Api\CompanyClientDetailController;
use App\Http\Controllers\Api\CompanyProjectController;
use App\Http\Controllers\Api\CompanyLeadController;
use App\Http\Controllers\Api\CompanyTaskController;
use App\Http\Controllers\Api\CompanyIncomeCategoryController;
use App\Http\Controllers\Api\CompanyIncomeController;
use App\Http\Controllers\Api\CompanyInvoiceController;
use App\Http\Controllers\Api\CompanyQuotationController;
use App\Http\Controllers\Api\CompanyLocationController;
use App\Http\Controllers\Api\CompanyLookupController;
use App\Http\Controllers\Api\CompanyTicketAssigneeController;
use App\Http\Controllers\Api\CompanySupportTicketController;
use App\Http\Controllers\Api\FestivalCampaignApiController;
use App\Http\Controllers\Api\TaskPartController;
use App\Http\Controllers\Api\StaffProfileController;
use App\Http\Controllers\EmailTrackingWebhookController;
use App\Http\Controllers\RazorpayWebhookController;
use App\Http\Controllers\Api\InquiryApiController;
use App\Http\Controllers\StaffRouteTrackingController;
use Illuminate\Support\Facades\Route;

// Public Inquiry & Demo Request API endpoints for landing pages
Route::post('/inquiries', [InquiryApiController::class, 'store'])->name('api.inquiries.store');
Route::post('/demo-request', [InquiryApiController::class, 'store'])->name('api.demo-request.store');
Route::get('/inquiries/states', [InquiryApiController::class, 'states'])->name('api.inquiries.states');
Route::get('/states', [InquiryApiController::class, 'states'])->name('api.states.public');


Route::prefix('staff')->group(function () {
    Route::post('/login', [StaffAuthController::class, 'login'])->name('api.staff.login');
    Route::get('/profile-image', [StaffProfileController::class, 'profileImage'])->name('api.staff.profile-image')->middleware('signed');
    Route::get('/profile-image/public', [StaffProfileController::class, 'publicProfileImage'])->name('api.staff.profile-image.public');

    Route::middleware('staff.api')->group(function () {
        Route::get('/me', [StaffAuthController::class, 'me'])->name('api.staff.me');
        Route::get('/profile', [StaffProfileController::class, 'show'])->name('api.staff.profile.show');
        Route::match(['put', 'post'], '/profile', [StaffProfileController::class, 'update'])->name('api.staff.profile.update');
        Route::post('/change-password', [StaffProfileController::class, 'changePassword'])->name('api.staff.change-password');
        Route::post('/logout', [StaffAuthController::class, 'logout'])->name('api.staff.logout');
        Route::get('/task-masters', [StaffTicketController::class, 'taskMasters'])->name('api.staff.task-masters.index');
        Route::get('/products', [StaffProductController::class, 'index'])->name('api.staff.products.index');
        Route::get('/products/{product}/image', [StaffProductController::class, 'image'])->name('api.staff.products.image');
        Route::get('/tickets', [StaffTicketController::class, 'index'])->name('api.staff.tickets.index');
        Route::get('/tickets/{ticket}', [StaffTicketController::class, 'show'])->name('api.staff.tickets.show');
        Route::post('/tickets/{ticket}/accept-job', [StaffTicketController::class, 'acceptJob'])->name('api.staff.tickets.accept-job.store');
        Route::post('/tickets/{ticket}/tasks', [StaffTicketController::class, 'storeTask'])->name('api.staff.tickets.tasks.store');
        Route::post('/tickets/{ticket}/task-report', [StaffTicketController::class, 'updateTaskReport'])->name('api.staff.tickets.task-report');
        Route::get('/tickets/{ticket}/quote_add', [StaffTicketController::class, 'quoteAddContext'])->name('api.staff.tickets.quote-add.context');
        Route::post('/tickets/{ticket}/quote_add', [StaffTicketController::class, 'storeQuoteAdd'])->name('api.staff.tickets.quote-add.store');
        Route::delete('/tickets/{ticket}/quote_add', [StaffTicketController::class, 'deleteQuoteItem'])->name('api.staff.tickets.quote-add.destroy');
        Route::post('/tickets/{ticket}/service-report', [StaffTicketController::class, 'updateServiceReport'])->name('api.staff.tickets.service-report');
        Route::post('/tickets/{ticket}/start-time', [StaffTicketController::class, 'startTime'])->name('api.staff.tickets.start-time');
        Route::post('/tickets/{ticket}/end-time', [StaffTicketController::class, 'endTime'])->name('api.staff.tickets.end-time');
        Route::get('/attendance/today', [StaffAttendanceController::class, 'today'])->name('api.staff.attendance.today');
        Route::post('/attendance/clock-in', [StaffAttendanceController::class, 'clockIn'])->name('api.staff.attendance.clock-in');
        Route::post('/attendance/clock-out', [StaffAttendanceController::class, 'clockOut'])->name('api.staff.attendance.clock-out');
        Route::get('/attendance/monthly/{month}/{year}', [StaffAttendanceController::class, 'monthly'])->name('api.staff.attendance.monthly');
        Route::post('/route-points', [StaffRouteTrackingController::class, 'store'])->name('api.staff.route-points.store');
    });
});

Route::get('/email-tracking/open', [CampaignTrackingController::class, 'open'])->name('api.campaigns.email.open');
Route::get('/email-tracking/click', [CampaignTrackingController::class, 'click'])->name('api.campaigns.email.click');
Route::post('/email-tracking/delivery-webhook', [EmailTrackingWebhookController::class, 'deliveryWebhook'])->name('api.campaigns.email.delivery-webhook');
Route::post('/whatsapp-tracking/delivery-webhook', [EmailTrackingWebhookController::class, 'whatsAppWebhook'])->name('api.campaigns.whatsapp.delivery-webhook');
Route::post('/razorpay/webhook', [RazorpayWebhookController::class, 'handle'])->name('api.razorpay.webhook');

Route::middleware('staff.api')->group(function () {
    Route::post('/tasks/{task_id}/parts', [TaskPartController::class, 'store'])
        ->whereNumber('task_id')
        ->name('api.tasks.parts.store');
    Route::get('/tasks/{task_id}/parts', [TaskPartController::class, 'index'])
        ->whereNumber('task_id')
        ->name('api.tasks.parts.index');
    Route::match(['put', 'patch'], '/tasks/{task_id}/parts/{part_id}', [TaskPartController::class, 'update'])
        ->whereNumber('task_id')
        ->whereNumber('part_id')
        ->name('api.tasks.parts.update');
    Route::delete('/tasks/{task_id}/parts/{part_id}', [TaskPartController::class, 'destroy'])
        ->whereNumber('task_id')
        ->whereNumber('part_id')
        ->name('api.tasks.parts.destroy');
});

    Route::middleware('staff.api')->group(function () {
        // Route::middleware('role:company_admin')->group(function () {
            Route::get('/company/projects', [CompanyProjectController::class, 'index'])->name('api.company.projects.index');
            Route::post('/company/projects', [CompanyProjectController::class, 'store'])->name('api.company.projects.store');
            Route::get('/company/projects/{project}', [CompanyProjectController::class, 'show'])->name('api.company.projects.show');
            Route::get('/company/project-stages',[CompanyProjectController::class, 'stages'])->name('api.company.project-stages.index');
            Route::get('/company/leads', [CompanyLeadController::class, 'index'])->name('api.company.leads.index');
            Route::get('/company/amcs', [CompanyAmcController::class, 'index'])->name('api.company.amcs.index');
            Route::post('/company/clients', [CompanyClientStoreController::class, 'store'])->name('api.company.clients.store');
            Route::get('/company/clients', [CompanyClientController::class, 'index'])->name('api.company.clients.index');
            Route::match(['patch', 'put', 'post'], '/company/clients/{clientId}', [CompanyClientStoreController::class, 'update'])->whereNumber('clientId')->name('api.company.clients.update');
            Route::get('/company/clients/{clientId}', [CompanyClientDetailController::class, 'show'])->whereNumber('clientId')->name('api.company.clients.show');
            Route::get('/company/states', [CompanyLocationController::class, 'states'])->name('api.company.states.index');
            Route::get('/company/cities', [CompanyLocationController::class, 'cities'])->name('api.company.cities.index');
            Route::get('/company/incomes', [CompanyIncomeController::class, 'index'])->name('api.company.incomes.index');
            Route::post('/company/incomes', [CompanyIncomeController::class, 'store'])->name('api.company.incomes.store');
            Route::match(['patch', 'put', 'post'], '/company/incomes/{income}', [CompanyIncomeController::class, 'update'])->whereNumber('income')->name('api.company.incomes.update');
            Route::delete('/company/incomes/{income}', [CompanyIncomeController::class, 'destroy'])->whereNumber('income')->name('api.company.incomes.destroy');
            Route::get('/company/expenses', [CompanyExpenseController::class, 'index'])->name('api.company.expenses.index');
            Route::post('/company/expenses', [CompanyExpenseController::class, 'store'])->name('api.company.expenses.store');
            Route::match(['patch', 'put', 'post'], '/company/expenses/{expense}', [CompanyExpenseController::class, 'update'])->whereNumber('expense')->name('api.company.expenses.update');
            Route::delete('/company/expenses/{expense}', [CompanyExpenseController::class, 'destroy'])->whereNumber('expense')->name('api.company.expenses.destroy');
            // Route::get('/company/income-categories', [CompanyIncomeCategoryController::class, 'index'])->name('api.company.income-categories.index');
            Route::get('/company/expense-categories', [CompanyExpenseCategoryController::class, 'index'])->name('api.company.expense-categories.index');
            Route::get('/company/invoices', [CompanyInvoiceController::class, 'index'])->name('api.company.invoices.index');
            Route::get('/company/quotations', [CompanyQuotationController::class, 'index'])->name('api.company.quotations.index');
            Route::post('/company/quotations', [CompanyQuotationController::class, 'store'])->name('api.company.quotations.store');
            Route::get('/company/quotations/{quotation}', [CompanyQuotationController::class, 'show'])->whereNumber('quotation')->name('api.company.quotations.show');
            Route::match(['patch', 'put', 'post'], '/company/quotations/{quotation}', [CompanyQuotationController::class, 'update'])->whereNumber('quotation')->name('api.company.quotations.update');
            Route::delete('/company/quotations/{quotation}', [CompanyQuotationController::class, 'destroy'])->whereNumber('quotation')->name('api.company.quotations.destroy');
            Route::get('/company/amcs/{amcId}', [CompanyAmcDetailController::class, 'show'])->whereNumber('amcId')->name('api.company.amcs.show');
            Route::post('/company/amcs', [CompanyAmcStoreController::class, 'store'])->name('api.company.amcs.store');
            Route::match(['patch', 'put'], '/company/amcs/{amc}', [CompanyAmcStoreController::class, 'update'])->name('api.company.amcs.update');
            Route::get('/company/amc-plans', [CompanyAmcPlanController::class, 'index'])->name('api.company.amc-plans.index');
            Route::get('/company/amc-plans/{amcPlanId}', [CompanyAmcPlanController::class, 'show'])->whereNumber('amcPlanId')->name('api.company.amc-plans.show');
    // });

            Route::get('/company/ticket-assignees', [CompanyTicketAssigneeController::class, 'index'])->name('api.company.ticket-assignees.index');
            Route::post('/company/support-tickets', [CompanySupportTicketController::class, 'store'])->name('api.company.support-tickets.store');
            Route::match(['patch', 'put', 'post'], '/company/support-tickets/{ticket}', [CompanySupportTicketController::class, 'update'])->whereNumber('ticket')->name('api.company.support-tickets.update');
            Route::post('/company/expenses', [CompanyExpenseController::class, 'store'])->name('api.company.expenses.store');
            Route::get('/company/gst-rates', [CompanyLookupController::class, 'gstRates'])->name('api.company.gst-rates.index');
            Route::get('/company/units', [CompanyLookupController::class, 'units'])->name('api.company.units.index');
            Route::get('/company/tasks', [CompanyTaskController::class, 'index'])->name('api.company.tasks.index');
            Route::post('/company/tasks', [CompanyTaskController::class, 'store'])->name('api.company.tasks.store');
            Route::match(['patch', 'put'], '/company/tasks/{task}', [CompanyTaskController::class, 'update'])->name('api.company.tasks.update');
            Route::match(['post', 'patch', 'put'], '/company/tasks/{task}/status', [CompanyTaskController::class, 'updateStatus'])->name('api.company.tasks.status.update');
            Route::delete('/company/tasks/{task}', [CompanyTaskController::class, 'destroy'])->name('api.company.tasks.destroy');
            Route::match(['patch', 'put'], '/company/tasks/{task}/blocker', [CompanyTaskController::class, 'updateBlocker'])
                ->name('api.company.tasks.blocker.update');

            Route::delete('/company/tasks/{task}/blocker', [CompanyTaskController::class, 'clearBlocker'])
                ->name('api.company.tasks.blocker.clear');
    });


Route::middleware('auth')->group(function () {
    Route::get('/campaigns', [CampaignApiController::class, 'index'])->name('api.campaigns.index');
    Route::post('/campaigns', [CampaignApiController::class, 'store'])->name('api.campaigns.store');
    Route::get('/campaigns/{campaign}', [CampaignApiController::class, 'show'])->name('api.campaigns.show');
    Route::patch('/campaigns/{campaign}', [CampaignApiController::class, 'update'])->name('api.campaigns.update');
    Route::post('/send-campaign/{campaign}', [CampaignApiController::class, 'send'])->name('api.campaigns.send');
    Route::get('/festival-campaigns/upcoming', [FestivalCampaignApiController::class, 'upcoming'])->name('api.festival-campaigns.upcoming');
    Route::post('/festival-campaigns', [FestivalCampaignApiController::class, 'store'])->name('api.festival-campaigns.store');
    Route::post('/festival-campaigns/banner', [FestivalCampaignApiController::class, 'uploadBanner'])->name('api.festival-campaigns.banner.upload');
    Route::post('/festival-campaigns/banner/generate', [FestivalCampaignApiController::class, 'generateBanner'])->name('api.festival-campaigns.banner.generate');
    Route::get('/analytics', CampaignAnalyticsController::class)->name('api.campaigns.analytics');
});
