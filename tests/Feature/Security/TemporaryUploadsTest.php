<?php

use App\Livewire\Assets\Show as AssetShow;
use App\Models\Asset;
use App\Models\Attachment;
use App\Support\Access\Access;
use App\Support\Brand\CompanyLogo;
use App\Support\Gdpr\Eraser;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\GenerateSignedUploadUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/*
 * Controllo di sicurezza 0.3: caricamenti temporanei di Livewire (config/livewire.php).
 * Nei test Livewire usa il disco "tmp-for-tests"; in produzione la cartella livewire-tmp
 * del disco privato (storage/app/private).
 */

beforeEach(function () {
    $this->admin = installManuTrack();
    Storage::fake('tmp-for-tests');
    $this->actingAs($this->admin);
});

afterEach(function () {
    @unlink(Eraser::reportPath());
});

/** Carica un file come fa il browser e restituisce il riferimento firmato */
function uploadTemporary(TestCase $test, UploadedFile $file): string
{
    $response = $test->post((new GenerateSignedUploadUrl)->forLocal(), ['files' => [$file]])->assertOk();

    return $response->json('paths.0');
}

it('senza firma o con la firma scaduta non si carica nulla', function () {
    $file = UploadedFile::fake()->image('foto.jpg', 20, 20);
    $this->post(route('livewire.upload-file'), ['files' => [$file]])->assertStatus(401);
    $expired = URL::temporarySignedRoute('livewire.upload-file', now()->subMinute(), absolute: false);
    $this->post($expired, ['files' => [$file]])->assertStatus(401);
    // Firma valida ma indirizzo cambiato
    $this->post((new GenerateSignedUploadUrl)->forLocal().'x', ['files' => [$file]])->assertStatus(401);

    expect(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);
});

it('la dimensione massima vale già per il file temporaneo', function () {
    config(['livewire.temporary_file_upload.rules' => ['required', 'file', 'max:'.(1024 * 1)]]);
    $this->postJson((new GenerateSignedUploadUrl)->forLocal(), ['files' => [UploadedFile::fake()->create('grande.pdf', 1500)]])->assertStatus(422);
    expect(Storage::disk('tmp-for-tests')->allFiles())->toBe([]);
});

it('il nome del file temporaneo non viene dal browser e un riferimento falsificato è rifiutato', function () {
    $ref = uploadTemporary($this, UploadedFile::fake()->image('../../../.env.jpg', 20, 20));
    [$token, $path] = explode(':', $ref, 2);
    expect(ltrim($path, '/'))->not->toContain('..')->not->toContain('/')->not->toContain('.env')
        ->and(Storage::disk('tmp-for-tests')->allFiles())->toHaveCount(1)
        ->and(Storage::disk('tmp-for-tests')->allFiles()[0])->toStartWith(FileUploadConfiguration::path().'/');

    $asset = Asset::factory()->create();
    $snapshot = livewireSnapshot($this->get(route('assets.show', $asset)), 'assets.show');
    // Riferimento verso un altro file, con la firma di un altro, o senza firma
    foreach (['../../.env', $token.':../../.env', 'nessuna-firma'] as $forged) {
        livewireCall($this, $snapshot, [['_finishUpload', ['photos', [$forged], true]]])->assertForbidden();
    }
    expect(Attachment::query()->count())->toBe(0);
});

it('un file temporaneo non si legge senza firma, né da un percorso pubblico', function () {
    $ref = uploadTemporary($this, UploadedFile::fake()->image('foto.jpg', 20, 20));
    $file = TemporaryUploadedFile::createFromLivewire(explode(':', $ref, 2)[1]);

    $this->get(route('livewire.preview-file', ['filename' => $file->getFilename()]))->assertStatus(401);
    $this->get($file->temporaryUrl())->assertOk();

    // Altri tipi (SVG, HTML, PDF) non hanno anteprima, neanche firmata
    expect(config('livewire.temporary_file_upload.preview_mimes'))->toBe(['png', 'jpg', 'jpeg', 'webp']);

    // Il disco privato non ha più indirizzi /storage/… (nessun file si serve da lì)
    expect(Route::has('storage.local'))->toBeFalse();
    $this->get('/storage/livewire-tmp/'.$file->getFilename())->assertNotFound();
    expect(is_dir(public_path('storage/livewire-tmp')))->toBeFalse();
});

