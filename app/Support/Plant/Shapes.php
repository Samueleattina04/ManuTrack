<?php

namespace App\Support\Plant;

/**
 * Forme generiche delle macchine nella vista 3D, per categoria (asset_categories.shape),
 * con l'ingombro predefinito in metri: larghezza, profondità, altezza.
 * Le stesse forme sono disegnate in resources/js/three/shapes.js.
 */
final class Shapes
{
    /** @var array<string, array{0: float, 1: float, 2: float}> */
    public const SIZES = [
        'box' => [2.0, 2.0, 1.6],
        'cylinder' => [1.6, 1.6, 2.0],
        'press' => [2.4, 2.0, 3.0],
        'conveyor' => [6.0, 1.0, 0.9],
        'tank' => [2.2, 2.2, 2.8],
        'robot' => [1.6, 1.6, 2.0],
    ];

    /** @return array{0: float, 1: float, 2: float} */
    public static function size(?string $shape): array
    {
        return self::SIZES[$shape ?? 'box'] ?? self::SIZES['box'];
    }

    public static function normalize(?string $shape): string
    {
        return isset(self::SIZES[$shape ?? '']) ? (string) $shape : 'box';
    }
}
