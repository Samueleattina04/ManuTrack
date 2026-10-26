<?php

namespace App\Actions\Setup;

use App\Support\Setup\EnvWriter;
use Minishlink\WebPush\VAPID;
use RuntimeException;

/**
 * Genera la coppia di chiavi VAPID per le notifiche push e la scrive in .env.
 * Se .env non è scrivibile restituisce le chiavi da copiare a mano.
 */
class GenerateVapidKeys
{
    /**
     * @return array{public: string, private: string, written: bool}
     */
    public function handle(bool $force = false): array
    {
        if (! $force && filled(config('manutrack.webpush.public_key')) && filled(config('manutrack.webpush.private_key'))) {
            throw new RuntimeException(__('install.vapid_exists'));
        }

        $keys = VAPID::createVapidKeys();

        $writer = new EnvWriter;
        $written = false;

        if ($writer->isWritable()) {
            $writer->set([
                'VAPID_PUBLIC_KEY' => $keys['publicKey'],
                'VAPID_PRIVATE_KEY' => $keys['privateKey'],
            ]);
            $written = true;
        }

        config([
            'manutrack.webpush.public_key' => $keys['publicKey'],
            'manutrack.webpush.private_key' => $keys['privateKey'],
        ]);

        return ['public' => $keys['publicKey'], 'private' => $keys['privateKey'], 'written' => $written];
    }
}
