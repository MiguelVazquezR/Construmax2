<?php

use App\Http\Controllers\CostController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('costs')->name('costs.')->group(function () {
    Route::get('/', [CostController::class, 'index'])->name('index');
    Route::get('/{budget}', [CostController::class, 'show'])->name('show');
});