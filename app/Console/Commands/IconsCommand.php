<?php

namespace App\Console\Commands;

use App\Support\Brand\IconRenderer;
use App\Support\Brand\SplashRenderer;
use App\Support\Setup\Requirements;
use Illuminate\Console\Command;

class IconsCommand extends Command
{
    protected $signature = 'manutrack:icons
        {--palette=* : Palette da generare (predefinito: tutte)}
        {--path= : Cartella di destinazione (predefinito: public/icons)}';

    protected $description = 'Genera favicon, icone dell\'app (anche maskable), apple-touch-icon e schermate di avvio iPhone dal logo';

    /** nome file => [lato, tipo] */
    private const FILES = [
        'icon-192.png' => [192, 'any'],
        'icon-512.png' => [512, 'any'],
        'maskable-192.png' => [192, 'maskable'],
        'maskable-512.png' => [512, 'maskable'],
        'apple-touch-icon.png' => [180, 'any'],
        'favicon-32.png' => [32, 'favicon'],
    ];

    public function handle(IconRenderer $renderer, SplashRenderer $splash): int
    {
        if (Requirements::imageDriver() === null || ! function_exists('imagecreatetruecolor')) {
            $this->components->error(__('install.icons_need_gd'));

            return self::FAILURE;
        }

        $palettes = (array) config('manutrack.palettes');
        $wanted = $this->option('palette') ?: array_keys($palettes);
        $base = $this->option('path') ?: public_path('icons');

        foreach ($wanted as $key) {
            if (! isset($palettes[$key])) {
                $this->components->error(__('install.palette_unknown', ['name' => $key]));

                return self::INVALID;
            }

            $dir = $base.DIRECTORY_SEPARATOR.$key;
            if (! is_dir($dir)) {
                mkdir($dir, 0775, true);
            }

            foreach (self::FILES as $file => [$size, $kind]) {
                file_put_contents($dir.DIRECTORY_SEPARATOR.$file, $renderer->png($palettes[$key], $size, $kind));
            }
            file_put_contents($dir.DIRECTORY_SEPARATOR.'favicon.svg', $renderer->svgFavicon($palettes[$key]));

            // Schermate di avvio per iPhone
            foreach (SplashRenderer::DEVICES as [$w, $h]) {
                file_put_contents($dir.DIRECTORY_SEPARATOR.SplashRenderer::fileName($w, $h), $splash->png($palettes[$key], $w, $h));
            }

            $this->components->task($palettes[$key]['label'].' → '.$dir);
        }

        return self::SUCCESS;
    }
}
