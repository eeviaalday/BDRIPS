<?php

namespace Database\Factories;

use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reference_number' => 'BRGY-'.now()->year.'-'.fake()->unique()->numberBetween(1000, 9999),
            'document_type' => fake()->randomElement([
                DocumentRequest::TYPE_CLEARANCE,
                DocumentRequest::TYPE_INDIGENCY,
                DocumentRequest::TYPE_JOBSEEKER,
                DocumentRequest::TYPE_RESIDENCY,
            ]),
            'purpose' => fake()->sentence(),
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ];
    }
}
