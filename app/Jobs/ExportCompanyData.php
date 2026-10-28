<?php

namespace App\Jobs;

use App\Models\DataExport;
use App\Support\Audit\Audit;
use App\Support\Gdpr\DataExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Prepara lo ZIP dell'esportazione completa. Gira nella coda (svuotata dal cron), così
 * la pagina non resta in attesa; con QUEUE_CONNECTION=sync gira subito.
 */
class ExportCompanyData implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public int $exportId) {}

    public function handle(DataExporter $exporter): void
    {
        $export = DataExport::query()->with('user')->find($this->exportId);
        if (! $export || $export->status !== 'in_coda') {
            return;
        }
        $export->forceFill(['status' => 'in_corso', 'started_at' => now()])->save();
        try {
            $exporter->build($export);
            $export->forceFill(['status' => 'pronto', 'finished_at' => now(), 'expires_at' => now()->addDays(DataExport::KEEP_DAYS)])->save();
            Audit::log('system.data_export_ready', $export, $export->tables_count.' tabelle · '.$export->files_count.' file', actor: $export->user);
        } catch (Throwable $e) {
            report($e);
            $export->forceFill(['status' => 'errore', 'error' => mb_strimwidth($e->getMessage(), 0, 500), 'finished_at' => now()])->save();
        }
    }
}
