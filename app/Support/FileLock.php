<?php

namespace App\Support;

/**
 * Blocco esclusivo su file (flock), affidabile anche su hosting condivisi
 * dove non c'è Redis e la cache può essere svuotata durante un aggiornamento.
 */
class FileLock
{
    /** @var resource|null */
    private $handle = null;

    public function __construct(private string $name) {}

    public function path(): string
    {
        return storage_path('framework/'.$this->name.'.lock');
    }

    public function acquire(): bool
    {
        $dir = dirname($this->path());
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($this->path(), 'c');
        if ($handle === false) {
            return false;
        }

        if (! flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return false;
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid().' '.date('c'));
        $this->handle = $handle;

        return true;
    }

    public function release(): void
    {
        if ($this->handle !== null) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
            $this->handle = null;
        }
    }

    public function __destruct()
    {
        $this->release();
    }
}
