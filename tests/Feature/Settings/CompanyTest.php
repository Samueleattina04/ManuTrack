<?php

use App\Livewire\Settings\Company;
use App\Support\Access\Access;
use App\Support\Brand\Brand;
use App\Support\Brand\Color;
use App\Support\Brand\CompanyLogo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = installManuTrack(['company' => 'Officine Colori']);
});

function pngLogo(int $w = 400, int $h = 120): UploadedFile
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    imagefilledrectangle($img, 0, 0, (int) ($w / 2), $h, imagecolorallocate($img, 0x2E, 0x5E, 0x2B)); // verde foglia
    imagefilledrectangle($img, (int) ($w / 2), 0, $w, $h, imagecolorallocate($img, 0xA8, 0xCB, 0x6E)); // pistacchio
    ob_start();
    imagepng($img);

    return UploadedFile::fake()->createWithContent('logo.png', (string) ob_get_clean());
}

it('salva nome, palette, fuso orario e lingua', function () {
    Livewire::actingAs($this->admin)->test(Company::class)
        ->set('name', 'Officine Nuove')->set('palette', 'pistacchio')->set('timezone', 'Europe/Rome')
        ->call('save')->assertHasNoErrors();

    expect(settings('company.name'))->toBe('Officine Nuove')
        ->and(Brand::paletteKey())->toBe('pistacchio');
    $this->actingAs($this->admin)->get('/')->assertSee('--acc-l:#2A5F2F', false);
});

it('costruisce una palette personalizzata leggibile con il testo bianco', function () {
    Livewire::actingAs($this->admin)->test(Company::class)
        ->set('palette', 'custom')->set('customAccent', '#7FBF3F')->set('customSoft', '#D9F0A3')
        ->call('save')->assertHasNoErrors();

    $p = Brand::palette();
    expect(Brand::paletteKey())->toBe('custom')
        ->and(Color::contrast($p['accent_l'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)
        ->and(Color::contrast($p['accent_d'], '#FFFFFF'))->toBeGreaterThanOrEqual(4.5)
        ->and(Color::contrast($p['accent_fg_d'], '#151A21'))->toBeGreaterThanOrEqual(7.0);

    // Icone generate per la palette personalizzata e usate dal manifest
    $manifest = $this->actingAs($this->admin)->get('/manifest.webmanifest')->json();
    expect($manifest['icons'][0]['src'])->toContain('/marchio/icone/');
    $this->get(parse_url($manifest['icons'][0]['src'], PHP_URL_PATH))->assertOk()->assertHeader('Content-Type', 'image/png');
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('rifiuta un colore non valido', function () {
    Livewire::actingAs($this->admin)->test(Company::class)
        ->set('palette', 'custom')->set('customAccent', 'verde')
        ->call('save')->assertHasErrors('customAccent');
});

it('carica il logo, lo mostra nell\'intestazione e nella pagina di accesso', function () {
    Livewire::actingAs($this->admin)->test(Company::class)
        ->set('logo', pngLogo())->assertHasNoErrors();

    $logo = CompanyLogo::current();
    expect($logo)->not->toBeNull();
    Storage::disk('local')->assertExists($logo['path']);

    $url = CompanyLogo::url();
    $this->actingAs($this->admin)->get('/')->assertSee($url)->assertSee('con ManuTrack')->assertSee('company-logo-plate', false);
    auth()->logout();
    $this->get('/login')->assertSee($url);
    $this->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('usa la versione per il tema scuro se caricata', function () {
    Livewire::actingAs($this->admin)->test(Company::class)
        ->set('logo', pngLogo())->set('logoDark', pngLogo(300, 90))->assertHasNoErrors();

    $dark = CompanyLogo::url(CompanyLogo::DARK);
    expect($dark)->toContain('tema=scuro');
    $this->actingAs($this->admin)->get('/')->assertSee($dark)->assertSee('company-logo-dark', false);
    $this->get($dark)->assertOk();

    Livewire::actingAs($this->admin)->test(Company::class)->call('removeLogo', 'scuro');
    expect(CompanyLogo::current(CompanyLogo::DARK))->toBeNull()->and(CompanyLogo::current())->not->toBeNull();
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('rifiuta SVG e file che fingono di essere immagini', function () {
    $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    Livewire::actingAs($this->admin)->test(Company::class)->set('logo', $svg)->assertHasErrors('logo');

    $fake = UploadedFile::fake()->createWithContent('logo.png', '<?php echo "no";');
    Livewire::actingAs($this->admin)->test(Company::class)->set('logo', $fake)->assertHasErrors('logo');

    expect(CompanyLogo::current())->toBeNull();
});

it('propone i colori presi dal logo', function () {
    Livewire::actingAs($this->admin)->test(Company::class)->set('logo', pngLogo());

    $c = Livewire::actingAs($this->admin)->test(Company::class)->call('sampleFromLogo');
    expect($c->get('palette'))->toBe('custom');
    expect(Color::hsl($c->get('customAccent'))[2])->toBeLessThan(Color::hsl($c->get('customSoft'))[2]);
    expect(Color::distance($c->get('customAccent'), '#2E5E2B'))->toBeLessThan(40)
        ->and(Color::distance($c->get('customSoft'), '#A8CB6E'))->toBeLessThan(40);
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('rimuove il logo e torna al nome dell\'azienda', function () {
    Livewire::actingAs($this->admin)->test(Company::class)->set('logo', pngLogo())->call('removeLogo');

    expect(CompanyLogo::current())->toBeNull();
    $this->get(route('branding.logo'))->assertNotFound();
    $this->actingAs($this->admin)->get('/')->assertSee('Officine Colori');
})->skip(fn () => ! function_exists('imagecreatetruecolor'), 'GD non disponibile');

it('solo chi ha il permesso modifica le impostazioni', function () {
    Livewire::actingAs(userWithRole(Access::MANAGER))->test(Company::class)->assertForbidden();
});

it('serve solo i file delle icone previsti', function () {
    $this->get('/marchio/icone/abcdef0123/..%2F..%2F.env')->assertNotFound();
    $this->get('/marchio/icone/zzz/icon-192.png')->assertNotFound();
});
