<?php

use App\Http\Controllers\BudgetExpenseController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('expenses')->name('expenses.')->group(function () {
    Route::get('/', [ExpenseController::class, 'index'])->name('index');
    Route::post('/', [ExpenseController::class, 'store'])->name('store');

    // Excel report with the filters applied on the index
    Route::get('/export', [ExpenseController::class, 'export'])->name('export');

    // Budget expenses: manage the payments of a budget breakdown
    Route::prefix('budgets')->name('budgets.')->group(function () {
        Route::get('/search', [BudgetExpenseController::class, 'search'])->name('search');
        Route::get('/{budget}', [BudgetExpenseController::class, 'show'])->name('show');
        Route::post('/{budget}/concepts/mark-paid', [BudgetExpenseController::class, 'markConceptsPaid'])->name('concepts.mark-paid');
        Route::post('/{budget}/concepts/{concept}/payment', [BudgetExpenseController::class, 'storeConceptPayment'])->name('concepts.payment');
    });

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

    // Complete the deposit linked to an expense (voucher + commission)
    Route::post('/{expense}/complete-deposit', [ExpenseController::class, 'completeDeposit'])->name('complete-deposit');
});
