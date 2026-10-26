<?php

use App\Support\Brand\IconRenderer;

beforeEach(function () {
    installManuTrack(['company' => 'Officine Pagine']);
});

it('mostra le pagine principali in italiano', function (string $url, string $text) {
    $this->get($url)->assertOk()->assertSee($text)->assertSee('lang="it"', false);
})->with([
    'panoramica' => ['/', 'Benvenuto in ManuTrack'],
    'sistema' => ['/sistema', 'Copie del database'],
    'design system' => ['/design', 'Elementi base'],
]);

it('include i meta per iPhone e il manifest', function () {
    $this->get('/')
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes">', false)
        ->assertSee('viewport-fit=cover', false)
        ->assertSee('rel="apple-touch-icon"', false)
        ->assertSee(route('manifest'), false);
});

it('genera un manifest completo con il nome dell\'azienda', function () {
    $manifest = $this->get('/manifest.webmanifest')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->json();

    expect($manifest['name'])->toBe('ManuTrack · Officine Pagine')
        ->and($manifest['short_name'])->toBe('ManuTrack')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['scope'])->toBe('/')
        ->and(collect($manifest['icons'])->pluck('purpose')->unique()->values()->all())->toBe(['any', 'maskable'])
        ->and(collect($manifest['icons'])->pluck('sizes')->unique()->sort()->values()->all())->toBe(['192x192', '512x512']);
});

it('ha le icone pre-generate per ogni palette', function (string $palette) {
    foreach (['icon-192.png', 'icon-512.png', 'maskable-192.png', 'maskable-512.png', 'apple-touch-icon.png', 'favicon-32.png', 'favicon.svg'] as $file) {
        expect(public_path("icons/{$palette}/{$file}"))->toBeFile();
    }
    [$w, $h] = getimagesize(public_path("icons/{$palette}/apple-touch-icon.png"));
    expect([$w, $h])->toBe([180, 180]);
})->with(['rame', 'ciano', 'indaco', 'pistacchio']);

it('disegna le icone con GD', function () {
    $png = app(IconRenderer::class)->png(config('manutrack.palettes.rame'), 48, 'maskable');
    $info = getimagesizefromstring($png);

    expect($info[0])->toBe(48)->and($info['mime'])->toBe('image/png');
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('mostra la pagina di manutenzione durante un aggiornamento', function () {
    $this->artisan('down', ['--render' => 'errors::503']);
    $this->get('/')->assertStatus(503)->assertSee('Aggiornamento in corso');
    $this->artisan('up');
});
