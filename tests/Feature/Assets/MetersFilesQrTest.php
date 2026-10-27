<?php

use App\Actions\Assets\RecordMeterReading;
use App\Actions\Assets\StoreAttachment;
use App\Enums\AttachmentKind;
use App\Livewire\Assets\Show;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\Meter;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Assets\QrCode;
use App\Support\Files\JpegMetadata;
use App\Support\Numbers;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->asset = Asset::factory()->create(['code' => 'PRS-01']);
});

function meterFor(Asset $asset, bool $cumulative = true): Meter
{
    $meter = new Meter(['name' => 'Ore di lavoro', 'unit' => 'h', 'cumulative' => $cumulative]);
    $meter->asset_id = $asset->id;
    $meter->save();

    return $meter;
}

/** JPEG 3000×2000 con un blocco EXIF che contiene una posizione GPS inventata */
function jpegWithGps(int $w = 3000, int $h = 2000): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 200, 120, 40));
    ob_start();
    imagejpeg($img, null, 80);
    $jpeg = (string) ob_get_clean();
    $exif = "Exif\0\0MM\0*\0\0\0\x08GPSLatitude=45.0000;GPSLongitude=9.0000;";
    $app1 = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

    return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
}

it('legge i numeri scritti all\'italiana', function () {
    expect(Numbers::parse('1.234,5'))->toBe(1234.5)
        ->and(Numbers::parse('1234.5'))->toBe(1234.5)
        ->and(Numbers::parse('12.500'))->toBe(12500.0)
        ->and(Numbers::parse('2 450 300'))->toBe(2450300.0)
        ->and(Numbers::parse('€ 99,90'))->toBe(99.9)
        ->and(Numbers::parse('dodici'))->toBeNull();
});

it('un contatore progressivo non accetta letture più basse, salvo sostituzione', function () {
    $meter = meterFor($this->asset);
    $record = app(RecordMeterReading::class);

    $record->handle($meter, 1000, $this->admin, now()->subDay());
    $record->handle($meter, 1050, $this->admin);
    expect((float) $meter->fresh()->current_value)->toBe(1050.0);

    expect(fn () => $record->handle($meter, 900, $this->admin))->toThrow(ValidationException::class);

    // Lettura dimenticata, inserita dopo: deve stare tra le due vicine e non cambia il valore attuale
    $record->handle($meter, 1020, $this->admin, now()->subHours(12));
    expect((float) $meter->fresh()->current_value)->toBe(1050.0);
    expect(fn () => $record->handle($meter, 1100, $this->admin, now()->subHours(13)))->toThrow(ValidationException::class);

    // Contatore sostituito: si riparte da zero
    $record->handle($meter, 5, $this->admin, reset: true);
    expect((float) $meter->fresh()->current_value)->toBe(5.0)
        ->and($meter->readings()->count())->toBe(4);
});

