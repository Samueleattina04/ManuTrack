<?php

namespace App\Support\Demo;

use App\Support\Settings;
use App\Support\Update\ReleaseManifest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Edizione di ManuTrack: "cliente" (il pacchetto che si consegna), "demo"
 * (pacchetto separato con i dati dimostrativi e l'azzeramento notturno) o
 * "sviluppo" (copia del repository, senza release.json).
 *
 * L'edizione è scritta in due posti indipendenti:
 * - nel pacchetto, dentro release.json (la decide lo script di rilascio);
 * - nel database, all'installazione (impostazione install.edition).
 *
 * Demo e clienti non si possono mischiare:
 * - l'installazione normale si rifiuta con il pacchetto demo;
 * - "manutrack:demo" e l'azzeramento notturno partono solo se pacchetto,
 *   database e .env (MANUTRACK_DEMO=true) dicono tutti e tre "demo";
 * - l'aggiornamento si rifiuta se l'edizione del pacchetto non è quella del database.
 */
class Edition
{
    public const CLIENT = 'cliente';

    public const DEMO = 'demo';

    public const DEV = 'sviluppo';

    public const SETTING = 'install.edition';

    /** Edizione del codice (pacchetto) */
    public static function package(?string $basePath = null): string
    {
        $manifest = ReleaseManifest::load($basePath ?? (config('manutrack.update.base_path') ?: base_path()));
        if ($manifest === null) {
            return self::DEV;
        }

        return $manifest->edition() === self::DEMO ? self::DEMO : self::CLIENT;
    }

    /** Valore restituito da databaseOrError() quando il database non si riesce a leggere */
    public const UNREADABLE = 'illeggibile';

    /** Edizione registrata nel database; null se non è ancora installato (o illeggibile) */
    public static function database(): ?string
    {
        $value = self::databaseOrError();

        return $value === self::UNREADABLE ? null : $value;
    }

    /**
     * Come database(), ma distingue "database vuoto" (null) da "database che non si
     * riesce a leggere" (UNREADABLE): nel dubbio non si azzera nulla.
     */
    public static function databaseOrError(): ?string
    {
        try {
            $installed = Schema::hasTable('users') && (DB::table('users')->exists() || (Schema::hasTable('system_versions') && DB::table('system_versions')->exists()));
            if (! $installed) {
                return null;
            }
            $value = app(Settings::class)->get(self::SETTING);
        } catch (Throwable) {
            return self::UNREADABLE;
        }

        // Le installazioni precedenti alla 0.8.0 non la registravano: sono clienti
        return is_string($value) && in_array($value, [self::CLIENT, self::DEMO, self::DEV], true) ? $value : self::CLIENT;
    }

    public static function envSaysDemo(): bool
    {
        return (bool) config('manutrack.demo');
    }

    /**
     * Motivo per cui l'installazione demo (o il suo azzeramento) non è permessa;
     * null se pacchetto, .env e database sono d'accordo.
     */
    public static function demoBlocker(): ?string
    {
        if (self::package() !== self::DEMO) {
            return __('demo.blocked.package');
        }
        if (! self::envSaysDemo()) {
            return __('demo.blocked.env');
        }
        $db = self::databaseOrError();
        if ($db === self::UNREADABLE) {
            return __('demo.blocked.unreadable');
        }
        if ($db !== null && $db !== self::DEMO) {
            return __('demo.blocked.database', ['edition' => $db]);
        }

        return null;
    }

    /** È un'installazione demo funzionante (per il banner e l'azzeramento) */
    public static function isDemo(): bool
    {
        return self::package() === self::DEMO && self::envSaysDemo() && self::database() === self::DEMO;
    }

    /**
     * Discordanze da segnalare nella pagina Sistema (lista vuota = tutto coerente).
     *
     * @return list<string>
     */
    public static function mismatches(): array
    {
        $package = self::package();
        $db = self::database();
        $out = [];
        if ($package === self::DEV || $db === null) {
            return $out;
        }
        if ($package !== $db) {
            $out[] = __('demo.mismatch.package_db', ['package' => $package, 'database' => $db]);
        }
        if (self::envSaysDemo() !== ($db === self::DEMO)) {
            $out[] = __($db === self::DEMO ? 'demo.mismatch.env_off' : 'demo.mismatch.env_on');
        }

        return $out;
    }
}
