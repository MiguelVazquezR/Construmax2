<?php

use App\Http\Controllers\CostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('costs')->name('costs.')->group(function () {
    Route::get('/', [CostController::class, 'index'])->name('index');
    Route::get('/{budget}', [CostController::class, 'show'])->name('show');
    Route::get('/{budget}/print', [CostController::class, 'print'])->name('print');
    Route::get('/{budget}/print-empeno-facil', [CostController::class, 'printEmpenoFacil'])->name('print-empeno-facil');
    Route::post('/{budget}/catalog', [CostController::class, 'storeCatalog'])->name('store-catalog');
    Route::post('/{budget}/catalog/{catalog}/approve', [CostController::class, 'approveCatalog'])->name('approve-catalog');
});