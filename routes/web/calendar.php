<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Ruta para verificar estado (Badge del Topbar)
    Route::get('/calendar/overview', [CalendarController::class, 'overview'])->name('calendar.overview');

    // Rutas CRUD
    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/calendar', [CalendarController::class, 'store'])->name('calendar.store');
    Route::put('/calendar/{calendar}', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/calendar/{calendar}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
    
    // Responder a invitación
    Route::put('/calendar/{calendar}/respond', [CalendarController::class, 'respond'])->name('calendar.respond');

});