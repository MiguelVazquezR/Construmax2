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

    Route::resource('technicians', TechnicianController::class);
    
});