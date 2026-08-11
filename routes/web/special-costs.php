<?php

use App\Http\Controllers\SpecialCostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('special-costs')->name('special-costs.')->group(function () {
    Route::get('/', [SpecialCostController::class, 'index'])->name('index');
    Route::get('/{catalog}', [SpecialCostController::class, 'show'])->name('show');
    Route::post('/{catalog}/version', [SpecialCostController::class, 'storeCatalog'])->name('store-catalog');
    Route::post('/{catalog}/approve', [SpecialCostController::class, 'approveCatalog'])->name('approve-catalog');
});