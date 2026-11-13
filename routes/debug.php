<?php

declare(strict_types=1);

use App\Http\Controllers\DebugQueryController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────────────────
// Developer Debugging & DQL Query Viewer
// ──────────────────────────────────────────────────────────────────
Route::prefix('debug')->group(function () {
    Route::get('query/{key}', [DebugQueryController::class, 'dbQuery'])->name('debug.query');
    Route::get('logs/{key}', [DebugQueryController::class, 'viewLogs'])->name('debug.logs');
});
