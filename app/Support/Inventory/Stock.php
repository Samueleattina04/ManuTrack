<?php

namespace App\Support\Inventory;

use App\Enums\MovementType;
use App\Enums\NotificationEvent;
use App\Enums\WorkOrderStatus;
use App\Models\Part;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Support\Audit\Audit;
use App\Support\Notifications\Notifier;
use App\Support\Numbers;
use App\Support\Push\PushMessage;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Tutti i movimenti di magazzino passano da qui.
 *
 * - Ricambio e giacenza sono bloccati durante il movimento: due prelievi
 *   contemporanei non possono portare la giacenza sotto zero.
 * - La giacenza non va mai sotto zero.
 * - Il carico aggiorna il costo medio ponderato del ricambio.
 * - Ogni movimento registra la giacenza risultante nel magazzino.
 * - Scendendo alla scorta minima si avvisa chi gestisce il magazzino.
 * - I movimenti non si modificano: un errore si corregge con un movimento opposto.
 */
class Stock
{
    public function __construct(private Notifier $notifier) {}

    /** Carico (arrivo da fornitore o giacenza iniziale) */
    public function receive(Part $part, Warehouse $warehouse, mixed $quantity, User $user, mixed $unitCost = null, ?Supplier $supplier = null, ?string $reference = null, ?string $note = null): StockMovement
    {
        $qty = $this->positive($quantity);
        $cost = $unitCost === null || $unitCost === '' ? null : Numbers::parse($unitCost);
        if ($unitCost !== null && $unitCost !== '' && ($cost === null || $cost < 0)) {
            throw ValidationException::withMessages(['unit_cost' => __('inventory.errors.cost')]);
        }

        return $this->move($part, $warehouse, MovementType::Receipt, $qty, $user, [
            'supplier_id' => $supplier?->id,
            'reference' => $reference,
            'note' => $note,
        ], receiptCost: $cost);
    }

    /** Prelievo, di solito per un ordine di lavoro */
    public function issue(Part $part, Warehouse $warehouse, mixed $quantity, User $user, ?WorkOrder $order = null, ?string $note = null): StockMovement
    {
        $qty = $this->positive($quantity);
        // Lo stato dell'ordine si rilegge dentro la transazione, a righe bloccate
        $guard = $order ? function () use ($order): void {
            $status = WorkOrder::query()->whereKey($order->id)->value('status');
            $status = $status instanceof WorkOrderStatus ? $status : WorkOrderStatus::tryFrom((string) $status);
            if (! $status || ! ($status->isActive() || $status === WorkOrderStatus::Completed)) {
                throw ValidationException::withMessages(['work_order' => __('inventory.errors.order_closed')]);
            }
        } : null;

        return $this->move($part, $warehouse, MovementType::Issue, -$qty, $user, ['work_order_id' => $order?->id, 'note' => $note], guard: $guard);
    }

    /** Reso a magazzino di un ricambio prelevato per un ordine e non usato */
    public function giveBack(Part $part, Warehouse $warehouse, mixed $quantity, User $user, WorkOrder $order, ?string $note = null): StockMovement
    {
        $qty = $this->positive($quantity);

        // Il reso torna al costo a cui era uscito
        $cost = (float) (StockMovement::query()->where('work_order_id', $order->id)->where('part_id', $part->id)
            ->where('type', MovementType::Issue->value)->latest('id')->value('unit_cost') ?? $part->unit_cost);

        // Il controllo "non più del prelevato" si fa con il ricambio bloccato:
        // due resi contemporanei non possono superarlo insieme
        $guard = function () use ($order, $part, $qty): void {
            $taken = $this->netIssued($order, $part);
            if ($qty > $taken + 0.0001) {
                throw ValidationException::withMessages(['quantity' => __('inventory.errors.return_too_much', ['qty' => Part::formatQty($taken), 'unit' => $part->unit])]);
            }
        };

        return $this->move($part, $warehouse, MovementType::Return, $qty, $user, ['work_order_id' => $order->id, 'note' => $note], fixedCost: $cost, guard: $guard);
    }

    /** Rettifica dopo una conta: la giacenza diventa quella contata */
    public function adjust(Part $part, Warehouse $warehouse, mixed $counted, User $user, string $reason): ?StockMovement
    {
        $value = Numbers::parse($counted);
        if ($value === null || $value < 0 || $value > 10_000_000) {
            throw ValidationException::withMessages(['quantity' => __('inventory.errors.quantity')]);
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['note' => __('inventory.errors.reason')]);
        }
        $current = (float) (StockLevel::query()->where('part_id', $part->id)->where('warehouse_id', $warehouse->id)->value('quantity') ?? 0);
        $delta = round($value - $current, 2);
        if (abs($delta) < 0.001) {
            throw ValidationException::withMessages(['quantity' => __('inventory.errors.no_difference')]);
        }

