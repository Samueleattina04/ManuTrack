<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $checklist_template_id
 * @property int $position
 * @property string $label
 * @property string $kind spunta | valore
 * @property string|null $unit
 * @property bool $required
 */
class ChecklistTemplateItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['position', 'label', 'kind', 'unit', 'required'];

    protected function casts(): array
    {
        return ['required' => 'boolean'];
    }
}
