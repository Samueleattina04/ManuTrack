<?php

namespace App\Support\Gdpr;

use App\Models\Asset;
use App\Models\Attachment;
use App\Models\DataExport;
use App\Support\Brand\CompanyLogo;
use App\Support\Plant\AssetModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Esportazione completa dei dati dell'azienda in uno ZIP (docs/GDPR.md):
 *   INDICE.md e indice.json  cosa c'è, quante righe, quali file e a cosa sono collegati;
 *   dati/<tabella>.json       una tabella per file, righe come oggetti JSON;
 *   file/...                  allegati originali, modelli 3D, logo dell'azienda.
 * Restano fuori solo dati tecnici o segreti (password, codici di accesso, sessioni, code),
 * elencati nell'indice con il motivo.
 */
final class DataExporter
{
    /** Tabelle tecniche escluse: tabella => motivo */
    public const EXCLUDED_TABLES = [
        'cache' => 'memoria temporanea',
        'cache_locks' => 'memoria temporanea',
        'jobs' => 'coda dei lavori in attesa',
        'job_batches' => 'coda dei lavori',
        'failed_jobs' => 'lavori non riusciti (dati tecnici)',
        'sessions' => 'sessioni di accesso (codici segreti)',
        'password_reset_tokens' => 'codici di reimpostazione della password (segreti)',
        'push_subscriptions' => 'chiavi di cifratura delle notifiche dei dispositivi (segrete)',
        'migrations' => 'versione dello schema del database',
        'data_exports' => 'elenco delle esportazioni',
    ];

    /** Colonne escluse: tabella => [colonne] */
    public const EXCLUDED_COLUMNS = [
        'users' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
        'invitations' => ['token_hash'],
    ];

    /** Descrizione delle tabelle per l'indice (le altre: nome tecnico) */
    private const DESCRIPTIONS = [
        'users' => 'Persone (senza password né codici di accesso)',
        'roles' => 'Ruoli', 'permissions' => 'Permessi', 'model_has_roles' => 'Ruoli delle persone',
        'model_has_permissions' => 'Permessi assegnati direttamente', 'role_has_permissions' => 'Permessi dei ruoli',
        'invitations' => 'Inviti (senza il codice d\'invito)', 'settings' => 'Impostazioni dell\'azienda',
        'audit_logs' => 'Registro attività', 'locations' => 'Ubicazioni', 'asset_categories' => 'Categorie di macchine',
        'assets' => 'Macchine e impianti', 'meters' => 'Contatori', 'meter_readings' => 'Letture dei contatori',
        'attachments' => 'Allegati (i file sono in file/allegati, vedi indice)', 'work_requests' => 'Segnalazioni',
        'work_orders' => 'Ordini di lavoro', 'work_order_assignees' => 'Assegnatari degli ordini',
        'work_order_checklist_items' => 'Checklist degli ordini', 'work_order_comments' => 'Commenti degli ordini',
        'work_order_status_changes' => 'Storico degli stati degli ordini', 'time_entries' => 'Tempi di lavoro',
        'downtime_events' => 'Fermi macchina', 'failure_codes' => 'Codici guasto', 'checklist_templates' => 'Modelli di checklist',
        'checklist_template_items' => 'Voci dei modelli di checklist', 'parts' => 'Ricambi', 'warehouses' => 'Magazzini',
        'stock_levels' => 'Giacenze', 'stock_movements' => 'Movimenti di magazzino', 'suppliers' => 'Fornitori',
        'part_supplier' => 'Ricambi per fornitore', 'asset_part' => 'Distinta ricambi delle macchine',
        'maintenance_plans' => 'Piani preventivi', 'maintenance_plan_parts' => 'Ricambi previsti dai piani',
        'user_notifications' => 'Avvisi', 'notification_preferences' => 'Preferenze degli avvisi',
        'plant_layouts' => 'Piante dello stabilimento', 'layout_items' => 'Elementi delle piante',
        'work_calendars' => 'Calendari di lavoro', 'work_calendar_schedules' => 'Orari settimanali',
        'work_calendar_shifts' => 'Turni', 'work_calendar_exceptions' => 'Giorni speciali',
        'work_calendar_assignments' => 'Assegnazioni dei calendari', 'sequences' => 'Numerazioni',
        'system_versions' => 'Versioni installate',
    ];

