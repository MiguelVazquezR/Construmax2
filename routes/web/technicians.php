<?php

use App\Http\Controllers\TechnicianController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    // Rutas específicas para acciones AJAX/Parciales
    Route::post('technicians/quick', [TechnicianController::class, 'quickStore'])->name('technicians.quick-store');
    
    // Rutas específicas para acciones AJAX/Parciales
    Route::put('technicians/{technician}/rating', [TechnicianController::class, 'updateRating'])->name('technicians.update-rating');
    Route::put('technicians/{technician}/status', [TechnicianController::class, 'updateStatus'])->name('technicians.update-status');
    Route::delete('technicians/{technician}/media/{media}', [TechnicianController::class, 'deleteMedia'])->name('technicians.delete-media');

    // Bank accounts
    Route::post('technicians/{technician}/bank-accounts', [TechnicianController::class, 'storeBankAccount'])->name('technicians.bank-accounts.store');
    Route::post('technicians/{technician}/bank-accounts/{account}', [TechnicianController::class, 'updateBankAccount'])->name('technicians.bank-accounts.update');
    Route::delete('technicians/{technician}/bank-accounts/{account}', [TechnicianController::class, 'destroyBankAccount'])->name('technicians.bank-accounts.destroy');
    Route::put('technicians/{technician}/bank-accounts/{account}/favorite', [TechnicianController::class, 'setFavoriteBankAccount'])->name('technicians.bank-accounts.favorite');

    Route::resource('technicians', TechnicianController::class);
    
});