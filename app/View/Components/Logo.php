<?php

namespace App\View\Components;

use App\Support\Brand\Brand;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Logo ManuTrack in SVG con colori da variabili CSS:
 * --logo-faccia-chiara, --logo-faccia-scura, --logo-ombra (opacità), --logo-testo.
 *
 * Varianti: full (M + scritta sotto), mark (solo M), horizontal (M + scritta a destra).
 * Per le versioni monocromatiche basta aggiungere la classe "logo-mono-light" o "logo-mono-dark".
 */
class Logo extends Component
{
    private static int $counter = 0;

    public string $uid;

    public function __construct(
        public string $variant = 'full',
        public bool $shadow = true,
        public ?string $label = 'ManuTrack',
    ) {
        $this->uid = 'mtl'.(++self::$counter);
    }

    public function viewBox(): string
    {
        return match ($this->variant) {
            'mark' => $this->shadow ? '0 0 500 470' : '0 0 406 378',
            'horizontal' => '0 0 1780 470',
            default => '0 0 800 610',
        };
    }

    public function light(): string
    {
        return Brand::points(Brand::FACE_LIGHT);
    }

    public function dark(): string
    {
        return Brand::points(Brand::FACE_DARK);
    }

    public function shadowPoints(): string
    {
        return Brand::points(Brand::SHADOW);
    }

    /**
     * Trasformazione della M e della scritta per la variante scelta.
     *
     * @return array{mark: string, word: string|null}
     */
    public function layout(): array
    {
        $box = Brand::wordmark()['box'] ?? ['left' => -184, 'right' => 596, 'top' => 477, 'baseline' => 584];

        return match ($this->variant) {
            'mark' => ['mark' => '', 'word' => null],
            // Scritta a destra della M, alta come metà della M, centrata in verticale
            'horizontal' => (function () use ($box) {
                $scale = 170 / ($box['baseline'] - $box['top']);
                $tx = 540 - $box['left'] * $scale;
                $ty = 189 + 85 - $box['baseline'] * $scale;

                return ['mark' => '', 'word' => 'translate('.round($tx, 2).' '.round($ty, 2).') scale('.round($scale, 4).')'];
            })(),
            default => ['mark' => 'translate(197 0)', 'word' => 'translate(197 0)'],
        };
    }

    public function wordmark(): ?string
    {
        return Brand::wordmark()['d'] ?? null;
    }

    public function render(): View
    {
        return view('components.logo');
    }
}
