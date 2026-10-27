<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Ubicazione nello stabilimento: sito > area > linea.
 *
 * @property int $id
 * @property int|null $parent_id
 * @property LocationType $type
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property string $path
 * @property int $depth
 * @property int $sort
 * @property Carbon $created_at
 */
class Location extends Model
{
    protected $fillable = ['parent_id', 'name', 'code', 'description', 'sort'];

    /** @var array<int, string>|null Etichette complete in memoria per la richiesta corrente */
    private static ?array $labels = null;

    protected function casts(): array
    {
        return ['type' => LocationType::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Location $location): void {
            $parent = $location->parent_id ? Location::query()->find($location->parent_id) : null;
            $location->depth = $parent ? $parent->depth + 1 : 0;
            $location->type = LocationType::forDepth($location->depth);
        });

        // Il percorso contiene l'id: si scrive dopo l'inserimento
        static::saved(function (Location $location): void {
            $parent = $location->parent_id ? Location::query()->find($location->parent_id) : null;
            $path = ($parent ? $parent->path : '/').$location->id.'/';
            if ($location->path !== $path) {
                // Percorso salvato prima di questa modifica (vuoto per una nuova ubicazione)
                $old = $location->getOriginal('path');
                $location->path = $path;
                $location->saveQuietly();
                if (is_string($old) && strlen($old) > 1) {
                    // Spostamento: si aggiornano anche i discendenti
                    foreach (Location::query()->where('path', 'like', $old.'%')->whereKeyNot($location->id)->get() as $child) {
                        $child->path = $path.substr($child->path, strlen($old));
                        $child->saveQuietly();
                    }
                }
            }
            self::$labels = null;
        });

        static::deleted(fn () => self::$labels = null);
    }

    /** @return BelongsTo<Location, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /** @return HasMany<Location, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Location::class, 'parent_id')->orderBy('sort')->orderBy('name');
    }

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Questa ubicazione e tutte quelle contenute.
     *
     * @param  Builder<Location>  $query
     */
    public function scopeBranch(Builder $query, Location $root): void
    {
        $query->where('path', 'like', $root->path.'%');
    }

    /** @return list<int> */
    public function branchIds(): array
    {
        return Location::query()->branch($this)->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Nome completo, es. "Sito Nord › Reparto presse › Linea 2".
     */
    public function fullName(): string
    {
        return self::labels()[$this->id] ?? $this->name;
    }

    /**
     * Tutte le ubicazioni in ordine d'albero con il nome completo (per menu a tendina).
     *
     * @return array<int, string>
     */
    public static function labels(): array
    {
        if (self::$labels !== null) {
            return self::$labels;
        }

        $all = Location::query()->orderBy('sort')->orderBy('name')->get(['id', 'parent_id', 'name', 'path'])->keyBy('id');
        $byParent = $all->groupBy(fn (Location $l) => (int) $l->parent_id);
        $labels = [];
        $walk = function (int $parent, string $prefix) use (&$walk, &$labels, $byParent): void {
            foreach ($byParent->get($parent, collect()) as $loc) {
                $label = $prefix === '' ? $loc->name : $prefix.' › '.$loc->name;
                $labels[(int) $loc->id] = $label;
                $walk((int) $loc->id, $label);
            }
        };
        $walk(0, '');

        return self::$labels = $labels;
    }

    /**
     * Albero completo per la gestione delle ubicazioni.
     *
     * @return Collection<int, Location>
     */
    public static function tree(): Collection
    {
        return Location::query()->whereNull('parent_id')->with('children.children')->withCount('assets')
            ->orderBy('sort')->orderBy('name')->get();
    }

    public static function flushLabels(): void
    {
        self::$labels = null;
    }
}
