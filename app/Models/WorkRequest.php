<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Segnalazione di un guasto o di un problema, fatta da chiunque in reparto.
 *
 * @property int $id
 * @property string $number
 * @property int|null $asset_id
 * @property int|null $location_id
 * @property string $description
 * @property bool $machine_stopped
 * @property Priority $priority
 * @property RequestStatus $status
 * @property int|null $user_id
 * @property int|null $work_order_id
 * @property int|null $duplicate_of_id
 * @property string|null $triage_note
 * @property int|null $triaged_by
 * @property Carbon|null $triaged_at
 * @property string|null $client_uuid
 * @property Carbon|null $reported_at
 * @property Carbon $created_at
 * @property-read Asset|null $asset
 * @property-read Location|null $location
 * @property-read User|null $user
 * @property-read WorkOrder|null $workOrder
 * @property-read WorkRequest|null $duplicateOf
 * @property-read User|null $triager
 */
class WorkRequest extends Model
{
    public const PREFIX = 'SEG';

    protected $fillable = ['asset_id', 'location_id', 'description', 'machine_stopped', 'priority', 'client_uuid', 'reported_at'];

    protected $attributes = [
        'priority' => 'media',
        'status' => 'nuova',
        'machine_stopped' => false,
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => RequestStatus::class,
            'machine_stopped' => 'boolean',
            'triaged_at' => 'datetime',
            'reported_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkRequest $request): void {
            if (! $request->number) {
                $request->number = Sequence::format(self::PREFIX, Sequence::next(self::PREFIX));
            }
            if ($request->asset_id && ! $request->location_id) {
                $request->location_id = Asset::query()->whereKey($request->asset_id)->value('location_id');
            }
            $request->reported_at ??= now();
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function triager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    /** @return BelongsTo<WorkOrder, $this> */
    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /** @return BelongsTo<WorkRequest, $this> */
    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(WorkRequest::class, 'duplicate_of_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('position')->orderBy('id');
    }

    /**
     * Le segnalazioni che una persona può vedere: tutte con il permesso, altrimenti le proprie.
     *
     * @param  Builder<WorkRequest>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('requests.view_all')) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * Urgente: priorità critica, oppure macchina ferma che ferma la linea.
     */
    public function isUrgent(): bool
    {
        return $this->priority === Priority::Critical || ($this->machine_stopped && $this->asset?->stopsLine());
    }

    /** Titolo breve per elenchi e ordini di lavoro */
    public function summary(int $length = 80): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', $this->description) ?? ''), $length);
    }
}
