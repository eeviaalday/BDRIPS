<?php

namespace Database\Factories;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    protected $model = DocumentRequest::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->resident(),
            'document_type_id' => DocumentType::factory(),
            'reference_number' => 'BRGY-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'purpose' => fake()->sentence(8),
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ];
    }

    /**
     * Belong to a given resident. Named forResident() rather than for() because
     * Factory already defines for() for its own relationship helper, and
     * overriding it with an incompatible signature is a fatal error.
     */
    public function forResident(User $user): static
    {
        return $this->state(['user_id' => $user->id]);
    }

    public function status(string $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function submitted(): static
    {
        return $this->status(DocumentRequest::STATUS_SUBMITTED);
    }

    public function underReview(): static
    {
        return $this->status(DocumentRequest::STATUS_UNDER_REVIEW);
    }

    public function ready(): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_READY,
            'reviewed_at' => now()->subDay(),
            'ready_at' => now()->subHours(4),
        ]);
    }

    public function claimed(): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_CLAIMED,
            'reviewed_at' => now()->subDays(2),
            'ready_at' => now()->subDays(1),
            'claimed_at' => now()->subHours(6),
        ]);
    }

    /**
     * Marked unclaimed: ready, then left past the window.
     *
     * The default dates are a deliberate convenience for the strike tests - an
     * unclaimed row is always also a strike candidate - but `unclaimedAt()` is
     * there for the tests that are about the boundaries, where a strike dated
     * "now" would be the wrong fixture for every assertion in the file.
     */
    public function unclaimed(?string $unclaimedAt = null, bool $excused = false): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_UNCLAIMED,
            'reviewed_at' => now()->subDays(20),
            'ready_at' => now()->subDays(16),
            'unclaimed_at' => $unclaimedAt === null
                ? now()->subDays(2)
                : Carbon::parse($unclaimedAt),
            'unclaimed_excused_at' => $excused ? now() : null,
        ]);
    }

    /**
     * Withdrawn by the resident before it was ready.
     */
    public function cancelled(): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_CANCELLED,
            'reviewed_at' => null,
            'ready_at' => null,
            'cancelled_at' => now()->subHour(),
        ]);
    }

    /**
     * A rejection always carries a reason here, because the one state a resident
     * cannot act on must never be reachable without an explanation. The model
     * exposes needsRejectionReason() for the path that tries to.
     */
    public function rejected(?string $reason = null): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => $reason ?? 'The stated purpose could not be read.',
            'reviewed_at' => now()->subDay(),
        ]);
    }

    /**
     * A rejection with no reason, for the test that proves the form request
     * refuses it. Not a state the application can reach on purpose.
     */
    public function rejectedWithoutReason(): static
    {
        return $this->state([
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => null,
            'reviewed_at' => now()->subDay(),
        ]);
    }
}
