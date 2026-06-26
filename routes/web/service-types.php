<?php

use App\Http\Controllers\ServiceTypeController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::get('/service-types', [ServiceTypeController::class, 'index'])->name('service-types.index');
    Route::post('/service-types', [ServiceTypeController::class, 'store'])->name('service-types.store');
    Route::put('/service-types/{serviceType}', [ServiceTypeController::class, 'update'])->name('service-types.update');
    Route::delete('/service-types/{serviceType}', [ServiceTypeController::class, 'destroy'])->name('service-types.destroy');
});