        // Movimento e voce del registro attività nella stessa transazione
        return DB::transaction(function () use ($part, $warehouse, $delta, $user, $reason, $current, $value) {
            $movement = $this->move($part, $warehouse, MovementType::Adjustment, $delta, $user, ['note' => trim($reason)], expectedBefore: $current);
            Audit::log('inventory.adjusted', $part, $part->code.' · '.$warehouse->name.' · '.Part::formatQty($current).' → '.Part::formatQty($value).' · '.trim($reason), actor: $user);

            return $movement;
        });
    }

    /** Trasferimento tra magazzini: due movimenti con lo stesso codice */
    public function transfer(Part $part, Warehouse $from, Warehouse $to, mixed $quantity, User $user, ?string $note = null): void
    {
        $qty = $this->positive($quantity);
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_warehouse' => __('inventory.errors.same_warehouse')]);
        }
        $group = (string) Str::uuid();
        DB::transaction(function () use ($part, $from, $to, $qty, $user, $note, $group): void {
            $this->move($part, $from, MovementType::Transfer, -$qty, $user, ['transfer_group' => $group, 'note' => $note]);
            $this->move($part, $to, MovementType::Transfer, $qty, $user, ['transfer_group' => $group, 'note' => $note]);
        });
    }

    /** Quantità prelevata per un ordine al netto dei resi */
    public function netIssued(WorkOrder $order, Part $part): float
    {
        return -(float) StockMovement::query()->where('work_order_id', $order->id)->where('part_id', $part->id)
            ->whereIn('type', [MovementType::Issue->value, MovementType::Return->value])->sum('quantity');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function move(Part $part, Warehouse $warehouse, MovementType $type, float $delta, User $user, array $extra, ?float $receiptCost = null, ?float $fixedCost = null, ?float $expectedBefore = null, ?Closure $guard = null): StockMovement
    {
        // In un magazzino disattivato si può solo togliere (svuotarlo), non aggiungere
        if (! $warehouse->is_active && $delta > 0) {
            throw ValidationException::withMessages(['warehouse' => __('inventory.errors.warehouse_inactive')]);
        }

        [$movement, $wasAbove, $isBelow] = DB::transaction(function () use ($part, $warehouse, $type, $delta, $user, $extra, $receiptCost, $fixedCost, $expectedBefore, $guard) {
            /** @var Part $locked */
            $locked = Part::query()->lockForUpdate()->findOrFail($part->id);
            StockLevel::query()->insertOrIgnore(['part_id' => $part->id, 'warehouse_id' => $warehouse->id, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()]);
            /** @var StockLevel $level */
            $level = StockLevel::query()->where('part_id', $part->id)->where('warehouse_id', $warehouse->id)->lockForUpdate()->firstOrFail();

            if ($guard) {
                $guard();
            }

            $before = (float) $level->quantity;
            // La conta è stata fatta su una giacenza che nel frattempo è cambiata
            if ($expectedBefore !== null && abs($before - $expectedBefore) > 0.001) {
                throw ValidationException::withMessages(['quantity' => __('inventory.errors.changed')]);
            }
            $after = round($before + $delta, 2);
            if ($after < 0) {
                throw ValidationException::withMessages(['quantity' => __('inventory.errors.not_enough', ['qty' => Part::formatQty($before), 'unit' => $locked->unit, 'warehouse' => $warehouse->name])]);
            }

            $oldTotal = (float) $locked->stock_total;
            $wasAbove = ! $locked->isBelowMinimum();

            // Costo medio ponderato: solo i carichi con un costo lo cambiano
            if ($type === MovementType::Receipt && $receiptCost !== null) {
                $base = max(0.0, $oldTotal);
                $locked->unit_cost = (string) round($base > 0 ? ($base * (float) $locked->unit_cost + $delta * $receiptCost) / ($base + $delta) : $receiptCost, 4);
            }

            $level->forceFill(['quantity' => $after])->save();
            $locked->stock_total = (string) round((float) StockLevel::query()->where('part_id', $part->id)->sum('quantity'), 2);
            $locked->save();

            $movement = StockMovement::query()->create($extra + [
                'part_id' => $part->id,
                'warehouse_id' => $warehouse->id,
                'type' => $type,
                'quantity' => $delta,
                'unit_cost' => $fixedCost ?? ($type === MovementType::Receipt && $receiptCost !== null ? $receiptCost : (float) $locked->unit_cost),
                'balance_after' => $after,
                'user_id' => $user->id,
            ]);

            return [$movement, $wasAbove, $locked->isBelowMinimum()];
        });

        $part->refresh();
        if ($wasAbove && $isBelow) {
            // Dentro un'altra transazione (trasferimento, rettifica) l'avviso parte solo dopo il commit
            DB::afterCommit(fn () => $this->notifyLowStock($part, $user));
        }

        return $movement;
    }

    private function positive(mixed $quantity): float
    {
        $value = Numbers::parse($quantity);
        if ($value === null || $value <= 0 || $value > 10_000_000) {
            throw ValidationException::withMessages(['quantity' => __('inventory.errors.quantity')]);
        }

        return round($value, 2);
    }

    /** Avviso (in coda) a chi gestisce il magazzino, tranne chi ha fatto il movimento */
    private function notifyLowStock(Part $part, User $actor): void
    {
        $users = User::permission('inventory.manage')->where('is_active', true)->whereKeyNot($actor->id)->get();
        $this->notifier->send(NotificationEvent::StockLow, $users, new PushMessage(
            title: __((float) $part->stock_total <= 0 ? 'inventory.push.out_title' : 'inventory.push.low_title', ['code' => $part->code]),
            body: __('inventory.push.low_body', ['name' => $part->name, 'qty' => Part::formatQty($part->stock_total), 'min' => Part::formatQty($part->min_stock), 'unit' => $part->unit]),
            url: route('inventory.parts.show', $part, absolute: false),
            tag: 'part-'.$part->id,
        ));
    }
}
