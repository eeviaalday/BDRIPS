<?php

namespace Database\Factories;

use App\Models\ReliefBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReliefBatch>
 */
class ReliefBatchFactory extends Factory
{
    protected $model = ReliefBatch::class;

    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(4), '.'),
            'description' => fake()->sentence(14),
            'items_included' => ['5kg Rice', '6 Canned Goods', '3 Instant Noodles'],
            'distribution_date' => now()->addDays(3)->toDateString(),
            'status' => ReliefBatch::STATUS_ACTIVE,
            'created_by_user_id' => User::factory()->secretary(),
        ];
    }

    public function closed(): static
    {
        return $this->state([
            'status' => ReliefBatch::STATUS_CLOSED,
            'closed_at' => now()->subDay(),
        ]);
    }

    public function createdBy(User $user): static
    {
        return $this->state(['created_by_user_id' => $user->id]);
    }
}