it('un contatore non progressivo accetta qualunque valore; mai negativi o nel futuro', function () {
    $meter = meterFor($this->asset, cumulative: false);
    $record = app(RecordMeterReading::class);
    $record->handle($meter, 80, $this->admin);
    $record->handle($meter, 20, $this->admin);
    expect((float) $meter->fresh()->current_value)->toBe(20.0);

    expect(fn () => $record->handle($meter, -1, $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => $record->handle($meter, 10, $this->admin, now()->addDay()))->toThrow(ValidationException::class);
});

it('il tecnico registra una lettura dalla scheda, il richiedente no', function () {
    $meter = meterFor($this->asset);

    Livewire::actingAs(userWithRole(Access::TECHNICIAN))->test(Show::class, ['asset' => $this->asset])
        ->set("reading.{$meter->id}", '1.250,5')->call('recordReading', $meter->id)->assertHasNoErrors();
    expect((float) $meter->fresh()->current_value)->toBe(1250.5);

    Livewire::actingAs(userWithRole(Access::TECHNICIAN))->test(Show::class, ['asset' => $this->asset])
        ->set("reading.{$meter->id}", '1000')->call('recordReading', $meter->id)->assertHasErrors("reading.{$meter->id}");

    Livewire::actingAs(userWithRole(Access::REQUESTER))->test(Show::class, ['asset' => $this->asset])
        ->set("reading.{$meter->id}", '2000')->call('recordReading', $meter->id)->assertForbidden();
});

it('toglie EXIF e GPS da un JPEG senza ricomprimerlo', function () {
    $jpeg = jpegWithGps(40, 30);
    expect($jpeg)->toContain('GPSLatitude');

    $clean = JpegMetadata::strip($jpeg);
    expect($clean)->not->toContain('GPSLatitude')->not->toContain('Exif')
        ->and(imagecreatefromstring($clean))->not->toBeFalse();
});

it('le foto vengono ridimensionate, ricodificate senza GPS e con la miniatura', function () {
    $file = UploadedFile::fake()->createWithContent('foto.jpg', jpegWithGps());

    $attachment = app(StoreAttachment::class)->handle($this->asset, $file, AttachmentKind::Photo);
    $disk = Storage::disk($attachment->disk);
    $stored = (string) $disk->get($attachment->path);
    [$w, $h] = getimagesizefromstring($stored);
    [$tw] = getimagesizefromstring((string) $disk->get($attachment->variants['thumb']));

    expect($stored)->not->toContain('GPSLatitude')->not->toContain('Exif')
        ->and([$w, $h])->toBe([1600, 1067])
        ->and($tw)->toBe(400)
        ->and($attachment->mime)->toBe('image/webp')
        ->and($attachment->path)->toStartWith('attachments/asset/'.$this->asset->id.'/')
        ->and($this->asset->fresh()->cover->id)->toBe($attachment->id);
});

it('rifiuta file non ammessi', function () {
    $store = app(StoreAttachment::class);

    expect(fn () => $store->handle($this->asset, UploadedFile::fake()->createWithContent('pagina.html', '<script>alert(1)</script>'), AttachmentKind::Document))
        ->toThrow(RuntimeException::class)
        ->and(fn () => $store->handle($this->asset, UploadedFile::fake()->createWithContent('finta.jpg', 'non è una foto'), AttachmentKind::Photo))
        ->toThrow(RuntimeException::class);
});

it('gli allegati si scaricano solo con il permesso di vedere le macchine', function () {
    $doc = app(StoreAttachment::class)->handle($this->asset, UploadedFile::fake()->createWithContent('manuale.pdf', '%PDF-1.4 finto'), AttachmentKind::Document);
    $photo = app(StoreAttachment::class)->handle($this->asset, UploadedFile::fake()->createWithContent('foto.jpg', jpegWithGps(800, 600)), AttachmentKind::Photo);

    $this->get(route('attachments.show', $doc))->assertRedirect(route('login'));

    $nobody = User::factory()->create();
    $this->actingAs($nobody)->get(route('attachments.download', $doc))->assertForbidden();

    $this->actingAs(userWithRole(Access::REQUESTER))->get(route('attachments.download', $doc))
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertDownload('manuale.pdf');

    $thumb = $this->actingAs($this->admin)->get(route('attachments.show', ['attachment' => $photo->id, 'v' => 'thumb']));
    $thumb->assertOk()->assertHeader('Content-Type', 'image/webp');
    expect($thumb->headers->get('Content-Security-Policy'))->toContain('sandbox')
        ->and($thumb->headers->get('Cache-Control'))->toContain('private');
});

it('eliminando un allegato si cancellano anche i file', function () {
    $photo = app(StoreAttachment::class)->handle($this->asset, UploadedFile::fake()->createWithContent('foto.jpg', jpegWithGps(800, 600)), AttachmentKind::Photo);
    $disk = Storage::disk($photo->disk);
    expect($disk->exists($photo->path))->toBeTrue();

    Livewire::actingAs($this->admin)->test(Show::class, ['asset' => $this->asset])->call('deleteAttachment', $photo->id);

    expect(Attachment::query()->count())->toBe(0)
        ->and($disk->exists($photo->path))->toBeFalse()
        ->and($disk->exists($photo->variants['thumb']))->toBeFalse();
});

it('chi può allegare elimina solo i propri file', function () {
    $technician = userWithRole(Access::TECHNICIAN);
    $this->actingAs($this->admin);
    $byAdmin = app(StoreAttachment::class)->handle($this->asset, UploadedFile::fake()->createWithContent('a.pdf', '%PDF'), AttachmentKind::Document);

    Livewire::actingAs($technician)->test(Show::class, ['asset' => $this->asset])->call('deleteAttachment', $byAdmin->id)->assertForbidden();
    expect(Attachment::query()->count())->toBe(1);
});

it('il QR porta alla scheda della macchina, anche passando dal login', function () {
    $url = route('assets.qr', $this->asset->qr_token);

    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($this->admin)->get($url)->assertRedirect(route('assets.show', $this->asset));
    $this->actingAs($this->admin)->get(route('assets.qr', str_repeat('a', 24)))->assertRedirect(route('assets.index'));
});

it('rigenerare il QR invalida le etichette vecchie', function () {
    $old = $this->asset->qr_token;
    Livewire::actingAs($this->admin)->test(Show::class, ['asset' => $this->asset])->call('regenerateQr');

    expect($this->asset->fresh()->qr_token)->not->toBe($old);
    $this->actingAs($this->admin)->get(route('assets.qr', $old))->assertRedirect(route('assets.index'));
});

it('produce QR in SVG ed etichette da stampare', function () {
    $svg = QrCode::svg('https://manutrack.example.com/q/abc');
    expect($svg)->toStartWith('<svg')->toContain('<path fill="#000" d="M');

    $this->actingAs($this->admin)->get(route('assets.qr.svg', $this->asset))
        ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');

    $other = Asset::factory()->create(['code' => 'CMP-07']);
    $this->actingAs($this->admin)->get(route('assets.labels', ['ids' => "{$this->asset->id},{$other->id}", 'formato' => 'medie', 'salta' => 3]))
        ->assertOk()->assertSee('PRS-01')->assertSee('CMP-07')->assertSee('105mm', false);
});

it('dalla scheda il tecnico carica foto e documenti', function () {
    Livewire::actingAs(userWithRole(Access::TECHNICIAN))->test(Show::class, ['asset' => $this->asset])
        ->set('photos', [UploadedFile::fake()->image('macchina.jpg', 2400, 1800)])
        ->assertHasNoErrors()
        ->set('documents', [UploadedFile::fake()->createWithContent('schema.pdf', '%PDF-1.4'), UploadedFile::fake()->createWithContent('virus.exe', 'MZ')])
        ->assertHasErrors('documents');

    expect(Attachment::query()->where('kind', 'foto')->sole()->width)->toBe(1600)
        ->and(Attachment::query()->where('kind', 'documento')->pluck('original_name')->all())->toBe(['schema.pdf']);

    Livewire::actingAs(userWithRole(Access::REQUESTER))->test(Show::class, ['asset' => $this->asset])
        ->set('photos', [UploadedFile::fake()->image('altra.jpg')])->assertForbidden();
});

it('lo storico delle letture si apre dalla scheda', function () {
    $meter = meterFor($this->asset);
    app(RecordMeterReading::class)->handle($meter, 1234, $this->admin);

    Livewire::actingAs($this->admin)->test(Show::class, ['asset' => $this->asset])
        ->call('toggleHistory', $meter->id)
        ->assertViewHas('history', fn ($h) => $h !== null && $h->count() === 1)
        ->assertSee('1.234');
});
