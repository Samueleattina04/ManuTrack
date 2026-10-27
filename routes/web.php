<?php

use App\Http\Controllers\AssetQrController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\BrandingController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Maintenance\QuickReportController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Controllers\Push\PushTestController;
use App\Http\Controllers\Pwa\PwaController;
use App\Http\Controllers\Pwa\ServiceWorkerController;
use App\Http\Controllers\SystemActionsController;
use App\Http\Controllers\SystemController;
use App\Livewire\Assets\Categories as AssetCategories;
use App\Livewire\Assets\Form as AssetForm;
use App\Livewire\Assets\Import as AssetImport;
use App\Livewire\Assets\Index as AssetsIndex;
use App\Livewire\Assets\Locations as AssetLocations;
use App\Livewire\Assets\Show as AssetShow;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Inventory\Import as PartImport;
use App\Livewire\Inventory\Movements as StockMovements;
use App\Livewire\Inventory\PartForm;
use App\Livewire\Inventory\Parts as PartsIndex;
use App\Livewire\Inventory\PartShow;
use App\Livewire\Inventory\Reorder as PartsReorder;
use App\Livewire\Inventory\Suppliers;
use App\Livewire\Inventory\Warehouses;
use App\Livewire\Kpi\Dashboard as KpiDashboard;
use App\Livewire\Maintenance\Settings as MaintenanceSettings;
use App\Livewire\Preventive\Calendar as PreventiveCalendar;
use App\Livewire\Preventive\Form as PlanForm;
use App\Livewire\Preventive\Index as PreventiveIndex;
use App\Livewire\Preventive\Show as PlanShow;
use App\Livewire\Profile;
use App\Livewire\Requests\Index as RequestsIndex;
use App\Livewire\Requests\Show as RequestShow;
use App\Livewire\Settings\Company;
use App\Livewire\Users\Index as UsersIndex;
use App\Livewire\Users\Roles;
use App\Livewire\WorkOrders\Create as WorkOrderCreate;
use App\Livewire\WorkOrders\Index as WorkOrdersIndex;
use App\Livewire\WorkOrders\Show as WorkOrderShow;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
| Nessuna closure nelle rotte: l'aggiornamento esegue route:cache.
*/

Route::get('/manifest.webmanifest', ManifestController::class)->name('manifest');

// App installabile: service worker e pagina offline (pubblici, servono anche prima dell'accesso)
// Senza sessione né cookie: il browser li richiede spesso e non servono
Route::withoutMiddleware([
    StartSession::class,
    ShareErrorsFromSession::class,
    ValidateCsrfToken::class,
    AddQueuedCookiesToResponse::class,
    EncryptCookies::class,
])->group(function () {
    Route::get('/sw.js', [ServiceWorkerController::class, 'worker'])->name('pwa.worker');
    Route::get('/offline', [ServiceWorkerController::class, 'offline'])->name('pwa.offline');
});

