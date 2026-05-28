<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'index'])->name('index');
    Route::post('/{budget}/upload', [InvoiceController::class, 'upload'])->name('upload');
});