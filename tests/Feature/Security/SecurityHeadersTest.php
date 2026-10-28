<?php

use App\Models\Asset;
use App\Support\Access\Access;
use App\Support\Gdpr\Eraser;
use App\Support\Setup\InstallState;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Testing\TestResponse;

/*
 * Controllo di sicurezza 0.2: CSP e intestazioni di sicurezza su tutte le pagine
 * (le prove nel browser, senza violazioni in console, sono in scripts/e2e/csp-check.cjs).
 */

/** Verifica le intestazioni di una pagina HTML e che ogni script e stile in linea abbia il nonce */
function assertSecurePage(TestResponse $response, string $label): void
{
    $t = test();
    $csp = (string) $response->headers->get('Content-Security-Policy');
    foreach (["frame-ancestors 'none'", "object-src 'none'", "connect-src 'self'", "base-uri 'none'"] as $directive) {
        $t->assertStringContainsString($directive, $csp, $label);
    }
    $t->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp, $label);
    $t->assertSame(1, preg_match("/script-src 'self' 'nonce-([A-Za-z0-9+\\/=]+)'/", $csp, $m), $label);
    $nonce = $m[1];
    $t->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'), $label);
    $t->assertSame('DENY', $response->headers->get('X-Frame-Options'), $label);
    $t->assertSame('same-origin', $response->headers->get('Referrer-Policy'), $label);
    $t->assertSame('same-origin', $response->headers->get('Cross-Origin-Opener-Policy'), $label);
    $t->assertStringContainsString('microphone=(), geolocation=()', (string) $response->headers->get('Permissions-Policy'), $label);

    $html = (string) $response->getContent();
    preg_match_all('/<(script|style)\b([^>]*)>/i', $html, $tags, PREG_SET_ORDER);
    foreach ($tags as [$tag, $name, $attributes]) {
        $external = str_contains($attributes, 'src=') || str_contains($attributes, 'type="application/json"') || str_contains($attributes, 'type="application/ld+json"');
        if (! $external) {
            $t->assertStringContainsString('nonce="'.$nonce.'"', $attributes, $label.': '.$tag);
        }
    }
    // Nessun gestore di eventi in linea (onclick=…): la CSP li blocca
    $t->assertSame(0, preg_match('/<[a-z][^>]*\son[a-z]+\s*=\s*["\']/i', $html), $label);
}

it('ogni pagina dell\'amministratore ha CSP con nonce e le altre intestazioni di sicurezza', function () {
    $admin = installManuTrack();
    $asset = Asset::factory()->create();
    $order = makeOrder($admin);
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);

    $params = ['asset' => $asset->id, 'workOrder' => $order->id];
    $checked = 0;
    foreach (Router::getRoutes() as $route) {
        /** @var Route $route */
        if (! in_array('GET', $route->methods(), true) || ! in_array('web', $route->gatherMiddleware(), true)) {
            continue;
        }
        $names = $route->parameterNames();
        if (array_diff($names, array_keys($params)) !== [] || str_contains($route->uri(), '.') || $route->getName() === null || str_starts_with($route->uri(), 'install')) {
            continue;
        }
        $response = $this->get(route($route->getName(), array_intersect_key($params, array_flip($names))));
        if ($response->getStatusCode() !== 200 || ! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            continue;
        }
        assertSecurePage($response, $route->uri());
        $checked++;
    }
    expect($checked)->toBeGreaterThan(40);
});

it('anche le pagine per gli ospiti, quelle d\'errore, l\'installazione e il rapporto di cancellazione', function () {
    // Prima dell'installazione
    $this->get('/install')->assertOk()->tap(fn ($r) => assertSecurePage($r, 'install'));
    installManuTrack();
    foreach (['/login', '/forgot-password', '/offline', '/pagina-che-non-esiste'] as $url) {
        assertSecurePage($this->get($url), $url);
    }
    $this->actingAs(userWithRole(Access::REQUESTER))->get('/utenti')->assertForbidden()->tap(fn ($r) => assertSecurePage($r, '403'));

    // Installazione cancellata: resta solo il rapporto, con le stesse intestazioni
    File::put(Eraser::reportPath(), json_encode(['programma' => 'ManuTrack']));
    try {
        $this->get('/')->assertStatus(410)->tap(fn ($r) => assertSecurePage($r, 'cancellata'));
    } finally {
        @unlink(Eraser::reportPath());
    }
    expect(InstallState::isInstalled())->toBeTrue();
});

