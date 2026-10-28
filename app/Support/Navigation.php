<?php

namespace App\Support;

use App\Models\UserNotification;
use Illuminate\Support\Facades\Route;

/**
 * Voci di navigazione condivise da barra laterale, barra in basso e palette comandi.
 * Ogni voce compare solo a chi ha il permesso; le sezioni non ancora disponibili
 * restano visibili ma disattivate ("in arrivo").
 */
class Navigation
{
    /**
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool, badge: int}>
     */
    public static function main(): array
    {
        return self::build([
            ['dashboard', 'gauge', 'home', 'g d', 'cruscotto panoramica', null],
            ['work_orders', 'clipboard-list', 'work-orders.index', 'g o', 'odl interventi lavori ordini', 'work_orders.view'],
            ['requests', 'alert-triangle', 'requests.index', 'g e', 'segnalazioni guasti richieste', null],
            ['assets', 'factory', 'assets.index', 'g m', 'macchine impianti ubicazioni categorie qr etichette contatori', 'assets.view'],
            ['plant', 'cube', 'plant.index', 'g t', 'stabilimento 3d 2d pianta gemello digitale layout', 'assets.view'],
            ['preventive', 'calendar', 'preventive.index', 'g p', 'preventiva piani calendario scadenze rispetto contatore', 'preventive.view'],
            ['kpi', 'bar-chart', 'kpi', 'g k', 'indicatori kpi mttr mtbf fermi arretrato costi report', 'reports.view'],
            ['inventory', 'box', 'inventory.parts.index', 'g r', 'ricambi magazzino fornitori movimenti riordino scorta', 'inventory.view'],
            ['notifications', 'bell', 'notifications.index', 'g n', 'avvisi notifiche preferenze email', null],
            ['app', 'smartphone', 'pwa.install', null, 'app installa telefono notifiche push diagnostica', null],
        ]);
    }

    /**
     * Voci di amministrazione (gruppo separato nella barra laterale).
     *
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool, badge: int}>
     */
    public static function admin(): array
    {
        return self::build([
            ['users', 'users', 'users.index', 'g u', 'persone utenti inviti ruoli permessi', 'users.view'],
            ['settings', 'settings', 'settings.company', 'g i', 'impostazioni azienda logo colori', 'settings.manage'],
            ['notification_rules', 'mail', 'settings.notifications', null, 'avvisi email ruoli riepilogo regole', 'settings.manage'],
            ['worktime', 'clock', 'settings.worktime', null, 'orario di lavoro turni calendario festività chiusure mtbf', 'settings.manage'],
            ['audit', 'clipboard-list', 'audit.index', null, 'registro attività log', 'audit.view'],
            ['privacy', 'shield', 'gdpr.exports', null, 'dati privacy gdpr esportazione anonimizza conservazione cancellazione', 'data.export'],
            ['system', 'server', 'system', 'g s', 'sistema versione aggiornamento copie', 'system.view'],
            ...(app()->isProduction() ? [] : [['design', 'palette', 'design', null, 'design system stile componenti', 'settings.manage']]),
        ]);
    }

    /**
     * Voci della barra in basso del telefono (la centrale è "Segnala").
     *
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool, badge: int}>
     */
    public static function mobile(): array
    {
        // Chi non vede gli ordini (es. il richiedente) trova al loro posto le proprie segnalazioni
        $second = auth()->user()?->can('work_orders.view')
            ? self::item('work_orders', 'clipboard-list', 'work-orders.index', short: true)
            : self::item('requests', 'alert-triangle', 'requests.index', short: true);

        return [
            self::item('dashboard', 'home', 'home', short: true),
            $second,
            self::item('report', 'plus', 'requests.create'),
            self::item('notifications', 'bell', 'notifications.index', short: true),
            self::item('profile', 'user', 'profile', short: true),
        ];
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string|null, 3: string|null, 4: string, 5: string|null}>  $rows
     * @return list<array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool, badge: int}>
     */
    private static function build(array $rows): array
    {
        $user = auth()->user();
        $items = [];
        foreach ($rows as [$key, $icon, $route, $shortcut, $keywords, $permission]) {
            if ($permission !== null && ! $user?->can($permission)) {
                continue;
            }
            $items[] = self::item($key, $icon, $route, $shortcut, $keywords);
        }

        return $items;
    }

    /** Avvisi non letti della persona collegata (una sola query per pagina) */
    public static function unread(): int
    {
        $user = auth()->user();
        if (! $user) {
            return 0;
        }

        // Conservato nella richiesta, non in una variabile statica: i processi lunghi (code, test) servono più persone
        $attributes = request()->attributes;
        if (! $attributes->has('mt.unread')) {
            $attributes->set('mt.unread', UserNotification::unreadCount($user));
        }

        return (int) $attributes->get('mt.unread');
    }

    /**
     * @return array{key: string, label: string, icon: string, route: string|null, url: string|null, shortcut: string|null, keywords: string, active: bool, disabled: bool, badge: int}
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
            'badge' => $key === 'notifications' ? self::unread() : 0,
        ];
    }
}
