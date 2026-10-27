<?php

namespace App\Support\Export;

use Closure;

/**
 * Una tabella da esportare: un foglio in Excel, un file in CSV.
 *
 * Tipi di colonna: text, int, number (fino a 2 cifre), decimal (2 cifre), quantity (fino a 3 cifre),
 * hours (1 cifra), percent (1 cifra), date, datetime.
 */
final class Table
{
    /**
     * @param  array<string, string>  $columns  intestazione => tipo
     * @param  iterable<array<int, mixed>>|Closure(): iterable<array<int, mixed>>  $rows
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        private readonly iterable|Closure $rows,
    ) {}

    /** @return iterable<array<int, mixed>> */
    public function rows(): iterable
    {
        return $this->rows instanceof Closure ? ($this->rows)() : $this->rows;
    }

    /** @return list<string> */
    public function types(): array
    {
        return array_values($this->columns);
    }
}
