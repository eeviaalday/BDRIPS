<?php

namespace Database\Factories;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReliefDistribution>
 */
class ReliefDistributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'relief_batch_id' => ReliefBatch::factory(),
            'household_number' => 'HH-'.fake()->unique()->numberBetween(1001, 9999),
            'sector_id' => Sector::factory(),
            'status' => 'pending',
            'logged_by_user_id' => null,
        ];
    }

    public function received(): static
    {
        return $this->state([
            'status' => 'received',
            'received_by_name' => fake()->name(),
            'claimed_at' => now(),
            'logged_by_user_id' => User::factory(),
        ]);
    }
}
