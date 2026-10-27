<?php

namespace App\Console\Commands;

use App\Support\Demo\DemoInstaller;
use App\Support\Demo\Edition;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Console\Command;
use Throwable;

/**
 * Installa (o azzera) l'installazione dimostrativa. Funziona solo con il
 * pacchetto demo, MANUTRACK_DEMO=true e un database vuoto o già demo.
 */
class DemoCommand extends Command
{
    protected $signature = 'manutrack:demo {--azzera : Azzera un\'installazione demo già esistente}';

    protected $description = 'Installa o azzera l\'installazione dimostrativa (solo pacchetto demo)';

    public function handle(DemoInstaller $installer): int
    {
        if ($reason = Edition::demoBlocker()) {
            $this->components->error($reason);

            return self::FAILURE;
        }
        if (Edition::database() === Edition::DEMO && ! $this->option('azzera')) {
            $this->components->error(__('demo.command.exists'));

            return self::FAILURE;
        }

        try {
            $installer->run(fn (string $line) => $this->components->task($line));
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $this->components->info(__('demo.command.done', ['email' => DatabaseSeeder::DEMO_EMAIL, 'password' => DatabaseSeeder::DEMO_PASSWORD]));

        return self::SUCCESS;
    }
}
