<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

/*
 * Apex ecosystem integration API (§14). Passport-authenticated; each client is
 * scoped: Apex POS gets je:post + invoice:post, Charlie HRMS gets je:post only.
 */
Route::prefix('v1')->middleware('auth:api')->group(function (): void {
    Route::post('journal-entries', [JournalEntryController::class, 'store'])
        ->middleware(['scope:je:post', 'idempotency']);

    Route::post('invoices', [InvoiceController::class, 'store'])
        ->middleware(['scope:invoice:post', 'idempotency']);

    Route::get('accounts', [AccountController::class, 'index'])
        ->middleware('scope:reports:read');

    Route::get('reports/trial-balance', [ReportController::class, 'trialBalance'])
        ->middleware('scope:reports:read');
});
