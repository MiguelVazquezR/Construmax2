<?php

use App\Http\Controllers\BudgetController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    Route::post('/budgets/{budget}/technician-payments', [BudgetController::class, 'storeTechnicianPayment'])->name('budgets.technician-payments.store');
    Route::delete('/technician-payments/{payment}', [BudgetController::class, 'destroyTechnicianPayment'])->name('budgets.technician-payments.destroy');
    // Rutas Resource principales
    Route::resource('budgets', BudgetController::class);

    // Pagos
    Route::post('/budgets/{budget}/payments', [BudgetController::class, 'storePayment'])->name('budgets.payments.store');
    Route::delete('/budgets/payments/{payment}', [BudgetController::class, 'destroyPayment'])->name('budgets.payments.destroy');

    // Archivos
    Route::post('/budgets/{budget}/files', [BudgetController::class, 'storeFile'])->name('budgets.files.store');
    Route::delete('/budgets/files/{media}', [BudgetController::class, 'destroyFile'])->name('budgets.files.destroy');
});
