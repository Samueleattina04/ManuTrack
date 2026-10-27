<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Numerazione progressiva per anno (OdL-2026-00042, SEG-2026-00007).
 * La riga è bloccata durante l'incremento: due richieste insieme non
 * ottengono mai lo stesso numero.
 *
 * @property int $id
 * @property string $name
 * @property int $year
 * @property int $last
 */
class Sequence extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'year', 'last'];

    public static function next(string $name, ?int $year = null): int
    {
        $year ??= (int) now()->year;

        return DB::transaction(function () use ($name, $year) {
            Sequence::query()->insertOrIgnore(['name' => $name, 'year' => $year, 'last' => 0]);
            /** @var Sequence $row */
            $row = Sequence::query()->where('name', $name)->where('year', $year)->lockForUpdate()->firstOrFail();
            $row->increment('last');

            return $row->last;
        });
    }

    public static function format(string $prefix, int $number, ?int $year = null): string
    {
        return sprintf('%s-%d-%05d', $prefix, $year ?? now()->year, $number);
    }
}
