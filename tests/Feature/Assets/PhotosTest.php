<?php

use App\Actions\Assets\StoreAttachment;
use App\Enums\AttachmentKind;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\User;
use App\Support\Access\Access;
use App\Support\Files\JpegMetadata;
use App\Support\Files\PhotoException;
use App\Support\Files\PhotoProcessor;
use App\Support\Setup\Requirements;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->asset = Asset::factory()->create();
    $this->actingAs($this->admin);
});

/**
 * JPEG di prova: metà sinistra rossa e destra blu, con un blocco EXIF vero che
 * contiene l'orientamento e una posizione GPS inventata.
 */
function photoJpeg(int $w, int $h, int $orientation = 1, bool $gps = true): string
{
    $img = imagecreatetruecolor($w, $h);
    imagefilledrectangle($img, 0, 0, intdiv($w, 2) - 1, $h - 1, imagecolorallocate($img, 220, 30, 30));
    imagefilledrectangle($img, intdiv($w, 2), 0, $w - 1, $h - 1, imagecolorallocate($img, 30, 30, 220));
    ob_start();
    imagejpeg($img, null, 85);
    $jpeg = (string) ob_get_clean();

    $text = $gps ? 'GPSLatitude=45.4642;GPSLongitude=9.1900' : 'foto';
    $tiff = 'MM'.pack('n', 42).pack('N', 8)
        .pack('n', 2)
        .pack('nnN', 0x0112, 3, 1).pack('n', $orientation)."\0\0"
        .pack('nnNN', 0x010E, 2, strlen($text) + 1, 38)
        .pack('N', 0)
        .$text."\0";
    $exif = "Exif\0\0".$tiff;

    return "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
}

function storePhoto(Asset $asset, string $name, string $content): Attachment
{
    return app(StoreAttachment::class)->handle($asset, UploadedFile::fake()->createWithContent($name, $content), AttachmentKind::Photo);
}

function storedImage(Attachment $a, ?string $variant = null): string
{
    return (string) Storage::disk($a->disk)->get($a->pathFor($variant));
}

it('il server sa salvare in WebP e lo dice tra i requisiti', function () {
    expect(PhotoProcessor::outputFormat())->toBe('webp');
    $check = collect(app(Requirements::class)->checks(false))->firstWhere('key', 'image-webp');
    expect($check['status'])->toBe('ok');
});

it('legge l\'orientamento EXIF senza l\'estensione exif', function () {
    expect(JpegMetadata::orientation(photoJpeg(40, 20, 6)))->toBe(6)
        ->and(JpegMetadata::orientation(photoJpeg(40, 20, 3)))->toBe(3)
        ->and(JpegMetadata::orientation(photoJpeg(40, 20, 1, gps: false)))->toBe(1)
        ->and(JpegMetadata::orientation('non è un jpeg'))->toBe(1);
});

it('converte un JPEG in WebP da 1600 px con miniatura da 400, senza EXIF né GPS', function () {
    $jpeg = photoJpeg(4000, 3000);
    expect($jpeg)->toContain('GPSLatitude');

    $a = storePhoto($this->asset, 'scatto.jpg', $jpeg);
    $main = storedImage($a);
    $thumb = storedImage($a, 'thumb');

    expect($a->mime)->toBe('image/webp')
        ->and($a->path)->toEndWith('.webp')
        ->and($a->original_name)->toBe('scatto.webp')
        ->and(getimagesizefromstring($main)['mime'])->toBe('image/webp')
        ->and([$a->width, $a->height])->toBe([1600, 1200])
        ->and(getimagesizefromstring($thumb)[0])->toBe(400)
        ->and($a->variants['thumb'])->toEndWith('-400.webp')
        ->and($main)->not->toContain('GPSLatitude')->not->toContain('Exif')
        ->and(strlen($main))->toBeLessThan(strlen($jpeg));
});

it('raddrizza le foto scattate con il telefono girato', function () {
    // Orientamento 6: va ruotata di 90° in senso orario, la metà sinistra (rossa) finisce in alto
    $a = storePhoto($this->asset, 'verticale.jpg', photoJpeg(600, 400, 6));
    $img = imagecreatefromstring(storedImage($a));

    expect([$a->width, $a->height])->toBe([400, 600]);
    $top = imagecolorsforindex($img, imagecolorat($img, 200, 50));
    $bottom = imagecolorsforindex($img, imagecolorat($img, 200, 550));
    expect($top['red'])->toBeGreaterThan(150)->and($top['blue'])->toBeLessThan(100)
        ->and($bottom['blue'])->toBeGreaterThan(150)->and($bottom['red'])->toBeLessThan(100);
});

it('accetta anche PNG con trasparenza e WebP', function () {
    $png = imagecreatetruecolor(800, 500);
    imagesavealpha($png, true);
    imagefill($png, 0, 0, imagecolorallocatealpha($png, 0, 0, 0, 127));
    ob_start();
    imagepng($png);
    $a = storePhoto($this->asset, 'schermo.png', (string) ob_get_clean());
    expect($a->mime)->toBe('image/webp')->and([$a->width, $a->height])->toBe([800, 500]);

    $webp = imagecreatetruecolor(2400, 1200);
    ob_start();
    imagewebp($webp, null, 90);
    $b = storePhoto($this->asset, 'gia.webp', (string) ob_get_clean());
    expect($b->mime)->toBe('image/webp')->and([$b->width, $b->height])->toBe([1600, 800]);
});

