<?php

use App\Models\Part;
use App\Models\Warehouse;

function makeWarehouse(string $name = 'Magazzino centrale', bool $active = true): Warehouse
{
    return Warehouse::query()->create(['name' => $name, 'is_active' => $active]);
}

function makePart(array $attributes = []): Part
{
    static $n = 0;
    $n++;

    return Part::query()->create(array_merge(['code' => 'RIC-'.$n, 'name' => 'Ricambio di prova '.$n, 'unit' => 'pz', 'min_stock' => 0], $attributes));
}

/** CSV come lo salva Excel in italiano: ";" e Windows-1252 (usato dai test di importazione) */
function excelCsv(array $rows): string
{
    $lines = array_map(fn ($r) => implode(';', array_map(fn ($c) => str_contains((string) $c, ';') ? '"'.$c.'"' : $c, $r)), $rows);

    return mb_convert_encoding(implode("\r\n", $lines)."\r\n", 'Windows-1252', 'UTF-8');
}
