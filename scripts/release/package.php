<?php

/**
 * Crea il pacchetto di rilascio da una cartella già preparata
 * (dipendenze di produzione installate e frontend compilato).
 *
 *   php scripts/release/package.php <cartella-build> <cartella-dist>
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

// Cose che non devono mai finire nel pacchetto
$forbidden = ['.env', 'public/hot', 'auth.json'];
foreach ($forbidden as $f) {
    if (file_exists($build.'/'.$f)) {
        fwrite(STDERR, "Il pacchetto conterrebbe {$f}: interrotto.\n");
        exit(1);
    }
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
    'built_at' => gmdate('c'),
    'php' => '>=8.3',
    'count' => count($files),
    'files' => $files,
];
file_put_contents($build.'/release.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

if (! is_dir($dist)) {
    mkdir($dist, 0775, true);
}
$zipPath = rtrim($dist, '/\\').DIRECTORY_SEPARATOR."manutrack-{$version}.zip";
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
