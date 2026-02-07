<?php

use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketTaskController;
use App\Http\Controllers\TicketAnalyticsController; // Nuevo Controlador
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Dashboard de Analíticas Operativas
    Route::get('/tickets/dashboard', [TicketAnalyticsController::class, 'index'])->name('tickets.dashboard');

    // Rutas principales de Tickets
    Route::resource('tickets', TicketController::class);
    
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