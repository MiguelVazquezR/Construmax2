<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Ruta para cambiar estatus (debe ir antes del resource o ser específica)
    Route::put('/customers/{customer}/toggle-status', [CustomerController::class, 'toggleStatus'])->name('customers.toggle-status');
    
    // Rutas para gestión de archivos multimedia
    Route::delete('/customers/{customer}/media/{media}', [CustomerController::class, 'deleteMedia'])->name('customers.media.destroy');
    Route::delete('/customers/{customer}/logo', [CustomerController::class, 'deleteLogo'])->name('customers.logo.destroy');
    Route::post('/customers/{customer}/upload-files', [CustomerController::class, 'uploadFiles'])->name('customers.upload-files');
    Route::post('/customers/quick-branch', [CustomerController::class, 'quickStoreBranch'])->name('customers.quick-branch');
    
    Route::resource('customers', CustomerController::class);
    
});