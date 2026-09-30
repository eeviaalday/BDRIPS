<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The resident's own certificate, from the tracking page.
 *
 * The endpoint existed and worked, but nothing on the page linked to it - a
 * comment on the page claimed no such endpoint existed at all. So the
 * certificate was only reachable by hand-typing a URL.
 */
class ResidentRequestPdfActionTest extends TestCase
{
    use RefreshDatabase;

    private function resident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user->fresh();
    }

    /**
     * Statuses where a certificate has actually been released.
     *
     * @return array<string, array{0: string}>
     */
    public static function releasedStatusProvider(): array
    {
        return [
            'waiting to be collected' => [DocumentRequest::STATUS_READY],
            'already collected' => [DocumentRequest::STATUS_COMPLETED],
        ];
    }

    #[Test]
    #[DataProvider('releasedStatusProvider')]
    public function the_tracking_page_offers_the_certificate_once_it_is_released(string $status): void
    {
        $user = $this->resident();
        $request = DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.show', $request, absolute: false))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('View PDF', $content);
        $this->assertStringContainsString(route('resident.requests.pdf', $request, absolute: false), $content);
    }

    /**
     * Statuses where there is nothing to hand over yet.
     *
     * @return array<string, array{0: string}>
     */
    public static function unreleasedStatusProvider(): array
    {
        return [
            'just submitted' => [DocumentRequest::STATUS_SUBMITTED],
            'under review' => [DocumentRequest::STATUS_UNDER_REVIEW],
            'turned down' => [DocumentRequest::STATUS_REJECTED],
            'abandoned' => [DocumentRequest::STATUS_ABANDONED],
        ];
    }

    #[Test]
    #[DataProvider('unreleasedStatusProvider')]
    public function the_tracking_page_offers_nothing_before_the_certificate_exists(string $status): void
    {
        $user = $this->resident();
        $request = DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.show', $request, absolute: false))
            ->assertOk()
            ->getContent();

        // Offering a download that 404s would be worse than offering nothing.
        $this->assertStringNotContainsString('View PDF', $content);
    }

    #[Test]
    public function a_resident_still_cannot_read_somebody_elses_certificate(): void
    {
        // The link being on the page must not become a way round the ownership
        // check that the endpoint enforces.
        $user = $this->resident();

        $theirs = DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'status' => DocumentRequest::STATUS_READY,
        ]);

        $stranger = $this->resident();

        $this->actingAs($stranger)
            ->get(route('resident.requests.pdf', $theirs))
            ->assertForbidden();
    }
}
