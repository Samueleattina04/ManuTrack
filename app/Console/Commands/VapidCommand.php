<?php

namespace App\Console\Commands;

use App\Actions\Setup\GenerateVapidKeys;
use Illuminate\Console\Command;
use Throwable;

class VapidCommand extends Command
{
    protected $signature = 'manutrack:vapid
        {--force : Sostituisce le chiavi esistenti (i dispositivi già iscritti dovranno riattivare le notifiche)}
        {--show : Mostra la chiave pubblica attuale}';

    protected $description = 'Genera le chiavi VAPID per le notifiche push e le scrive in .env';

    public function handle(GenerateVapidKeys $generate): int
    {
        if ($this->option('show')) {
            $key = config('manutrack.webpush.public_key');
            $key ? $this->line($key) : $this->components->warn(__('install.vapid_missing'));

            return $key ? self::SUCCESS : self::FAILURE;
        }

        try {
            $keys = $generate->handle((bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        if ($keys['written']) {
            $this->components->info(__('install.vapid_written'));
        } else {
            $this->components->warn(__('install.vapid_manual'));
            $this->line('VAPID_PUBLIC_KEY='.$keys['public']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['private']);
        }

        if ($this->option('force')) {
            $this->components->warn(__('install.vapid_rotated'));
        }

        return self::SUCCESS;
    }
}
