<?php

namespace App\Support\Setup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Stabilisce se questa copia di ManuTrack è già installata.
 *
 * Il file di blocco basta per la verifica veloce a ogni richiesta; il database fa
 * da seconda prova, così l'installazione non può ripartire nemmeno se qualcuno
 * cancella il file.
 */
class InstallState
{
    public static function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    public static function hasLock(): bool
    {
        return is_file(self::lockPath());
    }

    public static function isInstalled(): bool
    {
        if (self::hasLock()) {
            return true;
        }

        if (self::databaseSaysInstalled()) {
            // Il file è stato rimosso ma i dati ci sono: lo si ricrea
            self::writeLock(['restored' => true]);

            return true;
        }

        return false;
    }

    public static function databaseSaysInstalled(): bool
    {
        try {
            if (Schema::hasTable('system_versions') && DB::table('system_versions')->exists()) {
                return true;
            }

            return Schema::hasTable('users') && DB::table('users')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $info
     */
    public static function writeLock(array $info = []): void
    {
        $dir = dirname(self::lockPath());
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(self::lockPath(), json_encode([
            'installed_at' => now()->toIso8601String(),
            ...$info,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
