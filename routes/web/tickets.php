<?php

use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketTaskController;
use App\Http\Controllers\TicketAnalyticsController; // Nuevo Controlador
use Illuminate\Support\Facades\Route;

// --- RUTAS PÚBLICAS (ACCESO EXTERNO) ---
// Estas rutas validan la firma (signed) para seguridad sin login
Route::middleware(['signed'])->group(function () {
    Route::get('/t/track/{task}', [TicketTaskController::class, 'publicShow'])->name('tasks.public.show');
    Route::put('/t/track/{task}/toggle', [TicketTaskController::class, 'publicToggle'])->name('tasks.public.toggle');
    Route::post('/t/track/{task}/evidence', [TicketTaskController::class, 'publicEvidence'])->name('tasks.public.evidence');
    Route::delete('/t/track/evidence/{media}', [TicketTaskController::class, 'publicDestroyEvidence'])->name('tasks.public.evidence.destroy');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Dashboard de Analíticas Operativas
    Route::get('/tickets/dashboard', [TicketAnalyticsController::class, 'index'])->name('tickets.dashboard');

    // Rutas principales de Tickets
    Route::resource('tickets', TicketController::class);
    
    // RUTA NUEVA: Crear Ticket Automáticamente desde Presupuesto
    Route::post('/budgets/{budget}/ticket-auto', [TicketController::class, 'storeFromBudget'])->name('tickets.store-from-budget');
    
    // Actualizar estatus rápido
    Route::put('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');

    // --- TAREAS DEL TICKET ---
    Route::post('/tickets/{ticket}/tasks', [TicketTaskController::class, 'store'])->name('tickets.tasks.store');
    Route::put('/tickets/tasks/{task}', [TicketTaskController::class, 'update'])->name('tickets.tasks.update');
    Route::delete('/tickets/tasks/{task}', [TicketTaskController::class, 'destroy'])->name('tickets.tasks.destroy');
    Route::put('/tickets/tasks/{task}/toggle', [TicketTaskController::class, 'toggleComplete'])->name('tickets.tasks.toggle');

    // --- EVIDENCIAS ---
    Route::post('/tickets/{ticket}/evidence', [TicketController::class, 'storeEvidence'])->name('tickets.evidence.store');
    Route::post('/tickets/tasks/{task}/evidence', [TicketTaskController::class, 'storeEvidence'])->name('tickets.tasks.evidence.store');
    Route::delete('/tickets/evidence/{media}', [TicketController::class, 'destroyEvidence'])->name('tickets.evidence.destroy');

});