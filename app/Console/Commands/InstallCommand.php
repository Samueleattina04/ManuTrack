<?php

namespace App\Console\Commands;

use App\Actions\Setup\AlreadyInstalledException;
use App\Actions\Setup\InstallApplication;
use App\Support\Setup\Requirements;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class InstallCommand extends Command
{
    protected $signature = 'manutrack:install
        {--company= : Nome dell\'azienda}
        {--name= : Nome dell\'amministratore}
        {--email= : Email dell\'amministratore}
        {--password= : Password dell\'amministratore (sconsigliato: resta nella cronologia della shell)}
        {--palette=rame : Palette dei colori (rame, ciano, indaco)}
        {--vapid : Genera anche le chiavi per le notifiche push}';

    protected $description = 'Installa ManuTrack: database, impostazioni dell\'azienda, amministratore e chiavi push';

    public function handle(Requirements $requirements, InstallApplication $install): int
    {
        $this->components->info('ManuTrack '.manutrack_version().' · installazione');

        $this->showRequirements($requirements);
        if (! $requirements->passes()) {
            $this->components->error(__('install.requirements_failed'));

            return self::FAILURE;
        }

        $interactive = $this->input->isInteractive();
        $palettes = collect((array) config('manutrack.palettes'))->map(fn ($p) => $p['label'])->all();

        $data = [
            'company' => $this->option('company') ?: ($interactive ? text(__('install.field.company'), required: true) : null),
            'name' => $this->option('name') ?: ($interactive ? text(__('install.field.name'), required: true) : null),
            'email' => $this->option('email') ?: ($interactive ? text(__('install.field.email'), required: true) : null),
            'password' => $this->option('password') ?: ($interactive ? password(__('install.field.password'), required: true, hint: __('install.password_hint')) : null),
            'palette' => $interactive && ! $this->option('company')
                ? select(__('install.field.palette'), $palettes, default: 'rame')
                : (string) $this->option('palette'),
            'vapid' => $this->option('vapid') || ($interactive && ! $this->option('company') && confirm(__('install.field.vapid'), default: true)),
        ];

        $validator = Validator::make($data, [
            'company' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', Password::min(10)->letters()->numbers()],
            'palette' => ['required', 'in:'.implode(',', array_keys($palettes))],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::INVALID;
        }

        try {
            $result = $install->handle($data, fn (string $line) => $this->components->task($line));
        } catch (AlreadyInstalledException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        } catch (Throwable $e) {
            $this->components->error(__('install.failed', ['message' => $e->getMessage()]));

            return self::FAILURE;
        }

        $this->newLine();
        $this->components->info(__('install.done_cli', ['version' => $result['version'], 'email' => $data['email']]));
        if ($result['vapid'] === false) {
            $this->components->warn(__('install.vapid_manual'));
        }
        $this->components->bulletList([
            __('install.next.cron'),
            __('install.next.https'),
        ]);

        return self::SUCCESS;
    }

    private function showRequirements(Requirements $requirements): void
    {
        foreach ($requirements->checks() as $check) {
            $mark = match ($check['status']) {
                Requirements::OK => '<fg=green>✔</>',
                Requirements::WARNING => '<fg=yellow>!</>',
                default => '<fg=red>✘</>',
            };
            $this->components->twoColumnDetail($mark.' '.$check['label'], $check['detail']);
        }
        $this->newLine();
    }
}
