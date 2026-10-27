<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Fornitore di ricambi.
 *
 * @property int $id
 * @property string $name
 * @property string|null $contact_name
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $website
 * @property string|null $notes
 * @property bool $is_active
 * @property-read PartSupplier $pivot presente quando il fornitore arriva da Part::suppliers()
 */
class Supplier extends Model
{
    protected $fillable = ['name', 'contact_name', 'email', 'phone', 'website', 'notes', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsToMany<Part, $this, PartSupplier, 'pivot'> */
    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(Part::class)->using(PartSupplier::class)
            ->withPivot(['id', 'supplier_code', 'price', 'lead_time_days', 'is_preferred'])->withTimestamps();
    }

    /** @param Builder<Supplier> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
