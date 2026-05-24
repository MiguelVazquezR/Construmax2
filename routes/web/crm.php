<?php

use Illuminate\Support\Facades\Route;

// Redirigir el antiguo dashboard CRM al nuevo tablero unificado de analíticas
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('crm')->name('crm.')->group(function () {
    Route::redirect('/dashboard', '/tickets/dashboard')->name('dashboard');
});