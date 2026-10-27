<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('MAC-###??')),
            'name' => 'Macchina '.fake()->unique()->numberBetween(1, 99999),
            'criticality' => 'B',
            'status' => 'operativa',
        ];
    }
}
