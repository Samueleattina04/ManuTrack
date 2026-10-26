<?php

namespace App\Actions\Setup;

use App\Models\SystemVersion;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\AppVersion;
use App\Support\FileLock;
use App\Support\Settings;
use App\Support\Setup\EnvWriter;
use App\Support\Setup\InstallState;
use App\Support\Setup\Requirements;
use App\Support\Setup\SystemSync;
use Closure;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Installa ManuTrack: usata sia da "php artisan manutrack:install" sia
 * dall'installazione guidata nel browser.
 */
class InstallApplication
{
    public function __construct(
        private Requirements $requirements,
        private Settings $settings,
        private GenerateVapidKeys $vapid,
    ) {}

    /**
     * @param  array{company: string, name: string, email: string, password: string, palette?: string, vapid?: bool}  $data
     * @param  (Closure(string): void)|null  $output
     * @return array{version: string, user_id: int, vapid: bool|null}
     */
    public function handle(array $data, ?Closure $output = null): array
    {
        $say = $output ?? fn (string $line) => null;

        $lock = new FileLock('manutrack-install');
        if (! $lock->acquire()) {
            throw new RuntimeException(__('install.busy'));
        }

        try {
            if (InstallState::isInstalled()) {
                throw new AlreadyInstalledException(__('install.already_installed'));
            }

            if (! $this->requirements->passes()) {
                throw new RuntimeException(__('install.requirements_failed'));
            }

            $this->ensureAppKey($say);

            $say(__('install.step.migrate'));
            Artisan::call('migrate', ['--force' => true]);

            // Seconda verifica dopo le migrazioni: se esistono già utenti non si procede
            if (InstallState::databaseSaysInstalled()) {
                throw new AlreadyInstalledException(__('install.already_installed'));
            }

            $say(__('install.step.settings'));
            $palette = $data['palette'] ?? 'rame';
            if (! array_key_exists($palette, (array) config('manutrack.palettes'))) {
                $palette = 'rame';
            }
            $this->settings->setMany([
                'company.name' => $data['company'],
                'company.palette' => $palette,
            ]);
            app(SystemSync::class)->run();

            $say(__('install.step.admin'));
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => mb_strtolower($data['email']),
                'password' => Hash::make($data['password']),
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole(Access::ADMIN);
            $this->settings->set('install.admin_user_id', $user->id);

            $vapid = null;
            if (($data['vapid'] ?? false) && blank(config('manutrack.webpush.public_key'))) {
                $say(__('install.step.vapid'));
                $vapid = $this->vapid->handle(force: false)['written'];
            }

            SystemVersion::query()->create([
                'version' => AppVersion::code(),
                'status' => SystemVersion::SUCCESS,
                'trigger' => 'install',
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            InstallState::writeLock(['version' => AppVersion::code()]);
            $say(__('install.step.done'));

            return ['version' => AppVersion::code(), 'user_id' => $user->id, 'vapid' => $vapid];
        } finally {
            $lock->release();
        }
    }

    private function ensureAppKey(Closure $say): void
    {
        if (! empty(config('app.key'))) {
            return;
        }

        $say(__('install.step.key'));
        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
        (new EnvWriter)->set(['APP_KEY' => $key]);
        config(['app.key' => $key]);
    }
}
