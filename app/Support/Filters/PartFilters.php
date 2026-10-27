<?php

namespace App\Support\Filters;

use App\Models\Part;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtri dell'elenco dei ricambi (q, scorta, magazzino, categoria, disattivati),
 * gli stessi per la pagina e per l'esportazione.
 */
final class PartFilters
{
    public function __construct(
        public string $search = '',
        public string $stock = '',
        public string $warehouse = '',
        public string $category = '',
        public bool $inactive = false,
    ) {}

    /**
     * @param  array<string, mixed>  $q
     */
    public static function fromQuery(array $q): self
    {
        return new self((string) ($q['q'] ?? ''), (string) ($q['scorta'] ?? ''), (string) ($q['magazzino'] ?? ''), (string) ($q['categoria'] ?? ''), filter_var($q['disattivati'] ?? false, FILTER_VALIDATE_BOOLEAN));
    }

    /**
     * @return Builder<Part>
     */
    public function query(bool $withStock = true): Builder
    {
        return Part::query()
            ->where('is_active', ! $this->inactive)
            ->search($this->search)
            ->when($this->category !== '', fn (Builder $q) => $q->where('category', $this->category))
            ->when(ctype_digit($this->warehouse), fn (Builder $q) => $q->whereHas('levels', fn (Builder $l) => $l->where('warehouse_id', (int) $this->warehouse)->where('quantity', '>', 0)))
            ->when($withStock && $this->stock === 'sotto', fn (Builder $q) => $q->belowMinimum())
            ->when($withStock && $this->stock === 'esauriti', fn (Builder $q) => $q->where('stock_total', '<=', 0));
    }
}
