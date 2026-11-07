<?php

use App\Http\Controllers\Api\V1\KioskController;
use App\Http\Middleware\ForceJsonResponse;
use Illuminate\Support\Facades\Route;

/*
 * Attendance kiosk API used by the Android tablet app.
 *
 * The app is installed manually from a controlled APK, so no device
 * authorization is required here: "attendance.device.optional" only tries to
 * recognize the tablet (device token header or Android ID) and never blocks a
 * request. The web kiosk keeps its own routes, protected by the strict
 * "attendance.device" middleware.
 *
 * Fully documented in docs/kiosk-android-app.md: endpoints, payloads, error
 * codes, throttles and the client state machine.
 */
Route::prefix('v1/kiosk')
    ->name('api.kiosk.')
    ->middleware([ForceJsonResponse::class, 'attendance.device.optional'])
    ->group(function () {
        // A device that wants its ERP registration (friendly name, location)
        // can still register or recover itself with the shared password.
        Route::post('/devices/register', [KioskController::class, 'registerDevice'])
            ->middleware('throttle:10,1')
            ->name('devices.register');

        Route::post('/recover', [KioskController::class, 'recover'])
            ->middleware('throttle:20,1')
            ->name('recover');

        Route::get('/ping', [KioskController::class, 'ping'])
            ->middleware('throttle:240,1')
            ->name('ping');

        Route::post('/bootstrap', [KioskController::class, 'bootstrap'])
            ->middleware('throttle:120,1')
            ->name('bootstrap');

        Route::post('/punch', [KioskController::class, 'punch'])
            ->middleware('throttle:60,1')
            ->name('punch');

        Route::post('/face-punch', [KioskController::class, 'facePunch'])
            ->middleware('throttle:60,1')
            ->name('face-punch');
    });
