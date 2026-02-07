<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirección inicial
Route::redirect('/', '/login');

// Grupo Autenticado
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Dashboard Principal Inteligente
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

});

// Importar rutas modulares
require __DIR__ . '/web/users.php';
require __DIR__ . '/web/roles-permissions.php';
require __DIR__ . '/web/customers.php';
require __DIR__ . '/web/budgets.php';
require __DIR__ . '/web/crm.php';
require __DIR__ . '/web/tickets.php';
require __DIR__ . '/web/calendar.php';