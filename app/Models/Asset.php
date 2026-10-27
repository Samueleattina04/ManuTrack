<?php

namespace App\Models;

use App\Enums\AssetStatus;
use App\Enums\AttachmentKind;
use App\Enums\Criticality;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Macchina o impianto.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $asset_category_id
 * @property int|null $location_id
 * @property int|null $parent_id
 * @property string|null $serial_number
 * @property string|null $manufacturer
 * @property string|null $model
 * @property int|null $year
 * @property Carbon|null $installed_on
 * @property Criticality $criticality
 * @property AssetStatus $status
 * @property Carbon|null $status_changed_at
 * @property string $qr_token
 * @property string|null $purchase_cost
 * @property Carbon|null $warranty_until
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read AssetCategory|null $category
 * @property-read Location|null $location
 * @property-read Asset|null $parent
 * @property-read Attachment|null $cover
 */
class Asset extends Model
{
    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    protected $fillable = [
        'code', 'name', 'asset_category_id', 'location_id', 'parent_id', 'serial_number', 'manufacturer',
        'model', 'year', 'installed_on', 'criticality', 'status', 'purchase_cost', 'warranty_until', 'notes',
    ];

    protected $attributes = [
        'criticality' => 'B',
        'status' => 'operativa',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'criticality' => Criticality::class,
            'installed_on' => 'date',
            'warranty_until' => 'date',
            'status_changed_at' => 'datetime',
            'purchase_cost' => 'decimal:2',
            'year' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Asset $asset): void {
            $asset->qr_token = $asset->qr_token ?: self::newQrToken();
            $asset->created_by ??= auth()->id();
        });
    }

    public static function newQrToken(): string
    {
        return Str::lower(Str::random(24));
    }

    /** @return BelongsTo<AssetCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Asset, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_id');
    }

    /** @return HasMany<Asset, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_id')->orderBy('code');
    }

    /** @return HasMany<Meter, $this> */
    public function meters(): HasMany
    {
        return $this->hasMany(Meter::class)->orderBy('name');
    }

    /** @return HasMany<WorkOrder, $this> */
    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    /** @return HasMany<WorkRequest, $this> */
    public function workRequests(): HasMany
    {
        return $this->hasMany(WorkRequest::class);
    }

    /** @return HasMany<DowntimeEvent, $this> */
    public function downtimes(): HasMany
    {
        return $this->hasMany(DowntimeEvent::class);
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->orderBy('position')->orderBy('id');
    }

    /** Foto principale: la prima in ordine */
    /** @return MorphOne<Attachment, $this> */
    public function cover(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable')
            ->where('kind', AttachmentKind::Photo->value)
            ->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<Asset>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(fn (Builder $q) => $q
            ->where('code', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('serial_number', 'like', $like)
            ->orWhere('manufacturer', 'like', $like)
            ->orWhere('model', 'like', $like));
    }

    /**
     * Macchine in questa ubicazione o in quelle contenute.
     *
     * @param  Builder<Asset>  $query
     */
    public function scopeInLocation(Builder $query, Location $location): void
    {
        $query->whereIn('location_id', Location::query()->branch($location)->select('id'));
    }

    /**
     * Discendenti (sottogruppi dei sottogruppi…), per impedire cicli.
     *
     * @return list<int>
     */
    public function descendantIds(): array
    {
        $ids = [];
        $frontier = [$this->id];
        while ($frontier !== [] && count($ids) < 5000) {
            $frontier = Asset::query()->whereIn('parent_id', $frontier)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $ids = array_merge($ids, $frontier);
        }

        return $ids;
    }

    public function qrUrl(): string
    {
        return route('assets.qr', $this->qr_token);
    }

    public function stopsLine(): bool
    {
        return $this->criticality === Criticality::A;
    }
}
