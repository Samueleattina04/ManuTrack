<?php

namespace App\Support\Brand;

use RuntimeException;

/**
 * Disegna in PHP (GD) le icone dell'app a partire dalla geometria della M:
 * nessun bisogno di Node o di convertitori SVG sul server.
 *
 * Una maschera a risoluzione maggiore dice a quale faccia appartiene ogni punto;
 * il colore di ogni punto (sfondo, alone, facce, sfumature, ombra) è calcolato
 * esattamente e poi mediato, così bordi e sfumature risultano morbidi.
 */
class IconRenderer
{
    private const BG_TOP = [0x17, 0x1C, 0x24];

    private const BG_BOTTOM = [0x0B, 0x0E, 0x12];

    private const FAVICON_BG = [0x10, 0x14, 0x1A];

    private const SHADOW = 1;

    private const LIGHT = 2;

    private const DARK = 3;

    /**
     * @param  array<string, string>  $palette  voce di config('manutrack.palettes')
     * @param  string  $kind  any|maskable|favicon|mark (mark = solo la M su fondo trasparente)
     */
    public function png(array $palette, int $size, string $kind = 'any'): string
    {
        if (! function_exists('imagecreatetruecolor')) {
            throw new RuntimeException('Estensione GD non disponibile.');
        }

        $ss = $size <= 64 ? 4 : 2;
        $s = $size * $ss;
        $favicon = $kind === 'favicon';
        $markOnly = $kind === 'mark';

        // Scala e posizione della M (unità del logo → pixel della maschera)
        if ($markOnly) {
            // La M con la sua ombra (496×467 unità) occupa tutto il riquadro
            $scale = $s * 0.98 / 496;
        } elseif ($favicon) {
            $scale = $s * 0.8 / Brand::MARK_WIDTH;
        } else {
            // "maskable": la M resta dentro il cerchio sicuro (80% del lato)
            $scale = $kind === 'maskable' ? $s * 0.40 / 277 : $s * 0.60 / Brand::MARK_WIDTH;
        }
        $shadow = ! $favicon;
        $ox = $markOnly ? ($s - 496 * $scale) / 2 : ($s - Brand::MARK_WIDTH * $scale) / 2 - ($shadow ? 6 * $scale : 0);
        $oy = $markOnly ? ($s - 467 * $scale) / 2 : ($s - Brand::MARK_HEIGHT * $scale) / 2 - ($shadow ? 6 * $scale : 0);
        $tr = fn (array $pts) => array_map(fn ($p) => [$ox + $p[0] * $scale, $oy + $p[1] * $scale], $pts);

        $polys = [self::LIGHT => $tr(Brand::FACE_LIGHT), self::DARK => $tr(Brand::FACE_DARK)];
        if ($shadow) {
            $polys[self::SHADOW] = $tr(Brand::SHADOW);
        }

        $mask = imagecreatetruecolor($s, $s);
        foreach ([self::SHADOW, self::LIGHT, self::DARK] as $label) {
            if (isset($polys[$label])) {
                $flat = [];
                foreach ($polys[$label] as $p) {
                    $flat[] = (int) round($p[0]);
                    $flat[] = (int) round($p[1]);
                }
                imagefilledpolygon($mask, $flat, imagecolorallocate($mask, $label, 0, 0));
            }
        }

        // Sfumature: [poligono, direzione, colore, opacità iniziale, fine (0..1)]
        $grads = [
            self::SHADOW => [[$polys[self::SHADOW] ?? [], [1, 1], [0, 0, 0], 0.6, 1.0]],
            self::LIGHT => [
                [$polys[self::LIGHT], [0.35, 0.75], [0, 0, 0], 0.5, 0.55],
                [$polys[self::LIGHT], [-0.8, 0.8], [255, 255, 255], 0.16, 0.5],
            ],
            self::DARK => [[$polys[self::DARK], [-0.8, 0.9], [255, 255, 255], 0.09, 0.7]],
        ];
        $prepared = [];
        foreach ($grads as $label => $list) {
            foreach ($list as $g) {
                if ($g[0] !== []) {
                    $prepared[$label][] = $this->prepare(...$g);
                }
            }
        }

        $light = $this->rgb($palette['logo_light_d']);
        $dark = $this->rgb($palette['logo_dark_d']);
        $glow = $this->rgb($palette['logo_light_d']);

        $out = imagecreatetruecolor($size, $size);
        imagealphablending($out, false);
        imagesavealpha($out, true);

        $n = $ss * $ss;
        $radius = $s * 0.2;
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                $r = $g = $b = $a = 0.0;
                for ($j = 0; $j < $ss; $j++) {
                    for ($i = 0; $i < $ss; $i++) {
                        $px = $x * $ss + $i;
                        $py = $y * $ss + $j;
                        $fx = $px + 0.5;
                        $fy = $py + 0.5;

                        $label = (imagecolorat($mask, $px, $py) >> 16) & 0xFF;

                        // Solo la M: fuori dalla forma è trasparente, l'ombra è nero semitrasparente
                        if ($markOnly) {
                            if ($label === 0) {
                                continue;
                            }
                            if ($label === self::SHADOW) {
                                $a += $this->gradientAlpha($prepared[self::SHADOW][0], $fx, $fy);

                                continue;
                            }
                        }

                        // Sfondo
                        if ($markOnly) {
                            $c = [0, 0, 0];
                        } elseif ($favicon) {
                            if (! $this->inRoundedSquare($fx, $fy, $s, $radius)) {
                                continue;
                            }
                            $c = self::FAVICON_BG;
                        } else {
                            $c = $this->background($fx, $fy, $s, $glow);
                        }

                        // Forma
                        if ($label === self::LIGHT) {
                            $c = $light;
                        } elseif ($label === self::DARK) {
                            $c = $dark;
                        }
                        foreach ($prepared[$label] ?? [] as $grad) {
                            $c = $this->applyGradient($c, $grad, $fx, $fy);
                        }

                        $r += $c[0];
                        $g += $c[1];
                        $b += $c[2];
                        $a += 1;
                    }
                }

                if ($a <= 0.0) {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 0, 0, 0, 127));

