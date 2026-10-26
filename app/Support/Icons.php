<?php

namespace App\Support;

/**
 * Raccolta di icone SVG (Lucide) in resources/icons/lucide.php.
 */
class Icons
{
    /** @var array<string, string>|null */
    private static ?array $icons = null;

    public static function inner(string $name): string
    {
        self::$icons ??= require resource_path('icons/lucide.php');

        return self::$icons[$name] ?? self::$icons['info'];
    }

    public static function has(string $name): bool
    {
        self::$icons ??= require resource_path('icons/lucide.php');

        return isset(self::$icons[$name]);
    }
}
