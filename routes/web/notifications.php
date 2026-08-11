<?php

use App\Http\Controllers\Config\NotificationController;
use Illuminate\Support\Facades\Route;

// API routes for fetching and marking notifications
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/notifications/fetch', [NotificationController::class, 'fetch'])->name('notifications.fetch');
    Route::post('/notifications/{id}/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::delete('/notifications/{id}', [NotificationController::class, 'deleteOne'])->name('notifications.delete');
    Route::delete('/notifications', [NotificationController::class, 'deleteAll'])->name('notifications.delete-all');
});

// Notification settings management
Route::middleware(['auth', 'verified'])->prefix('config/notifications')->name('config.notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'index'])->name('index');
    Route::post('/', [NotificationController::class, 'store'])->name('store');
    Route::delete('/{setting}', [NotificationController::class, 'destroy'])->name('destroy');
    Route::delete('/user/{user}', [NotificationController::class, 'deleteUserSettings'])->name('delete-user');
});
