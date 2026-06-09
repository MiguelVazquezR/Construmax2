<?php

use App\Http\Controllers\TutorialController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/tutorials', [TutorialController::class, 'index'])->name('tutorials.index');

});
