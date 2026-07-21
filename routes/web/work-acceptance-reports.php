<?php

use App\Http\Controllers\WorkAcceptanceReportController;
use Illuminate\Support\Facades\Route;

// --- Public routes (signed URLs, no auth) ---
Route::middleware(['signed'])->prefix('work-acceptance-reports/public')->name('work-acceptance-reports.public.')->group(function () {
    Route::get('/{report}', [WorkAcceptanceReportController::class, 'publicShow'])->name('show');
    Route::post('/{report}/sign', [WorkAcceptanceReportController::class, 'storeSignature'])->name('store-signature');
    Route::put('/{report}', [WorkAcceptanceReportController::class, 'publicUpdate'])->name('update');
});

// --- Internal authenticated routes ---
Route::middleware(['auth:sanctum', config('jetstream.auth_session'), 'verified'])
    ->prefix('work-acceptance-reports')
    ->name('work-acceptance-reports.')
    ->group(function () {
        Route::post('/', [WorkAcceptanceReportController::class, 'store'])->name('store');
        Route::get('/{report}', [WorkAcceptanceReportController::class, 'show'])->name('show');
        Route::put('/{report}', [WorkAcceptanceReportController::class, 'update'])->name('update');
        Route::post('/{report}/sign', [WorkAcceptanceReportController::class, 'storeSignature'])->name('sign');
        Route::delete('/{report}/signature', [WorkAcceptanceReportController::class, 'deleteSignature'])->name('delete-signature');
        Route::post('/{report}/generate-link', [WorkAcceptanceReportController::class, 'generatePublicLink'])->name('generate-link');
    });
