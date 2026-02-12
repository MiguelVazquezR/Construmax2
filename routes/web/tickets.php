<?php

use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketTaskController;
use App\Http\Controllers\TicketAnalyticsController;
use Illuminate\Support\Facades\Route;

// --- RUTAS PÚBLICAS (ACCESO EXTERNO) ---
Route::middleware(['signed'])->group(function () {
    // CAMBIO: Ruta para ver TODAS las tareas del técnico en un ticket específico
    Route::get('/t/job-order/{ticket}/{user}', [TicketTaskController::class, 'publicJobOrder'])->name('tickets.public.job-order');
    
    // Acciones individuales (se mantienen para que funcionen las peticiones AJAX desde la vista maestra)
    Route::put('/t/track/{task}/toggle', [TicketTaskController::class, 'publicToggle'])->name('tasks.public.toggle');
    Route::post('/t/track/{task}/evidence', [TicketTaskController::class, 'publicEvidence'])->name('tasks.public.evidence');
    Route::delete('/t/track/evidence/{media}', [TicketTaskController::class, 'publicDestroyEvidence'])->name('tasks.public.evidence.destroy');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    Route::get('/tickets/dashboard', [TicketAnalyticsController::class, 'index'])->name('tickets.dashboard');
    Route::resource('tickets', TicketController::class);
    Route::post('/budgets/{budget}/ticket-auto', [TicketController::class, 'storeFromBudget'])->name('tickets.store-from-budget');
    Route::put('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');

    Route::post('/tickets/{ticket}/tasks', [TicketTaskController::class, 'store'])->name('tickets.tasks.store');
    Route::put('/tickets/tasks/{task}', [TicketTaskController::class, 'update'])->name('tickets.tasks.update');
    Route::delete('/tickets/tasks/{task}', [TicketTaskController::class, 'destroy'])->name('tickets.tasks.destroy');
    Route::put('/tickets/tasks/{task}/toggle', [TicketTaskController::class, 'toggleComplete'])->name('tickets.tasks.toggle');

    Route::post('/tickets/{ticket}/evidence', [TicketController::class, 'storeEvidence'])->name('tickets.evidence.store');
    Route::post('/tickets/tasks/{task}/evidence', [TicketTaskController::class, 'storeEvidence'])->name('tickets.tasks.evidence.store');
    Route::delete('/tickets/evidence/{media}', [TicketController::class, 'destroyEvidence'])->name('tickets.evidence.destroy');

});