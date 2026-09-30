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
    public function definition(): array
    {
        return [
            'title' => fake()->randomElement(['Typhoon Relief Drive', 'Rice Assistance Program', 'Food Pack Distribution']),
            'description' => fake()->sentence(),
            'items_included' => ['5kg Rice', 'Canned Goods', 'Noodles', 'Coffee'],
            'distribution_date' => fake()->dateTimeBetween('now', '+2 months'),
            'status' => 'active',
            'created_by_user_id' => User::factory(),
        ];
    }
}
