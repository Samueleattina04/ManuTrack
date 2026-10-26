<?php

namespace App\Support;

use App\Models\SystemVersion;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Versione del codice (file VERSION) e versione registrata nel database.
 */
class AppVersion
{
    private static ?string $code = null;

    public static function code(): string
    {
        if (self::$code === null) {
            $file = base_path('VERSION');
            self::$code = is_file($file) ? trim((string) file_get_contents($file)) : '0.0.0';
        }

        return self::$code;
    }

    /**
     * Ultima versione applicata con successo, oppure null se mai installata.
     */
    public static function installed(): ?string
    {
        try {
            if (! Schema::hasTable('system_versions')) {
                return null;
            }

            return SystemVersion::query()
                ->where('status', SystemVersion::SUCCESS)
                ->latest('id')
                ->value('version');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Vero se il codice caricato è più recente di quello registrato nel database.
     */
    public static function updatePending(): bool
    {
        $installed = self::installed();

        return $installed !== null && version_compare(self::code(), $installed, '>');
    }

    /**
     * Solo per i test: forza la versione del codice.
     */
    public static function fake(?string $version): void
    {
        self::$code = $version;
    }
}