    public function build(DataExport $export): void
    {
        @set_time_limit(0);
        $disk = Storage::disk(AssetModel::disk());
        $relative = 'exports/manutrack-dati-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(6)).'.zip';
        $zipPath = $disk->path($relative);
        @mkdir(dirname($zipPath), 0775, true);
        $work = storage_path('framework/cache/export-'.$export->id.'-'.Str::random(8));
        @mkdir($work, 0775, true);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Impossibile creare lo ZIP');
        }

        try {
            $tables = $this->exportTables($zip, $work);
            $files = $this->exportFiles($zip);
            $index = $this->index($export, $tables, $files);
            $zip->addFromString('indice.json', (string) json_encode($index, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $zip->addFromString('INDICE.md', $this->markdown($index));
            if (! $zip->close()) {
                throw new RuntimeException('Impossibile completare lo ZIP');
            }
        } finally {
            foreach (glob($work.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($work);
        }

        $export->forceFill([
            'path' => $relative,
            'size' => (int) filesize($zipPath),
            'tables_count' => count($tables),
            'files_count' => count($files),
        ])->save();
    }

    /** @return list<string> tabelle del database */
    public static function tables(): array
    {
        // Solo le tabelle del database di ManuTrack (non altri schemi visibili all'utente del database)
        $names = array_map(fn (array $t) => (string) $t['name'], Schema::getTables(DB::connection()->getDatabaseName()));
        sort($names);

        return $names;
    }

    /**
     * @return list<array{table: string, file: string, rows: int, description: string, excluded_columns: list<string>}>
     */
    private function exportTables(ZipArchive $zip, string $work): array
    {
        $out = [];
        foreach (self::tables() as $table) {
            if (isset(self::EXCLUDED_TABLES[$table])) {
                continue;
            }
            $excluded = self::EXCLUDED_COLUMNS[$table] ?? [];
            $columns = array_values(array_diff(Schema::getColumnListing($table), $excluded));
            $file = $work.'/'.$table.'.json';
            $h = fopen($file, 'w');
            fwrite($h, "[\n");
            $rows = 0;
            $order = in_array('id', $columns, true) ? 'id' : $columns[0];
            foreach (DB::table($table)->select($columns)->orderBy($order)->lazy(1000) as $row) {
                $data = (array) $row;
                if ($table === 'settings' && preg_match('/secret|private|password|token/i', (string) $data['key'])) {
                    continue;
                }
                fwrite($h, ($rows ? ",\n" : '').json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $rows++;
            }
            fwrite($h, "\n]\n");
            fclose($h);
            $zip->addFile($file, 'dati/'.$table.'.json');
            $out[] = ['table' => $table, 'file' => 'dati/'.$table.'.json', 'rows' => $rows, 'description' => self::DESCRIPTIONS[$table] ?? $table, 'excluded_columns' => $excluded];
        }

        return $out;
    }

    /**
     * @return list<array{path: string, size: int, sha256: string, linked_to: string, missing?: bool}>
     */
    private function exportFiles(ZipArchive $zip): array
    {
        $out = [];
        $add = function (string $disk, string $source, string $target, string $linkedTo) use ($zip, &$out): void {
            $storage = Storage::disk($disk);
            if (! $storage->exists($source)) {
                $out[] = ['path' => $target, 'size' => 0, 'sha256' => '', 'linked_to' => $linkedTo, 'missing' => true];

                return;
            }
            $full = $storage->path($source);
            $zip->addFile($full, $target);
            $out[] = ['path' => $target, 'size' => (int) filesize($full), 'sha256' => (string) hash_file('sha256', $full), 'linked_to' => $linkedTo];
        };

        foreach (Attachment::query()->orderBy('id')->lazy(500) as $a) {
            /** @var Attachment $a */
            $name = Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $a->original_name) ?: 'file', 120, '');
            $add($a->disk, $a->path, 'file/allegati/'.$a->id.'-'.$name, 'attachments.id='.$a->id.' ('.class_basename($a->attachable_type).' '.$a->attachable_id.')');
        }
        foreach (Asset::query()->whereNotNull('model_path')->orderBy('id')->get() as $asset) {
            $add(AssetModel::disk(), (string) $asset->model_path, 'file/modelli/'.$asset->id.'-'.Str::slug($asset->code).'.glb', 'assets.id='.$asset->id.' (modello 3D di '.$asset->code.')');
        }
        foreach ([CompanyLogo::SETTING => 'chiaro', CompanyLogo::SETTING_DARK => 'scuro'] as $setting => $variant) {
            $logo = settings($setting);
            if (is_array($logo) && isset($logo['path'])) {
                $add(CompanyLogo::disk(), (string) $logo['path'], 'file/logo/logo-'.$variant.'.'.pathinfo((string) $logo['path'], PATHINFO_EXTENSION), 'settings.'.$setting);
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array<string, mixed>>  $files
     * @return array<string, mixed>
     */
    private function index(DataExport $export, array $tables, array $files): array
    {
        return [
            'programma' => 'ManuTrack',
            'versione' => manutrack_version(),
            'azienda' => (string) settings('company.name', config('app.name')),
            'creato_il' => now()->toIso8601String(),
            'fuso_orario' => config('app.timezone'),
            'richiesto_da' => $export->user ? ['id' => $export->user->id, 'nome' => $export->user->name] : null,
            'formato' => [
                'tabelle' => 'JSON UTF-8: un array di oggetti per tabella, una chiave per colonna. Date e ore nel fuso indicato, formato AAAA-MM-GG HH:MM:SS. Importi e quantità come testo con il punto decimale.',
                'file' => 'Ogni file ha la sua impronta SHA-256 per verificarne l\'integrità (sha256sum su Linux, Get-FileHash su Windows).',
            ],
            'tabelle' => $tables,
            'file' => $files,
            'esclusi' => [
                'tabelle' => self::EXCLUDED_TABLES,
                'colonne' => self::EXCLUDED_COLUMNS,
                'altro' => [
                    'Impostazioni con chiavi, password o codici segreti.',
                    'Miniature delle foto (ricreate dagli originali).',
                    'Il file .env del server (password del database, chiavi): è della configurazione, non dei dati.',
                    'Copie di sicurezza del database e file esportati in precedenza.',
                ],
            ],
        ];
    }

    /** @param  array<string, mixed>  $index */
    private function markdown(array $index): string
    {
        $md = "# Esportazione dei dati di {$index['azienda']}\n\n";
        $md .= "- Creata il: {$index['creato_il']} (fuso {$index['fuso_orario']})\n- ManuTrack {$index['versione']}\n";
        if ($index['richiesto_da']) {
            $md .= "- Richiesta da: {$index['richiesto_da']['nome']}\n";
        }
        $md .= "\n## Formato\n\n- {$index['formato']['tabelle']}\n- {$index['formato']['file']}\n- L'elenco completo, leggibile da un programma, è in `indice.json`.\n";
        $md .= "\n## Tabelle (cartella `dati/`)\n\n| File | Contenuto | Righe | Colonne escluse |\n|---|---|---:|---|\n";
        foreach ($index['tabelle'] as $t) {
            $md .= "| `{$t['file']}` | {$t['description']} | {$t['rows']} | ".($t['excluded_columns'] ? implode(', ', $t['excluded_columns']) : '—')." |\n";
        }
        $count = count($index['file']);
        $size = array_sum(array_column($index['file'], 'size'));
        $missing = count(array_filter($index['file'], fn ($f) => ! empty($f['missing'])));
        $md .= "\n## File (cartella `file/`)\n\n- {$count} file, ".number_format($size / 1048576, 1, ',', '.')." MB in tutto.\n";
        $md .= "- `file/allegati/<id>-<nome>`: foto e documenti; `<id>` è la riga in `dati/attachments.json`, che dice a quale macchina, segnalazione o ordine appartiene.\n";
        $md .= "- `file/modelli/<id>-<codice>.glb`: modelli 3D; `<id>` è la macchina in `dati/assets.json`.\n- `file/logo/`: logo dell'azienda.\n";
        if ($missing) {
            $md .= "- **{$missing} file risultano mancanti sul server** (segnati con `missing` in `indice.json`).\n";
        }
        $md .= "\n## Cosa non c'è e perché\n\n";
        foreach ($index['esclusi']['tabelle'] as $t => $why) {
            $md .= "- Tabella `{$t}`: {$why}.\n";
        }
        foreach ($index['esclusi']['colonne'] as $t => $cols) {
            $md .= "- `{$t}`: colonne ".implode(', ', $cols)." (password e codici segreti).\n";
        }
        foreach ($index['esclusi']['altro'] as $line) {
            $md .= "- {$line}\n";
        }

        return $md;
    }
}
