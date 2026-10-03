<?php

namespace Database\Factories;

use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentDelivery>
 */
class DocumentDeliveryFactory extends Factory
{
    protected $model = DocumentDelivery::class;

    public function definition(): array
    {
        return [
            // Both relations resolve their own model, so the common case - "a copy
            // went out against a real request" - is one call with no arguments. The
            // overrides exist for the tests that need the delivery attached to a
            // request and an account that are not the ones the factory would invent.
            'document_request_id' => DocumentRequest::factory(),
            'user_id' => User::factory()->resident(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function forRequest(DocumentRequest $documentRequest): static
    {
        return $this->state([
            'document_request_id' => $documentRequest->getKey(),
            // The account that took the copy, not a copy of the request's owner.
            // Overriding both keeps the two honest: a delivery attributed to a
            // stranger would be a bug the tests would then be asserting.
            'user_id' => $documentRequest->user_id,
        ]);
    }

    public function downloadedAt(string $moment): static
    {
        return $this->state([
            'created_at' => $moment,
            'updated_at' => $moment,
        ]);
    }
}
