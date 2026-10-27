<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Categoria di macchina (presse, compressori, nastri…).
 *
 * @property int $id
 * @property string $name
 * @property string $icon
 * @property string $shape
 * @property string|null $description
 */
class AssetCategory extends Model
{
    /** Icone proposte per le categorie */
    public const ICONS = ['factory', 'cog', 'gauge', 'zap', 'flame', 'droplet', 'wind', 'truck', 'layers', 'box', 'cylinder', 'bot', 'wrench', 'thermometer', 'monitor'];

    /** Forme generiche per la vista 3D (fase 10) */
    public const SHAPES = ['box', 'cylinder', 'press', 'conveyor', 'tank', 'robot'];

    protected $fillable = ['name', 'icon', 'shape', 'description'];

    /** @return HasMany<Asset, $this> */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
