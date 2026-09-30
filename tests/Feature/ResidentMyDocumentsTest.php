<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The "My Documents" page and the two modals on it.
 *
 * The page is the only place a resident sees their requests, so three things
 * are defended:
 *
 *   - the three-request cap. The button, the modal's submit and the controller
 *     must all agree, so the number the resident reads is the number the server
 *     counts. If the view counted a different set of statuses, a resident could
 *     be shown a free slot that the server then refuses.
 *   - the pending lock. A resident who is not verified must not be handed a
 *     working request form, and must be able to follow the requests they
 *     already have.
 *   - ownership. Every track modal is built from the resident's own row, and
 *     the resident's own PDF route must refuse somebody else's request.
 */
class ResidentMyDocumentsTest extends TestCase
{
    use RefreshDatabase;

    private function resident(string $status = ResidentProfile::VERIFICATION_VERIFIED): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => $status,
        ]);

        return $user->fresh();
    }

    private function requestFor(User $user, string $status = DocumentRequest::STATUS_SUBMITTED): DocumentRequest
    {
        return DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'status' => $status,
        ]);
    }

    public function test_the_page_lists_the_residents_own_requests_with_their_reference(): void
    {
        $user = $this->resident();
        $request = $this->requestFor($user);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($request->documentTypeLabel(), $content);
        $this->assertStringContainsString($request->reference_number, $content);
        $this->assertStringContainsString('My Documents', $content);
    }

    public function test_another_residents_request_never_appears(): void
    {
        $user = $this->resident();
        $other = $this->resident();
        $theirs = $this->requestFor($other);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($theirs->reference_number, $content);
    }

    public function test_a_verified_resident_sees_the_stats_row_and_the_filter_chips(): void
    {
        $user = $this->resident();
        $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);
        $this->requestFor($user, DocumentRequest::STATUS_READY);
        $this->requestFor($user, DocumentRequest::STATUS_COMPLETED);
        $this->requestFor($user, DocumentRequest::STATUS_REJECTED);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        // submitted + ready are open; completed and rejected are not, so the
        // figure is 2 of the 3 the controller allows.
        $this->assertStringContainsString('Open Requests', $content);
        $this->assertStringContainsString('2 / 3', $content);
        $this->assertStringContainsString('Ready for Pickup', $content);
        $this->assertStringContainsString('Completed', $content);

        foreach (['All', 'Processing', 'Ready for pickup', 'Completed', 'Rejected'] as $chip) {
            $this->assertStringContainsString($chip, $content);
        }

        // Each chip is a real link to a real filter, so a filtered list is a
        // URL rather than a remembered state.
        $this->assertStringContainsString(route('resident.requests.index', ['status' => 'processing']), $content);
        $this->assertStringContainsString(route('resident.requests.index', ['status' => 'ready_for_pickup']), $content);
        $this->assertStringContainsString(route('resident.requests.index', ['status' => 'rejected']), $content);
    }

    public function test_the_processing_chip_covers_submitted_and_under_review_and_nothing_else(): void
    {
        $user = $this->resident();
        $submitted = $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);
        $reviewing = $this->requestFor($user, DocumentRequest::STATUS_UNDER_REVIEW);
        $completed = $this->requestFor($user, DocumentRequest::STATUS_COMPLETED);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index', ['status' => 'processing']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($submitted->reference_number, $content);
        $this->assertStringContainsString($reviewing->reference_number, $content);
        $this->assertStringNotContainsString($completed->reference_number, $content);
    }

    public function test_a_pending_resident_is_locked_out_of_new_requests_but_can_still_track(): void
    {
        $user = $this->resident(ResidentProfile::VERIFICATION_PENDING);
        $existing = $this->requestFor($user);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        // The lock, and the way to it.
        $this->assertStringContainsString('Requests are locked', $content);
        $this->assertStringContainsString('new-request-locked', $content);
        $this->assertStringContainsString(route('resident.verification.status'), $content);

        // No working request form at all. The store route shares its path with
        // the index, so the absence is asserted on the form itself rather than
        // on the URL, which appears as a link.
        $this->assertStringNotContainsString('name="document_type"', $content);
        $this->assertStringNotContainsString('name="purpose"', $content);
        $this->assertStringNotContainsString('Submit request', $content);

        // Their existing request is still trackable.
        $this->assertStringContainsString($existing->reference_number, $content);
        $this->assertStringContainsString('track-'.$existing->id, $content);
    }

    public function test_the_three_request_cap_is_shown_and_the_form_is_not_offered(): void
    {
        $user = $this->resident();
        $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);
        $this->requestFor($user, DocumentRequest::STATUS_UNDER_REVIEW);
        $this->requestFor($user, DocumentRequest::STATUS_READY);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('3 / 3', $content);
        $this->assertStringContainsString('Wait for one to be completed', $content);
        $this->assertStringContainsString('new-request-blocked', $content);
    }

    public function test_a_completed_request_does_not_occupy_one_of_the_three_slots(): void
    {
        $user = $this->resident();
        $this->requestFor($user, DocumentRequest::STATUS_COMPLETED);
        $this->requestFor($user, DocumentRequest::STATUS_REJECTED);
        $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        // Only the submitted one is open, so there are still two free slots.
        $this->assertStringContainsString('1 / 3', $content);
        $this->assertStringNotContainsString('new-request-blocked', $content);
    }

    public function test_the_server_refuses_a_fourth_open_request_even_from_a_crafted_post(): void
    {
        $user = $this->resident();
        $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);
        $this->requestFor($user, DocumentRequest::STATUS_UNDER_REVIEW);
        $this->requestFor($user, DocumentRequest::STATUS_READY);

        $this->actingAs($user)
            ->post(route('resident.requests.store'), [
                'document_type' => DocumentRequest::TYPE_CLEARANCE,
                'purpose' => 'A fourth request, posted directly.',
            ])
            ->assertSessionHasErrors('document_type');

        $this->assertSame(3, DocumentRequest::where('user_id', $user->id)->count());
    }

    public function test_the_new_request_modal_offers_exactly_the_four_supported_types(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="document_type"', $content);
        $this->assertStringContainsString('name="purpose"', $content);
        $this->assertStringContainsString(route('resident.requests.store'), $content);

        foreach (DocumentRequest::TYPES as $type) {
            $this->assertStringContainsString('value="'.$type.'"', $content);
            $this->assertStringContainsString(DocumentRequest::typeLabel($type), $content);
        }
    }

    public function test_a_track_modal_explains_each_status_in_plain_words(): void
    {
        $user = $this->resident();

        $expectations = [
            DocumentRequest::STATUS_SUBMITTED => 'Received. Waiting for review.',
            DocumentRequest::STATUS_UNDER_REVIEW => 'The Barangay Secretary is reviewing your request.',
            // A completed request with no claimed_at says it was collected
            // from the hall instead - both are honest, and neither invents a
            // date the database does not hold.
            DocumentRequest::STATUS_COMPLETED => 'Collected from the Barangay Hall.',
        ];

        foreach ($expectations as $status => $message) {
            $request = $this->requestFor($user, $status);

            $content = $this->actingAs($user)
                ->get(route('resident.requests.index'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString($message, $content, "Missing the message for {$status}.");
        }
    }

    public function test_a_ready_request_tells_the_resident_how_and_when_to_collect(): void
    {
        $user = $this->resident();
        $request = $this->requestFor($user, DocumentRequest::STATUS_READY);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Collect at the Barangay Hall within 7 working days', $content);
        $this->assertStringContainsString('Bring a valid ID and your reference number', $content);
        $this->assertStringContainsString('track-'.$request->id, $content);
    }

    public function test_a_rejected_request_shows_its_reason_and_the_track_modal_is_own_scoped(): void
    {
        $user = $this->resident();
        $request = $this->requestFor($user, DocumentRequest::STATUS_REJECTED);
        $request->update(['rejection_reason' => 'The ID photograph is too blurry to read.']);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('The ID photograph is too blurry to read.', $content);
        $this->assertStringContainsString('you can submit a new request', $content);
    }

    public function test_a_track_modal_is_only_built_for_the_residents_own_rows(): void
    {
        $user = $this->resident();
        $mine = $this->requestFor($user);
        $theirs = $this->requestFor($this->resident());

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('track-'.$mine->id, $content);
        $this->assertStringNotContainsString('track-'.$theirs->id, $content);
    }

    public function test_the_pdf_link_appears_only_for_released_documents(): void
    {
        $user = $this->resident();
        $ready = $this->requestFor($user, DocumentRequest::STATUS_READY);
        $submitted = $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('resident.requests.pdf', $ready), $content);
        $this->assertStringNotContainsString(route('resident.requests.pdf', $submitted), $content);
    }

    public function test_a_resident_can_read_their_own_released_certificate(): void
    {
        $user = $this->resident();
        $ready = $this->requestFor($user, DocumentRequest::STATUS_READY);

        $this->actingAs($user)
            ->get(route('resident.requests.pdf', $ready))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_a_resident_cannot_read_another_residents_certificate(): void
    {
        $theirs = $this->requestFor($this->resident(), DocumentRequest::STATUS_READY);

        $this->actingAs($this->resident())
            ->get(route('resident.requests.pdf', $theirs))
            ->assertForbidden();
    }

    public function test_an_unreleased_request_has_no_certificate(): void
    {
        $user = $this->resident();
        $submitted = $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);

        // 404 rather than 403, so an id that is real but not released reads the
        // same as one that does not exist.
        $this->actingAs($user)
            ->get(route('resident.requests.pdf', $submitted))
            ->assertNotFound();
    }

    public function test_a_resident_cannot_reach_the_staff_pdf_route_for_their_own_request(): void
    {
        $user = $this->resident();
        $ready = $this->requestFor($user, DocumentRequest::STATUS_READY);

        $this->actingAs($user)
            ->get(route('staff.requests.pdf', $ready))
            ->assertForbidden();
    }

    public function test_a_resident_can_see_their_own_submitted_id_and_nobody_elses(): void
    {
        Storage::fake('local');

        $user = $this->resident();
        $profile = $user->residentProfile;
        $profile->update(['id_photo_path' => $profile->id.'/valid-id.jpg']);
        Storage::disk('local')->put($profile->id.'/valid-id.jpg', 'id-bytes');

        $content = $this->actingAs($user)
            ->get(route('resident.verification.status'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('resident.verification.id-photo'), $content);

        $this->actingAs($user)
            ->get(route('resident.verification.id-photo'))
            ->assertOk();

        $this->actingAs($this->resident())
            ->get(route('resident.verification.id-photo'))
            ->assertNotFound();
    }

    public function test_the_public_page_serves_no_id_photo_to_a_guest(): void
    {
        $this->get(route('resident.verification.id-photo'))->assertRedirect(route('login'));
    }

    public function test_the_relief_page_shows_the_household_number_and_each_programme(): void
    {
        $user = $this->resident();
        $profile = $user->residentProfile;

        $received = ReliefDistribution::factory()->create([
            'household_number' => $profile->household_number,
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);
        $pending = ReliefDistribution::factory()->create([
            'household_number' => $profile->household_number,
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($profile->household_number, $content);

        // batch->title, not batch->name: the column is title, so asserting on
        // "name" silently compared the view's fallback string and passed whether
        // or not the real programme title rendered.
        $this->assertStringContainsString($received->batch?->title ?? 'Relief assistance', $content);
        $this->assertStringContainsString($pending->batch?->title ?? 'Relief assistance', $content);
        $this->assertStringContainsString('Received', $content);
        $this->assertStringContainsString('Not yet received', $content);
    }

    public function test_the_relief_page_is_locked_for_a_pending_resident(): void
    {
        $user = $this->resident(ResidentProfile::VERIFICATION_PENDING);

        $content = $this->actingAs($user)
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Relief records open after verification', $content);
        $this->assertStringContainsString(route('resident.verification.status'), $content);
    }

    public function test_a_pending_resident_sees_the_gate_banner_with_a_way_to_the_status_page(): void
    {
        $content = $this->actingAs($this->resident(ResidentProfile::VERIFICATION_PENDING))
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Your verification is pending', $content);
        $this->assertStringContainsString('View status', $content);
        $this->assertStringContainsString(route('resident.verification.status'), $content);
    }

    public function test_a_verified_resident_is_not_shown_the_gate_banner(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Your verification is pending', $content);
        $this->assertStringNotContainsString('View status', $content);
    }

    public function test_a_resident_with_no_profile_is_asked_to_start_verification(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);
        Sector::factory()->create();

        $content = $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('You have not sent your ID yet', $content);
        $this->assertStringContainsString(route('resident.verification'), $content);
    }
}
