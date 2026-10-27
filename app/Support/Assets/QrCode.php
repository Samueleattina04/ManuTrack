<?php

namespace App\Support\Assets;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Codice QR in SVG: un solo tracciato, nitido a qualunque dimensione di stampa,
 * senza bisogno di GD o Imagick.
 */
final class QrCode
{
    /**
     * @param  int  $quiet  margine bianco in moduli (lo standard ne chiede 4, 3 bastano ai telefoni)
     */
    public static function svg(string $data, int $quiet = 3, ?string $title = null): string
    {
        $matrix = Encoder::encode($data, ErrorCorrectionLevel::M(), 'UTF-8')->getMatrix();
        $size = $matrix->getWidth();
        $path = '';

        for ($y = 0; $y < $size; $y++) {
            $x = 0;
            while ($x < $size) {
                if ($matrix->get($x, $y) !== 1) {
                    $x++;

                    continue;
                }
                $start = $x;
                while ($x < $size && $matrix->get($x, $y) === 1) {
                    $x++;
                }
                $path .= 'M'.($start + $quiet).' '.($y + $quiet).'h'.($x - $start).'v1h-'.($x - $start).'z';
            }
        }

        $box = $size + 2 * $quiet;
        $titleTag = $title !== null ? '<title>'.htmlspecialchars($title, ENT_XML1).'</title>' : '';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$box.' '.$box.'" shape-rendering="crispEdges" role="img">'
            .$titleTag.'<rect width="'.$box.'" height="'.$box.'" fill="#fff"/><path fill="#000" d="'.$path.'"/></svg>';
    }
}
