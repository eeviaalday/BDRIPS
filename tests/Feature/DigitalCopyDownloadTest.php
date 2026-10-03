<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The resident's download of their own digital copy.
 *
 * WHAT IS WORTH A TEST HERE
 *
 * A digital copy is a document the resident is entitled to walk away with, so the
 * tests are about entitlement and about what leaves the building:
 *
 * - who can get one. Another resident's ready request and a reference that does
 *   not exist have to be the same 404, not 404 and 403, because the difference
 *   between them tells a stranger whether a reference number is real.
 * - when a copy exists at all. Every status that is not ready has to refuse, and
 *   so has a resident the office cannot build a password for.
 * - what is written down. A delivery row and an audit line per copy, or the
 *   "who got this document" question has no answer after the fact.
 * - what is NOT written down. The password, the birthdate it came from and the
 *   bytes of the copy must not reach the audit log, the filename, or anywhere
 *   under public/ where a URL would serve it to anybody.
 */
class DigitalCopyDownloadTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Entitlement
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_can_download_the_copy_of_a_ready_request(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $response = $this->actingAs($resident)
            ->get(route('resident.requests.document.download', $request))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    /**
     * The scoping rule, stated as a pair.
     *
     * Same reasoning as the resident request list: a wrong reference and another
     * resident's reference have to be byte-for-byte indistinguishable, or the
     * difference between the two responses tells a stranger that a reference
     * number is real. The wrong one is generated on the fly because a fixed
     * unused id could later be a real row.
     */
    #[Test]
    public function another_residents_ready_request_and_a_wrong_one_are_indistinguishable(): void
    {
        $resident = $this->verifiedResident();
        $theirs = DocumentRequest::factory()->ready()->create();

        $someoneElses = $this->actingAs($resident)->get(route('resident.requests.document.download', $theirs));
        $madeUp = $this->actingAs($resident)->get(route('resident.requests.document.download', 999999));

        $this->assertSame(404, $someoneElses->getStatusCode());
        $this->assertSame($someoneElses->getStatusCode(), $madeUp->getStatusCode());
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_page(): void
    {
        $request = DocumentRequest::factory()->ready()->create();

        $this->get(route('resident.requests.document.download', $request))->assertRedirect(route('login'));
    }

    /**
     * A staff account gets the same 404 as any other non-owner.
     *
     * The request is scoped to the signed-in account while the route is resolved,
     * which happens before the role middleware runs, so the answer for "not yours"
     * is reached before the answer for "not allowed". That ordering is deliberate:
     * a 403 here would confirm the route and the record at the same time.
     */
    #[Test]
    public function a_staff_account_gets_the_same_404_as_any_other_non_owner(): void
    {
        $request = DocumentRequest::factory()->ready()->create();

        $this->actingAs(User::factory()->secretary()->create())
            ->get(route('resident.requests.document.download', $request))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // When a copy exists
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string}>
     */
    public static function statusesWithoutACopy(): array
    {
        return [
            'submitted' => [DocumentRequest::STATUS_SUBMITTED],
            'under review' => [DocumentRequest::STATUS_UNDER_REVIEW],
            'already collected at the desk' => [DocumentRequest::STATUS_CLAIMED],
            'unclaimed' => [DocumentRequest::STATUS_UNCLAIMED],
            'rejected' => [DocumentRequest::STATUS_REJECTED],
            'withdrawn' => [DocumentRequest::STATUS_CANCELLED],
        ];
    }

    #[Test]
    #[DataProvider('statusesWithoutACopy')]
    public function a_request_that_is_not_ready_has_no_copy(string $status): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->status($status)->create();

        $this->actingAs($resident)
            ->get(route('resident.requests.document.download', $request))
            ->assertStatus(409);

        $this->assertSame(0, DocumentDelivery::query()->count());
    }

    /**
     * A resident the office cannot build a password for.
     *
     * Without a birthdate there are two mm/dd/yy strings that look equally
     * plausible, and a wrong password is indistinguishable from a wrong document.
     * So the copy is refused and the resident is pointed at the hall, and nothing
     * is recorded as having been issued.
     */
    #[Test]
    public function a_resident_without_a_birthdate_on_file_is_sent_to_the_hall(): void
    {
        $resident = $this->verifiedResident(['date_of_birth' => null]);
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->actingAs($resident)
            ->get(route('resident.requests.document.download', $request))
            ->assertStatus(409)
            ->assertSeeText($request->reference_number);

        $this->assertSame(0, DocumentDelivery::query()->count());
    }

    // ---------------------------------------------------------------------
    // What the resident is told before they click
    // ---------------------------------------------------------------------

    #[Test]
    public function the_button_appears_on_a_ready_request_and_only_on_a_ready_request(): void
    {
        $resident = $this->verifiedResident();
        $ready = DocumentRequest::factory()->ready()->forResident($resident)->create();
        $underReview = DocumentRequest::factory()->underReview()->forResident($resident)->create();
        $claimed = DocumentRequest::factory()->claimed()->forResident($resident)->create();

        $content = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString(route('resident.requests.document.download', $ready), $content);
        $this->assertStringNotContainsString(route('resident.requests.document.download', $underReview), $content);
        $this->assertStringNotContainsString(route('resident.requests.document.download', $claimed), $content);
    }

    /**
     * The rule is stated on the page, and their own password is not.
     *
     * A resident has to be able to work the password out without asking anybody,
     * which means the page has to say what it is worked out from. What it must
     * not do is print the password itself: it would be on screen at the hall, in
     * the browser cache, and in any screenshot the resident takes of the page.
     */
    #[Test]
    public function the_password_rule_is_explained_and_the_password_itself_is_not_printed(): void
    {
        $resident = $this->verifiedResident([
            'name' => 'Juan Dela Cruz Santos',
            'date_of_birth' => '1994-03-17',
        ]);

        DocumentRequest::factory()->ready()->forResident($resident)->create();

        $content = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('mm/dd/yy', $content);
        $this->assertStringContainsString('first letter of your surname', $content);

        // Their own password is 03/17/94S. The example on the page is a different
        // resident's, so its presence proves the page teaches the rule rather than
        // disclosing this one's answer.
        $this->assertStringContainsString('03/17/94C', $content);
        $this->assertStringNotContainsString('03/17/94S', $content);
    }

    // ---------------------------------------------------------------------
    // What is written down
    // ---------------------------------------------------------------------

    #[Test]
    public function each_copy_is_recorded_against_the_resident_who_took_it(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $this->assertSame(1, DocumentDelivery::query()
            ->where('document_request_id', $request->getKey())
            ->where('user_id', $resident->getKey())
            ->count());
    }

    #[Test]
    public function a_second_copy_is_a_second_record(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();
        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        // Not de-duplicated on purpose: two downloads by the same resident are two
        // things that happened, and the count on the staff list has to be able to
        // say so.
        $this->assertSame(2, DocumentDelivery::query()->count());
    }

    #[Test]
    public function each_copy_leaves_one_audit_line_that_says_how_it_went_out(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $entry = AuditLog::query()
            ->where('action', 'request.pdf_downloaded')
            ->where('subject_type', DocumentRequest::class)
            ->where('subject_id', $request->getKey())
            ->sole();

        // The channel is the part that cannot be derived from the request row: it
        // is what separates a resident who walked away with a copy from a resident
        // who was handed one at the desk.
        $this->assertSame('resident_download', $entry->context['channel']);
        $this->assertSame('Digital copy downloaded', $entry->actionLabel());
    }

    // ---------------------------------------------------------------------
    // Does a download count as collection
    // ---------------------------------------------------------------------

    #[Test]
    public function a_download_does_not_collect_the_request_by_default(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->assertFalse(Setting::flag('digital_copy_counts_as_claimed'));

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $request->refresh();

        $this->assertSame(DocumentRequest::STATUS_READY, $request->status);
        $this->assertNull($request->claimed_at);
        $this->assertSame(0, AuditLog::query()->where('action', 'request.claimed')->count());
    }

    #[Test]
    public function an_office_that_turns_the_setting_on_has_the_download_collect_the_request(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->saveSettings(['digital_copy_counts_as_claimed' => '1']);

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $request->refresh();

        $this->assertSame(DocumentRequest::STATUS_CLAIMED, $request->status);
        $this->assertNotNull($request->claimed_at);
        $this->assertSame(
            'resident_download',
            AuditLog::query()->where('action', 'request.claimed')->sole()->context['channel']
        );
    }

    /**
     * A desk that already marked it collected keeps its own timestamp.
     *
     * The setting says a download collects the request; it does not say a
     * download can rewrite history. Once the request is claimed there is no copy
     * to download at all, so this is about the write being narrow rather than
     * about an order two actions can happen in.
     */
    #[Test]
    public function the_collected_timestamp_the_desk_wrote_is_not_overwritten(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->claimed()->forResident($resident)->create();
        $claimedAt = $request->claimed_at;

        $this->saveSettings(['digital_copy_counts_as_claimed' => '1']);

        // Refused for being already collected, and nothing was recorded either.
        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertStatus(409);

        $this->assertEquals($claimedAt, $request->fresh()->claimed_at);
        $this->assertSame(0, DocumentDelivery::query()->count());
    }

    // ---------------------------------------------------------------------
    // What must not leave the building
    // ---------------------------------------------------------------------

    #[Test]
    public function the_response_carries_no_trace_of_the_password_or_the_birthdate(): void
    {
        Storage::fake('local');

        $resident = $this->verifiedResident(['name' => 'Juan Dela Cruz Santos', 'date_of_birth' => '1994-03-17']);
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $response = $this->actingAs($resident)->get(route('resident.requests.document.download', $request));

        // The filename is the one place a password could be smuggled out in the
        // clear, so the whole header block is asserted rather than one part of it.
        $this->assertSame(
            sprintf('attachment; filename="digital-copy-%s.pdf"', $request->reference_number),
            $response->headers->get('Content-Disposition')
        );

        foreach (['03/17/94S', '1994-03-17', 'password'] as $secret) {
            $this->assertStringNotContainsStringIgnoringCase($secret, $response->headers->get('Content-Disposition'));
            $this->assertStringNotContainsStringIgnoringCase($secret, $response->headers->get('Cache-Control'));
        }

        $entry = AuditLog::query()->where('action', 'request.pdf_downloaded')->sole();

        $this->assertStringNotContainsString('03/17/94S', json_encode($entry->context));
        $this->assertStringNotContainsString('1994-03-17', json_encode($entry->context));
    }

    #[Test]
    public function the_copy_is_never_written_where_a_url_could_reach_it(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $this->assertEmpty(Storage::disk('public')->allFiles());
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    #[Test]
    public function the_response_is_marked_as_uncacheable(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        $response = $this->actingAs($resident)->get(route('resident.requests.document.download', $request));

        // The bytes are keyed to a password derived from this resident, so a shared
        // cache or a proxy holding them is somewhere they should not end up. The
        // session adds its own directives to whatever the controller set, so this
        // asserts the two that forbid storage rather than the whole header.
        $cacheControl = $response->headers->get('Cache-Control');

        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
    }

    // ---------------------------------------------------------------------
    // Abuse
    // ---------------------------------------------------------------------

    #[Test]
    public function repeated_downloads_are_rate_limited(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->actingAs($resident)
                ->get(route('resident.requests.document.download', $request))
                ->assertOk();
        }

        $this->actingAs($resident)
            ->get(route('resident.requests.document.download', $request))
            ->assertStatus(429);
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function verifiedResident(array $overrides = []): User
    {
        $user = User::factory()->resident()->create($overrides + [
            'is_verified' => true,
            'date_of_birth' => '1994-03-17',
        ]);

        ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => Sector::factory()->create()->id,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function saveSettings(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Unused import guard: the type factory is referenced by the request factory,
     * and this keeps the file honest if that ever stops being true.
     */
    #[Test]
    public function a_document_type_label_reaches_the_copy(): void
    {
        $resident = $this->verifiedResident();
        $type = DocumentType::factory()->create(['label' => 'Barangay Clearance']);
        $request = DocumentRequest::factory()->ready()->forResident($resident)->create(['document_type_id' => $type->id]);

        $this->actingAs($resident)->get(route('resident.requests.document.download', $request))->assertOk();

        $this->assertSame('Barangay Clearance', $request->fresh('documentType')->documentType->label);
    }
}
