<?php

use App\Http\Controllers\FieldWorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    // API: available tickets for scheduling
    Route::get('/field-work/available-tickets', [FieldWorkScheduleController::class, 'availableTickets'])
        ->name('field-work.available-tickets');

    // API: field work events for calendar rendering
    Route::get('/field-work/events', [FieldWorkScheduleController::class, 'events'])
        ->name('field-work.events');

    // CRUD
    Route::post('/field-work', [FieldWorkScheduleController::class, 'store'])
        ->name('field-work.store');

    Route::put('/field-work/{schedule}', [FieldWorkScheduleController::class, 'update'])
        ->name('field-work.update');

    Route::delete('/field-work/{schedule}', [FieldWorkScheduleController::class, 'destroy'])
        ->name('field-work.destroy');
});
