<?php

/**
 * Crea il pacchetto di rilascio da una cartella già preparata
 * (dipendenze di produzione installate e frontend compilato).
 *
 *   php scripts/release/package.php <cartella-build> <cartella-dist> [--edition=cliente|demo]
 *
 * Due edizioni, mai mischiate:
 * - cliente (predefinita): senza seeder, factory né tracce dei dati dimostrativi;
 * - demo: con i seeder, file manutrack-demo-<versione>.zip, "edition": "demo"
 *   in release.json. Si installa solo con "php artisan manutrack:demo".
 *
 * - scrive release.json con versione, data ed elenco dei file con la dimensione;
 * - crea dist/manutrack-<versione>.zip con release.json come ULTIMO file;
 * - scrive il file .sha256 accanto allo zip.
 *
 * Il pacchetto non contiene .env né dati in storage/: si può estrarre sopra
 * un'installazione esistente senza toccare configurazione e file caricati.
 */
if ($argc < 3) {
    fwrite(STDERR, "Uso: php scripts/release/package.php <cartella-build> <cartella-dist>\n");
    exit(2);
}

$build = rtrim(realpath($argv[1]) ?: '', DIRECTORY_SEPARATOR);
$dist = $argv[2];
$edition = 'cliente';
foreach (array_slice($argv, 3) as $arg) {
    if (preg_match('/^--edition=(cliente|demo)$/', $arg, $m)) {
        $edition = $m[1];
    } else {
        fwrite(STDERR, "Opzione sconosciuta: {$arg}\n");
        exit(2);
    }
}

if ($build === '' || ! is_file($build.'/VERSION') || ! is_file($build.'/artisan')) {
    fwrite(STDERR, "Cartella di build non valida: {$argv[1]}\n");
    exit(2);
}
if (! class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Serve l'estensione PHP zip.\n");
    exit(2);
}

$version = trim((string) file_get_contents($build.'/VERSION'));
if (! preg_match('/^\d+\.\d+\.\d+([.-][0-9A-Za-z.-]+)?$/', $version)) {
    fwrite(STDERR, "VERSION non valida: {$version}\n");
    exit(2);
}

// Cose che non devono mai finire nel pacchetto: configurazione, dati e
// dati dimostrativi (seeder, factory, database di prova, test)
$forbidden = ['.env', 'public/hot', 'auth.json', 'database/factories', 'database/database.sqlite', 'tests'];
if ($edition === 'cliente') {
    $forbidden[] = 'database/seeders';
} elseif (! is_file($build.'/database/seeders/DatabaseSeeder.php')) {
    fwrite(STDERR, "Il pacchetto demo deve contenere database/seeders: interrotto.\n");
    exit(1);
}
foreach ($forbidden as $f) {
    if (file_exists($build.'/'.$f)) {
        fwrite(STDERR, "Il pacchetto conterrebbe {$f}: interrotto.\n");
        exit(1);
    }
}

// Nessuna traccia dei dati dimostrativi nel codice dell'app: la password
// degli account di prova e i nomi dei seeder
$demoMarkers = $edition === 'demo' ? [] : ['manutrack-demo', 'DemoAssetsSeeder', 'DemoMaintenanceSeeder', 'DemoInventorySeeder', 'DemoPreventiveSeeder', 'DemoHistorySeeder', 'DemoPlantSeeder'];
foreach (['app', 'bootstrap', 'config', 'database', 'lang', 'public', 'resources', 'routes'] as $dir) {
    if (! is_dir($build.'/'.$dir)) {
        continue;
    }
    $scan = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($build.'/'.$dir, FilesystemIterator::SKIP_DOTS));
    foreach ($scan as $file) {
        /** @var SplFileInfo $file */
        if (! $file->isFile() || $file->getSize() > 5_000_000) {
            continue;
        }
        $content = (string) file_get_contents($file->getPathname());
        foreach ($demoMarkers as $marker) {
            // La password di prova ("manutrack-demo"), non il nome del pacchetto demo ("manutrack-demo-<versione>.zip")
            $pattern = '/'.preg_quote($marker, '/').($marker === 'manutrack-demo' ? '(?!-)' : '').'/';
            if (preg_match($pattern, $content) === 1) {
                fwrite(STDERR, "Il pacchetto conterrebbe dati dimostrativi ({$marker}) in ".substr($file->getPathname(), strlen($build) + 1).": interrotto.\n");
                exit(1);
            }
        }
    }
}
if ($edition === 'cliente' && preg_match_all('/\\\\?Database\\\\+(Seeders|Factories)\\\\/', (string) @file_get_contents($build.'/vendor/composer/autoload_classmap.php'), $m)) {
    fwrite(STDERR, "L'autoload del pacchetto contiene ancora seeder o factory: interrotto.\n");
    exit(1);
}

// File elencati in release.json: tutto tranne storage/ e bootstrap/cache/,
// che sul server cambiano da soli.
$files = [];
$all = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($build, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
    /** @var SplFileInfo $file */
    if (! $file->isFile()) {
        continue;
    }
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($build) + 1));
    if ($rel === 'release.json' || preg_match('#(^|/)\.git/#', $rel)) {
        continue;
    }
    $all[] = $rel;
    if (str_starts_with($rel, 'storage/') || str_starts_with($rel, 'bootstrap/cache/')) {
        continue;
    }
    $files[$rel] = $file->getSize();
}
ksort($files);
sort($all);

$manifest = [
    'name' => 'ManuTrack',
    'version' => $version,
    'edition' => $edition,
    'built_at' => gmdate('c'),
    'php' => '>=8.3',
    'count' => count($files),
    'files' => $files,
];
file_put_contents($build.'/release.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

if (! is_dir($dist)) {
    mkdir($dist, 0775, true);
}
$zipPath = rtrim($dist, '/\\').DIRECTORY_SEPARATOR.($edition === 'demo' ? "manutrack-demo-{$version}.zip" : "manutrack-{$version}.zip");
@unlink($zipPath);

$zip = new ZipArchive;
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "Impossibile creare {$zipPath}\n");
    exit(1);
}
foreach ($all as $rel) {
    $zip->addFile($build.'/'.$rel, $rel);
}
// Il marcatore va per ultimo: chi estrae in ordine lo scrive alla fine
$zip->addFile($build.'/release.json', 'release.json');
$zip->close();

$hash = hash_file('sha256', $zipPath);
file_put_contents($zipPath.'.sha256', $hash.'  '.basename($zipPath)."\n");

printf("Pacchetto: %s\nFile: %d (%d nel controllo di completezza)\nDimensione: %.1f MB\nSHA-256: %s\n",
    $zipPath, count($all) + 1, count($files), filesize($zipPath) / 1048576, $hash);
