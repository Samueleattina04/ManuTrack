<?php

use App\Support\Backup\DatabaseBackup;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    installManuTrack();

    Schema::create('prova_copie', function (Blueprint $table) {
        $table->id();
        $table->text('testo')->nullable();
        $table->binary('dati')->nullable();
        $table->decimal('importo', 10, 2)->nullable();
    });
});

it('salva e ripristina testi particolari, dati binari e valori nulli', function () {
    $rows = [
        ['testo' => "riga 1\nriga 2\r\nfine", 'dati' => random_bytes(64), 'importo' => '1234.50'],
        ['testo' => "L'olio è \"caldo\" \\ 100% \x1a ☢ 😀", 'dati' => null, 'importo' => null],
        ['testo' => '', 'dati' => "\0\0\xff", 'importo' => '0.00'],
    ];
    DB::table('prova_copie')->insert($rows);

    $backup = app(DatabaseBackup::class);
    $file = $backup->create('prova');

    DB::table('prova_copie')->delete();
    DB::table('users')->delete();
    Schema::create('tabella_nuova', fn (Blueprint $t) => $t->id());

    $backup->restore($file);

    $restored = DB::table('prova_copie')->orderBy('id')->get();
    expect($restored)->toHaveCount(3);
    foreach ($rows as $i => $row) {
        expect($restored[$i]->testo)->toBe($row['testo'])
            ->and($restored[$i]->dati)->toBe($row['dati'])
            ->and($restored[$i]->importo)->toBe($row['importo']);
    }
    expect(DB::table('users')->count())->toBe(1)
        ->and(Schema::hasTable('tabella_nuova'))->toBeFalse();
});

it('rifiuta una copia incompleta', function () {
    $backup = app(DatabaseBackup::class);
    $file = $backup->create('prova');

    $lines = gzfile($file);
    array_pop($lines);
    array_pop($lines);
    $gz = gzopen($file, 'wb');
    gzwrite($gz, implode('', $lines));
    gzclose($gz);

    expect(fn () => $backup->restore($file))->toThrow(RuntimeException::class, 'incompleta');
    expect(DB::table('users')->count())->toBe(1);
});

it('conserva solo le ultime copie', function () {
    config(['manutrack.backups.keep' => 2]);
    $backup = app(DatabaseBackup::class);
    $dir = $backup->directory();

    foreach (['20260101-100000', '20260102-100000', '20260103-100000', '20260104-100000'] as $stamp) {
        file_put_contents($dir."/manutrack-{$stamp}-x.sql.gz", gzencode("-- fine\n"));
    }

    expect($backup->prune())->toBe(2)
        ->and(array_column($backup->list(), 'name'))->toBe([
            'manutrack-20260104-100000-x.sql.gz',
            'manutrack-20260103-100000-x.sql.gz',
        ]);
});

it('protegge la cartella delle copie dal web', function () {
    $dir = app(DatabaseBackup::class)->directory();

    expect(file_get_contents($dir.'/.htaccess'))->toContain('Require all denied');
});

it('ripristina con il comando e segna come fallito un aggiornamento rimasto a metà', function () {
    DB::table('prova_copie')->insert(['testo' => 'prima']);
    DB::table('system_versions')->insert(['version' => '9.9.9', 'status' => 'running', 'trigger' => 'auto', 'started_at' => now()]);
    $file = app(DatabaseBackup::class)->create('comando');
    DB::table('prova_copie')->update(['testo' => 'dopo']);

    $this->artisan('manutrack:restore', ['file' => basename($file), '--force' => true])->assertSuccessful();

    expect(DB::table('prova_copie')->value('testo'))->toBe('prima')
        ->and(DB::table('system_versions')->where('version', '9.9.9')->value('status'))->toBe('failed')
        ->and(app()->isDownForMaintenance())->toBeFalse();
});
