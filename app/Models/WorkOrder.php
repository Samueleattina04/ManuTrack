<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\WaitingReason;
use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * Ordine di lavoro (OdL).
 *
 * @property int $id
 * @property string $number
 * @property WorkOrderType $type
 * @property string $title
 * @property string|null $description
 * @property int|null $asset_id
 * @property int|null $location_id
 * @property Priority $priority
 * @property WorkOrderStatus $status
 * @property WaitingReason|null $waiting_reason
 * @property Carbon|null $due_at
 * @property Carbon|null $planned_start_at
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $closed_at
 * @property int $reopened_count
 * @property int|null $failure_problem_id
 * @property int|null $failure_cause_id
 * @property int|null $failure_remedy_id
 * @property string|null $completion_notes
 * @property string|null $external_cost
 * @property string|null $labor_rate
 * @property int|null $created_by
 * @property int|null $completed_by
 * @property int|null $closed_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Asset|null $asset
 * @property-read Location|null $location
 * @property-read WorkRequest|null $request
 * @property-read User|null $creator
 */
class WorkOrder extends Model
{
    public const PREFIX = 'OdL';

    protected $fillable = [
        'type', 'title', 'description', 'asset_id', 'location_id', 'priority', 'due_at', 'planned_start_at', 'external_cost',
    ];

    protected $attributes = [
        'type' => 'correttivo',
        'priority' => 'media',
        'status' => 'aperto',
    ];

    protected function casts(): array
    {
        return [
            'type' => WorkOrderType::class,
            'priority' => Priority::class,
            'status' => WorkOrderStatus::class,
            'waiting_reason' => WaitingReason::class,
            'due_at' => 'datetime',
            'planned_start_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'closed_at' => 'datetime',
            'external_cost' => 'decimal:2',
            'labor_rate' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkOrder $order): void {
            if (! $order->number) {
                $order->number = Sequence::format(self::PREFIX, Sequence::next(self::PREFIX));
            }
            $order->created_by ??= auth()->id();
            if ($order->asset_id && ! $order->location_id) {
                $order->location_id = Asset::query()->whereKey($order->asset_id)->value('location_id');
            }
        });
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'work_order_assignees')->withPivot('created_at')->orderBy('name');
    }

    /** @return HasOne<WorkRequest, $this> */
    public function request(): HasOne
    {
        return $this->hasOne(WorkRequest::class);
    }

    /** @return HasMany<WorkOrderStatusChange, $this> */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(WorkOrderStatusChange::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<WorkOrderComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(WorkOrderComment::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<WorkOrderChecklistItem, $this> */
    public function checklist(): HasMany
    {
        return $this->hasMany(WorkOrderChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<TimeEntry, $this> */
    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class)->orderByDesc('started_at');
    }

    /** @return HasMany<DowntimeEvent, $this> */
    public function downtimes(): HasMany
    {
        return $this->hasMany(DowntimeEvent::class)->orderBy('started_at');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('position')->orderBy('id');
    }

    /** @return BelongsTo<FailureCode, $this> */
    public function failureProblem(): BelongsTo
    {
        return $this->belongsTo(FailureCode::class, 'failure_problem_id');
    }

    /** @return BelongsTo<FailureCode, $this> */
    public function failureCause(): BelongsTo
    {
        return $this->belongsTo(FailureCode::class, 'failure_cause_id');
    }

    /** @return BelongsTo<FailureCode, $this> */
    public function failureRemedy(): BelongsTo
    {
        return $this->belongsTo(FailureCode::class, 'failure_remedy_id');
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignees()->whereKey($user->id)->exists();
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeAssignedTo(Builder $query, User $user): void
    {
        $query->whereHas('assignees', fn (Builder $q) => $q->whereKey($user->id));
    }

    /**
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q
            ->where('number', 'like', $like)
            ->orWhere('title', 'like', $like)
            ->orWhereHas('asset', fn (Builder $a) => $a->where('code', 'like', $like)->orWhere('name', 'like', $like)));
    }

    /**
     * Prima le priorità più alte, poi le scadenze più vicine.
     *
     * @param  Builder<WorkOrder>  $query
     */
    public function scopeByUrgency(Builder $query): void
    {
        $query->orderByRaw("case priority when 'critica' then 0 when 'alta' then 1 when 'media' then 2 else 3 end")
            ->orderByRaw('due_at is null')->orderBy('due_at')->orderByDesc('id');
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->status->isActive() && $this->due_at->isPast();
    }

    public function laborMinutes(): int
    {
        return (int) $this->timeEntries->sum(fn (TimeEntry $t) => $t->currentMinutes());
    }

    /** Costo orario: quello fissato alla chiusura, altrimenti quello attuale delle impostazioni */
    public function hourlyRate(): float
    {
        return (float) ($this->labor_rate ?? settings('costs.hourly_rate', 0));
    }

    /**
     * @return array{labor: float, external: float, total: float}
     */
    public function costs(): array
    {
        $labor = round($this->laborMinutes() / 60 * $this->hourlyRate(), 2);
        $external = (float) ($this->external_cost ?? 0);

        return ['labor' => $labor, 'external' => $external, 'total' => round($labor + $external, 2)];
    }

    public function checklistComplete(): bool
    {
        return ! $this->checklist->contains(fn (WorkOrderChecklistItem $i) => $i->required && ! $i->isDone());
    }
}
