<?php

declare(strict_types=1);

use App\Modules\Communication\Http\Controllers\WhatsAppIntegrationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| WhatsApp Integration Routes
|--------------------------------------------------------------------------
|
| Here you can register routes for the WhatsApp Cloud API integration module.
|
*/

// Public webhook endpoints called directly by Meta
Route::prefix('integrations/whatsapp')->group(function () {
    Route::get('/webhook/{uuid}', [WhatsAppIntegrationController::class, 'verifyWebhook'])->name('integrations.whatsapp.webhook.verify');
    Route::post('/webhook/{uuid}', [WhatsAppIntegrationController::class, 'handleWebhook'])->name('integrations.whatsapp.webhook.handle');
});

// Authenticated configuration/actions endpoints
Route::middleware(['web', 'auth', 'active'])->prefix('integrations/whatsapp')->group(function () {
    Route::get('/settings', [WhatsAppIntegrationController::class, 'settings'])->name('integrations.whatsapp.settings');
    Route::post('/connect', [WhatsAppIntegrationController::class, 'connect'])->name('integrations.whatsapp.connect');
    Route::post('/verify', [WhatsAppIntegrationController::class, 'verifyConnection'])->name('integrations.whatsapp.verify');
    Route::get('/status', [WhatsAppIntegrationController::class, 'getStatus'])->name('integrations.whatsapp.status');
});
