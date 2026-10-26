<?php

use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\SystemActionsController;
use App\Http\Controllers\SystemController;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Profile;
use App\Livewire\Settings\Company;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Users\Roles;
use Illuminate\Support\Facades\Route;

/*
| Nessuna closure nelle rotte: l'aggiornamento esegue route:cache.
*/

Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

// Marchio dell'azienda (pubblico: serve anche alla pagina di accesso)
Route::get('/marchio/logo', [BrandingController::class, 'logo'])->name('branding.logo');
Route::get('/marchio/icone/{version}/{file}', [BrandingController::class, 'icon'])->name('branding.icon');

// Installazione dal browser: disponibile solo finché l'app non è installata
Route::middleware('installer')->prefix('install')->name('install.')->group(function () {
    Route::get('/', [InstallController::class, 'show'])->name('show');
    Route::post('/', [InstallController::class, 'store'])->middleware('throttle:install')->name('store');
});

// Inviti (per chi non ha ancora un account)
Route::middleware(['installed', 'guest', 'throttle:invitation'])->group(function () {
    Route::get('/invito/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('/invito/{token}', [InvitationController::class, 'accept'])->name('invitation.accept');
});

Route::middleware(['installed', 'auth', 'active'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/profilo', Profile::class)->name('profile');
    Route::get('/design', DesignController::class)->name('design');

    Route::get('/utenti', UsersIndex::class)->middleware('can:users.view')->name('users.index');
    Route::get('/ruoli', Roles::class)->middleware('can:users.manage')->name('roles.index');
    Route::get('/impostazioni/azienda', Company::class)->middleware('can:settings.manage')->name('settings.company');
    Route::get('/registro', AuditIndex::class)->middleware('can:audit.view')->name('audit.index');

    Route::get('/sistema', SystemController::class)->middleware(['can:system.view', 'password.confirm'])->name('system');
    Route::middleware(['can:system.update', 'password.confirm'])->prefix('sistema')->name('system.')->group(function () {
        Route::post('/aggiorna', [SystemActionsController::class, 'update'])->name('update');
        Route::post('/ritenta', [SystemActionsController::class, 'retry'])->name('retry');
        Route::post('/copia', [SystemActionsController::class, 'backup'])->name('backup');
    });
});
