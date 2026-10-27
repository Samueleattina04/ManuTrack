<?php

namespace App\Support\Access;

/**
 * Ruoli e permessi dell'azienda.
 *
 * I permessi sono raggruppati per modulo; quelli dei moduli che arriveranno
 * nelle fasi successive sono già definiti, così l'amministratore può
 * preparare i ruoli fin da subito. L'amministratore ha sempre tutti i permessi.
 */
final class Access
{
    public const ADMIN = 'amministratore';

    public const MANAGER = 'responsabile';

    public const TECHNICIAN = 'tecnico';

    public const STOREKEEPER = 'magazziniere';

    public const REQUESTER = 'richiedente';

    public const VIEWER = 'osservatore';

    /** Ordine di visualizzazione dei ruoli */
    public const ROLES = [self::ADMIN, self::MANAGER, self::TECHNICIAN, self::STOREKEEPER, self::REQUESTER, self::VIEWER];

    /**
     * Permessi raggruppati per modulo.
     *
     * @var array<string, list<string>>
     */
    public const PERMISSIONS = [
        'users' => ['users.view', 'users.manage'],
        'settings' => ['settings.manage'],
        'system' => ['system.view', 'system.update', 'audit.view', 'data.export'],
        'assets' => ['assets.view', 'assets.manage', 'assets.status', 'assets.readings', 'assets.attach'],
        'requests' => ['requests.create', 'requests.view_all', 'requests.triage'],
        'work_orders' => ['work_orders.view', 'work_orders.create', 'work_orders.execute', 'work_orders.assign', 'work_orders.close', 'work_orders.view_costs'],
        'preventive' => ['preventive.view', 'preventive.manage'],
        'inventory' => ['inventory.view', 'inventory.move', 'inventory.manage'],
        'reports' => ['reports.view', 'reports.export'],
    ];

    /**
     * Permessi predefiniti per ruolo (l'amministratore li ha tutti).
     *
     * @var array<string, list<string>>
     */
    public const DEFAULTS = [
        self::MANAGER => [
            'users.view',
            'assets.view', 'assets.manage', 'assets.status', 'assets.readings', 'assets.attach',
            'requests.create', 'requests.view_all', 'requests.triage',
            'work_orders.view', 'work_orders.create', 'work_orders.execute', 'work_orders.assign', 'work_orders.close', 'work_orders.view_costs',
            'preventive.view', 'preventive.manage',
            'inventory.view', 'inventory.move',
            'reports.view', 'reports.export',
        ],
        self::TECHNICIAN => [
            'assets.view', 'assets.status', 'assets.readings', 'assets.attach',
            'requests.create', 'requests.view_all',
            'work_orders.view', 'work_orders.create', 'work_orders.execute',
            'preventive.view',
            'inventory.view', 'inventory.move',
        ],
        self::STOREKEEPER => [
            'assets.view',
            'requests.create',
            'work_orders.view',
            'inventory.view', 'inventory.move', 'inventory.manage',
            'reports.view',
        ],
        self::REQUESTER => [
            'assets.view',
            'requests.create',
        ],
        self::VIEWER => [
            'assets.view',
            'requests.view_all',
            'work_orders.view', 'work_orders.view_costs',
            'preventive.view',
            'inventory.view',
            'reports.view', 'reports.export',
        ],
    ];

    /**
     * @return list<string>
     */
    public static function allPermissions(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    /**
     * @return list<string>
     */
    public static function defaultsFor(string $role): array
    {
        return $role === self::ADMIN ? self::allPermissions() : (self::DEFAULTS[$role] ?? []);
    }
}
