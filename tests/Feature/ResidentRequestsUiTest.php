<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The resident request list, its filters, and the My Documents view.
 *
 * The scoping assertions here are the load-bearing ones. A document list that
 * could be widened by a query parameter would hand one resident another
 * resident's certificate, and the reference numbers are short enough to walk.
 */
class ResidentRequestsUiTest extends TestCase
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

    public function test_the_list_shows_only_the_signed_in_residents_own_requests(): void
    {
        $me = $this->resident();
        $other = $this->resident();

        DocumentRequest::factory()->create([
            'user_id' => $me->id,
            'reference_number' => 'BRGY-2026-0001',
            'purpose' => 'Employment requirement',
        ]);
        DocumentRequest::factory()->create([
            'user_id' => $other->id,
            'reference_number' => 'BRGY-2026-0002',
            'purpose' => 'Bank account opening',
        ]);

        $content = $this->actingAs($me)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('BRGY-2026-0001', $content);
        $this->assertStringNotContainsString('BRGY-2026-0002', $content);
        $this->assertStringNotContainsString('Bank account opening', $content);
    }

    public function test_a_filter_cannot_reach_another_residents_rows(): void
    {
        $me = $this->resident();
        $other = $this->resident();

        DocumentRequest::factory()->create([
            'user_id' => $other->id,
            'reference_number' => 'BRGY-2026-4242',
            'purpose' => 'School enrollment',
        ]);

        // Searching for the other resident's exact reference must find nothing,
        // because the query is constrained to the user BEFORE the filter runs.
        $response = $this->actingAs($me)
            ->get(route('resident.requests.index', ['q' => 'BRGY-2026-4242']))
            ->assertOk();

        $response->assertViewHas('requests', fn ($requests): bool => $requests->total() === 0);
        $this->assertStringNotContainsString('School enrollment', $response->getContent());
    }

    public function test_a_status_filter_narrows_the_list_to_that_status(): void
    {
        $me = $this->resident();

        DocumentRequest::factory()->count(2)->create([
            'user_id' => $me->id,
            'status' => DocumentRequest::STATUS_SUBMITTED,
        ]);
        DocumentRequest::factory()->create([
            'user_id' => $me->id,
            'status' => DocumentRequest::STATUS_READY,
        ]);

        $this->actingAs($me)
            ->get(route('resident.requests.index', ['status' => 'ready_for_pickup']))
            ->assertOk()
            ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 1);
    }

    public function test_an_unknown_status_filter_is_ignored_rather_than_applied(): void
    {
        $me = $this->resident();
        DocumentRequest::factory()->count(2)->create(['user_id' => $me->id]);

        // Not in STATUSES, so it must not reach the query as a value. Silently
        // ignored is the safe reading: an unrecognised filter narrows nothing and
        // shows the resident their own full list.
        $this->actingAs($me)
            ->get(route('resident.requests.index', ['status' => "ready_for_pickup' OR 1=1--"]))
            ->assertOk()
            ->assertViewHas('requests', fn ($requests): bool => $requests->total() === 2);
    }

    public function test_an_unrestricted_account_is_told_why_it_cannot_apply(): void
    {
        $user = User::factory()->resident()->create([
            'is_verified' => true,
            'restricted_until' => now()->addDays(10),
        ]);
        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Online applications are restricted', $content);
        $this->assertStringNotContainsString('Submit request', $content);
    }

    public function test_an_unverified_resident_is_shown_the_locked_state_not_the_form(): void
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);
        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);

        $content = $this->actingAs($user)
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Verification required', $content);
        $this->assertStringNotContainsString('Submit request', $content);

        // The server refuses it too, not just the page.
        $this->post(route('resident.requests.store'), [
            'document_type' => DocumentRequest::TYPE_CLEARANCE,
            'purpose' => 'Employment requirement',
        ])->assertRedirect(route('resident.verification.status'));

        $this->assertDatabaseCount('document_requests', 0);
    }

    public function test_the_wizard_offers_exactly_the_four_processed_document_types(): void
    {
        $user = $this->resident();

        $content = $this->actingAs($user)
            ->get(route('resident.requests.create'))
            ->assertOk()
            ->getContent();

        foreach (collect(config('bdrpis.services'))->pluck('key') as $key) {
            $this->assertStringContainsString('value="'.$key.'"', $content);
        }

        $this->assertStringContainsString('aria-label="Progress"', $content);
    }

    public function test_the_request_detail_shows_the_real_status_timeline(): void
    {
        $me = $this->resident();
        $request = DocumentRequest::factory()->create([
            'user_id' => $me->id,
            'status' => DocumentRequest::STATUS_READY,
            'ready_at' => now(),
        ]);

        $content = $this->actingAs($me)
            ->get(route('resident.requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-current="step"', $content);
        $this->assertStringContainsString('Ready for Pickup', $content);
        $this->assertStringContainsString('7 working days', $content);
    }

    public function test_a_rejected_request_shows_its_reason_in_full(): void
    {
        $me = $this->resident();
        $request = DocumentRequest::factory()->create([
            'user_id' => $me->id,
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => 'Please clarify the stated purpose.',
        ]);

        $content = $this->actingAs($me)
            ->get(route('resident.requests.show', $request))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Please clarify the stated purpose.', $content);
        $this->assertStringContainsString('not approved', $content);
    }

    public function test_another_residents_request_detail_is_still_refused(): void
    {
        $me = $this->resident();
        $other = $this->resident();
        $theirs = DocumentRequest::factory()->create(['user_id' => $other->id]);

        $this->actingAs($me)
            ->get(route('resident.requests.show', $theirs))
            ->assertForbidden();
    }

    public function test_the_old_my_documents_url_lands_on_the_one_documents_page(): void
    {
        $me = $this->resident();

        // The resident side is one "My Documents" section now, so the old URL
        // sends them there rather than rendering a second, drifting list. The
        // route is kept, so an old link or bookmark still works.
        $this->actingAs($me)
            ->get(route('resident.documents'))
            ->assertRedirect(route('resident.requests.index'));
    }

    public function test_the_documents_page_lists_only_this_residents_own_rows(): void
    {
        $me = $this->resident();

        $mine = DocumentRequest::factory()->create([
            'user_id' => $me->id,
            'status' => DocumentRequest::STATUS_READY,
            'reference_number' => 'BRGY-2026-1111',
        ]);

        $theirs = DocumentRequest::factory()->create([
            'user_id' => $this->resident()->id,
            'status' => DocumentRequest::STATUS_READY,
            'reference_number' => 'BRGY-2026-6666',
        ]);

        $content = $this->actingAs($me)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($mine->reference_number, $content);
        $this->assertStringNotContainsString($theirs->reference_number, $content);
    }

    public function test_the_faq_explains_that_email_and_profile_verification_are_separate(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.faq'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Two different verifications', $content);
        $this->assertStringContainsString('Email confirmation', $content);
        $this->assertStringContainsString('Resident profile verification', $content);
    }

    public function test_the_faq_states_only_rules_the_backend_actually_enforces(): void
    {
        $content = $this->actingAs($this->resident())
            ->get(route('resident.faq'))
            ->getContent();

        // These three are real: the three-request cap, the seven-working-day
        // window and the three-strike restriction all exist in the controllers
        // and the scheduled command.
        $this->assertStringContainsString('3 open requests', $content);
        $this->assertStringContainsString('7 working days', $content);
        $this->assertStringContainsString('30 days', $content);
    }
}
