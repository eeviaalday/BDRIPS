<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What a verified resident is told when they press New Request at their limit.
 *
 * The defect this covers was that the button dispatched the lock dialog, and
 * that dialog is only rendered for an unverified resident. A verified resident at
 * the limit therefore dispatched an event with no listener mounted: the press was
 * accepted by the page and produced nothing at all, which reads as a broken
 * button rather than as a rule.
 *
 * These tests are about the rendered control and the toast that answers it. The
 * server-side refusal is a separate concern and is already covered where it
 * belongs, in ResidentDocumentRequestsTest; the last test here repeats the
 * direct-submit case only because "the frontend says no" is worthless if the
 * backend says yes.
 */
class ResidentRequestLimitToastTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The three states of the button
    // ---------------------------------------------------------------------

    /**
     * The three states have to be three different controls. Below the limit a
     * resident is sent to the form; at the limit the press is answered rather
     * than obeyed; unverified is the lock dialog, which is unchanged.
     */
    #[Test]
    public function a_resident_at_the_limit_gets_a_button_that_explains_rather_than_one_that_obeys(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertMatchesRegularExpression(
            '/<button[^>]*>[\s\S]{0,900}?\+ New Request<\//s',
            $html,
            'The New Request control is not a button at the limit.'
        );

        $this->assertStringContainsString('show-toast', $html);
        $this->assertStringNotContainsString(
            "open-modal', 'new-request'",
            $html,
            'A resident at the limit can still reach the form.'
        );
    }

    /**
     * The specific defect, pinned: the old control dispatched the lock dialog,
     * and that dialog is not on the page for a verified resident, so the event
     * went nowhere.
     */
    #[Test]
    public function the_limit_button_does_not_dispatch_the_lock_dialog(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $button = $this->newRequestButton($html);

        $this->assertStringNotContainsString(
            'requests-locked',
            $button,
            'The limit button still dispatches a dialog that is not on this page.'
        );
    }

    #[Test]
    public function a_resident_below_the_limit_still_goes_to_the_form(): void
    {
        $resident = $this->verifiedResident();

        DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $html = $this->page($resident);

        $this->assertStringContainsString(
            'href="'.route('resident.requests.create').'"',
            $html,
            'The New Request control is not a link to the form below the limit.'
        );
        $this->assertStringContainsString("open-modal', 'new-request'", $html);
        $this->assertStringNotContainsString('show-toast', $html);
    }

    /**
     * An unverified resident keeps the lock dialog, and is not shown the slot
     * toast: the reason their request is unavailable is their verification, not
     * their allowance, and telling them about three slots would be a true
     * statement about the wrong problem.
     */
    #[Test]
    public function an_unverified_resident_keeps_the_lock_dialog_and_is_not_shown_the_slot_toast(): void
    {
        $resident = $this->pendingResident();

        $html = $this->page($resident);

        $button = $this->newRequestButton($html);

        $this->assertStringContainsString("open-modal', 'requests-locked'", $button);
        $this->assertStringNotContainsString('show-toast', $button);
        $this->assertStringContainsString('New requests are locked', $html);
    }

    /**
     * A pending resident AT the limit is still told about verification, not about
     * slots. The lock is the thing they can act on.
     */
    #[Test]
    public function an_unverified_resident_at_the_limit_is_still_shown_the_lock(): void
    {
        $resident = $this->pendingResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertStringContainsString("open-modal', 'requests-locked'", $html);
        $this->assertStringNotContainsString('show-toast', $html);
    }

    // ---------------------------------------------------------------------
    // The toast itself
    // ---------------------------------------------------------------------

    #[Test]
    public function the_toast_says_the_limit_was_reached(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertStringContainsString('Request limit reached', $html);
        $this->assertStringContainsString(
            'You have reached the maximum of '.DocumentRequest::OPEN_REQUEST_LIMIT.' document requests',
            $html
        );
        $this->assertStringContainsString(
            'no longer counts toward your limit',
            $html,
            'The toast does not say what to do next.'
        );
    }

    /**
     * The figure is rendered rather than typed, so the toast cannot drift away
     * from the constant the server enforces.
     */
    #[Test]
    public function the_toast_reads_the_limit_from_the_constant(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        // The rendered wording has the figure the page was given...
        $this->assertStringContainsString(
            'maximum of '.DocumentRequest::OPEN_REQUEST_LIMIT.' document requests',
            $this->page($resident)
        );

        // ...and the template reaches for that figure rather than repeating it.
        $this->assertSame(
            'limit',
            $this->limitVariableInTemplate(),
            'The toast is not rendering the limit from the page data.'
        );
    }

    /**
     * The Blade expression the limit message is built from, so the assertion
     * tracks the template rather than a number copied into the test.
     *
     * The message is assembled once in a @php block near the top of the page,
     * because two controls on the page say it.
     */
    private function limitVariableInTemplate(): string
    {
        $template = file_get_contents(resource_path('views/resident/requests/index.blade.php'));

        preg_match('~maximum of\s*\'\s*\.\s*\$(\w+)\s*\.\s*\'\s*document requests~', $template, $matches);

        $this->assertNotEmpty(
            $matches,
            'The toast is not rendering the limit from a view variable.'
        );

        return $matches[1];
    }

    /**
     * Announced, not merely shown. Without a live region a screen reader user
     * gets no signal that the press did anything, which is the original defect in
     * a different form.
     */
    #[Test]
    public function the_toast_is_a_polite_live_region(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertStringContainsString('role="status"', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
    }

    #[Test]
    public function the_toast_dismisses_itself_and_offers_a_close_button(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertStringContainsString('setTimeout', $html);
        $this->assertStringContainsString('Dismiss notification', $html);
        $this->assertStringContainsString('x-on:click="hide()"', $html);
    }

    /**
     * Above the navbar and the dialogs, and outside the flow, so it cannot be
     * clipped by the page container or painted over by an overlay opened by the
     * same press.
     */
    #[Test]
    public function the_toast_sits_above_the_page_and_the_overlays(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertStringContainsString('fixed', $html);
        $this->assertMatchesRegularExpression('/z-\[(?:[5-9]|\d{3})\]/', $html);

        // Fixed, so it is not inside the padded page container that would clip it.
        $this->assertStringNotContainsString('overflow-hidden', $this->toastMarkup($html));
    }

    /**
     * Narrow screens get a full-width panel anchored to the left edge. Pinned to
     * the right at 400px it would run off the side of the viewport.
     */
    #[Test]
    public function the_toast_is_responsive(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        // Full width across the viewport on a phone...
        $this->assertStringContainsString('inset-x-0', $html);

        // ...and a capped, right-aligned panel from the sm breakpoint up.
        $this->assertStringContainsString('sm:right-0', $html);
        $this->assertStringContainsString('max-w-sm', $html);
    }

    /**
     * One toast per page, listening for its own name, so a page carrying more
     * than one does not have them all react to the same press.
     */
    #[Test]
    public function the_toast_is_mounted_once_and_named(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);

        $html = $this->page($resident);

        $this->assertSame(
            1,
            substr_count($html, 'x-on:show-toast.window'),
            'The toast listener is not mounted exactly once.'
        );
        $this->assertStringContainsString("'request-limit'", $html);
    }

    /**
     * A resident who can open the form has nothing to be told here, so the toast
     * is not on their page at all.
     */
    #[Test]
    public function the_toast_is_not_on_the_page_for_a_resident_below_the_limit(): void
    {
        $resident = $this->verifiedResident();

        $this->assertStringNotContainsString('x-on:show-toast.window', $this->page($resident));
    }

    /**
     * Not on the pending page either, for the same reason: their button answers
     * with the lock dialog.
     */
    #[Test]
    public function the_toast_is_not_on_a_pending_residents_page(): void
    {
        $this->assertStringNotContainsString('x-on:show-toast.window', $this->page($this->pendingResident()));
    }

    // ---------------------------------------------------------------------
    // The Slots remaining card
    // ---------------------------------------------------------------------

    /**
     * The literal "&mdash;" on the card was an HTML entity written where the
     * filter-card component expects a value. The component renders its value
     * through {{ }}, which escapes it, so "&mdash;" reached the browser as
     * "&amp;mdash;" and the resident read those characters on the card.
     *
     * Asserted on the card alone, because the string also appears in this file
     * and in page comments.
     */
    #[Test]
    public function the_slots_card_never_shows_a_literal_html_entity(): void
    {
        foreach (['verified', 'pending'] as $state) {
            $resident = $this->resident($state);
            $this->fillToLimit($resident);

            $card = $this->slotsCardMarkup($this->page($resident));

            $this->assertStringNotContainsString('mdash', $card);
            $this->assertStringNotContainsString('&mdash;', $card);
        }
    }

    /**
     * A pending resident is shown the figure their own requests leave, not a
     * placeholder and not an allowance they cannot spend either way.
     */
    #[Test]
    public function a_pending_resident_is_shown_the_slots_their_requests_leave(): void
    {
        $resident = $this->pendingResident();

        // Nothing filed yet: the whole allowance is nominally left.
        $this->assertStringContainsString(
            '>'.DocumentRequest::OPEN_REQUEST_LIMIT.'<',
            $this->slotsCardMarkup($this->page($resident))
        );

        // One open request takes one off it.
        DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $this->assertStringContainsString(
            '>'.(DocumentRequest::OPEN_REQUEST_LIMIT - 1).'<',
            $this->slotsCardMarkup($this->page($resident))
        );
    }

    /**
     * The count is never the hardcoded allowance: a verified resident at the
     * limit sees zero, and a claimed request does not count against them.
     */
    #[Test]
    public function the_slots_card_counts_the_residents_own_open_requests(): void
    {
        $resident = $this->verifiedResident();

        $this->fillToLimit($resident);

        // fillToLimit files one request in each of the three OPEN_STATUSES, so all
        // three count and the allowance is spent.
        $this->assertStringContainsString('>0<', $this->slotsCardMarkup($this->page($resident)));

        // Claiming one leaves two open - claimed is finished - so one slot comes
        // back. This is the assertion that would fail if the card printed the
        // allowance instead of the calculation.
        DocumentRequest::whereBelongsTo($resident)
            ->where('status', DocumentRequest::STATUS_SUBMITTED)
            ->first()
            ->update(['status' => DocumentRequest::STATUS_CLAIMED]);

        $this->assertSame(2, DocumentRequest::openCountFor($resident));

        $this->assertStringContainsString(
            '>'.(DocumentRequest::OPEN_REQUEST_LIMIT - 2).'<',
            $this->slotsCardMarkup($this->page($resident))
        );
    }

    /**
     * A pending resident's card says what the number means, because a figure they
     * cannot act on reads as a refusal unless it is labelled.
     */
    #[Test]
    public function a_pending_residents_card_is_labelled_locked_until_verified(): void
    {
        $card = $this->slotsCardMarkup($this->page($this->pendingResident()));

        $this->assertStringContainsString('Locked until verified', $card);
        $this->assertStringNotContainsString('Start a new request', $card);
    }

    /**
     * All three states answer a press, so no state of this card is a control
     * that does nothing.
     */
    #[Test]
    public function the_slots_card_answers_a_press_in_every_state(): void
    {
        $atLimit = $this->slotsCardMarkup($this->page($this->atLimitResident()));
        $this->assertStringContainsString('show-toast', $atLimit);

        $pending = $this->slotsCardMarkup($this->page($this->pendingResident()));
        $this->assertStringContainsString("open-modal', 'requests-locked'", $pending);

        $below = $this->slotsCardMarkup($this->page($this->verifiedResident()));
        $this->assertStringContainsString("open-modal', 'new-request'", $below);
    }

    // ---------------------------------------------------------------------
    // The server is still the authority
    // ---------------------------------------------------------------------

    /**
     * The toast is a courtesy. Posting straight to the store route is how a
     * resident bypasses the frontend, and the server has to refuse on its own.
     */
    #[Test]
    public function the_server_still_refuses_a_fourth_request_posted_directly(): void
    {
        $resident = $this->verifiedResident();
        $this->fillToLimit($resident);
        $type = DocumentType::factory()->create();

        $this->actingAs($resident)
            ->post(route('resident.requests.store'), [
                'document_type_id' => $type->id,
                'purpose' => 'Employment requirement.',
            ])
            ->assertSessionHasErrors('account');

        $this->assertSame(
            DocumentRequest::OPEN_REQUEST_LIMIT,
            DocumentRequest::openCountFor($resident),
            'The refused request was written anyway.'
        );
    }

    /**
     * Which statuses count is unchanged by any of this: the same three still fill
     * the allowance, and a claimed or rejected request still frees its slot.
     */
    #[Test]
    public function the_statuses_that_count_toward_the_limit_are_unchanged(): void
    {
        $resident = $this->verifiedResident();

        foreach (DocumentRequest::OPEN_STATUSES as $status) {
            DocumentRequest::factory()->forResident($resident)->status($status)->create();
        }

        $this->assertSame(DocumentRequest::OPEN_REQUEST_LIMIT, DocumentRequest::openCountFor($resident));

        // A finished request frees its slot, which is what stops the limit from
        // being a permanent lockout.
        DocumentRequest::whereBelongsTo($resident)
            ->where('status', DocumentRequest::STATUS_SUBMITTED)
            ->first()
            ->update(['status' => DocumentRequest::STATUS_CLAIMED]);

        $this->actingAs($resident)
            ->post(route('resident.requests.store'), [
                'document_type_id' => DocumentType::factory()->create()->id,
                'purpose' => 'Employment requirement.',
            ])
            ->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function fillToLimit(User $resident): void
    {
        foreach (DocumentRequest::OPEN_STATUSES as $status) {
            DocumentRequest::factory()->forResident($resident)->status($status)->create();
        }
    }

    /**
     * The Slots remaining card on its own.
     *
     * It renders as an <a> or a <button> depending on what a press means, so this
     * finds the first such element carrying the label. Scoping assertions to it
     * stops them being satisfied by a string that appears elsewhere on the page.
     */
    private function slotsCardMarkup(string $html): string
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML('<?xml encoding="UTF-8">'.$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach ((new DOMXPath($document))->query('//a|//button') as $node) {
            if (str_contains($node->textContent, 'Slots remaining')) {
                return (string) $document->saveHTML($node);
            }
        }

        $this->fail('The Slots remaining card was not found as an <a> or a <button>.');
    }

    /**
     * A resident in whichever of the two states the caller names.
     */
    private function resident(string $state): User
    {
        return $state === 'pending' ? $this->pendingResident() : $this->verifiedResident();
    }

    /**
     * A verified resident who has already spent the allowance.
     */
    private function atLimitResident(): User
    {
        $resident = $this->verifiedResident();

        $this->fillToLimit($resident);

        return $resident;
    }

    private function page(User $resident): string
    {
        return $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    /**
     * Just the New Request control, so an assertion about it cannot be satisfied
     * by the same string appearing somewhere else on the page.
     */
    private function newRequestButton(string $html): string
    {
        preg_match('/<button[^>]*>[\s\S]{0,900}?\+ New Request<\//s', $html, $matches);

        $this->assertNotEmpty($matches, 'The New Request button was not found on the page.');

        return $matches[0];
    }

    private function toastMarkup(string $html): string
    {
        preg_match('~<div\s+x-data="\{[^{}]*visible: false.*?</div>\s*</div>~s', $html, $matches);

        return $matches[0] ?? '';
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
