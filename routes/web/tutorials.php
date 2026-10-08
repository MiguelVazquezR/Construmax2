<?php

use App\Http\Controllers\TutorialController;
use Illuminate\Support\Facades\Route;

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/tutorials', [TutorialController::class, 'index'])->name('tutorials.index');
    Route::post('/tutorials', [TutorialController::class, 'store'])->name('tutorials.store');
    Route::put('/tutorials/{tutorial}', [TutorialController::class, 'update'])->name('tutorials.update');
    Route::delete('/tutorials/{tutorial}', [TutorialController::class, 'destroy'])->name('tutorials.destroy');

});
