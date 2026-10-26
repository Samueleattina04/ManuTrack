<?php

namespace App\Support;

use Illuminate\Support\Facades\Route;

/**
 * Voci di navigazione condivise da barra laterale, barra in basso e palette comandi.
 * Le sezioni non ancora disponibili restano visibili ma disattivate ("in arrivo").
 */
class Navigation
{
    /**
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool}>
     */
    public static function main(): array
    {
        $items = [
            ['dashboard', 'gauge', 'home', 'g d', 'cruscotto panoramica'],
            ['work_orders', 'clipboard-list', null, 'g o', 'odl interventi lavori'],
            ['assets', 'factory', null, 'g m', 'macchine impianti'],
            ['plant', 'cube', null, null, 'stabilimento 3d gemello'],
            ['preventive', 'calendar', null, 'g p', 'piani calendario'],
            ['inventory', 'box', null, 'g r', 'ricambi magazzino'],
            ['notifications', 'bell', null, 'g n', 'avvisi'],
            ['system', 'server', 'system', 'g s', 'sistema versione aggiornamento copie'],
            ['design', 'palette', 'design', null, 'design system stile componenti'],
        ];

        return array_map(fn (array $i) => self::item(...$i), $items);
    }

    /**
     * Voci della barra in basso del telefono (la centrale è "Segnala").
     *
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool}>
     */
    public static function mobile(): array
    {
        return [
            self::item('dashboard', 'home', 'home', short: true),
            self::item('work_orders', 'clipboard-list', null, short: true),
            self::item('report', 'plus', null),
            self::item('notifications', 'bell', null, short: true),
            self::item('system', 'server', 'system', short: true),
        ];
    }

    /**
     * @return array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool}
     */
    private static function item(string $key, string $icon, ?string $route, ?string $shortcut = null, string $keywords = '', bool $short = false): array
    {
        $exists = $route !== null && Route::has($route);

        return [
            'key' => $key,
            'label' => __(($short ? 'app.nav_short.' : 'app.nav.').$key),
            'icon' => $icon,
            'route' => $route,
            'url' => $exists ? route($route) : null,
            'shortcut' => $shortcut,
            'keywords' => $keywords,
            'active' => $exists && request()->routeIs($route),
            'disabled' => ! $exists,
        ];
    }
}
