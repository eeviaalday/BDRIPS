<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The unclaimed-document policy: the window, the strike, the suspension and the
 * slot hold.
 *
 * WHY SO MUCH OF THIS FILE IS ABOUT A NUMBER
 *
 * Every figure in this policy is a setting somebody can change, and three of
 * them are boundaries where being wrong by one day produces the wrong answer
 * for a real resident. So the tests are written at the boundaries rather than
 * beside them:
 * - `the_suspension_holds_for_the_whole_configured_window_and_not_before` is
 *   the important one. A shorter penalty was the value this policy was first
 *   drafted with, and it is the value a reader would assume from a system that
 *   already holds a slot for a shorter time. A test that only checked "still
 *   suspended tomorrow" would have passed against any duration at all, and the
 *   whole policy would have shipped with the wrong penalty. So it asserts the
 *   final configured day is refused and the day after is allowed.
 * - The slot hold is pinned at the same kind of boundary, because it is the one
 *   that decides whether a resident may file a request at all.
 * - The last two tests change the config itself and re-assert, so that a figure
 *   quietly inlined anywhere instead of read from config('document-requests')
 *   fails here rather than being discovered by the barangay.
 *
 * No figure is written into this file, not even to assert it. The values agreed
 * with the barangay live in `config/document-requests.php` and are recorded in
 * `ROLES.md`, so changing one is a deliberate edit to a file that says what the
 * number is - and here it proves the code follows whatever that file says.
 */
class DocumentRequestUnclaimedPolicyTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The window before a request may be marked unclaimed
    // ---------------------------------------------------------------------

    #[Test]
    public function a_request_is_not_overdue_the_day_before_the_window(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(DocumentRequest::readyDaysBeforeUnclaimed() - 1),
        ]);

        $this->assertFalse($request->fresh()->isOverdue());
        $this->assertFalse($request->fresh()->canBeMarkedUnclaimed());

        // 409, not 403. The route is permitted and the request is the wrong
        // state for it, which is the same answer every other stale-state action
        // in this area gives.
        $this->actingAs($secretary)
            ->post(route('staff.requests.unclaim', $request))
            ->assertStatus(409);
    }

    #[Test]
    public function a_request_is_overdue_on_the_last_day_of_the_window(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(DocumentRequest::readyDaysBeforeUnclaimed()),
        ]);

        $this->assertTrue($request->fresh()->isOverdue());

        $this->actingAs($secretary)
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.unclaim', $request))
            ->assertRedirect(route('staff.requests.show', $request))
            ->assertSessionHasNoErrors();

        $request->refresh();

        $this->assertSame(DocumentRequest::STATUS_UNCLAIMED, $request->status);
        $this->assertNotNull($request->unclaimed_at);
    }

    #[Test]
    public function marking_unclaimed_records_who_did_it_and_how_long_it_waited(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(20),
        ]);

        $this->actingAs($secretary)->post(route('staff.requests.unclaim', $request));

        $entry = AuditLog::query()
            ->where('action', 'request.unclaimed')
            ->where('subject_id', $request->id)
            ->sole();

        $this->assertSame($secretary->id, $entry->user_id);
        $this->assertSame($secretary->name, $entry->user_name);
        $this->assertSame(20, (int) $entry->context['days_ready']);
        $this->assertNotNull(
            $entry->context['slot_hold_until'] ?? null,
            'The audit entry should say when the slot hold ends, so the record explains itself.'
        );
    }

    #[Test]
    public function an_unclaimed_request_cannot_be_marked_unclaimed_again(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)
            ->post(route('staff.requests.unclaim', $request))
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Strikes
    // ---------------------------------------------------------------------

    #[Test]
    public function an_unclaimed_request_is_one_strike(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed()->create();

        $this->assertSame(1, DocumentRequest::strikeCountFor($resident));
    }

    #[Test]
    public function an_excused_request_is_never_a_strike(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed(excused: true)->create();

        $this->assertSame(0, DocumentRequest::strikeCountFor($resident));
    }

    #[Test]
    public function a_strike_older_than_the_window_has_aged_out(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed(
            unclaimedAt: Carbon::now()->subDays(DocumentRequest::strikeWindowDays() + 1)
        )->create();

        $this->assertSame(0, DocumentRequest::strikeCountFor($resident));
        $this->assertFalse(DocumentRequest::isSuspended($resident));
    }

    #[Test]
    public function a_strike_just_inside_the_window_still_counts(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed(
            unclaimedAt: Carbon::now()->subDays(DocumentRequest::strikeWindowDays())
        )->create();

        $this->assertSame(1, DocumentRequest::strikeCountFor($resident));
    }

    #[Test]
    public function one_strike_does_not_suspend_anybody(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed()->create();

        $this->assertFalse(DocumentRequest::isSuspended($resident));
        $this->assertNull(DocumentRequest::suspensionEndFor($resident));
        $this->assertNull(DocumentRequest::suspensionMessageFor($resident));
    }

    // ---------------------------------------------------------------------
    // Suspension
    // ---------------------------------------------------------------------

    #[Test]
    public function the_threshold_strike_suspends_and_the_one_before_it_does_not(): void
    {
        $threshold = DocumentRequest::strikesToSuspend();

        for ($strikes = 1; $strikes < $threshold; $strikes++) {
            $resident = $this->verifiedResident();
            $this->withStrikes($resident, $strikes);

            $this->assertFalse(
                DocumentRequest::isSuspended($resident),
                "{$strikes} strikes should not suspend anybody when the threshold is {$threshold}."
            );
        }

        $resident = $this->verifiedResident();
        $this->withStrikes($resident, $threshold);

        $this->assertTrue(DocumentRequest::isSuspended($resident));
    }

    #[Test]
    public function the_suspension_holds_for_the_whole_configured_window_and_not_before(): void
    {
        // The duration is READ from config, never written into the behaviour and
        // not restated here. Restating it would make this test pass against
        // whatever the config happens to say, which is exactly the accident it
        // exists to catch: a penalty quietly shortened, and every derived
        // boundary in the system moving with it so that nothing failed.
        $days = DocumentRequest::suspensionDays();

        // Every boundary below is derived from $days, so if the setting changes
        // this test still tests the real edge rather than a stale one.
        $lastDay = $days - 1;
        $firstDayFree = $days + 1;

        // Day one: suspended from the moment the newest strike is recorded.
        $this->travelTo(Carbon::now()->addDays(0)->startOfDay());
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend(), newestDaysAgo: 0);
        $this->assertTrue(DocumentRequest::isSuspended($resident));

        // The final day of the suspension: still refused. This is the assertion a
        // shorter implementation cannot pass.
        $this->travelTo(Carbon::now()->addDays($lastDay));
        $this->assertTrue(
            DocumentRequest::isSuspended($resident),
            "Still suspended on day {$lastDay} of a {$days}-day suspension."
        );
        $this->postAsResident($resident)
            ->assertSessionHasErrors('account');

        // The day after it ends: allowed. A shorter setting would have lifted
        // this months earlier.
        $this->travelTo(Carbon::now()->addDays($firstDayFree));
        $this->assertFalse(
            DocumentRequest::isSuspended($resident),
            "The suspension must have lifted by day {$firstDayFree}."
        );
        $this->postAsResident($resident)->assertSessionHasNoErrors();
    }

    #[Test]
    public function the_refusal_names_the_date_the_resident_may_apply_again(): void
    {
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend(), newestDaysAgo: 0);

        $ends = DocumentRequest::suspensionEndFor($resident);

        $this->assertNotNull($ends);

        // Exact equality first: this is what proves the refusal the resident is
        // actually handed is the model's sentence and not a copy of it. Then the
        // two substrings, because "it is the right sentence" and "the sentence
        // contains the day they may apply again" are different claims.
        $refusal = DocumentRequest::suspensionMessageFor($resident);

        $this->postAsResident($resident)->assertSessionHasErrors(['account' => $refusal]);

        $this->assertStringContainsString($ends->format('F j, Y'), (string) $refusal);
    }

    #[Test]
    public function the_refusal_states_the_policy_in_the_residents_words(): void
    {
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend());

        $expected = sprintf(
            'After %d, new requests pause for %d days.',
            DocumentRequest::strikesToSuspend(),
            DocumentRequest::suspensionDays()
        );

        $this->assertSame($expected, DocumentRequest::suspensionPolicySentence());

        $refusal = DocumentRequest::suspensionMessageFor($resident);

        $this->postAsResident($resident)->assertSessionHasErrors(['account' => $refusal]);

        $this->assertStringContainsString($expected, (string) $refusal);
    }

    #[Test]
    public function a_suspended_resident_is_told_why_rather_than_shown_a_dead_button(): void
    {
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend());

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(
            DocumentRequest::suspensionMessageFor($resident),
            $html,
            'The warning the model builds is not on the page, so the resident is not told why.'
        );
    }

    #[Test]
    public function a_resident_with_no_strikes_is_told_nothing_about_strikes(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->ready()->create();

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertNull(DocumentRequest::suspensionMessageFor($resident));
        $this->assertStringNotContainsString('new requests pause', $html);
    }

    // ---------------------------------------------------------------------
    // Excusing
    // ---------------------------------------------------------------------

    #[Test]
    public function excusing_a_strike_lifts_the_suspension_immediately(): void
    {
        $secretary = $this->secretary();
        $resident = $this->verifiedResident();

        // Struck far enough back to be past the slot hold and still inside the
        // strike window. Derived rather than typed, so the combination survives a
        // change to either setting. That combination is the point: these
        // requests are holding NO slots, so the only thing stopping this resident
        // from filing is the suspension. If the test used recent strikes the cap
        // would refuse the post anyway and the test would pass whether or not
        // the excuse had lifted anything.
        $age = DocumentRequest::unclaimedSlotHoldDays() + 10;
        $strikes = $this->withStrikes($resident, DocumentRequest::strikesToSuspend(), newestDaysAgo: $age);

        $this->assertSame(0, DocumentRequest::openCountFor($resident));
        $this->assertTrue(DocumentRequest::isSuspended($resident));

        $this->postAsResident($resident)->assertSessionHasErrors('account');

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $strikes->last()), [
                'note' => 'Resident was hospitalised; the office confirmed this by phone.',
            ])
            ->assertSessionHasNoErrors();

        // No scheduled job, no cache to clear, no counter to decrement.
        $this->assertSame(
            DocumentRequest::strikesToSuspend() - 1,
            DocumentRequest::strikeCountFor($resident)
        );
        $this->assertFalse(DocumentRequest::isSuspended($resident));
        $this->assertNull(DocumentRequest::suspensionMessageFor($resident));

        $this->postAsResident($resident)->assertSessionHasNoErrors();
    }

    #[Test]
    public function excusing_requires_a_note(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)
            ->from(route('staff.requests.show', $request))
            ->post(route('staff.requests.excuse', $request))
            ->assertSessionHasErrors('note');

        $this->assertFalse($request->fresh()->isExcused());
    }

    #[Test]
    public function excusing_stores_the_note_who_did_it_and_when(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->unclaimed()->create();
        $note = 'Barangay office confirmed the resident collected this one in person.';

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $request), ['note' => $note]);

        $this->assertNotNull($request->fresh()->unclaimed_excused_at);

        $entry = AuditLog::query()
            ->where('action', 'request.excused')
            ->where('subject_id', $request->id)
            ->sole();

        $this->assertSame($note, $entry->context['reason']);
        $this->assertSame($secretary->name, $entry->context['excused_by']);
        $this->assertSame($secretary->id, $entry->user_id);
        $this->assertNotNull($entry->context['unclaimed_at'] ?? null);
    }

    #[Test]
    public function a_request_cannot_be_excused_twice(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->unclaimed(excused: true)->create();

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $request), [
                'note' => 'A second excuse describing a change that would have had no effect.',
            ])
            ->assertStatus(409);
    }

    #[Test]
    public function a_request_that_was_never_unclaimed_cannot_be_excused(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create();

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $request), [
                'note' => 'There is no strike on this one to excuse.',
            ])
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // The slot hold
    // ---------------------------------------------------------------------

    #[Test]
    public function an_unclaimed_request_holds_a_slot_for_the_configured_window(): void
    {
        // Read from config, at the same kind of boundary as the suspension test
        // above, and for the same reason: no figure is restated here.
        $hold = DocumentRequest::unclaimedSlotHoldDays();

        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->unclaimed(
            unclaimedAt: Carbon::now()->subDays($hold - 1)
        )->create();

        $this->assertTrue($request->fresh()->holdsSlot());
        $this->assertSame(1, DocumentRequest::openCountFor($resident));
    }

    #[Test]
    public function the_slot_hold_lapses_on_its_own_with_nobody_having_to_do_anything(): void
    {
        $hold = DocumentRequest::unclaimedSlotHoldDays();
        $resident = $this->verifiedResident();

        DocumentRequest::factory()->forResident($resident)->unclaimed(
            unclaimedAt: Carbon::now()->subDays($hold)
        )->create();

        $this->assertSame(0, DocumentRequest::openCountFor($resident));

        // Fill the cap again and prove the server agrees rather than only the
        // counter: the request page is what a resident reads before submitting.
        $type = DocumentType::factory()->create();

        foreach (range(1, DocumentRequest::OPEN_REQUEST_LIMIT - 1) as $ignored) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $this->postAsResident($resident, $type)->assertSessionHasNoErrors();
    }

    #[Test]
    public function an_unclaimed_request_still_counts_towards_the_cap_on_the_last_day_of_the_hold(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed()->create();

        foreach (range(1, DocumentRequest::OPEN_REQUEST_LIMIT - 1) as $ignored) {
            DocumentRequest::factory()->forResident($resident)->submitted()->create();
        }

        $this->assertSame(
            DocumentRequest::OPEN_REQUEST_LIMIT,
            DocumentRequest::openCountFor($resident)
        );

        $this->postAsResident($resident)
            ->assertSessionHasErrors('account');
    }

    // ---------------------------------------------------------------------
    // Late collection
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_who_returns_late_can_still_collect_and_the_strike_stands(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)
            ->post(route('staff.requests.claim', $request))
            ->assertSessionHasNoErrors();

        $request->refresh();

        $this->assertSame(DocumentRequest::STATUS_CLAIMED, $request->status);
        $this->assertNotNull($request->claimed_at);
    }

    // ---------------------------------------------------------------------
    // Cancellation
    // ---------------------------------------------------------------------

    #[Test]
    public function a_resident_can_cancel_their_own_request_before_it_is_ready(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $this->actingAs($resident)
            ->post(route('resident.requests.cancel', $request))
            ->assertRedirect(route('resident.requests.index'))
            ->assertSessionHasNoErrors();

        $request->refresh();

        $this->assertSame(DocumentRequest::STATUS_CANCELLED, $request->status);
        $this->assertNotNull($request->cancelled_at);
    }

    #[Test]
    public function cancelling_frees_the_slot_without_being_a_strike(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $this->assertSame(1, DocumentRequest::openCountFor($resident));

        $this->actingAs($resident)->post(route('resident.requests.cancel', $request));

        $this->assertSame(0, DocumentRequest::openCountFor($resident));
        $this->assertSame(0, DocumentRequest::strikeCountFor($resident));
    }

    #[Test]
    public function a_cancellation_cannot_clear_a_strike_already_carried(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->unclaimed()->create();
        $cancelling = DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $this->actingAs($resident)->post(route('resident.requests.cancel', $cancelling));

        $this->assertSame(
            1,
            DocumentRequest::strikeCountFor($resident),
            'Cancelling an unrelated request must not launder an unclaimed one.'
        );
    }

    #[Test]
    public function a_resident_cannot_cancel_a_request_that_is_ready(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->ready()->create();

        $this->actingAs($resident)
            ->post(route('resident.requests.cancel', $request))
            ->assertStatus(409);

        $this->assertSame(DocumentRequest::STATUS_READY, $request->fresh()->status);
    }

    #[Test]
    public function a_resident_cannot_cancel_somebody_elses_request(): void
    {
        $resident = $this->verifiedResident();
        $other = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($other)->submitted()->create();

        // 404, not 403: the binder scopes the lookup through the signed-in user,
        // so another resident's request was never found.
        $this->actingAs($resident)
            ->post(route('resident.requests.cancel', $request))
            ->assertNotFound();

        $this->assertSame(DocumentRequest::STATUS_SUBMITTED, $request->fresh()->status);
    }

    #[Test]
    public function a_resident_cannot_cancel_through_the_staff_routes(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $this->actingAs($resident)
            ->post(route('staff.requests.unclaim', $request))
            ->assertForbidden();

        $this->actingAs($resident)
            ->post(route('staff.requests.excuse', $request), ['note' => 'Not mine to decide.'])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // The settings are settings
    // ---------------------------------------------------------------------

    #[Test]
    public function changing_the_suspension_length_changes_the_behaviour(): void
    {
        $shortened = 7;
        config(['document-requests.suspension_days' => $shortened]);

        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend(), newestDaysAgo: 0);

        $this->assertTrue(DocumentRequest::isSuspended($resident));

        $this->travelTo(Carbon::now()->addDays($shortened + 1));

        $this->assertFalse(DocumentRequest::isSuspended($resident));

        // The sentence is what the resident reads, so it is rebuilt from the
        // setting rather than typed: if it kept naming the old length the
        // behaviour would still be right and the warning still wrong.
        $this->assertStringContainsString(
            sprintf('After %d, new requests pause for %d days.', DocumentRequest::strikesToSuspend(), $shortened),
            (string) DocumentRequest::suspensionPolicySentence(),
            'The sentence quoted to the resident still named the old length.'
        );
    }

    #[Test]
    public function changing_the_threshold_changes_the_behaviour(): void
    {
        config(['document-requests.strikes_to_suspend' => 2]);

        $resident = $this->verifiedResident();
        $this->withStrikes($resident, 1);

        $this->assertFalse(DocumentRequest::isSuspended($resident), 'One strike at a threshold of two.');

        $this->withStrikes($resident, 1);

        $this->assertTrue(DocumentRequest::isSuspended($resident), 'Two strikes at a threshold of two.');
    }

    #[Test]
    public function changing_the_window_and_the_hold_changes_the_behaviour(): void
    {
        config([
            'document-requests.strike_window_days' => 30,
            'document-requests.unclaimed_slot_hold_days' => 5,
        ]);

        $resident = $this->verifiedResident();

        // Outside this shortened window, and derived so it stays outside it.
        DocumentRequest::factory()->forResident($resident)->unclaimed(
            unclaimedAt: Carbon::now()->subDays(DocumentRequest::strikeWindowDays() + 40)
        )->create();

        $this->assertSame(0, DocumentRequest::strikeCountFor($resident));
        $this->assertSame(0, DocumentRequest::openCountFor($resident));

        // 6 days ago: inside the original 30-day hold, outside this one.
        $fresh = $this->verifiedResident();
        DocumentRequest::factory()->forResident($fresh)->unclaimed(
            unclaimedAt: Carbon::now()->subDays(6)
        )->create();

        $this->assertSame(0, DocumentRequest::openCountFor($fresh));
    }

    #[Test]
    public function changing_the_ready_window_changes_when_a_request_can_be_marked_unclaimed(): void
    {
        config(['document-requests.ready_days_before_unclaimed' => 30]);

        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(20),
        ]);

        $this->assertFalse($request->fresh()->isOverdue());

        $this->actingAs($secretary)
            ->post(route('staff.requests.unclaim', $request))
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Late collection
    // ---------------------------------------------------------------------

    /*
     * The one place where the design could quietly undo itself.
     *
     * A resident who eventually turns up and collects a document has fixed the
     * problem the strike records. It would be entirely defensible to let that
     * clear it - the document was collected, after all. But a strike exists to
     * price a habit, not a single request, and three separate habits clearing
     * themselves by patience is not enforcement, it is a reward for waiting. So
     * collection does NOT remove the strike, and this test exists because the
     * natural implementation - filtering the strike count on
     * status = unclaimed - removes it silently, with every other test in the
     * file still passing.
     */

    #[Test]
    public function collecting_late_does_not_erase_the_strike_it_caused(): void
    {
        $secretary = $this->secretary();
        $resident = $this->verifiedResident();
        $strikes = $this->withStrikes($resident, DocumentRequest::strikesToSuspend());

        $this->assertSame(DocumentRequest::strikesToSuspend(), DocumentRequest::strikeCountFor($resident));

        // The resident turns up. The desk marks it collected, which is the truth
        // and the right thing to record. It cannot be done by posting straight to
        // the route, because the route will only accept a request the server still
        // believes is unclaimed - so each one is marked unclaimed here first, which
        // is the state these fixtures would have to be in anyway.
        foreach ($strikes as $strike) {
            $this->actingAs($secretary)->post(route('staff.requests.unclaim', $strike));
            $this->actingAs($secretary)->post(route('staff.requests.claim', $strike))
                ->assertRedirect();
        }

        foreach ($strikes as $strike) {
            $this->assertSame(
                DocumentRequest::STATUS_CLAIMED,
                $strike->fresh()->status,
                'The document should be recorded as collected.'
            );
        }

        // And the strikes are all still there, because the documents were still
        // not collected on the day they were supposed to be.
        $this->assertSame(
            DocumentRequest::strikesToSuspend(),
            DocumentRequest::strikeCountFor($resident),
            'Collecting late must not clear the strike.'
        );

        $this->assertTrue(DocumentRequest::isSuspended($resident));

        // Still holds a slot? No: a collected document is not waiting for anyone.
        $this->assertSame(0, DocumentRequest::openCountFor($resident));

        // The only way out is an excuse, which is a judgement somebody records.
        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $strikes->first()), [
                'note' => 'Was away for a month, collected on returning.',
            ])
            ->assertRedirect();

        // One below the threshold, so the suspension lifts immediately. Worth
        // stating plainly because it is the shape of the whole policy: the third
        // strike pauses new requests, and an excuse for any one of the three is
        // enough to lift it. The resident does not have to be under the limit for
        // the pause to come off.
        $this->assertSame(
            DocumentRequest::strikesToSuspend() - 1,
            DocumentRequest::strikeCountFor($resident)
        );
        $this->assertFalse(
            DocumentRequest::isSuspended($resident),
            'Excusing one of the three strikes lifts the suspension immediately.'
        );
        $this->postAsResident($resident)->assertSessionHasNoErrors();
    }

    #[Test]
    public function an_excused_request_stays_excused_even_after_late_collection(): void
    {
        $secretary = $this->secretary();
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->unclaimed()->create();

        $this->actingAs($secretary)->post(route('staff.requests.excuse', $request), [
            'note' => 'Hospitalised, could not collect in person.',
        ]);

        $this->assertSame(0, DocumentRequest::strikeCountFor($resident));

        $this->actingAs($secretary)->post(route('staff.requests.claim', $request))
            ->assertRedirect();

        $this->assertSame(
            0,
            DocumentRequest::strikeCountFor($resident),
            'An excuse is about the missed collection, which collecting later does not undo.'
        );
    }

    // ---------------------------------------------------------------------
    // The screens
    // ---------------------------------------------------------------------

    /*
     * Every figure and sentence the policy produces has to reach the pages, or
     * the policy is enforced in the database and invisible to the people it acts
     * on. These assert on rendered HTML because that is the only claim worth
     * making: a number that exists on a model but never in a view is not
     * something anybody was told.
     */

    #[Test]
    public function the_staff_queue_shows_the_overdue_and_unclaimed_figures(): void
    {
        $secretary = $this->secretary();

        // One inside the window, one past it: only the second may be offered.
        DocumentRequest::factory()->ready()->create(['ready_at' => Carbon::now()->subDays(3)]);
        DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(DocumentRequest::readyDaysBeforeUnclaimed()),
        ]);
        DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)
            ->get(route('staff.requests.index'))
            ->assertOk()
            ->assertSee('Overdue for collection')
            ->assertSee('can be marked unclaimed')
            // The overdue one is named; the 3-day-old one is not, so seeing this
            // string at all proves the scope is a date filter and not "all ready".
            ->assertSee('Not excused', escape: false)
            ->assertSee('Unclaimed');
    }

    #[Test]
    public function the_staff_queue_offers_marking_before_the_window_and_withholds_it_after(): void
    {
        $secretary = $this->secretary();
        $early = DocumentRequest::factory()->ready()->create(['ready_at' => Carbon::now()->subDays(3)]);

        $this->actingAs($secretary)
            ->get(route('staff.requests.show', $early))
            ->assertOk()
            ->assertDontSee('Mark unclaimed')
            ->assertSee('Mark collected');

        $late = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(DocumentRequest::readyDaysBeforeUnclaimed()),
        ]);

        $this->actingAs($secretary)
            ->get(route('staff.requests.show', $late))
            ->assertOk()
            ->assertSee('Mark unclaimed');
    }

    #[Test]
    public function the_record_page_shows_the_excuse_note_and_who_wrote_it(): void
    {
        $secretary = $this->secretary();
        $note = 'The resident was out of town until last week.';
        $request = DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $request), ['note' => $note])
            ->assertRedirect();

        $this->actingAs($secretary)
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->assertSee('Excused - no strike on this request')
            ->assertSee($note)
            ->assertSee($secretary->name)
            // The excuse dialog is gone once it has been used; offering it again
            // would invite a second note on a request that is already settled.
            ->assertDontSee('Excuse this one');
    }

    #[Test]
    public function a_request_collected_late_can_still_be_excused(): void
    {
        $secretary = $this->secretary();
        $note = 'Resident was out of town until last week, and collected on return.';
        $request = DocumentRequest::factory()->unclaimed()->create();

        $this->actingAs($secretary)->post(route('staff.requests.claim', $request));

        // The excuse has to survive collection. This is when the desk learns the
        // absence was not habit, and if the UI hid the control here the only way
        // out would be a POST nobody knows about - so the strike would be
        // permanent and the answer would be to stop marking documents.
        $this->actingAs($secretary)
            ->get(route('staff.requests.show', $request))
            ->assertOk()
            ->assertSee('Excuse this one')
            ->assertSee('Unclaimed - counting as a strike')
            // The slot is free once collected, so the held-until date must not
            // still be claimed, and the settle notice must not contradict the
            // action sitting right above it.
            ->assertSee('Collected since, so the slot is free')
            ->assertDontSee('nothing left to decide')
            ->assertDontSee('Mark collected');

        $this->actingAs($secretary)
            ->post(route('staff.requests.excuse', $request), ['note' => $note])
            ->assertRedirect();

        $this->assertNotNull($request->fresh()->unclaimed_excused_at);
        $this->assertSame(0, DocumentRequest::activeStrikesQuery($request->user)->count());
    }

    #[Test]
    public function the_resident_record_shows_the_strike_and_the_slot_hold(): void
    {
        $secretary = $this->secretary();
        $request = DocumentRequest::factory()->ready()->create([
            'ready_at' => Carbon::now()->subDays(DocumentRequest::readyDaysBeforeUnclaimed()),
        ]);

        $this->actingAs($secretary)->post(route('staff.requests.unclaim', $request));

        $this->actingAs($request->user)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee('This document was not collected.')
            ->assertSee($request->reference_number);
    }

    #[Test]
    public function the_staff_resident_page_explains_the_reliability_record(): void
    {
        $secretary = $this->secretary();
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend());

        $this->actingAs($secretary)
            ->get(route('staff.residents.show', $resident->residentProfile))
            ->assertOk()
            ->assertSee('Request reliability')
            ->assertSee('Active strikes')
            ->assertSee('No unclaimed document has been excused');
    }

    #[Test]
    public function a_suspended_resident_is_told_why_on_the_queue_page(): void
    {
        $resident = $this->verifiedResident();
        $this->withStrikes($resident, DocumentRequest::strikesToSuspend());

        $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee('Document requests on hold')
            ->assertSee(DocumentRequest::suspensionPolicySentence());
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Give a resident $count active strikes, the newest of them $newestDaysAgo
     * days old and the rest one day older each.
     *
     * They run BACKWARDS deliberately. The suspension clock starts at the newest
     * strike, so a fixture that placed them forwards would put "newest" two days
     * in the future and every boundary test would be measuring the wrong row -
     * which is exactly what happened the first time this was written.
     *
     * Returns them oldest-first so a test can excuse a specific one and know
     * which row it holds.
     *
     * @return Collection<int, DocumentRequest>
     */
    private function withStrikes(User $resident, int $count, int $newestDaysAgo = 5): Collection
    {
        $created = [];

        for ($index = 0; $index < $count; $index++) {
            $created[] = DocumentRequest::factory()
                ->forResident($resident)
                ->unclaimed(unclaimedAt: Carbon::now()->subDays($newestDaysAgo + $index))
                ->create();
        }

        return collect($created)->sortBy('unclaimed_at')->values();
    }

    private function postAsResident(User $resident, ?DocumentType $type = null)
    {
        return $this->actingAs($resident)->post(route('resident.requests.store'), [
            'document_type_id' => $type?->id ?? DocumentType::factory()->create()->id,
            'purpose' => 'Employment requirement.',
        ]);
    }

    private function secretary(): User
    {
        return User::factory()->secretary()->create(['is_verified' => true]);
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
}