// Rinnovo dell'iscrizione push chiesto dal service worker (senza sessione né token CSRF)
Route::post('/push/rinnova', [PushSubscriptionController::class, 'renew'])->middleware(['installed', 'throttle:push'])->name('push.renew');

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

    // App e notifiche: procedura guidata, diagnostica, iscrizioni
    Route::get('/app', [PwaController::class, 'install'])->name('pwa.install');
    Route::get('/notifiche/diagnostica', [PwaController::class, 'diagnostics'])->name('pwa.diagnostics');
    Route::middleware('throttle:push')->group(function () {
        Route::post('/push/iscrizioni', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
        Route::delete('/push/iscrizioni', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
        Route::delete('/push/dispositivi/{subscription}', [PushSubscriptionController::class, 'remove'])->name('push.devices.remove');
    });
    Route::post('/push/prova', PushTestController::class)->middleware('throttle:push-test')->name('push.test');

    // Macchine e impianti
    Route::middleware('can:assets.view')->group(function () {
        Route::get('/macchine', AssetsIndex::class)->name('assets.index');
        Route::get('/macchine/ubicazioni', AssetLocations::class)->name('assets.locations');
        Route::get('/macchine/etichette', [AssetQrController::class, 'labels'])->name('assets.labels');
        Route::get('/macchine/{asset}', AssetShow::class)->whereNumber('asset')->name('assets.show');
        Route::get('/macchine/{asset}/qr.svg', [AssetQrController::class, 'svg'])->whereNumber('asset')->name('assets.qr.svg');
    });
    Route::middleware('can:assets.manage')->group(function () {
        Route::get('/macchine/nuova', AssetForm::class)->name('assets.create');
        Route::get('/macchine/{asset}/modifica', AssetForm::class)->whereNumber('asset')->name('assets.edit');
        Route::get('/macchine/categorie', AssetCategories::class)->name('assets.categories');
        Route::get('/macchine/importa', AssetImport::class)->name('assets.import');
        Route::get('/macchine/importa/modello.csv', [AssetQrController::class, 'template'])->name('assets.import.template');
    });

    // Indirizzo stampato sulle etichette QR: chi non ha fatto l'accesso ci torna dopo il login
    Route::get('/q/{token}', [AssetQrController::class, 'resolve'])->name('assets.qr');

    // Segnalazioni: pagina rapida (anche offline) e invio, poi elenco e valutazione
    Route::get('/segnala', [QuickReportController::class, 'show'])->middleware('can:requests.create')->name('requests.create');
    Route::post('/segnalazioni/invia', [QuickReportController::class, 'store'])->middleware(['can:requests.create', 'throttle:reports'])->name('requests.store');
    Route::get('/segnalazioni', RequestsIndex::class)->name('requests.index');
    Route::get('/segnalazioni/{workRequest}', RequestShow::class)->whereNumber('workRequest')->name('requests.show');

    // Ordini di lavoro
    Route::middleware('can:work_orders.view')->group(function () {
        Route::get('/ordini', WorkOrdersIndex::class)->name('work-orders.index');
        Route::get('/ordini/{workOrder}', WorkOrderShow::class)->whereNumber('workOrder')->name('work-orders.show');
    });
    Route::get('/ordini/nuovo', WorkOrderCreate::class)->middleware('can:work_orders.create')->name('work-orders.create');
    Route::get('/ordini/impostazioni', MaintenanceSettings::class)->middleware('can:work_orders.settings')->name('work-orders.settings');

    // Indicatori
    Route::get('/indicatori', KpiDashboard::class)->middleware('can:reports.view')->name('kpi');

    // Manutenzione preventiva
    Route::middleware('can:preventive.view')->group(function () {
        Route::get('/preventiva', PreventiveIndex::class)->name('preventive.index');
        Route::get('/preventiva/calendario', PreventiveCalendar::class)->name('preventive.calendar');
        Route::get('/preventiva/piani/{plan}', PlanShow::class)->whereNumber('plan')->name('preventive.show');
    });
    Route::middleware('can:preventive.manage')->group(function () {
        Route::get('/preventiva/piani/nuovo', PlanForm::class)->name('preventive.create');
        Route::get('/preventiva/piani/{plan}/modifica', PlanForm::class)->whereNumber('plan')->name('preventive.edit');
    });

    // Magazzino ricambi
    Route::middleware('can:inventory.view')->group(function () {
        Route::get('/magazzino', PartsIndex::class)->name('inventory.parts.index');
        Route::get('/magazzino/ricambi/{part}', PartShow::class)->whereNumber('part')->name('inventory.parts.show');
        Route::get('/magazzino/movimenti', StockMovements::class)->name('inventory.movements');
        Route::get('/magazzino/fornitori', Suppliers::class)->name('inventory.suppliers');
    });
    Route::middleware('can:inventory.manage')->group(function () {
        Route::get('/magazzino/ricambi/nuovo', PartForm::class)->name('inventory.parts.create');
        Route::get('/magazzino/ricambi/{part}/modifica', PartForm::class)->whereNumber('part')->name('inventory.parts.edit');
        Route::get('/magazzino/magazzini', Warehouses::class)->name('inventory.warehouses');
        Route::get('/magazzino/riordino', PartsReorder::class)->name('inventory.reorder');
        Route::get('/magazzino/importa', PartImport::class)->name('inventory.import');
        Route::get('/magazzino/importa/modello.csv', [InventoryController::class, 'template'])->name('inventory.import.template');
    });

    // Allegati (il controllo dei permessi dipende dall'oggetto a cui appartengono)
    Route::get('/allegati/{attachment}', [AttachmentController::class, 'show'])->whereNumber('attachment')->name('attachments.show');
    Route::get('/allegati/{attachment}/scarica', [AttachmentController::class, 'download'])->whereNumber('attachment')->name('attachments.download');

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
