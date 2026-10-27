<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modello di checklist da copiare negli ordini di lavoro.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 */
class ChecklistTemplate extends Model
{
    protected $fillable = ['name', 'description'];

    /** @return HasMany<ChecklistTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class)->orderBy('position')->orderBy('id');
    }
}
