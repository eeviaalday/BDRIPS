<?php

namespace Database\Factories;

use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResidentProfile>
 */
class ResidentProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'household_number' => 'HH-'.fake()->unique()->numberBetween(1001, 9999),
            'national_or_voter_id' => fake()->unique()->bothify('####-####-####'),
            'address' => fake()->streetAddress().', San Isidro',
            'sector_id' => Sector::factory(),
            'id_photo_path' => null,
            'verification_status' => 'verified',
            'verified_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'verification_status' => 'pending',
            'verified_at' => null,
        ]);
    }
}
