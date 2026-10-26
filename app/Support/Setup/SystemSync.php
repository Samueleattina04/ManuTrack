<?php

namespace App\Support\Setup;

use App\Support\Setup\Synchronizers\DefaultSettings;

/**
 * Esegue in ordine tutti i sincronizzatori registrati.
 */
class SystemSync
{
    /**
     * Le fasi successive aggiungono qui ruoli e permessi, categorie predefinite, ecc.
     *
     * @var list<class-string<Synchronizer>>
     */
    public const SYNCHRONIZERS = [
        DefaultSettings::class,
    ];

    /**
     * @return list<string> nomi dei sincronizzatori eseguiti
     */
    public function run(): array
    {
        $done = [];

        foreach (self::SYNCHRONIZERS as $class) {
            app($class)->sync();
            $done[] = class_basename($class);
        }

        return $done;
    }
}
