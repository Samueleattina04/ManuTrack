<?php

namespace App\Support\Setup;

use App\Support\Files\PhotoProcessor;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Controlli dei requisiti del server, mostrati in installazione e nella pagina Sistema.
 */
class Requirements
{
    public const OK = 'ok';

    public const WARNING = 'warning';

    public const ERROR = 'error';

    /** Estensioni indispensabili */
    private const REQUIRED_EXTENSIONS = [
        'pdo_mysql', 'mbstring', 'openssl', 'curl', 'fileinfo', 'tokenizer', 'xml', 'ctype', 'json', 'filter',
    ];

    /** Estensioni consigliate, con il motivo */
    private const RECOMMENDED_EXTENSIONS = [
        'zip' => 'esportazioni GDPR in archivio ZIP',
        'intl' => 'formattazione di date e numeri in italiano',
        'bcmath' => 'velocizza la cifratura delle notifiche push (in alternativa gmp; funzionano anche senza)',
    ];

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function checks(bool $withDatabase = true): array
    {
        $checks = [];

        $checks[] = $this->check(
            'php',
            __('install.req.php', ['version' => '8.3']),
            version_compare(PHP_VERSION, '8.3.0', '>=') ? self::OK : self::ERROR,
            PHP_VERSION,
        );

        foreach (self::REQUIRED_EXTENSIONS as $ext) {
            $checks[] = $this->check(
                'ext-'.$ext,
                __('install.req.extension', ['name' => $ext]),
                extension_loaded($ext) ? self::OK : self::ERROR,
                extension_loaded($ext) ? __('install.req.present') : __('install.req.missing'),
            );
        }

        foreach (self::RECOMMENDED_EXTENSIONS as $ext => $why) {
            $present = extension_loaded($ext) || ($ext === 'bcmath' && extension_loaded('gmp'));
            $checks[] = $this->check(
                'ext-'.$ext,
                __('install.req.extension', ['name' => $ext]),
                $present ? self::OK : self::WARNING,
                $present ? __('install.req.present') : __('install.req.recommended_for', ['why' => $why]),
            );
        }

        $image = self::imageDriver();
        $checks[] = $this->check(
            'image',
            __('install.req.image'),
            $image !== null ? self::OK : self::WARNING,
            $image !== null ? strtoupper($image) : __('install.req.image_fallback'),
        );

        // Formato delle foto: WebP se la libreria lo sa scrivere, altrimenti JPEG ridotto
        if ($image !== null) {
            $webp = PhotoProcessor::outputFormat() === 'webp';
            $checks[] = $this->check(
                'image-webp',
                __('install.req.webp'),
                $webp ? self::OK : self::WARNING,
                $webp ? __('install.req.yes') : __('install.req.webp_fallback'),
            );
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $checks[] = $this->check(
                'writable-'.$label,
                __('install.req.writable', ['path' => $label]),
                is_writable($path) ? self::OK : self::ERROR,
                is_writable($path) ? __('install.req.yes') : __('install.req.not_writable'),
            );
        }

        $env = base_path('.env');
        $envWritable = is_file($env) ? is_writable($env) : is_writable(base_path());
        $checks[] = $this->check(
            'writable-env',
            __('install.req.writable_file', ['path' => '.env']),
            $envWritable ? self::OK : self::WARNING,
            $envWritable ? __('install.req.yes') : __('install.req.env_manual'),
        );

        // Indirizzo dell'app: da qui nascono i link nelle email e nei QR
        $checks[] = AppUrlCheck::requirement(app()->runningInConsole() ? null : request());

        if ($withDatabase) {
            $checks[] = $this->databaseCheck();
        }

        return $checks;
    }

    public function passes(bool $withDatabase = true): bool
    {
        foreach ($this->checks($withDatabase) as $check) {
            if ($check['status'] === self::ERROR) {
                return false;
            }
        }

        return true;
    }

    /**
     * Driver disponibile per le immagini: "imagick", "gd" oppure null.
     */
    public static function imageDriver(): ?string
    {
        if (extension_loaded('imagick') && class_exists(\Imagick::class)) {
            return 'imagick';
        }

        if (extension_loaded('gd') && function_exists('imagecreatetruecolor')) {
            return 'gd';
        }

        return null;
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    public function databaseCheck(): array
    {
        try {
            $version = (string) (DB::connection()->selectOne('select version() as v')->v ?? '');
            $driver = DB::connection()->getDriverName();
            $ok = in_array($driver, ['mysql', 'mariadb'], true);

            return $this->check('database', __('install.req.database'), $ok ? self::OK : self::WARNING, $driver.' '.$version);
        } catch (Throwable $e) {
            return $this->check('database', __('install.req.database'), self::ERROR, __('install.req.db_error', ['message' => mb_strimwidth($e->getMessage(), 0, 160, '…')]));
        }
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function check(string $key, string $label, string $status, string $detail): array
    {
        return compact('key', 'label', 'status', 'detail');
    }
}