it('il contenuto decide il tipo, non il nome: un falso JPEG non diventa foto, un HTML non si apre nel browser', function () {
    $asset = Asset::factory()->create();
    $snapshot = livewireSnapshot($this->get(route('assets.show', $asset)), 'assets.show');

    $fake = uploadTemporary($this, UploadedFile::fake()->createWithContent('foto.jpg', '<html><script>alert(1)</script></html>'));
    livewireCall($this, $snapshot, [['_finishUpload', ['photos', [$fake], true]]])->assertOk();
    expect(Attachment::query()->count())->toBe(0);

    $html = uploadTemporary($this, UploadedFile::fake()->createWithContent('manuale.pdf', '<html><script>alert(1)</script></html>'));
    livewireCall($this, $snapshot, [['_finishUpload', ['documents', [$html], true]]])->assertOk();
    $document = Attachment::query()->sole();
    expect($document->mime)->toBe('text/html');
    $this->get(route('attachments.show', $document))->assertOk()
        ->assertHeader('Content-Type', 'application/octet-stream')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect((string) $this->get(route('attachments.show', $document))->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('un file caricato per un componente non si salva in un altro con un tipo diverso', function () {
    // Modello GLB caricato come temporaneo e poi mandato al logo e alle foto
    $glb = "glTF\x02\x00\x00\x00".pack('V', 20).str_repeat("\0", 8);
    $ref = uploadTemporary($this, UploadedFile::fake()->createWithContent('logo.png', $glb));

    $company = livewireSnapshot($this->get(route('settings.company')), 'settings.company');
    livewireCall($this, $company, [['_finishUpload', ['logo', [$ref], false]]])->assertOk();
    expect(CompanyLogo::current(CompanyLogo::LIGHT))->toBeNull();

    $asset = Asset::factory()->create();
    $show = livewireSnapshot($this->get(route('assets.show', $asset)), 'assets.show');
    livewireCall($this, $show, [['_finishUpload', ['photos', [$ref], true]]])->assertOk();
    expect(Attachment::query()->count())->toBe(0);

    // Un SVG (che può contenere script) non è mai accettato come logo
    $svg = uploadTemporary($this, UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'));
    livewireCall($this, $company, [['_finishUpload', ['logo', [$svg], false]]])->assertOk();
    expect(CompanyLogo::current(CompanyLogo::LIGHT))->toBeNull();
});

it('chi non può allegare non salva il temporaneo di un altro', function () {
    $ref = uploadTemporary($this, UploadedFile::fake()->image('foto.jpg', 20, 20));
    $asset = Asset::factory()->create();
    $this->actingAs(userWithRole(Access::VIEWER));
    $snapshot = livewireSnapshot($this->get(route('assets.show', $asset)), 'assets.show');

    livewireCall($this, $snapshot, [['_finishUpload', ['photos', [$ref], true]]])->assertForbidden();
    expect(Attachment::query()->count())->toBe(0);
});

it('dopo il salvataggio il temporaneo si cancella; quelli dimenticati spariscono entro un giorno anche senza nuovi caricamenti', function () {
    $asset = Asset::factory()->create();
    Livewire::test(AssetShow::class, ['asset' => $asset])->set('photos', [UploadedFile::fake()->image('foto.jpg', 40, 40)]);
    expect(Attachment::query()->count())->toBe(1)
        ->and(Storage::disk('tmp-for-tests')->allFiles(FileUploadConfiguration::path()))->toBe([]);

    // Temporanei abbandonati (pagina chiusa a metà): la pulizia notturna li toglie
    $old = FileUploadConfiguration::path('vecchio.jpg');
    $recent = FileUploadConfiguration::path('recente.jpg');
    Storage::disk('tmp-for-tests')->put($old, 'x');
    Storage::disk('tmp-for-tests')->put($recent, 'x');
    touch(Storage::disk('tmp-for-tests')->path($old), now()->subHours(25)->getTimestamp());

    $schedule = app(Schedule::class);
    $event = collect($schedule->events())->first(fn ($e) => $e->description === 'manutrack:prune');
    expect($event)->not->toBeNull();
    $event->run($this->app);

    expect(Storage::disk('tmp-for-tests')->exists($old))->toBeFalse()
        ->and(Storage::disk('tmp-for-tests')->exists($recent))->toBeTrue();
});

it('la cancellazione totale toglie anche i file temporanei', function () {
    Storage::disk('local')->put('livewire-tmp/abbandonato.jpg', 'foto');
    $this->withSession(['auth.password_confirmed_at' => time()]);
    app(Eraser::class)->run($this->admin, 'prova');

    expect(is_file(storage_path('app/private/livewire-tmp/abbandonato.jpg')))->toBeFalse();
});
