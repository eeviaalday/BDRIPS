<?php

namespace Database\Factories;

use App\Models\DocumentType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentType>
 */
class DocumentTypeFactory extends Factory
{
    protected $model = DocumentType::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'label' => rtrim(fake()->sentence(3), '.'),
            'description' => fake()->sentence(12),
            'fee_amount' => fake()->randomElement([0, 50, 100]),
            'icon' => 'document',
            'tone' => 'navy',
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    /**
     * Retired: hidden from the chooser, but requests already filed under it
     * still resolve, which is why retiring is a flag and not a delete.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function free(): static
    {
        return $this->state(['fee_amount' => 0]);
    }
}