it('il nonce cambia a ogni richiesta', function () {
    installManuTrack();
    $a = $this->get('/login')->headers->get('Content-Security-Policy');
    $b = $this->get('/login')->headers->get('Content-Security-Policy');
    expect($a)->not->toBe($b);
});

it('fotocamera permessa solo nella pagina Segnala (lettura del QR)', function () {
    $admin = installManuTrack();
    $this->actingAs($admin);
    expect($this->get(route('requests.create'))->headers->get('Permissions-Policy'))->toStartWith('camera=(self)');
    expect($this->get('/')->headers->get('Permissions-Policy'))->toStartWith('camera=()');
    expect($this->get(route('assets.index'))->headers->get('Permissions-Policy'))->toStartWith('camera=()');
});

it('HSTS solo su https e configurabile; CSP anche solo segnalata o spenta', function () {
    installManuTrack();
    expect($this->get('/login')->headers->has('Strict-Transport-Security'))->toBeFalse();
    // Il valore viene dal .env (MANUTRACK_HSTS_SECONDS, 300 nel .env.example per iniziare): qui si fissa
    config(['manutrack.security.hsts_seconds' => 300]);
    expect($this->get('https://localhost/login')->headers->get('Strict-Transport-Security'))->toBe('max-age=300');
    config(['manutrack.security.hsts_seconds' => 31536000]);
    expect($this->get('https://localhost/login')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');
    config(['manutrack.security.hsts_seconds' => 0]);
    expect($this->get('https://localhost/login')->headers->has('Strict-Transport-Security'))->toBeFalse();

    config(['manutrack.security.csp' => 'report']);
    $response = $this->get('/login');
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("frame-ancestors 'none'");
    config(['manutrack.security.csp' => 'off']);
    expect($this->get('/login')->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($this->get('/login')->headers->get('X-Frame-Options'))->toBe('DENY');
});

it('i file serviti tengono la loro CSP più stretta e le risposte JSON non hanno CSP di pagina', function () {
    $admin = installManuTrack();
    $this->actingAs($admin);
    $this->get(route('notifications.count'))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeaderMissing('Content-Security-Policy');
    $asset = Asset::factory()->create();
    $this->get(route('assets.qr.svg', $asset))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('nessuna vista ha gestori di eventi in linea né script senza nonce', function () {
    foreach (File::allFiles(resource_path('views')) as $file) {
        $source = $file->getContents();
        $this->assertSame(0, preg_match('/<[a-z][^>]*\son(click|change|submit|load|error|input|keydown|keyup)\s*=/i', $source), $file->getRelativePathname());
        preg_match_all('/<(script|style)\b([^>]*)>/i', $source, $tags, PREG_SET_ORDER);
        foreach ($tags as [$tag, $name, $attributes]) {
            if (! str_contains($attributes, 'src=') && ! str_contains($attributes, 'application/json')) {
                $this->assertStringContainsString('nonce=', $attributes, $file->getRelativePathname().': '.$tag);
            }
        }
    }
});

it('su Apache .htaccess aggiunge le intestazioni solo ai file statici, senza sostituire quelle dell\'app', function () {
    $htaccess = (string) file_get_contents(public_path('.htaccess'));
    foreach (['X-Content-Type-Options', 'Referrer-Policy', 'X-Frame-Options', 'Permissions-Policy', 'Strict-Transport-Security', 'Content-Security-Policy'] as $header) {
        // "always set" o "set" sostituirebbero l'intestazione della pagina (per esempio la fotocamera di Segnala)
        expect(preg_match('/^\s*Header\s+(always\s+)?(set|append|add)\s+'.preg_quote($header, '/').'\b/mi', $htaccess))->toBe(0);
    }
    expect($htaccess)->toContain('Header setifempty X-Frame-Options "DENY"')
        ->toContain('Header setifempty Permissions-Policy "camera=()');
});
