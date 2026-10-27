<?php

namespace App\Models;

use App\Enums\IntervalUnit;
use App\Enums\PlanMode;
use App\Enums\PlanTrigger;
use App\Enums\Priority;
use App\Enums\WorkOrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Piano di manutenzione preventiva di una macchina: a tempo (ogni N giorni,
 * settimane, mesi o anni) oppure a contatore (ogni N ore, cicli…).
 * Quando si avvicina la scadenza genera un ordine di lavoro preventivo;
 * al completamento (o all'annullamento) calcola la scadenza successiva.
 *
 * @property int $id
 * @property string $title
 * @property string|null $description
 * @property int $asset_id
 * @property PlanTrigger $trigger
 * @property PlanMode $schedule_mode
 * @property int|null $interval_value
 * @property IntervalUnit|null $interval_unit
 * @property Carbon|null $start_on
 * @property Carbon|null $next_due_on
 * @property int $lead_days
 * @property int|null $meter_id
 * @property string|null $meter_interval
 * @property string $meter_lead
 * @property string|null $next_meter_due
 * @property Priority $priority
 * @property int|null $assignee_id
 * @property int|null $estimated_minutes
 * @property int|null $checklist_template_id
 * @property bool $is_active
 * @property int|null $current_work_order_id
 * @property Carbon|null $last_generated_at
 * @property Carbon|null $last_done_at
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Asset $asset
 * @property-read Meter|null $meter
 * @property-read User|null $assignee
 * @property-read ChecklistTemplate|null $checklistTemplate
 * @property-read WorkOrder|null $currentOrder
 */
class MaintenancePlan extends Model
{
    protected $fillable = [
        'title', 'description', 'asset_id', 'trigger', 'schedule_mode', 'interval_value', 'interval_unit', 'start_on',
        'lead_days', 'meter_id', 'meter_interval', 'meter_lead', 'priority', 'assignee_id', 'estimated_minutes',
        'checklist_template_id', 'is_active',
    ];

    protected $attributes = [
        'trigger' => 'tempo',
        'schedule_mode' => 'fisso',
        'priority' => 'media',
        'lead_days' => 7,
        'meter_lead' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'trigger' => PlanTrigger::class,
            'schedule_mode' => PlanMode::class,
            'interval_unit' => IntervalUnit::class,
            'priority' => Priority::class,
            'start_on' => 'date',
            'next_due_on' => 'date',
            'meter_interval' => 'decimal:2',
            'meter_lead' => 'decimal:2',
            'next_meter_due' => 'decimal:2',
            'is_active' => 'boolean',
            'last_generated_at' => 'datetime',
            'last_done_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Meter, $this> */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** @return BelongsTo<ChecklistTemplate, $this> */
    public function checklistTemplate(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class);
    }

    /**
     * Ordine generato e non ancora completato (al massimo uno per piano)
     *
     * @return BelongsTo<WorkOrder, $this>
     */
    public function currentOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'current_work_order_id');
    }

    /** @return HasMany<WorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /**
     * Ricambi previsti per ogni intervento
     *
     * @return BelongsToMany<Part, $this>
     */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class, 'maintenance_plan_parts')->withPivot(['id', 'quantity'])->orderBy('code');
    }

    /** @param Builder<MaintenancePlan> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function isTime(): bool
    {
        return $this->trigger === PlanTrigger::Time;
    }

    /** "Ogni 3 mesi", "Ogni 500 h" */
    public function frequencyLabel(): string
    {
        if ($this->isTime()) {
            $n = (int) $this->interval_value;

            return __('preventive.every', ['n' => $n, 'unit' => $this->interval_unit?->label($n) ?? '']);
        }

        return __('preventive.every', ['n' => Part::formatQty($this->meter_interval), 'unit' => $this->meter->unit ?? '']);
    }

    /** Ha un ordine generato ancora aperto? */
    public function hasOpenOrder(): bool
    {
        $order = $this->currentOrder;

        return $order !== null && ($order->status->isActive());
    }

    /**
     * Stato rispetto alla scadenza: "scaduto", "in_scadenza" (entro l'anticipo), "ok", "fermo" (disattivato)
     */
    public function dueState(?Carbon $today = null): string
    {
        if (! $this->is_active) {
            return 'fermo';
        }
        $today ??= today();
        if ($this->isTime()) {
            if (! $this->next_due_on) {
                return 'ok';
            }
            if ($this->next_due_on->lt($today)) {
                return 'scaduto';
            }

            return $this->next_due_on->copy()->subDays($this->lead_days)->lte($today) ? 'in_scadenza' : 'ok';
        }

        $current = (float) ($this->meter->current_value ?? 0);
        $due = (float) $this->next_meter_due;
        if ($this->next_meter_due === null) {
            return 'ok';
        }
        if ($current >= $due) {
            return 'scaduto';
        }

        return $current >= $due - (float) $this->meter_lead ? 'in_scadenza' : 'ok';
    }

    /**
     * Stati di un ordine del piano considerato eseguito
     *
     * @return list<string>
     */
    public static function doneStatuses(): array
    {
        return [WorkOrderStatus::Completed->value, WorkOrderStatus::Closed->value];
    }
}
