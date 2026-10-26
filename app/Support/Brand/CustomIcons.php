<?php

namespace App\Support\Brand;

use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Genera le icone dell'app per la palette personalizzata dell'azienda
 * (in storage, servite da una rotta). Senza GD si usano quelle della
 * preimpostazione più vicina.
 */
class CustomIcons
{
    /** nome file => [lato, tipo] */
    public const FILES = [
        'icon-192.png' => [192, 'any'],
        'icon-512.png' => [512, 'any'],
        'maskable-192.png' => [192, 'maskable'],
        'maskable-512.png' => [512, 'maskable'],
        'apple-touch-icon.png' => [180, 'any'],
        'favicon-32.png' => [32, 'favicon'],
    ];

    public function __construct(private IconRenderer $renderer) {}

    /**
     * @param  array<string, string>  $palette
     * @return string|null versione generata, oppure null se GD non è disponibile
     */
    public function generate(array $palette): ?string
    {
        if (! function_exists('imagecreatetruecolor')) {
            settings()->forget(Brand::CUSTOM_ICONS_SETTING);

            return null;
        }

        $version = substr(sha1((string) json_encode($palette)), 0, 10);
        $disk = Storage::disk('local');

        try {
            foreach (self::FILES as $file => [$size, $kind]) {
                $disk->put("branding/icons/{$version}/{$file}", $this->renderer->png($palette, $size, $kind));
            }
            $disk->put("branding/icons/{$version}/favicon.svg", $this->renderer->svgFavicon($palette));
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        // Le versioni precedenti non servono più
        foreach ($disk->directories('branding/icons') as $dir) {
            if (basename($dir) !== $version) {
                $disk->deleteDirectory($dir);
            }
        }

        settings()->set(Brand::CUSTOM_ICONS_SETTING, $version);

        return $version;
    }
}
