<?php

namespace App\Support\Setup;

use RuntimeException;

/**
 * Modifica in modo sicuro le righe del file .env (scrittura atomica).
 */
class EnvWriter
{
    public function __construct(private ?string $path = null)
    {
        $this->path ??= app()->environmentFilePath();
    }

    public function isWritable(): bool
    {
        return is_file($this->path) ? is_writable($this->path) : is_writable(dirname($this->path));
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function set(array $values): void
    {
        if (! $this->isWritable()) {
            throw new RuntimeException(__('install.env_not_writable'));
        }

        $content = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->format($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = (string) preg_replace_callback($pattern, fn () => $line, $content);
            } else {
                $content = rtrim($content, "\n")."\n".$line."\n";
            }
        }

        $tmp = $this->path.'.tmp-'.bin2hex(random_bytes(4));
        file_put_contents($tmp, $content);
        @chmod($tmp, 0640);
        rename($tmp, $this->path);
    }

    private function format(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/[\s#"\'$\\\\]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
