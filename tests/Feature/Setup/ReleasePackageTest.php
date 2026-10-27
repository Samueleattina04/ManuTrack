<?php

/*
 * Il pacchetto di rilascio non deve contenere dati dimostrativi:
 * scripts/release/package.php si rifiuta di crearlo se trova seeder,
 * factory, test o tracce degli account di prova.
 */

function fakeBuild(string $root, array $files): string
{
    $build = $root.'/build';
    $files += ['VERSION' => "9.9.9\n", 'artisan' => "<?php\n", 'app/Example.php' => "<?php\n"];
    foreach ($files as $path => $content) {
        @mkdir(dirname($build.'/'.$path), 0775, true);
        file_put_contents($build.'/'.$path, $content);
    }

    return $build;
}

function runPackager(string $build, string $dist, string $edition = 'cliente'): array
{
    $proc = proc_open([PHP_BINARY, base_path('scripts/release/package.php'), $build, $dist, '--edition='.$edition], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);

    return [proc_close($proc), $out];
}

it('crea il pacchetto da una build pulita', function () {
    [$code] = runPackager(fakeBuild($this->sandbox, []), $this->sandbox.'/dist');
    expect($code)->toBe(0)->and(file_exists($this->sandbox.'/dist/manutrack-9.9.9.zip'))->toBeTrue();
});

it('si rifiuta se la build contiene dati dimostrativi', function (array $files, string $message) {
    [$code, $out] = runPackager(fakeBuild($this->sandbox, $files), $this->sandbox.'/dist');
    expect($code)->toBe(1)->and($out)->toContain($message)
        ->and(file_exists($this->sandbox.'/dist/manutrack-9.9.9.zip'))->toBeFalse();
})->with([
    'seeder' => [['database/seeders/DemoInventorySeeder.php' => "<?php\n"], 'database/seeders'],
    'factory' => [['database/factories/UserFactory.php' => "<?php\n"], 'database/factories'],
    'test' => [['tests/Pest.php' => "<?php\n"], 'tests'],
    'password demo nel codice' => [['config/demo.php' => "<?php return ['password' => 'manutrack-demo'];\n"], 'manutrack-demo'],
    'seeder nell\'autoload' => [['vendor/composer/autoload_classmap.php' => "<?php return ['Database\\\\Seeders\\\\DemoAssetsSeeder' => 'x'];\n"], 'autoload'],
]);

it('il pacchetto demo è separato: nome, edizione in release.json e seeder obbligatori', function () {
    $build = fakeBuild($this->sandbox, ['database/seeders/DatabaseSeeder.php' => "<?php // manutrack-demo\n"]);
    [$code] = runPackager($build, $this->sandbox.'/dist', 'demo');

    expect($code)->toBe(0)
        ->and(file_exists($this->sandbox.'/dist/manutrack-demo-9.9.9.zip'))->toBeTrue()
        ->and(file_exists($this->sandbox.'/dist/manutrack-9.9.9.zip'))->toBeFalse()
        ->and(json_decode((string) file_get_contents($build.'/release.json'), true)['edition'])->toBe('demo');

    // Il pacchetto cliente scrive la sua edizione
    [$code] = runPackager(fakeBuild($this->sandbox.'/c', []), $this->sandbox.'/dist');
    expect($code)->toBe(0)->and(json_decode((string) file_get_contents($this->sandbox.'/c/build/release.json'), true)['edition'])->toBe('cliente');
});

it('il pacchetto demo senza seeder non si crea', function () {
    [$code, $out] = runPackager(fakeBuild($this->sandbox, []), $this->sandbox.'/dist', 'demo');
    expect($code)->toBe(1)->and($out)->toContain('database/seeders');
});
