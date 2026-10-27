<?php

namespace App\Models;

use App\Enums\FailureCodeKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Codice guasto: problema (cosa si vede), causa, rimedio.
 *
 * @property int $id
 * @property FailureCodeKind $kind
 * @property string $code
 * @property string $label
 * @property bool $is_active
 */
class FailureCode extends Model
{
    protected $fillable = ['kind', 'code', 'label', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['kind' => FailureCodeKind::class, 'is_active' => 'boolean'];
    }

    /** @param Builder<FailureCode> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function display(): string
    {
        return $this->code.' · '.$this->label;
    }
}
