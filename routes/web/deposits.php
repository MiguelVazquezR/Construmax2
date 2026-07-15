<?php

use App\Http\Controllers\DepositController;
use App\Http\Controllers\DepositTypeController;
use App\Http\Controllers\PublicDepositController;
use Illuminate\Support\Facades\Route;

// --- Public routes (signed, no auth, permanent — no expiration) ---
Route::prefix('d')->name('public.deposits.')->group(function () {
    Route::get('/{deposit}', [PublicDepositController::class, 'show'])
        ->name('show')
        ->middleware('signed');
    Route::get('/day/{date}', [PublicDepositController::class, 'day'])
        ->name('day')
        ->middleware('signed');
    Route::post('/{deposit}/complete', [PublicDepositController::class, 'complete'])
        ->name('complete')
        ->middleware('signed');
});

// --- Authenticated routes ---
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::prefix('deposits')->name('deposits.')->group(function () {
        Route::get('/', [DepositController::class, 'index'])->name('index');
        Route::post('/', [DepositController::class, 'store'])->name('store');
        Route::put('/{deposit}', [DepositController::class, 'update'])->name('update');
        Route::delete('/{deposit}', [DepositController::class, 'destroy'])->name('destroy');
        Route::post('/{deposit}/approve', [DepositController::class, 'approve'])->name('approve');

        // Public link generation (permanent, signed)
        Route::get('/{deposit}/public-link', [DepositController::class, 'depositLink'])->name('public-link');
        Route::get('/public-link/day/{date}', [DepositController::class, 'dayLink'])->name('day-link');

        // Auxiliary data for the deposit form (JSON)
        Route::get('/technicians/{technician}/bank-accounts', [DepositController::class, 'technicianBankAccounts'])
            ->name('technician-bank-accounts');
        Route::get('/technicians/{technician}/pending-tickets', [DepositController::class, 'technicianPendingTickets'])
            ->name('technician-pending-tickets');

        // Deposit types CRUD (JSON API)
        Route::prefix('types')->name('types.')->group(function () {
            Route::get('/', [DepositTypeController::class, 'index'])->name('index');
            Route::post('/', [DepositTypeController::class, 'store'])->name('store');
            Route::put('/{depositType}', [DepositTypeController::class, 'update'])->name('update');
            Route::delete('/{depositType}', [DepositTypeController::class, 'destroy'])->name('destroy');
        });
    });
});
