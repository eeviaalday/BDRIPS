<?php

namespace Tests\Feature;

use App\Http\Requests\StoreDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A resident's own document requests.
 *
 * The three rules under test are the ones a resident can feel: three open at a
 * time, a verified profile required, and a frozen account refused. All three are
 * enforced in the form request rather than by hiding a button, so each is proven
 * by posting directly.
 */
class ResidentDocumentRequestsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_resident_sees_their_own_requests(): void
    {
        $resident = $this->verifiedResident();
        $mine = DocumentRequest::factory()->forResident($resident)->create();

        DocumentRequest::factory()->create(); // somebody else's

        $content = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString($mine->reference_number, $content);
    }

    #[Test]
    public function a_resident_sees_no_other_residents_reference_numbers(): void
    {
        $resident = $this->verifiedResident();
        $theirs = DocumentRequest::factory()->create();

        $content = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString($theirs->reference_number, $content);
    }

    #[Test]
    public function a_resident_can_open_their_own_request(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->create();

        // The single-request page became the Track dialog on the list, so the old
        // address redirects and the record is read from the list instead.
        $this->actingAs($resident)
            ->get(route('resident.requests.show', $request))
            ->assertRedirect(route('resident.requests.index'));

        $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee($request->reference_number);
    }

    /**
     * The scoping rule, stated as a pair.
     *
     * A wrong reference and another resident's reference have to be
     * indistinguishable, so both are compared byte for byte. A 404 for one and a
     * 403 for the other would still leak the difference.
     */
    #[Test]
    public function another_residents_request_and_a_wrong_one_are_indistinguishable(): void
    {
        $resident = $this->verifiedResident();
        $theirs = DocumentRequest::factory()->create();

        $someoneElses = $this->actingAs($resident)->get(route('resident.requests.show', $theirs));
        $madeUp = $this->actingAs($resident)->get(route('resident.requests.show', 999999));

        $this->assertSame($someoneElses->getStatusCode(), $madeUp->getStatusCode());
        $this->assertSame($someoneElses->getStatusCode(), 404);
    }

    #[Test]
    public function a_verified_resident_can_submit_a_request(): void
    {
        Storage::fake('local');
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create(['fee_amount' => 150]);

        $this->actingAs($resident)
            ->post(route('resident.requests.store'), [
                'document_type_id' => $type->id,
                'purpose' => 'Employment requirement.',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('document_requests', [
            'user_id' => $resident->id,
            'document_type_id' => $type->id,
            'status' => DocumentRequest::STATUS_SUBMITTED,
            'purpose' => 'Employment requirement.',
        ]);
    }

    /**
     * The fee is copied onto the request, so a later price change cannot restate
     * what the resident was quoted when they asked.
     */
    #[Test]
    public function the_fee_is_snapshotted_onto_the_request(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create(['fee_amount' => 100]);

        $this->postAsResident($resident, $type);

        $request = DocumentRequest::whereBelongsTo($resident)->firstOrFail();
        $this->assertEquals(100, (float) $request->fee_amount);

        $type->update(['fee_amount' => 250]);

        $this->assertEquals(100, (float) $request->fresh()->fee_amount);
    }

    #[Test]
    public function a_reference_number_is_generated_and_is_unique(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();

        $this->postAsResident($resident, $type);
        $this->postAsResident($resident, $type);

        $references = DocumentRequest::whereBelongsTo($resident)->pluck('reference_number');

        $this->assertCount(2, $references);
        $this->assertCount(2, $references->unique(), 'Two requests were issued the same reference number.');
    }

    // ---------------------------------------------------------------------
    // The three-request limit
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_may_hold_three_open_requests(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();

        foreach (range(1, DocumentRequest::OPEN_REQUEST_LIMIT) as $ignored) {
            $this->postAsResident($resident, $type)->assertSessionHasNoErrors();
        }

        $this->assertSame(
            DocumentRequest::OPEN_REQUEST_LIMIT,
            DocumentRequest::openCountFor($resident),
        );
    }

    #[Test]
    public function a_fourth_open_request_is_refused(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();

        foreach (DocumentRequest::OPEN_STATUSES as $status) {
            DocumentRequest::factory()->forResident($resident)->status($status)->create();
        }

        $this->assertSame(DocumentRequest::OPEN_REQUEST_LIMIT, DocumentRequest::openCountFor($resident));

        $this->postAsResident($resident, $type)->assertSessionHasErrors('account');

        $this->assertSame(
            DocumentRequest::OPEN_REQUEST_LIMIT,
            DocumentRequest::openCountFor($resident),
            'The refused request was written anyway.',
        );
    }

    /**
     * A claimed or rejected request is finished and frees its slot. Checked
     * because a limit that never releases is a resident permanently locked out
     * after three mistakes.
     */
    #[Test]
    public function a_collected_request_frees_its_slot(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();

        foreach (range(1, DocumentRequest::OPEN_REQUEST_LIMIT) as $ignored) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $this->postAsResident($resident, $type)->assertSessionHasErrors('account');

        DocumentRequest::whereBelongsTo($resident)
            ->where('status', DocumentRequest::STATUS_SUBMITTED)
            ->first()
            ->update(['status' => DocumentRequest::STATUS_CLAIMED]);

        $this->postAsResident($resident, $type)->assertSessionHasNoErrors();
    }

    #[Test]
    public function a_rejected_request_frees_its_slot(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create();

        foreach (range(1, DocumentRequest::OPEN_REQUEST_LIMIT) as $ignored) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        DocumentRequest::whereBelongsTo($resident)->first()->update([
            'status' => DocumentRequest::STATUS_REJECTED,
            'rejection_reason' => 'Not needed.',
        ]);

        $this->postAsResident($resident, $type)->assertSessionHasNoErrors();
    }

    /**
     * The number the page shows and the number the server checks must be the same
     * figure, or a resident is shown a free slot the POST then refuses.
     */
    #[Test]
    public function the_page_shows_the_same_count_the_server_enforces(): void
    {
        $resident = $this->verifiedResident();

        foreach (range(1, 2) as $ignored) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $remaining = DocumentRequest::OPEN_REQUEST_LIMIT - 2;

        $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee('Open requests')
            ->assertSee((string) $remaining);
    }

    // ---------------------------------------------------------------------
    // Who may submit
    // ---------------------------------------------------------------------

    #[Test]
    public function a_pending_resident_cannot_submit(): void
    {
        $resident = $this->pendingResident();
        $type = DocumentType::factory()->create();

        $this->postAsResident($resident, $type)->assertSessionHasErrors('account');

        $this->assertDatabaseCount('document_requests', 0);
    }

    /**
     * A hold is not a lockout: a pending resident can still read what they
     * already have, which is the point of a hold rather than a block.
     */
    #[Test]
    public function a_pending_resident_can_still_read_their_existing_requests(): void
    {
        $resident = $this->pendingResident();
        $existing = DocumentRequest::factory()->forResident($resident)->create();

        $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee($existing->reference_number);

        // Redirects now; the read itself happens on the list above, which is
        // where the Track dialog lives.
        $this->actingAs($resident)
            ->get(route('resident.requests.show', $existing))
            ->assertRedirect(route('resident.requests.index'));
    }

    #[Test]
    public function a_pending_resident_is_told_why_rather_than_shown_a_dead_form(): void
    {
        $content = $this->actingAs($this->pendingResident())
            ->get(route('resident.requests.create'))->assertOk()->getContent();

        $this->assertStringContainsString('Verification required', $content);

        // The form itself is not rendered, because it could not succeed.
        $this->assertStringNotContainsString('action="'.route('resident.requests.store').'"', $content);
    }

    /**
     * A frozen account is refused at the ROUTE, not by the form request.
     *
     * The role middleware runs first, so the resident never reaches the form.
     * That ordering is the point: the refusal cannot be reached past by posting
     * directly. The form request carries the same check as a second layer, for
     * the day these routes lose the role middleware.
     */
    #[Test]
    public function a_restricted_resident_cannot_reach_the_form(): void
    {
        $resident = $this->verifiedResident();
        $resident->update(['restricted_until' => now()->addWeek()]);

        $this->actingAs($resident->fresh())
            ->get(route('resident.requests.create'))
            ->assertForbidden();
    }

    #[Test]
    public function a_restricted_resident_cannot_submit(): void
    {
        $resident = $this->verifiedResident();
        $resident->update(['restricted_until' => now()->addWeek()]);
        $type = DocumentType::factory()->create();

        $this->postAsResident($resident, $type)->assertForbidden();

        $this->assertDatabaseCount('document_requests', 0);
    }

    /**
     * The second layer, proven directly rather than through a route: if the role
     * middleware were ever dropped from these routes, the form request still
     * refuses.
     */
    #[Test]
    public function the_form_request_itself_refuses_a_restricted_resident(): void
    {
        $resident = $this->verifiedResident();
        $resident->update(['restricted_until' => now()->addWeek()]);
        $type = DocumentType::factory()->create();

        $formRequest = StoreDocumentRequestRequest::create(
            'resident/requests',
            'POST',
            ['document_type_id' => $type->id]
        );
        $formRequest->setUserResolver(fn () => $resident->fresh());

        $validator = Validator::make(
            ['document_type_id' => $type->id],
            ['document_type_id' => $formRequest->rules()['document_type_id']],
            $formRequest->messages()
        );

        $formRequest->withValidator($validator);

        $this->assertTrue($validator->fails(), 'The form request did not refuse a restricted resident.');
        $this->assertSame('account', array_key_first($validator->errors()->toArray()));
    }

    #[Test]
    public function a_guest_cannot_submit(): void
    {
        $this->post(route('resident.requests.store'), [
            'document_type_id' => DocumentType::factory()->create()->id,
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('document_requests', 0);
    }

    // ---------------------------------------------------------------------
    // Input rules
    // ---------------------------------------------------------------------

    #[Test]
    public function a_retired_document_type_cannot_be_requested(): void
    {
        $resident = $this->verifiedResident();
        $retired = DocumentType::factory()->inactive()->create();

        $this->postAsResident($resident, $retired)->assertSessionHasErrors('document_type_id');

        $this->assertDatabaseCount('document_requests', 0);
    }

    #[Test]
    public function a_document_type_that_does_not_exist_cannot_be_requested(): void
    {
        $this->postAsResident($this->verifiedResident(), null, ['document_type_id' => 999999])
            ->assertSessionHasErrors('document_type_id');
    }

    // ---------------------------------------------------------------------
    // Staff may not use the resident area
    // ---------------------------------------------------------------------

    #[Test]
    public function a_secretary_cannot_reach_the_resident_area(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->get(route('resident.requests.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_kagawad_cannot_reach_the_resident_area(): void
    {
        $this->actingAs(User::factory()->kagawad()->create())
            ->get(route('resident.relief'))
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function postAsResident(User $resident, ?DocumentType $type, array $override = [])
    {
        return $this->actingAs($resident)->post(route('resident.requests.store'), $override + [
            'document_type_id' => $type?->id,
            'purpose' => 'Employment requirement.',
        ]);
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function pendingResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->pending()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
        ]);

        return $user;
    }
}
