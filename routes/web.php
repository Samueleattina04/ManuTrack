<?php

use App\Http\Controllers\DesignController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\SystemController;
use Illuminate\Support\Facades\Route;

/*
| Nessuna closure nelle rotte: l'aggiornamento esegue route:cache.
*/

Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

// Installazione dal browser: disponibile solo finché l'app non è installata
Route::middleware('installer')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('/', [InstallController::class, 'store'])->middleware('throttle:install')->name('store');
});

Route::middleware('installed')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/sistema', SystemController::class)->name('system');
    Route::get('/design', DesignController::class)->name('design');
});
