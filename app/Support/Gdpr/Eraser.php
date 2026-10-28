<?php

namespace App\Support\Gdpr;

use App\Models\User;
use App\Support\Access\Access;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Cancellazione totale dell'installazione a fine contratto (docs/GDPR.md).
 *
 * Cancella tutte le tabelle del database e tutti i file dei dati (allegati, modelli 3D, logo,
 * esportazioni, copie di sicurezza fatte da ManuTrack, registri del server, sessioni) e lascia
 * un rapporto di cosa è stato eliminato, senza dati personali. Da quel momento l'app mostra solo
 * il rapporto. Restano fuori dal controllo dell'app: copie fatte dall'hosting, file già
 * scaricati, email inviate, il file .env e il codice (da togliere dal pannello dell'hosting).
 */
final class Eraser
{
    public static function reportPath(): string
    {
        return storage_path('app/manutrack-cancellata.json');
    }

    public static function erased(): bool
    {
        return is_file(self::reportPath());
    }

    /** Frase da scrivere per confermare */
    public static function phrase(): string
    {
        return 'CANCELLA '.mb_strtoupper((string) settings('company.name', config('app.name')));
    }

    /**
     * Controlli della conferma forte: amministratore, frase esatta, password.
     */
    public function authorize(?User $actor, string $phrase, ?string $password, bool $fromConsole = false): void
    {
        if (! $fromConsole) {
            if (! $actor || ! $actor->hasRole(Access::ADMIN)) {
                throw ValidationException::withMessages(['phrase' => __('gdpr.erase.admin_only')]);
            }
            if (! Hash::check((string) $password, $actor->password)) {
                throw ValidationException::withMessages(['password' => __('gdpr.erase.wrong_password')]);
            }
        }
        if (trim($phrase) !== self::phrase()) {
            throw ValidationException::withMessages(['phrase' => __('gdpr.erase.wrong_phrase', ['phrase' => self::phrase()])]);
        }
    }

    /**
     * Cosa verrebbe cancellato adesso.
     *
     * @return array{tables: array<string, int>, folders: array<string, array{files: int, bytes: int}>}
     */
    public function inventory(): array
    {
        $tables = [];
        foreach (DataExporter::tables() as $t) {
            $tables[$t] = (int) DB::table($t)->count();
        }
        $folders = [];
        foreach ($this->folders() as $label => $dir) {
            $files = is_dir($dir) ? File::allFiles($dir) : [];
            $folders[$label] = ['files' => count($files), 'bytes' => array_sum(array_map(fn ($f) => $f->getSize(), $files))];
        }

        return ['tables' => $tables, 'folders' => $folders];
    }

    /**
     * @return array<string, mixed> il rapporto (anche salvato su disco)
     */
    public function run(?User $actor, string $method): array
    {
        @set_time_limit(0);
        $before = $this->inventory();
        $company = (string) settings('company.name', config('app.name'));

        foreach ($this->folders() as $dir) {
            if (is_dir($dir)) {
                File::cleanDirectory($dir);
            }
        }
        Schema::dropAllTables();

        $report = [
            'programma' => 'ManuTrack',
            'versione' => manutrack_version(),
            'cancellata_il' => now()->toIso8601String(),
            'modo' => $method,
            // Solo il ruolo e l'id: nessun dato personale nel rapporto
            'richiesta_da' => $actor ? 'amministratore (id '.$actor->id.')' : 'comando dal terminale',
            'azienda_iniziali' => mb_substr($company, 0, 1).'…',
            'tabelle_cancellate' => $before['tables'],
            'righe_cancellate' => array_sum($before['tables']),
            'cartelle_svuotate' => $before['folders'],
            'non_cancellato' => [
                'Copie di sicurezza fatte dall\'hosting (pannello DirectAdmin o del fornitore): da eliminare dal pannello.',
                'Esportazioni e file scaricati in precedenza da persone dell\'azienda: sotto la loro custodia.',
                'Email e notifiche già inviate.',
                'Il file .env (password del database, chiavi) e il codice dell\'app: da eliminare con la cartella dell\'installazione e il database dal pannello dell\'hosting.',
            ],
        ];
        File::ensureDirectoryExists(dirname(self::reportPath()));
        file_put_contents(self::reportPath(), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        return $report;
    }

    /** @return array<string, string> cartelle dei dati: etichetta => percorso */
    private function folders(): array
    {
        return [
            'file privati (allegati, modelli 3D, logo, esportazioni)' => storage_path('app/private'),
            'file pubblici' => storage_path('app/public'),
            'copie di sicurezza di ManuTrack' => (string) config('manutrack.backups.path', storage_path('app/backups')),
            'registri del server' => storage_path('logs'),
            'sessioni e memoria temporanea' => storage_path('framework/sessions'),
            'cache' => storage_path('framework/cache/data'),
        ];
    }
}
