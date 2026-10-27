<?php

namespace App\Enums;

/**
 * Ciclo di vita di un ordine di lavoro:
 *
 *   aperto ──► pianificato ──► in_corso ⇄ in_attesa
 *      │            │              │
 *      └────────────┴──► annullato └──► completato ──► chiuso
 *                                           └── riaperto (torna in_corso)
 */
enum WorkOrderStatus: string
{
    case Open = 'aperto';
    case Planned = 'pianificato';
    case InProgress = 'in_corso';
    case Waiting = 'in_attesa';
    case Completed = 'completato';
    case Closed = 'chiuso';
    case Cancelled = 'annullato';

    /**
     * Passaggi ammessi: stato di arrivo => permesso richiesto.
     *
     * @return array<string, string>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Open => [self::Planned->value => 'work_orders.assign', self::InProgress->value => 'work_orders.execute', self::Cancelled->value => 'work_orders.assign'],
            self::Planned => [self::InProgress->value => 'work_orders.execute', self::Cancelled->value => 'work_orders.assign'],
            self::InProgress => [self::Waiting->value => 'work_orders.execute', self::Completed->value => 'work_orders.execute'],
            self::Waiting => [self::InProgress->value => 'work_orders.execute'],
            self::Completed => [self::Closed->value => 'work_orders.close', self::InProgress->value => 'work_orders.close'],
            self::Closed, self::Cancelled => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return array_key_exists($to->value, $this->transitions());
    }

    /** Ordini su cui si lavora ancora */
    public function isActive(): bool
    {
        return in_array($this, [self::Open, self::Planned, self::InProgress, self::Waiting], true);
    }

    /** @return list<self> */
    public static function active(): array
    {
        return [self::Open, self::Planned, self::InProgress, self::Waiting];
    }

    public function label(): string
    {
        return __('enums.work_order_status.'.$this->value);
    }

    public function tone(): string
    {
        return match ($this) {
            self::Open => 'warn',
            self::Planned => 'mnt',
            self::InProgress => 'mnt',
            self::Waiting => 'warn',
            self::Completed => 'ok',
            self::Closed, self::Cancelled => 'stop',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'alert-circle',
            self::Planned => 'calendar',
            self::InProgress => 'wrench',
            self::Waiting => 'pause-circle',
            self::Completed => 'check-circle',
            self::Closed => 'lock',
            self::Cancelled => 'x-circle',
        };
    }
}
