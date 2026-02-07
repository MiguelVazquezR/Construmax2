<?php

use App\Http\Controllers\RolePermissionController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->prefix('config')->name('config.')->group(function () {
    
    // Ruta principal (Index)
    Route::get('/roles-permissions', [RolePermissionController::class, 'index'])->name('roles-permissions.index');

    // Rutas para ROLES
    Route::post('/roles', [RolePermissionController::class, 'storeRole'])->name('roles.store');
    Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole'])->name('roles.update');
    Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole'])->name('roles.destroy');

    // Rutas para PERMISOS (Protegidas por lógica en controlador, pero definimos la ruta)
    Route::post('/permissions', [RolePermissionController::class, 'storePermission'])->name('permissions.store');
    Route::put('/permissions/{permission}', [RolePermissionController::class, 'updatePermission'])->name('permissions.update');
    Route::delete('/permissions/{permission}', [RolePermissionController::class, 'destroyPermission'])->name('permissions.destroy');

});