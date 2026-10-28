<?php

use App\Actions\Preventive\GeneratePlanOrders;
use App\Support\Demo\DemoInstaller;
use App\Support\Demo\Edition;
use App\Support\Gdpr\Eraser;
use App\Support\Setup\SystemStatus;
use App\Support\Update\Updater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

/*
|--------------------------------------------------------------------------
| Pianificazioni
|--------------------------------------------------------------------------
|
| Sul server basta una sola voce di cron, ogni minuto:
|   * * * * * /usr/local/bin/php /percorso/manutrack/artisan schedule:run >> /dev/null 2>&1
|
| Tutti i compiti sono eseguiti dentro lo stesso processo PHP (Schedule::call),
| perché sugli hosting condivisi proc_open/exec sono spesso disabilitati.
| Nessun processo resta attivo dopo la fine del minuto.
|
*/

// Installazione cancellata a fine contratto: niente database, niente compiti
if (Eraser::erased()) {
    return;
}

// Segnale di vita del cron, mostrato nella pagina Sistema
Schedule::call(function (): void {
    Cache::forever(SystemStatus::CRON_KEY, now()->toIso8601String());
})->everyMinute()->name('manutrack:heartbeat');

// Aggiornamento automatico: solo con pacchetto completo e dopo il tempo di quiete
Schedule::call(function (): void {
    app(Updater::class)->runAutomatic();
})->everyMinute()->name('manutrack:auto-update')->withoutOverlapping(30);

// Code: si svuotano e il processo termina (niente worker permanenti)
Schedule::call(function (): void {
    if (config('queue.default') === 'sync') {
        return;
    }
    Artisan::call('queue:work', [
        '--stop-when-empty' => true,
        '--max-time' => 50,
        '--tries' => 3,
        '--sleep' => 1,
    ]);
})->everyMinute()->name('manutrack:queue')->withoutOverlapping(10);

// Manutenzione preventiva: ordini dei piani in scadenza
Schedule::call(function (): void {
    app(GeneratePlanOrders::class)->run();
})->everyFifteenMinutes()->name('manutrack:preventive')->withoutOverlapping(30);

// Installazione demo: si azzera ogni notte (solo se pacchetto, .env e database sono tutti "demo")
Schedule::call(function (): void {
    if (Edition::isDemo()) {
        app(DemoInstaller::class)->run();
    }
})->dailyAt((string) config('manutrack.demo_reset_at', '03:00'))->name('manutrack:demo-reset')->withoutOverlapping(60);

// Riepilogo giornaliero degli avvisi via email (e pulizia degli avvisi oltre 90 giorni)
Schedule::call(function (): void {
    Artisan::call('manutrack:avvisi-riepilogo');
})->dailyAt('07:10')->name('manutrack:digest')->withoutOverlapping(30);

// Rapporto mensile: il 1° del mese si avvisa che quello del mese appena finito è pronto
Schedule::call(function (): void {
    Artisan::call('manutrack:rapporto-mensile');
})->monthlyOn(1, '07:20')->name('manutrack:monthly-report')->withoutOverlapping(30);

// Orario di lavoro: festività nazionali proposte per l'anno in corso e il prossimo
Schedule::call(function (): void {
    Artisan::call('manutrack:festivita');
})->dailyAt('02:45')->name('manutrack:holidays')->withoutOverlapping(30);

// Conservazione dei dati (GDPR): anonimizzazione, registro attività, esportazioni scadute
Schedule::call(function (): void {
    Artisan::call('manutrack:conservazione');
})->dailyAt('03:50')->name('manutrack:retention')->withoutOverlapping(60);

// Pulizie periodiche
Schedule::call(function (): void {
    Artisan::call('queue:prune-failed', ['--hours' => 24 * 30]);
    Artisan::call('queue:prune-batches', ['--hours' => 24 * 7]);
    // File temporanei di Livewire abbandonati (pagina chiusa prima di salvare): Livewire li
    // toglie solo al caricamento successivo, che può non arrivare mai
    if (! FileUploadConfiguration::isUsingS3()) {
        $storage = FileUploadConfiguration::storage();
        foreach ($storage->allFiles(FileUploadConfiguration::path()) as $file) {
            if ($storage->lastModified($file) < now()->subDay()->getTimestamp()) {
                $storage->delete($file);
            }
        }
    }
})->dailyAt('03:30')->name('manutrack:prune');
