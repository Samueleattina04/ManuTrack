<?php

namespace App\Support\Filters;

use App\Enums\AssetStatus;
use App\Enums\Criticality;
use App\Models\Asset;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtri dell'elenco delle macchine (q, ubicazione, categoria, stato, criticita, ordine),
 * gli stessi per la pagina e per l'esportazione.
 */
final class AssetFilters
{
    public const SORTS = [
        'code' => ['code', 'asc'],
        'name' => ['name', 'asc'],
        'status' => ['status_changed_at', 'desc'],
        'recent' => ['updated_at', 'desc'],
    ];

    public function __construct(
        public string $search = '',
        public string $location = '',
        public string $category = '',
        public string $status = '',
        public string $criticality = '',
        public string $sort = 'code',
    ) {}

    /**
     * @param  array<string, mixed>  $q
     */
    public static function fromQuery(array $q): self
    {
        return new self((string) ($q['q'] ?? ''), (string) ($q['ubicazione'] ?? ''), (string) ($q['categoria'] ?? ''), (string) ($q['stato'] ?? ''), (string) ($q['criticita'] ?? ''), (string) ($q['ordine'] ?? 'code'));
    }

    /**
     * @return Builder<Asset>
     */
    public function query(bool $withStatus = true): Builder
    {
        $location = $this->location !== '' ? Location::query()->find((int) $this->location) : null;

        return Asset::query()
            ->search($this->search)
            ->when($location, fn (Builder $q) => $q->inLocation($location))
            ->when($this->category === 'none', fn (Builder $q) => $q->whereNull('asset_category_id'))
            ->when(ctype_digit($this->category), fn (Builder $q) => $q->where('asset_category_id', (int) $this->category))
            ->when($withStatus && AssetStatus::tryFrom($this->status), fn (Builder $q) => $q->where('status', $this->status))
            ->when(Criticality::tryFrom($this->criticality), fn (Builder $q) => $q->where('criticality', $this->criticality));
    }

    /**
     * @return Builder<Asset>
     */
    public function sorted(): Builder
    {
        [$column, $direction] = self::SORTS[$this->sort] ?? self::SORTS['code'];

        return $this->query()->orderBy($column, $direction)->orderBy('code');
    }
}
