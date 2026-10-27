<?php

use App\Support\Brand\SplashRenderer;

beforeEach(function () {
    $this->admin = installManuTrack();
});

it('genera il service worker con versione, file in cache e intestazioni giuste', function () {
    $response = $this->get('/sw.js')->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Service-Worker-Allowed', '/')
        ->assertHeaderMissing('Set-Cookie');

    $js = $response->getContent();
    expect($js)->toContain('const VERSION = "'.manutrack_version().'-')
        ->and($js)->toContain('"\/offline"')
        ->and($js)->toContain("addEventListener('push'")
        ->and($js)->toContain("addEventListener('notificationclick'")
        ->and($js)->toContain('SKIP_WAITING')
        ->and($js)->not->toContain('skipWaiting();\n});'); // nessun aggiornamento forzato all'installazione
});

it('la pagina offline è autonoma e senza sessione', function () {
    $this->get('/offline')->assertOk()->assertSee(__('pwa.offline.title'))->assertHeaderMissing('Set-Cookie');
});

it('procedura guidata e diagnostica sono per chi ha fatto l\'accesso', function () {
    $this->get('/app')->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get('/app')->assertOk()->assertSee(__('pwa.wizard.heading'))->assertSee(__('pwa.wizard.ios_2'));
    $this->actingAs($this->admin)->get('/notifiche/diagnostica')->assertOk()->assertSee(__('pwa.diag.devices'));
});

it('la pagina include chiave pubblica VAPID e schermate di avvio per iPhone', function () {
    config(['manutrack.webpush.public_key' => 'BChiavePubblicaDiProva']);

    $html = $this->actingAs($this->admin)->get('/')->assertOk()->getContent();
    expect($html)->toContain('<meta name="vapid-public-key" content="BChiavePubblicaDiProva">')
        ->and(substr_count($html, 'rel="apple-touch-startup-image"'))->toBe(count(SplashRenderer::DEVICES));
});

it('ha le schermate di avvio per ogni palette', function (string $palette) {
    foreach (SplashRenderer::DEVICES as [$w, $h]) {
        $file = public_path("icons/{$palette}/".SplashRenderer::fileName($w, $h));
        expect($file)->toBeFile();
        expect(getimagesize($file))->toMatchArray([0 => $w, 1 => $h]);
    }
})->with(['rame', 'ciano', 'indaco', 'pistacchio']);
