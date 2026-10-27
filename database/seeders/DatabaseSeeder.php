<?php

namespace Database\Seeders;

use App\Models\SystemVersion;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\AppVersion;
use App\Support\Settings;
use App\Support\Setup\InstallState;
use App\Support\Setup\SystemSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Dati per lo sviluppo in locale ("php artisan migrate --seed"):
 * un'installazione pronta con un amministratore fittizio.
 * In produzione si usa invece "php artisan manutrack:install".
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_EMAIL = 'admin@example.com';

    public const DEMO_PASSWORD = 'manutrack-demo';

    /**
     * I dati e gli account dimostrativi si creano solo in sviluppo o in
     * un'installazione dimostrativa (MANUTRACK_DEMO=true), mai in produzione.
     * manutrack:install e l'installazione dal browser non usano i seeder.
     */
    public static function demoAllowed(): bool
    {
        return ! app()->isProduction() || (bool) config('manutrack.demo');
    }

    public function run(): void
    {
        if (! self::demoAllowed()) {
            $this->command?->error('In produzione usa "php artisan manutrack:install".');

            return;
        }

        app(Settings::class)->setMany([
            'company.name' => config('manutrack.company.name') ?: 'Officine Demo',
        ]);
        app(SystemSync::class)->run();

        $admin = User::query()->firstOrCreate(
            ['email' => self::DEMO_EMAIL],
            ['name' => 'Amministratore Demo', 'password' => Hash::make(self::DEMO_PASSWORD)],
        );
        $admin->forceFill(['email_verified_at' => now()])->save();
        $admin->syncRoles([Access::ADMIN]);
        settings()->set('install.admin_user_id', $admin->id);

        if (! SystemVersion::query()->where('status', SystemVersion::SUCCESS)->exists()) {
            SystemVersion::query()->create([
                'version' => AppVersion::code(),
                'status' => SystemVersion::SUCCESS,
                'trigger' => 'install',
                'started_at' => now(),
                'finished_at' => now(),
            ]);
        }

        $this->call(DemoAssetsSeeder::class);
        $this->call(DemoMaintenanceSeeder::class);
        $this->call(DemoInventorySeeder::class);
        $this->call(DemoPreventiveSeeder::class);

        InstallState::writeLock(['version' => AppVersion::code(), 'seeded' => true]);

        $this->command?->info('Amministratore di prova: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD);
    }
}
