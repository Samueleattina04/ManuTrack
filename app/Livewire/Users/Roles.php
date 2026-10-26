<?php

namespace App\Livewire\Users;

use App\Support\Access\Access;
use App\Support\Audit\Audit;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Matrice ruoli × permessi. L'amministratore ha sempre tutto e non si modifica.
 */
class Roles extends Component
{
    public function mount(): void
    {
        $this->authorize('users.manage');
    }

    public function toggle(string $roleName, string $permission): void
    {
        $this->authorize('users.manage');
        abort_if($roleName === Access::ADMIN, 403);
        abort_unless(in_array($roleName, Access::ROLES, true) && in_array($permission, Access::allPermissions(), true), 404);

        $role = $this->role($roleName);
        $had = $role->hasPermissionTo($permission);
        $had ? $role->revokePermissionTo($permission) : $role->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Audit::log('roles.permission_'.($had ? 'revoked' : 'granted'), $role, $roleName.' · '.$permission);
    }

    public function restoreDefaults(string $roleName): void
    {
        $this->authorize('users.manage');
        abort_if($roleName === Access::ADMIN, 403);
        abort_unless(in_array($roleName, Access::ROLES, true), 404);

        $role = $this->role($roleName);
        $role->syncPermissions(Access::defaultsFor($roleName));
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Audit::log('roles.defaults_restored', $role, $roleName);
        $this->dispatch('toast', message: __('access.defaults_restored', ['role' => __('access.roles.'.$roleName)]));
    }

    private function role(string $name): Role
    {
        return Role::query()->where('name', $name)->where('guard_name', 'web')->firstOrFail();
    }

    public function render(): View
    {
        $roles = Role::query()->with('permissions')->whereIn('name', Access::ROLES)->get()->keyBy('name');
        $granted = $roles->map(fn (Role $r) => $r->permissions->pluck('name')->all())->all();

        return view('livewire.users.roles', [
            'roles' => Access::ROLES,
            'groups' => Access::PERMISSIONS,
            'granted' => $granted,
        ])->title(__('access.title'))->layoutData(['heading' => __('access.title')]);
    }
}
