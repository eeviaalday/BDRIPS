<?php

namespace Database\Factories;

use App\Models\Sector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sector>
 */
class SectorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sector_name' => 'Sector '.fake()->numberBetween(1, 10),
            'purok_name' => 'Purok '.fake()->lastName(),
        ];
    }
}
