<?php

use App\Livewire\Users\Roles;
use App\Support\Access\Access;
use App\Support\Setup\SystemSync;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = installManuTrack();
});

it('crea i sei ruoli con i permessi predefiniti', function () {
    expect(Role::query()->pluck('name')->sort()->values()->all())->toBe(collect(Access::ROLES)->sort()->values()->all());

    foreach (Access::DEFAULTS as $role => $permissions) {
        expect(Role::findByName($role)->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($permissions)->sort()->values()->all());
    }
    expect($this->admin->isAdmin())->toBeTrue();
});

it('permette o vieta le pagine di amministrazione in base al ruolo', function (string $role, array $allowed) {
    $user = $role === Access::ADMIN ? $this->admin : userWithRole($role);
    $pages = ['/utenti' => 'users', '/ruoli' => 'roles', '/impostazioni/azienda' => 'settings', '/registro' => 'audit', '/sistema' => 'system'];

    foreach ($pages as $url => $key) {
        $response = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get($url);
        in_array($key, $allowed, true) ? $response->assertOk() : $response->assertForbidden();
    }
    // La panoramica e il profilo sono per tutti
    $this->actingAs($user)->get('/')->assertOk();
    $this->actingAs($user)->get('/profilo')->assertOk();
})->with([
    'amministratore' => [Access::ADMIN, ['users', 'roles', 'settings', 'audit', 'system']],
    'responsabile' => [Access::MANAGER, ['users']],
    'tecnico' => [Access::TECHNICIAN, []],
    'magazziniere' => [Access::STOREKEEPER, []],
    'richiedente' => [Access::REQUESTER, []],
    'osservatore' => [Access::VIEWER, []],
]);

it('mostra nel menu solo le voci consentite', function () {
    $this->actingAs(userWithRole(Access::TECHNICIAN))->get('/')
        ->assertDontSee(route('users.index'))
        ->assertDontSee(route('settings.company'));

    $this->actingAs($this->admin)->get('/')
        ->assertSee(route('users.index'))
        ->assertSee(route('settings.company'));
});

it('la sincronizzazione è ripetibile e non disfa le modifiche dell\'amministratore', function () {
    $role = Role::findByName(Access::TECHNICIAN);
    $role->revokePermissionTo('inventory.move');

    app(SystemSync::class)->run();
    app(SystemSync::class)->run();

    expect($role->fresh()->hasPermissionTo('inventory.move'))->toBeFalse()
        ->and(Permission::query()->count())->toBe(count(Access::allPermissions()));
});

it('aggiunge ai ruoli i permessi nuovi di una versione successiva', function () {
    // Simula una versione precedente che non conosceva "reports.export"
    settings()->set('access.known_permissions', array_values(array_diff(Access::allPermissions(), ['reports.export'])));
    Role::findByName(Access::VIEWER)->revokePermissionTo('reports.export');

    app(SystemSync::class)->run();

    expect(Role::findByName(Access::VIEWER)->hasPermissionTo('reports.export'))->toBeTrue();
});

it('l\'amministratore modifica la matrice dei permessi', function () {
    Livewire::actingAs($this->admin)->test(Roles::class)
        ->call('toggle', Access::TECHNICIAN, 'assets.manage');
    expect(Role::findByName(Access::TECHNICIAN)->hasPermissionTo('assets.manage'))->toBeTrue();

    Livewire::actingAs($this->admin)->test(Roles::class)
        ->call('restoreDefaults', Access::TECHNICIAN);
    expect(Role::findByName(Access::TECHNICIAN)->hasPermissionTo('assets.manage'))->toBeFalse();
});

it('il ruolo amministratore non si modifica', function () {
    Livewire::actingAs($this->admin)->test(Roles::class)
        ->call('toggle', Access::ADMIN, 'users.view')
        ->assertForbidden();
});

it('chi non è amministratore non modifica i permessi', function () {
    Livewire::actingAs(userWithRole(Access::MANAGER))->test(Roles::class)->assertForbidden();
});

it('la pagina Persone porta a Ruoli e permessi solo a chi gestisce le persone', function () {
    $this->actingAs($this->admin)->get('/utenti')->assertOk()->assertSee(route('roles.index'), false)->assertSee(__('access.title'));
    $this->actingAs(userWithRole(Access::MANAGER))->get('/utenti')->assertOk()->assertDontSee(route('roles.index'), false);
});
