<?php

namespace App\Support\Update;

/**
 * Legge e verifica release.json, il file marcatore scritto per ultimo dallo script
 * di rilascio. Contiene la versione e l'elenco dei file con la loro dimensione:
 * se anche un solo file manca o ha dimensione diversa, il caricamento non è completo.
 */
class ReleaseManifest
{
    public const FILE = 'release.json';

    /**
     * @param  array{version?: string, edition?: string, built_at?: string, files?: array<string, int>}  $data
     */
    public function __construct(
        private array $data,
        private string $basePath,
        private int $markerMtime,
    ) {}

    public static function load(?string $basePath = null): ?self
    {
        $basePath ??= base_path();
        $file = $basePath.DIRECTORY_SEPARATOR.self::FILE;

        if (! is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);
        if (! is_array($data)) {
            return null;
        }

        return new self($data, $basePath, (int) filemtime($file));
    }

    public function version(): ?string
    {
        return $this->data['version'] ?? null;
    }

    /** "cliente" o "demo" (i pacchetti precedenti alla 0.8.0 non lo scrivevano: cliente) */
    public function edition(): string
    {
        return ($this->data['edition'] ?? 'cliente') === 'demo' ? 'demo' : 'cliente';
    }

    public function builtAt(): ?string
    {
        return $this->data['built_at'] ?? null;
    }

    /**
     * Verifica i file elencati. Restituisce l'elenco dei problemi (vuoto = completo)
     * e l'ora di modifica più recente trovata.
     *
     * @return array{problems: list<string>, newest_mtime: int, checked: int}
     */
    public function verify(int $maxProblems = 20): array
    {
        $problems = [];
        $newest = $this->markerMtime;
        $files = $this->data['files'] ?? [];

        if ($files === []) {
            $problems[] = 'release.json non contiene l\'elenco dei file';
        }

        foreach ($files as $relative => $size) {
            $path = $this->basePath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            $stat = @stat($path);

            if ($stat === false) {
                $problems[] = 'manca '.$relative;
            } elseif ((int) $stat['size'] !== (int) $size) {
                $problems[] = 'dimensione diversa: '.$relative;
            } else {
                $newest = max($newest, (int) $stat['mtime']);
            }

            if (count($problems) >= $maxProblems) {
                break;
            }
        }

        return ['problems' => $problems, 'newest_mtime' => $newest, 'checked' => count($files)];
    }
}
