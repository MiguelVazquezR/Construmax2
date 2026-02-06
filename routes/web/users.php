<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Rutas personalizadas (definirlas explícitamente)
    Route::put('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');

    // Ruta Resource: Genera index, create, store, show, edit, update, destroy automáticamente
    // URL base: /users | Nombre base: users.*
    Route::resource('users', UserController::class);

});