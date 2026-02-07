<?php

use App\Http\Controllers\CRMAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('crm')->name('crm.')->group(function () {
    
    // Dashboard Principal de Analíticas
    Route::get('/dashboard', [CRMAnalyticsController::class, 'index'])->name('dashboard');
    
    // Aquí podrías agregar más rutas de reportes específicos en el futuro
});