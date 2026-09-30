<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The secretary's document review desk.
 *
 * The detail screen is where a secretary decides the resident's case, so what
 * matters is that the screen only offers actions the request's status actually
 * allows, and that the two actions which cannot be undone are behind a
 * confirmation rather than a single click.
 */
class SecretaryRequestReviewTest extends TestCase
{
    use RefreshDatabase;

    private function secretary(): User
    {
        return User::factory()->secretary()->create();
    }

    private function requestFor(User $applicant, string $status = DocumentRequest::STATUS_SUBMITTED): DocumentRequest
    {
        return DocumentRequest::factory()->create([
            'user_id' => $applicant->id,
            'status' => $status,
        ]);
    }

    private function verifiedApplicant(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user->refresh();
    }

    public function test_a_submitted_request_offers_review_reject_and_ready_but_not_the_certificate(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('staff.requests.approve', $request), $content);
        $this->assertStringContainsString(route('staff.requests.reject', $request), $content);
        $this->assertStringContainsString(route('staff.requests.ready', $request), $content);
        $this->assertStringNotContainsString(route('staff.requests.claim', $request), $content);
    }

    public function test_a_ready_request_offers_the_certificate_and_claim_but_not_reject(): void
    {
        $request = $this->requestFor($this->verifiedApplicant(), DocumentRequest::STATUS_READY);

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('staff.requests.pdf', $request), $content);
        $this->assertStringContainsString(route('staff.requests.claim', $request), $content);
        $this->assertStringNotContainsString(route('staff.requests.reject', $request), $content);
    }

    public function test_a_closed_request_offers_no_action_at_all(): void
    {
        $request = $this->requestFor($this->verifiedApplicant(), DocumentRequest::STATUS_COMPLETED);

        $content = $this->actingAs($this->secretary())
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('staff.requests.approve', $request), $content);
        $this->assertStringNotContainsString(route('staff.requests.reject', $request), $content);
        $this->assertStringNotContainsString(route('staff.requests.ready', $request), $content);
        $this->assertStringNotContainsString(route('staff.requests.claim', $request), $content);
    }

    public function test_the_rejection_reason_is_typed_inside_the_confirmation_dialog(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs($this->secretary())
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->assertSee('name="rejection_reason"', escape: false)
            ->assertSee('Confirm rejection');
    }

    public function test_the_screen_shows_the_resident_the_same_timeline_they_will_see(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs($this->secretary())
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->assertSee('Lifecycle')
            ->assertSee('aria-current="step"', escape: false);
    }

    public function test_rejecting_requires_a_reason_and_records_it(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs($this->secretary())
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.reject', $request), ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        $this->assertSame(DocumentRequest::STATUS_SUBMITTED, $request->refresh()->status);

        $this->actingAs($this->secretary())
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.reject', $request), ['rejection_reason' => 'Blurry ID photo.'])
            ->assertRedirect(route('staff.requests.show', $request));

        $request->refresh();
        $this->assertSame(DocumentRequest::STATUS_REJECTED, $request->status);
        $this->assertSame('Blurry ID photo.', $request->rejection_reason);
    }

    public function test_approving_can_move_straight_to_ready(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs($this->secretary())
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.approve', $request), [
                'secretary_comment' => 'Checked against the master list.',
                'ready' => '1',
            ])
            ->assertRedirect(route('staff.requests.show', $request));

        $request->refresh();
        $this->assertSame(DocumentRequest::STATUS_READY, $request->status);
        $this->assertNotNull($request->ready_at);
        $this->assertSame('Checked against the master list.', $request->secretary_comment);
    }

    public function test_approving_without_the_ready_box_only_moves_to_review(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs($this->secretary())
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.approve', $request), [])
            ->assertRedirect(route('staff.requests.show', $request));

        $request->refresh();
        $this->assertSame(DocumentRequest::STATUS_UNDER_REVIEW, $request->status);
        $this->assertNull($request->ready_at);
    }

    public function test_claiming_stamps_the_claimed_at_time(): void
    {
        $request = $this->requestFor($this->verifiedApplicant(), DocumentRequest::STATUS_READY);

        $this->actingAs($this->secretary())
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.claim', $request))
            ->assertRedirect(route('staff.requests.show', $request));

        $request->refresh();
        $this->assertSame(DocumentRequest::STATUS_COMPLETED, $request->status);
        $this->assertNotNull($request->claimed_at);
    }

    public function test_a_resident_cannot_open_a_strangers_request(): void
    {
        $request = $this->requestFor($this->verifiedApplicant());

        $this->actingAs(User::factory()->resident()->create(['is_verified' => true]))
            ->get(route('staff.requests.show', $request))
            ->assertForbidden();

        $this->actingAs(User::factory()->kagawad()->create())
            ->get(route('staff.requests.show', $request))
            ->assertForbidden();

        // actingAs() leaves the user on the shared guard, so it has to be
        // dropped before this request is genuinely anonymous.
        auth()->logout();

        $this->get(route('staff.requests.show', $request))->assertRedirect(route('login'));
    }

    public function test_the_queue_defaults_to_the_open_statuses_and_can_be_narrowed(): void
    {
        $applicant = $this->verifiedApplicant();
        $submitted = $this->requestFor($applicant, DocumentRequest::STATUS_SUBMITTED);
        $reviewing = $this->requestFor($applicant, DocumentRequest::STATUS_UNDER_REVIEW);
        $completed = $this->requestFor($applicant, DocumentRequest::STATUS_COMPLETED);
        $abandoned = $this->requestFor($applicant, DocumentRequest::STATUS_ABANDONED);

        $open = $this->actingAs($this->secretary())
            ->get(route('staff.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('staff.requests.show', $submitted), $open);
        $this->assertStringContainsString(route('staff.requests.show', $reviewing), $open);
        $this->assertStringNotContainsString(route('staff.requests.show', $completed), $open);
        $this->assertStringNotContainsString(route('staff.requests.show', $abandoned), $open);

        $closed = $this->actingAs($this->secretary())
            ->get(route('staff.requests.index', ['status' => DocumentRequest::STATUS_COMPLETED]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('staff.requests.show', $completed), $closed);
        $this->assertStringNotContainsString(route('staff.requests.show', $submitted), $closed);
        $this->assertStringNotContainsString(route('staff.requests.show', $abandoned), $closed);
    }
}
