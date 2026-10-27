<?php

namespace App\Support\Brand;

/**
 * Schermate di avvio per iPhone (apple-touch-startup-image): fondo grafite del
 * tema scuro con la M della palette al centro. Android le crea da solo dal manifest.
 */
class SplashRenderer
{
    /**
     * Schermi iPhone in verticale: [larghezza px, altezza px, larghezza CSS, altezza CSS, densità].
     *
     * @var list<array{0: int, 1: int, 2: int, 3: int, 4: int}>
     */
    public const DEVICES = [
        [1320, 2868, 440, 956, 3], // iPhone 16 Pro Max
        [1206, 2622, 402, 874, 3], // iPhone 16 Pro
        [1290, 2796, 430, 932, 3], // iPhone 14/15 Pro Max, 15 Plus, 16 Plus
        [1179, 2556, 393, 852, 3], // iPhone 14/15 Pro, 15, 16
        [1284, 2778, 428, 926, 3], // iPhone 12/13 Pro Max, 14 Plus
        [1170, 2532, 390, 844, 3], // iPhone 12/13/14, 12/13 Pro
        [1080, 2340, 360, 780, 3], // iPhone 12/13 mini
        [1125, 2436, 375, 812, 3], // iPhone X, XS, 11 Pro
        [1242, 2688, 414, 896, 3], // iPhone XS Max, 11 Pro Max
        [828, 1792, 414, 896, 2],  // iPhone XR, 11
        [750, 1334, 375, 667, 2],  // iPhone 6/7/8, SE (2ª e 3ª gen.)
    ];

    private const BACKGROUND = [0x0C, 0x0F, 0x14];

    public function __construct(private IconRenderer $icons) {}

    public static function fileName(int $width, int $height): string
    {
        return "splash-{$width}x{$height}.png";
    }

    public static function media(int $cssWidth, int $cssHeight, int $ratio): string
    {
        return "(device-width: {$cssWidth}px) and (device-height: {$cssHeight}px) and (-webkit-device-pixel-ratio: {$ratio}) and (orientation: portrait)";
    }

    /**
     * @param  array<string, string>  $palette
     */
    public function png(array $palette, int $width, int $height): string
    {
        $markSize = (int) round($width * 0.34);
        $mark = imagecreatefromstring($this->icons->png($palette, $markSize, 'mark'));

        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, ...self::BACKGROUND));
        imagealphablending($img, true);
        imagecopy($img, $mark, (int) (($width - $markSize) / 2), (int) (($height - $markSize) / 2 - $height * 0.03), 0, 0, $markSize, $markSize);

        ob_start();
        imagepng($img, null, 9);

        return (string) ob_get_clean();
    }
}
