<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResidentDocumentRequestDetailTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedResident(): User
    {
        $sector = Sector::factory()->create();
        $user = User::factory()->resident()->create();

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
            'verified_at' => now(),
        ]);

        $user->update(['is_verified' => true, 'assigned_sector_id' => $sector->id]);

        return $user->fresh();
    }

    private function requestFor(User $user, string $status = DocumentRequest::STATUS_SUBMITTED): DocumentRequest
    {
        return DocumentRequest::factory()->create([
            'user_id' => $user->id,
            'reference_number' => DocumentRequest::generateReferenceNumber(),
            'status' => $status,
        ]);
    }

    public function test_a_verified_resident_can_open_their_own_document_request_detail_page(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user);

        $this->actingAs($user)
            ->get(route('resident.requests.show', $documentRequest))
            ->assertOk();
    }

    public function test_the_detail_page_displays_the_reference_number_and_status(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user, DocumentRequest::STATUS_UNDER_REVIEW);

        $this->actingAs($user)
            ->get(route('resident.requests.show', $documentRequest))
            ->assertOk()
            ->assertSee($documentRequest->reference_number)
            ->assertSee($documentRequest->documentTypeLabel())
            ->assertSee($documentRequest->purpose);
    }

    public function test_a_resident_cannot_open_another_residents_document_request(): void
    {
        $owner = $this->verifiedResident();
        $intruder = $this->verifiedResident();

        $documentRequest = $this->requestFor($owner);

        $this->actingAs($intruder)
            ->get(route('resident.requests.show', $documentRequest))
            ->assertForbidden();
    }

    public function test_a_guest_cannot_open_the_resident_document_request_detail_page(): void
    {
        $documentRequest = $this->requestFor($this->verifiedResident());

        $this->get(route('resident.requests.show', $documentRequest))
            ->assertRedirect(route('login'));
    }

    public function test_a_kagawad_cannot_access_the_resident_only_document_request_detail_route(): void
    {
        $documentRequest = $this->requestFor($this->verifiedResident());
        $kagawad = User::factory()->kagawad()->create();

        $this->actingAs($kagawad)
            ->get(route('resident.requests.show', $documentRequest))
            ->assertForbidden();
    }

    public function test_staff_cannot_reach_a_residents_request_through_the_resident_ownership_route(): void
    {
        $documentRequest = $this->requestFor($this->verifiedResident());

        $this->actingAs(User::factory()->secretary()->create())
            ->get(route('resident.requests.show', $documentRequest))
            ->assertForbidden();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('resident.requests.show', $documentRequest))
            ->assertForbidden();
    }

    public function test_an_unverified_email_cannot_open_the_document_request_detail_page(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($user->fresh())
            ->get(route('resident.requests.show', $documentRequest))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_an_unknown_document_request_returns_not_found(): void
    {
        $user = $this->verifiedResident();

        $this->actingAs($user)
            ->get('/resident/requests/999999')
            ->assertNotFound();
    }

    public function test_the_detail_page_renders_for_every_request_status(): void
    {
        $user = $this->verifiedResident();

        foreach (DocumentRequest::STATUSES as $status) {
            $documentRequest = $this->requestFor($user, $status);

            $this->actingAs($user)
                ->get(route('resident.requests.show', $documentRequest))
                ->assertOk();
        }
    }

    public function test_the_detail_page_shows_the_rejection_reason_and_secretary_comment(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user, DocumentRequest::STATUS_REJECTED);
        $documentRequest->update([
            'rejection_reason' => 'Please clarify the stated purpose.',
            'secretary_comment' => 'Resident visited the hall for clarification.',
        ]);

        $this->actingAs($user)
            ->get(route('resident.requests.show', $documentRequest->fresh()))
            ->assertOk()
            ->assertSee('Please clarify the stated purpose.')
            ->assertSee('Resident visited the hall for clarification.');
    }

    public function test_the_detail_page_shows_the_pickup_reminder_only_when_ready(): void
    {
        $user = $this->verifiedResident();

        $ready = $this->requestFor($user, DocumentRequest::STATUS_READY);
        $ready->update(['ready_at' => now()]);

        $this->actingAs($user)
            ->get(route('resident.requests.show', $ready))
            ->assertOk()
            ->assertSee('Please claim your document at the Barangay Hall within 7 working days.');

        $submitted = $this->requestFor($user, DocumentRequest::STATUS_SUBMITTED);

        $this->actingAs($user)
            ->get(route('resident.requests.show', $submitted))
            ->assertOk()
            ->assertDontSee('Please claim your document at the Barangay Hall within 7 working days.');
    }

    public function test_the_index_page_links_to_the_detail_page_that_now_resolves(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user);

        $this->actingAs($user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee(route('resident.requests.show', $documentRequest), false);
    }

    public function test_the_detail_route_resolves_by_primary_key(): void
    {
        $user = $this->verifiedResident();
        $documentRequest = $this->requestFor($user);

        $this->assertSame(
            url('/resident/requests/'.$documentRequest->id),
            route('resident.requests.show', $documentRequest)
        );
    }
}
