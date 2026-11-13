<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Attendance kiosk API for the Android tablet app (see docs/kiosk-android-app.md).
require __DIR__.'/api-kiosk.php';
