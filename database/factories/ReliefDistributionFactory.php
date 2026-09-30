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
    protected $model = ReliefDistribution::class;

    public function definition(): array
    {
        return [
            'relief_batch_id' => ReliefBatch::factory(),
            'household_number' => 'HH-'.fake()->unique()->numberBetween(1001, 9999),
            'sector_id' => Sector::factory(),
            'status' => ReliefDistribution::STATUS_PENDING,
        ];
    }

    public function received(?User $by = null): static
    {
        return $this->state([
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => $by?->name ?? fake()->name(),
            'logged_by_user_id' => $by?->id,
            'claimed_at' => now()->subHour(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(['status' => ReliefDistribution::STATUS_PENDING]);
    }

    public function forSector(Sector $sector): static
    {
        return $this->state(['sector_id' => $sector->id]);
    }

    public function forBatch(ReliefBatch $batch): static
    {
        return $this->state(['relief_batch_id' => $batch->id]);
    }
}
