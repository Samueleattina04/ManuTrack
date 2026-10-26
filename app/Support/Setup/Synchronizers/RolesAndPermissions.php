<?php

namespace App\Support\Setup\Synchronizers;

use App\Models\User;
use App\Support\Access\Access;
use App\Support\Settings;
use App\Support\Setup\Synchronizer;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea ruoli e permessi mancanti senza disfare le modifiche dell'amministratore:
 * a un ruolo esistente si aggiungono solo i permessi introdotti dalla nuova
 * versione, secondo i valori predefiniti.
 */
class RolesAndPermissions implements Synchronizer
{
    private const KNOWN = 'access.known_permissions';

    public function __construct(private Settings $settings) {}

    public function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $known = (array) $this->settings->get(self::KNOWN, []);
        $all = Access::allPermissions();
        $new = array_values(array_diff($all, $known));

        foreach ($all as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (Access::ROLES as $roleName) {
            $role = Role::query()->where('name', $roleName)->where('guard_name', 'web')->first();

            if ($role === null) {
                $role = Role::create(['name' => $roleName, 'guard_name' => 'web']);
                $role->syncPermissions(Access::defaultsFor($roleName));

                continue;
            }

            $toAdd = array_values(array_intersect($new, Access::defaultsFor($roleName)));
            if ($toAdd !== []) {
                $role->givePermissionTo($toAdd);
            }
        }

        $this->settings->set(self::KNOWN, $all);

        // Deve esistere almeno un amministratore attivo: si usa quello dell'installazione
        $hasAdmin = User::role(Access::ADMIN)->where('is_active', true)->exists();
        $installer = $this->settings->get('install.admin_user_id');
        if (! $hasAdmin && $installer && ($user = User::query()->find($installer))) {
            $user->forceFill(['is_active' => true, 'deactivated_at' => null])->save();
            $user->assignRole(Access::ADMIN);
        }

        $registrar->forgetCachedPermissions();
    }
}