it('gestisce foto molto grandi', function () {
    $a = storePhoto($this->asset, 'enorme.jpg', photoJpeg(6000, 4500, gps: false));
    expect([$a->width, $a->height])->toBe([1600, 1200]);
});

it('rifiuta foto oltre il limite di megapixel con un messaggio chiaro', function () {
    // Intestazione PNG che dichiara 9000×9000 px (81 MP): non si prova nemmeno ad aprirla
    $png = "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.pack('NN', 9000, 9000)."\x08\x02\0\0\0".pack('N', 0);
    expect(fn () => storePhoto($this->asset, 'gigante.png', $png))
        ->toThrow(PhotoException::class, __('assets.files.photo_too_large', ['mp' => PhotoProcessor::MAX_MEGAPIXELS]));
});

it('una foto HEIC non si perde: si conserva tra i documenti con un avviso chiaro', function () {
    $heic = "\0\0\0\x18ftypheic\0\0\0\0mif1heic".random_bytes(200);
    $a = storePhoto($this->asset, 'IMG_0001.HEIC', $heic);

    expect($a->kind)->toBe(AttachmentKind::Document)
        ->and($a->notice)->toBe(__('assets.files.photo_heic'))
        ->and($a->original_name)->toBe('IMG_0001.HEIC')
        ->and(Storage::disk($a->disk)->get($a->path))->toBe($heic);
});

it('un file che non è una foto dà un errore chiaro e non salva nulla', function () {
    expect(fn () => storePhoto($this->asset, 'foto.jpg', 'testo qualunque'))
        ->toThrow(PhotoException::class, __('assets.files.photo_invalid'));
    expect(Attachment::query()->count())->toBe(0);
});

it('se il server non supporta WebP salva in JPEG ridotto e lo segnala', function () {
    config(['manutrack.attachments.format' => 'jpeg']);
    $a = storePhoto($this->asset, 'scatto.jpg', photoJpeg(3000, 2000));

    expect($a->mime)->toBe('image/jpeg')->and($a->path)->toEndWith('.jpg')
        ->and([$a->width, $a->height])->toBe([1600, 1067])
        ->and(storedImage($a))->not->toContain('GPSLatitude');

    $this->get(route('system'))->assertRedirect(); // conferma della password
    $this->withSession(['auth.password_confirmed_at' => time()])->get(route('system'))->assertSee(__('system.photos.jpeg'));
});

it('le foto si vedono solo con il permesso, miniature comprese', function () {
    $a = storePhoto($this->asset, 'scatto.jpg', photoJpeg(800, 600));
    auth()->logout();

    $this->get($a->url('thumb'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get($a->url())->assertForbidden();
    $this->actingAs(userWithRole(Access::REQUESTER))->get($a->url('thumb'))
        ->assertOk()->assertHeader('Content-Type', 'image/webp');
});

it('manutrack:foto-ottimizza converte le foto vecchie, con una prova che non cambia nulla', function () {
    // Foto salvata come nella versione precedente: JPEG senza miniatura
    $disk = Storage::disk('local');
    $disk->put('attachments/asset/'.$this->asset->id.'/vecchia.jpg', $jpeg = photoJpeg(3200, 2400));
    $old = $this->asset->attachments()->create([
        'kind' => 'foto', 'disk' => 'local', 'path' => 'attachments/asset/'.$this->asset->id.'/vecchia.jpg',
        'original_name' => 'vecchia.jpg', 'mime' => 'image/jpeg', 'size' => strlen($jpeg), 'width' => 3200, 'height' => 2400,
    ]);

    expect(Artisan::call('manutrack:foto-ottimizza', ['--prova' => true]))->toBe(0);
    expect(Artisan::output())->toContain(__('photos.would_save'));
    expect($old->fresh()->mime)->toBe('image/jpeg')->and($disk->exists($old->path))->toBeTrue();

    expect(Artisan::call('manutrack:foto-ottimizza'))->toBe(0);
    $new = $old->fresh();
    expect($new->mime)->toBe('image/webp')
        ->and([$new->width, $new->height])->toBe([1600, 1200])
        ->and($disk->exists($new->path))->toBeTrue()
        ->and($disk->exists($new->variants['thumb']))->toBeTrue()
        ->and($disk->exists('attachments/asset/'.$this->asset->id.'/vecchia.jpg'))->toBeFalse()
        ->and($new->original_name)->toBe('vecchia.webp');

    // Già tutto convertito
    Artisan::call('manutrack:foto-ottimizza');
    expect(Artisan::output())->toContain(__('photos.nothing'));
});

it('manutrack:foto-ottimizza lascia com\'è una foto che non riesce a convertire', function () {
    $missing = $this->asset->attachments()->create([
        'kind' => 'foto', 'disk' => 'local', 'path' => 'attachments/asset/x/sparita.jpg',
        'original_name' => 'sparita.jpg', 'mime' => 'image/jpeg', 'size' => 10,
    ]);

    expect(Artisan::call('manutrack:foto-ottimizza'))->toBe(1);
    expect($missing->fresh()->path)->toBe('attachments/asset/x/sparita.jpg')
        ->and($missing->fresh()->mime)->toBe('image/jpeg');
});
