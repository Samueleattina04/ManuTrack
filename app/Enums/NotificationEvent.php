<?php

namespace App\Enums;

/**
 * Eventi che generano un avviso. Per ognuno: chi può riceverlo (permesso),
 * e i canali predefiniti. L'avviso nell'app c'è sempre; push ed email si
 * scelgono nelle preferenze (email predefinita per ruolo dall'amministratore).
 */
enum NotificationEvent: string
{
    case RequestNew = 'segnalazione_nuova';
    case RequestUrgent = 'segnalazione_urgente';
    case RequestTriaged = 'segnalazione_esito';
    case AssetDown = 'macchina_ferma';
    case OrderAssigned = 'ordine_assegnato';
    case OrderCompleted = 'ordine_completato';
    case PreventiveToAssign = 'preventiva_da_assegnare';
    case StockLow = 'scorta_minima';
    case SystemUpdate = 'aggiornamento';
    case MonthlyReport = 'rapporto_mensile';

    public function label(): string
    {
        return __('notifications.events.'.$this->value.'.label');
    }

    public function description(): string
    {
        return __('notifications.events.'.$this->value.'.description');
    }

    /** Permesso necessario per ricevere l'evento */
    public function permission(): string
    {
        return match ($this) {
            self::RequestNew, self::RequestUrgent, self::AssetDown => 'requests.triage',
            self::RequestTriaged, self::OrderCompleted => 'requests.create',
            self::OrderAssigned => 'work_orders.execute',
            self::PreventiveToAssign => 'work_orders.assign',
            self::StockLow => 'inventory.manage',
            self::SystemUpdate => 'system.update',
            self::MonthlyReport => 'reports.view',
        };
    }

    public function defaultPush(): bool
    {
        return ! in_array($this, [self::MonthlyReport, self::SystemUpdate], true);
    }

    /** Email predefinita, se il ruolo non dice altro */
    public function defaultEmail(): string
    {
        return match ($this) {
            self::RequestUrgent, self::SystemUpdate, self::MonthlyReport => 'subito',
            self::StockLow => 'riepilogo',
            default => 'no',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RequestNew, self::RequestUrgent, self::AssetDown => 'alert-triangle',
            self::RequestTriaged, self::OrderCompleted => 'check-circle',
            self::OrderAssigned, self::PreventiveToAssign => 'clipboard-list',
            self::StockLow => 'box',
            self::SystemUpdate => 'server',
            self::MonthlyReport => 'bar-chart',
        };
    }
}
