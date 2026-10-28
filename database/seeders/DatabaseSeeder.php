<?php

namespace Database\Seeders;

use App\Models\SystemVersion;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\AppVersion;
use App\Support\Demo\Edition;
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
        // In produzione servono il pacchetto demo e MANUTRACK_DEMO=true (in pratica: manutrack:demo)
        return ! app()->isProduction() || (Edition::package() === Edition::DEMO && Edition::envSaysDemo());
    }

    public function run(): void
    {
        if (! self::demoAllowed()) {
            $this->command?->error('In produzione usa "php artisan manutrack:install".');

            return;
        }

        app(Settings::class)->setMany([
            'company.name' => config('manutrack.company.name') ?: 'Officine Demo',
            Edition::SETTING => Edition::package() === Edition::DEMO ? Edition::DEMO : Edition::DEV,
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
        $this->call(DemoHistorySeeder::class);
        $this->call(DemoPreventiveSeeder::class);
        $this->call(DemoPlantSeeder::class);
        $this->call(DemoWorkTimeSeeder::class);

        InstallState::writeLock(['version' => AppVersion::code(), 'seeded' => true]);

        $this->command?->info('Amministratore di prova: '.self::DEMO_EMAIL.' / '.self::DEMO_PASSWORD);
    }
}
