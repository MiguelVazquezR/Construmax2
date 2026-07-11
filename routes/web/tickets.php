<?php

use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketTaskController;
use App\Http\Controllers\TicketAnalyticsController;
use App\Http\Controllers\TaskTemplateController;
use Illuminate\Support\Facades\Route;

// --- RUTAS PÚBLICAS (ACCESO EXTERNO) ---
Route::middleware(['signed'])->group(function () {
    Route::get('/t/job-order/{ticket}/{user}', [TicketTaskController::class, 'publicJobOrder'])->name('tickets.public.job-order');
    Route::put('/t/track/{task}/toggle', [TicketTaskController::class, 'publicToggle'])->name('tasks.public.toggle');
    Route::post('/t/track/{task}/evidence', [TicketTaskController::class, 'publicEvidence'])->name('tasks.public.evidence');
    Route::delete('/t/track/evidence/{media}', [TicketTaskController::class, 'publicDestroyEvidence'])->name('tasks.public.evidence.destroy');
    Route::put('/t/track/{task}/notes', [TicketTaskController::class, 'publicUpdateNotes'])->name('tasks.public.notes');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Rutas de Tickets
    Route::get('/tickets/dashboard', [TicketAnalyticsController::class, 'index'])->name('tickets.dashboard');
    Route::resource('tickets', TicketController::class);
    Route::post('/budgets/{budget}/ticket-auto', [TicketController::class, 'storeFromBudget'])->name('tickets.store-from-budget');
    Route::put('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->name('tickets.update-status');
    Route::put('/tickets/{ticket}/technicians', [TicketController::class, 'updateTechnicians'])->name('tickets.update-technicians');
    Route::put('/tickets/{ticket}/important-note', [TicketController::class, 'updateImportantNote'])->name('tickets.update-important-note');
    Route::put('/tickets/{ticket}/report-number', [TicketController::class, 'updateReportNumber'])->name('tickets.update-report-number');
    Route::put('/tickets/{ticket}/update-field', [TicketController::class, 'updateField'])->name('tickets.update-field');

    // Rutas de Tareas
    Route::post('/tickets/{ticket}/tasks', [TicketTaskController::class, 'store'])->name('tickets.tasks.store');
    Route::put('/tickets/tasks/{task}', [TicketTaskController::class, 'update'])->name('tickets.tasks.update');
    Route::delete('/tickets/tasks/{task}', [TicketTaskController::class, 'destroy'])->name('tickets.tasks.destroy');
    Route::put('/tickets/tasks/{task}/toggle', [TicketTaskController::class, 'toggleComplete'])->name('tickets.tasks.toggle');
    Route::put('/tickets/tasks/{task}/notes', [TicketTaskController::class, 'updateNotes'])->name('tickets.tasks.notes');

    // Rutas de Evidencias
    Route::post('/tickets/{ticket}/evidence', [TicketController::class, 'storeEvidence'])->name('tickets.evidence.store');
    Route::get('/tickets/{ticket}/evidence-template', [TicketController::class, 'evidenceTemplate'])->name('tickets.evidence-template');
    Route::post('/tickets/tasks/{task}/evidence', [TicketTaskController::class, 'storeEvidence'])->name('tickets.tasks.evidence.store');
    Route::post('/tickets/tasks/{task}/evidence/reorder', [TicketTaskController::class, 'reorderEvidence'])->name('tickets.tasks.evidence.reorder');
    Route::delete('/tickets/evidence/{media}', [TicketController::class, 'destroyEvidence'])->name('tickets.evidence.destroy');

    // Rutas de Plantillas de Tareas
    Route::get('/task-templates', [TaskTemplateController::class, 'index'])->name('task-templates.index');
    Route::post('/task-templates', [TaskTemplateController::class, 'store'])->name('task-templates.store');
    Route::put('/task-templates/{taskTemplate}', [TaskTemplateController::class, 'update'])->name('task-templates.update');
    Route::put('/task-templates/{taskTemplate}/toggle-status', [TaskTemplateController::class, 'toggleStatus'])->name('task-templates.toggle-status');
    Route::delete('/task-templates/{taskTemplate}', [TaskTemplateController::class, 'destroy'])->name('task-templates.destroy');

});