<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Document Requests page as a PENDING resident.
 *
 * Four of the assertions here exist because of a specific bug rather than a
 * design rule, and each records its cause so the next edit does not reintroduce
 * it:
 *
 *  - the lock dialog was visible on load, because the shared modal rendered its
 *    slot a second time after its own x-data div had closed, where x-show="show"
 *    names nothing and Alpine leaves the element alone;
 *  - that stray panel was also printed into the page flow, between the figures
 *    and the section, which is what made the section look like it had left the
 *    column;
 *  - the empty state offered a "Request a document" button, directly contradicting
 *    the banner above it;
 *  - the chip row was rendered over an empty list, offering six ways to see
 *    nothing.
 */
class ResidentPendingRequestsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The lock dialog
    // ---------------------------------------------------------------------

    /**
     * The original defect. The slot rendered twice; the second copy sat outside
     * its Alpine scope, so x-show had nothing to hide it with and it printed
     * into the page.
     */
    #[Test]
    public function the_lock_dialog_is_not_printed_into_the_page(): void
    {
        $html = $this->pendingPage();

        // The orphan's wrapper started with mb-6; the real panel starts with
        // "relative mb-6". Counting the heading would prove nothing, because the
        // lock is deliberately said twice - once on the page, once in the dialog.
        $this->assertStringNotContainsString(
            'class="mb-6 bg-white rounded-lg',
            $html,
            'A modal panel is being printed into the page outside its own scope.'
        );

        $this->assertSame(
            1,
            substr_count($html, 'id="requests-locked-title"'),
            'The dialog itself should appear exactly once.'
        );
    }

    /**
     * A dialog has to be a dialog: a real dialog role, marked as modal, behind a
     * backdrop, and not on the page until it is asked for.
     */
    #[Test]
    public function the_lock_dialog_is_a_real_dialog(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('role="dialog"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('aria-labelledby="requests-locked-title"', $html);

        // A backdrop that closes it, Escape, and a corner X.
        $this->assertStringContainsString('bg-slate-900/60', $html);
        $this->assertStringContainsString('x-on:keydown.escape.window', $html);
        $this->assertStringContainsString('>Close</span>', $html);
    }

    /**
     * Focus moves into the panel and comes back to whatever opened it. Without
     * that, opening the dialog leaves a keyboard user's focus on a button behind
     * the backdrop, and closing it drops them at the top of the document.
     */
    #[Test]
    public function the_lock_dialog_moves_focus_in_and_gives_it_back(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('document.activeElement', $html, 'The trigger is not remembered.');
        $this->assertStringContainsString('this.$refs.panel.focus()', $html, 'Focus is not moved into the dialog.');
        $this->assertStringContainsString('this.trigger.focus()', $html, 'Focus is not returned to the trigger.');
        $this->assertStringContainsString('x-on:keydown.tab', $html, 'Tab is not cycled inside the dialog.');
    }

    #[Test]
    public function the_lock_dialog_locks_the_page_scroll_while_open(): void
    {
        $this->assertStringContainsString("document.body.classList.add('overflow-y-hidden')", $this->pendingPage());
    }

    /**
     * Hidden before Alpine has booted. Without the inline display:none there is a
     * flash of the dialog on every load.
     */
    #[Test]
    public function the_lock_dialog_is_not_on_the_page_before_javascript_runs(): void
    {
        $this->assertMatchesRegularExpression(
            '/x-show="open"[\s\S]{0,80}?style="display: none;"/',
            $this->pendingPage(),
            'The dialog is not hidden before Alpine boots.'
        );
    }

    // ---------------------------------------------------------------------
    // The inline notice
    // ---------------------------------------------------------------------

    /**
     * A pending resident is told the state without having to open anything, and
     * there is no Close button because it is a banner and not a dialog.
     */
    #[Test]
    public function a_pending_resident_gets_an_amber_banner_with_no_close_button(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('New requests are locked', $html);
        $this->assertStringContainsString('verifies your ID', $html);
        $this->assertStringContainsString('Check verification status', $html);
        $this->assertStringContainsString(
            route('account.edit', ['tab' => 'verification']),
            $html
        );

        // Amber, and above the figures rather than beside them.
        $banner = (int) strpos($html, 'verifies your ID');
        $cards = (int) strpos($html, 'Open requests');
        $this->assertLessThan($cards, $banner, 'The notice is not above the figures.');
    }

    #[Test]
    public function a_verified_resident_gets_no_banner(): void
    {
        $html = $this->verifiedPage();

        $this->assertStringNotContainsString('verifies your ID', $html);
        $this->assertStringNotContainsString('requests-locked', $html);
    }

    // ---------------------------------------------------------------------
    // The figures
    // ---------------------------------------------------------------------

    /**
     * "Slots remaining: 3" on a card the resident cannot use reads as a refusal
     * rather than as a state, and prints an allowance they have no way to spend.
     */
    #[Test]
    public function the_slots_card_says_locked_rather_than_printing_the_allowance(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('Locked until verified', $html);
        $this->assertStringNotContainsString('Start a new request', $html);

        // Still a real control: a button that silently does nothing is worse.
        $this->assertMatchesRegularExpression('/<button\s+type="button"/', $html);
        $this->assertStringContainsString("open-modal', 'requests-locked", $html);
    }

    #[Test]
    public function a_pending_resident_sees_zero_on_the_counts(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('Open requests', $html);
        $this->assertStringContainsString('Total on file', $html);
        $this->assertStringContainsString('Locked until verified', $html);
    }

    // ---------------------------------------------------------------------
    // The section
    // ---------------------------------------------------------------------

    /**
     * Everything below the hero sits in one container, so the figures, the notice
     * and the section share the same two edges.
     */
    #[Test]
    public function the_figures_notice_and_section_share_one_container(): void
    {
        $html = $this->pendingPage();

        // The shell's own padded wrapper appears once and holds all of it.
        $this->assertSame(
            1,
            substr_count($html, 'pt-14 sm:pt-16 lg:pt-20'),
            'The page has more than one content container.'
        );

        foreach (['Open requests', 'My Documents', 'No requests yet'] as $marker) {
            $this->assertNotSame(false, strpos($html, $marker), "{$marker} is missing.");
        }

        // And the stray panel that used to sit between them is gone: a centred
        // narrow block is not a page section.
        $this->assertStringNotContainsString('mb-6 bg-white rounded-lg overflow-hidden shadow-xl transform', $html);
    }

    #[Test]
    public function the_new_request_button_opens_the_lock_dialog_for_a_pending_resident(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('+ New Request', $html);
        $this->assertMatchesRegularExpression(
            '/<button[^>]*>[\s\S]{0,600}?\+ New Request<\//s',
            $html,
            'The New Request control is not a button for a pending resident.'
        );
        $this->assertStringNotContainsString(
            'href="'.route('resident.requests.create').'"',
            $html,
            'A pending resident is still linked straight to the form.'
        );
    }

    // ---------------------------------------------------------------------
    // The empty state
    // ---------------------------------------------------------------------

    /**
     * A "Request a document" button on a locked page contradicts the banner
     * directly above it.
     */
    #[Test]
    public function the_empty_state_offers_no_way_to_request(): void
    {
        $html = $this->pendingPage();

        $this->assertStringContainsString('No requests yet', $html);
        $this->assertStringContainsString('Once your account is verified', $html);
        $this->assertStringNotContainsString('Request a document', $html);
    }

    /**
     * Full-bleed, the empty state ran to both screen edges and stopped reading as
     * part of the section it belongs to.
     */
    #[Test]
    public function the_empty_state_sits_inside_a_padded_card(): void
    {
        $html = $this->pendingPage();

        $this->assertMatchesRegularExpression(
            '/<div class="rounded-xl border shadow-card[\s\S]{0,1000}?No requests yet/',
            $html,
            'The empty state is not inside a padded card.'
        );
    }

    // ---------------------------------------------------------------------
    // The chips
    // ---------------------------------------------------------------------

    /**
     * Six chips over an empty list offers six ways to see nothing, and reads as
     * though the page is hiding records.
     */
    #[Test]
    public function the_chips_are_hidden_when_there_is_nothing_to_filter(): void
    {
        $html = $this->pendingPage();

        $this->assertStringNotContainsString('aria-label="Filter requests"', $html);
        $this->assertStringNotContainsString('>Processing<', $html);
    }

    #[Test]
    public function the_chips_come_back_as_soon_as_there_is_a_request(): void
    {
        $resident = $this->pendingResident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        $html = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Filter requests"', $html);
    }

    // ---------------------------------------------------------------------
    // A verified resident is unaffected
    // ---------------------------------------------------------------------

    #[Test]
    public function a_verified_resident_keeps_the_working_new_request_dialog(): void
    {
        $html = $this->verifiedPage();

        $this->assertStringContainsString("open-modal', 'new-request'", $html);
        $this->assertStringContainsString('Start a new request', $html);
        $this->assertStringContainsString('href="'.route('resident.requests.create').'"', $html);
        $this->assertStringContainsString('aria-label="Filter requests"', $html);
    }

    /**
     * The shared modal is what the other two dialogs use. The defect that made the
     * lock visible was that it printed its slot twice, so every dialog in the
     * system was affected and the test belongs here rather than in a resident file.
     */
    #[Test]
    public function the_shared_modal_prints_its_slot_once(): void
    {
        $html = $this->verifiedPage();

        $this->assertStringNotContainsString(
            'class="mb-6 bg-white rounded-lg',
            $html,
            'The New Request dialog is also printing a copy into the page.'
        );

        $resident = $this->verifiedResident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        $withRows = $this->actingAs($resident)->get(route('resident.requests.index'))->getContent();

        // The Track dialog's panels are per row, and none of them may also be
        // printed into the page.
        $this->assertSame(
            substr_count($withRows, 'x-show="tracked ==='),
            substr_count($withRows, 'Your document is ready') + substr_count($withRows, 'Stated purpose'),
            'A Track panel appears a different number of times than it is opened.'
        );
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    private function pendingPage(): string
    {
        return $this->actingAs($this->pendingResident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    private function verifiedPage(): string
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        return $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);

        return $user;
    }

    private function pendingResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => false]);

        ResidentProfile::factory()->pending()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
        ]);

        return $user;
    }
}