                    continue;
                }

                $alpha = (int) round(127 * (1 - min(1.0, $a / $n)));
                imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, (int) round($r / $a), (int) round($g / $a), (int) round($b / $a), $alpha));
            }
        }

        ob_start();
        imagepng($out, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * Favicon vettoriale con colori fissi (il browser non conosce le variabili CSS della pagina).
     *
     * @param  array<string, string>  $palette
     */
    public function svgFavicon(array $palette): string
    {
        $scale = 0.8 * 64 / Brand::MARK_WIDTH;
        $tx = (64 - Brand::MARK_WIDTH * $scale) / 2;
        $ty = (64 - Brand::MARK_HEIGHT * $scale) / 2;

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" rx="13" fill="#10141a"/>'
            .'<g transform="translate('.round($tx, 2).' '.round($ty, 2).') scale('.round($scale, 5).')">'
            .'<polygon points="'.Brand::points(Brand::FACE_LIGHT).'" fill="'.$palette['logo_light_d'].'"/>'
            .'<polygon points="'.Brand::points(Brand::FACE_DARK).'" fill="'.$palette['logo_dark_d'].'"/>'
            .'</g></svg>'."\n";
    }

    /**
     * Sfondo: sfumatura verticale grafite e alone del colore del logo dietro la M.
     *
     * @param  array{0: int, 1: int, 2: int}  $glow
     * @return array{0: float, 1: float, 2: float}
     */
    private function background(float $x, float $y, int $s, array $glow): array
    {
        $t = $y / $s;
        $c = [
            self::BG_TOP[0] + (self::BG_BOTTOM[0] - self::BG_TOP[0]) * $t,
            self::BG_TOP[1] + (self::BG_BOTTOM[1] - self::BG_TOP[1]) * $t,
            self::BG_TOP[2] + (self::BG_BOTTOM[2] - self::BG_TOP[2]) * $t,
        ];

        $d = sqrt(($x - $s * 0.5) ** 2 + ($y - $s * 0.42) ** 2) / ($s * 0.55);
        $k = 0.26 * max(0.0, 1 - $d) ** 2;

        return [
            $c[0] + ($glow[0] - $c[0]) * $k,
            $c[1] + ($glow[1] - $c[1]) * $k,
            $c[2] + ($glow[2] - $c[2]) * $k,
        ];
    }

    /**
     * Prepara una sfumatura lineare nel riquadro della forma (come gli SVG con objectBoundingBox).
     *
     * @param  list<array{0: float, 1: float}>  $poly
     * @param  array{0: float, 1: float}  $dir
     * @param  array{0: int, 1: int, 2: int}  $color
     * @return array{sx: float, sy: float, vx: float, vy: float, len2: float, color: array{0: int, 1: int, 2: int}, from: float, end: float}
     */
    private function prepare(array $poly, array $dir, array $color, float $from, float $end): array
    {
        $xs = array_column($poly, 0);
        $ys = array_column($poly, 1);
        $minX = min($xs);
        $minY = min($ys);
        $w = max($xs) - $minX;
        $h = max($ys) - $minY;
        $vx = $dir[0] * $w;
        $vy = $dir[1] * $h;

        return [
            'sx' => $vx >= 0 ? $minX : $minX + $w,
            'sy' => $vy >= 0 ? $minY : $minY + $h,
            'vx' => $vx,
            'vy' => $vy,
            'len2' => max(1e-6, $vx * $vx + $vy * $vy),
            'color' => $color,
            'from' => $from,
            'end' => $end,
        ];
    }

    /**
     * @param  array{0: float|int, 1: float|int, 2: float|int}  $c
     * @param  array{sx: float, sy: float, vx: float, vy: float, len2: float, color: array{0: int, 1: int, 2: int}, from: float, end: float}  $g
     * @return array{0: float, 1: float, 2: float}
     */
    private function applyGradient(array $c, array $g, float $x, float $y): array
    {
        $t = (($x - $g['sx']) * $g['vx'] + ($y - $g['sy']) * $g['vy']) / $g['len2'];
        $alpha = $g['from'] * max(0.0, 1 - max(0.0, $t) / $g['end']);
        if ($alpha <= 0) {
            return [(float) $c[0], (float) $c[1], (float) $c[2]];
        }

        return [
            $c[0] + ($g['color'][0] - $c[0]) * $alpha,
            $c[1] + ($g['color'][1] - $c[1]) * $alpha,
            $c[2] + ($g['color'][2] - $c[2]) * $alpha,
        ];
    }

    /**
     * Opacità di una sfumatura in un punto (per l'ombra su fondo trasparente).
     *
     * @param  array{sx: float, sy: float, vx: float, vy: float, len2: float, color: array{0: int, 1: int, 2: int}, from: float, end: float}  $g
     */
    private function gradientAlpha(array $g, float $x, float $y): float
    {
        $t = (($x - $g['sx']) * $g['vx'] + ($y - $g['sy']) * $g['vy']) / $g['len2'];

        return $g['from'] * max(0.0, 1 - max(0.0, $t) / $g['end']);
    }

    private function inRoundedSquare(float $x, float $y, int $s, float $r): bool
    {
        $cx = min(max($x, $r), $s - $r);
        $cy = min(max($y, $r), $s - $r);

        return ($x - $cx) ** 2 + ($y - $cy) ** 2 <= $r * $r;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [(int) hexdec(substr($hex, 0, 2)), (int) hexdec(substr($hex, 2, 2)), (int) hexdec(substr($hex, 4, 2))];
    }
}
