<?php

use App\Jobs\ExportCompanyData;
use App\Livewire\Gdpr\DataExports;
use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\DataExport;
use App\Support\Access\Access;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = installManuTrack();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => time()]);
});

/** Contenuto dello ZIP: nome => testo */
function zipContents(string $path): array
{
    $zip = new ZipArchive;
    $zip->open($path);
    $out = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        $out[$name] = $zip->getFromIndex($i);
    }
    $zip->close();

    return $out;
}

it('solo chi può esportare, e con la password riconfermata', function () {
    $this->get(route('gdpr.exports'))->assertOk()->assertSee(__('gdpr.export.start'));
    $this->actingAs(userWithRole(Access::MANAGER))->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.exports'))->assertForbidden();
    $this->actingAs($this->admin)->withSession(['auth.password_confirmed_at' => 0])->get(route('gdpr.exports'))->assertRedirect(route('password.confirm'));
});

it('lo ZIP ha indice, tutte le tabelle, gli allegati con impronta, e niente segreti', function () {
    $asset = Asset::factory()->create(['code' => 'ZIP-01']);
    $this->post(route('assets.model.upload', $asset), ['model' => UploadedFile::fake()->createWithContent('m.glb', tinyGlb())]);
    Storage::disk('local')->put('attachments/prova/foto.jpg', 'immagine-di-prova');
    $att = $asset->attachments()->create(['kind' => 'documento', 'disk' => 'local', 'path' => 'attachments/prova/foto.jpg', 'original_name' => 'Targhetta pressa.jpg', 'mime' => 'image/jpeg', 'size' => 17, 'position' => 0, 'user_id' => $this->admin->id]);
    $this->admin->forceFill(['two_factor_secret' => 'SEGRETO-2FA', 'remember_token' => 'TOKEN-RICORDA'])->save();

    Livewire::test(DataExports::class)->call('start');
    $export = DataExport::query()->firstOrFail();
    expect($export->status)->toBe('pronto')->and($export->expires_at->isFuture())->toBeTrue();

    $files = zipContents(Storage::disk('local')->path($export->path));
    expect($files)->toHaveKeys(['INDICE.md', 'indice.json', 'dati/users.json', 'dati/assets.json', 'dati/work_orders.json', 'dati/audit_logs.json', 'dati/attachments.json', 'dati/work_calendars.json', 'dati/plant_layouts.json'])
        ->not->toHaveKey('dati/sessions.json')->not->toHaveKey('dati/password_reset_tokens.json')->not->toHaveKey('dati/push_subscriptions.json');

    $users = json_decode($files['dati/users.json'], true);
    expect($users[0])->toHaveKey('email')->not->toHaveKey('password')->not->toHaveKey('two_factor_secret')->not->toHaveKey('remember_token');
    $all = implode("\n", $files);
    expect($all)->not->toContain('SEGRETO-2FA')->not->toContain('TOKEN-RICORDA')->not->toContain($this->admin->password);

    $index = json_decode($files['indice.json'], true);
    $byPath = collect($index['file'])->keyBy('path');
    $photo = 'file/allegati/'.$att->id.'-Targhetta pressa.jpg';
    expect($files[$photo])->toBe('immagine-di-prova')
        ->and($byPath[$photo]['sha256'])->toBe(hash('sha256', 'immagine-di-prova'))
        ->and($byPath[$photo]['linked_to'])->toContain('attachments.id='.$att->id)
        ->and($files['file/modelli/'.$asset->id.'-zip-01.glb'])->toBe(tinyGlb())
        ->and(collect($index['tabelle'])->firstWhere('table', 'assets')['rows'])->toBe(1)
        ->and($index['esclusi']['tabelle'])->toHaveKey('sessions');
    expect($files['INDICE.md'])->toContain('Officine Prova')->toContain('`dati/assets.json`')->toContain('password');

    // Scarico: registro attività; scaduto: non più scaricabile
    $this->get(route('gdpr.exports.download', $export))->assertOk()->assertHeader('Content-Type', 'application/zip');
    expect(AuditLog::query()->where('action', 'system.data_export_downloaded')->exists())->toBeTrue();
    $export->forceFill(['expires_at' => now()->subMinute()])->save();
    $this->get(route('gdpr.exports.download', $export))->assertNotFound();
    $this->actingAs(userWithRole(Access::VIEWER))->withSession(['auth.password_confirmed_at' => time()])->get(route('gdpr.exports.download', $export))->assertForbidden();
});

it('va in coda, una alla volta; un errore resta visibile', function () {
    Queue::fake();
    Livewire::test(DataExports::class)->call('start')->call('start')->assertDispatched('toast', message: __('gdpr.export.already'), tone: 'warn');
    Queue::assertPushed(ExportCompanyData::class, 1);
    expect(DataExport::query()->value('status'))->toBe('in_coda');
    Livewire::test(DataExports::class)->assertSee(__('gdpr.export.working'));
});
