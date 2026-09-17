<?php

use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->name('index');
    Route::post('/', [ExpenseController::class, 'store'])->name('store');

    // Remote ticket search for the expense form (optional project link)
    Route::get('/tickets/search', [ExpenseController::class, 'searchTickets'])->name('tickets.search');

    // Categories catalog management (JSON API used by the expense form)
    Route::prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [ExpenseCategoryController::class, 'index'])->name('index');
        Route::post('/', [ExpenseCategoryController::class, 'store'])->name('store');
        Route::put('/{category}', [ExpenseCategoryController::class, 'update'])->name('update');
        Route::delete('/{category}', [ExpenseCategoryController::class, 'destroy'])->name('destroy');
    });

    Route::put('/{expense}', [ExpenseController::class, 'update'])->name('update');
    Route::delete('/{expense}', [ExpenseController::class, 'destroy'])->name('destroy');
    Route::post('/{expense}/mark-paid', [ExpenseController::class, 'markPaid'])->name('mark-paid');
});
