<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The three figures on the resident's Document Requests page, which are also the
 * filter, and the status chips beside them.
 *
 * The two things worth guarding are the count and the scoping. "Total on file"
 * was reading the rendered collection, so it changed when a filter was applied -
 * clicking "Open requests" made the same household's total fall from four to two,
 * which is a figure that lies. And the filter is a query parameter on a page that
 * also carries the resident's own data, so it has to narrow their rows and nobody
 * else's.
 */
class ResidentRequestFilterTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Total on file
    // ---------------------------------------------------------------------

    /**
     * The bug this file exists for: the total was read off the collection the
     * page had just filtered, so applying any filter changed it.
     */
    #[Test]
    #[DataProvider('statusFilters')]
    public function the_total_is_every_request_whatever_the_filter(string $status): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => $status]))
            ->assertOk()
            ->getContent();

        $this->assertSame(
            '5',
            $this->figureIn($html, 'Total on file'),
            "The total is not 5 while filtering by {$status}. It was reading the filtered collection."
        );
    }

    #[Test]
    public function the_total_is_unchanged_with_no_filter(): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->assertSee('Total on file');
    }

    // ---------------------------------------------------------------------
    // The filter
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('statusFilterExpectations')]
    public function a_filter_shows_only_that_state(string $status, int $expected): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => $status]))
            ->assertOk()
            ->getContent();

        foreach (DocumentRequest::STATUSES as $candidate) {
            $request = $resident->documentRequests()->where('status', $candidate)->firstOrFail();

            $present = str_contains($html, $request->reference_number);

            $this->assertSame(
                $expected > 0 ? $this->candidateIsExpected($status, $candidate) : false,
                $present,
                "{$request->reference_number} ({$candidate}) shown wrongly for the {$status} filter."
            );
        }
    }

    /**
     * A hand-edited or stale URL should show the resident their documents, not an
     * empty page that looks like they have none.
     */
    #[Test]
    public function an_unknown_filter_falls_back_to_everything(): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => 'nonsense']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Nothing in this filter', $html);
        // Scoped to the chip bar: the breadcrumb above also marks the current
        // page, so a page-wide search would always find one.
        $chips = substr(
            $html,
            (int) strpos($html, 'aria-label="Filter requests"'),
            (int) strpos($html, '</nav>', (int) strpos($html, 'aria-label="Filter requests"'))
                - (int) strpos($html, 'aria-label="Filter requests"')
        );

        // Exactly one chip is current, and it is All: an unrecognised value
        // resolves to no filter, so All is the state the page is showing.
        $this->assertSame(1, substr_count($chips, 'aria-current="page"'), 'More than one chip claims to be current.');
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('resident.requests.index'), '/').'"[^>]*aria-current="page"[^>]*>All</',
            $chips,
            'All should be the current chip after an unrecognised filter.'
        );

        foreach ($resident->documentRequests as $request) {
            $this->assertStringContainsString(
                $request->reference_number,
                $html,
                'An unrecognised filter hid a request instead of falling back to all of them.'
            );
        }
    }

    // ---------------------------------------------------------------------
    // The cards
    // ---------------------------------------------------------------------

    /**
     * The cards are controls, so they have to be real ones. A div with a click
     * handler is not focusable, cannot be activated from the keyboard, and
     * announces nothing - the figure would be visible and entirely unusable for
     * anyone not using a mouse.
     */
    #[Test]
    public function the_filter_cards_are_real_links(): void
    {
        $html = $this->actingAs($this->resident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.requests.index', ['status' => 'open']), '/').'"[^>]*>/',
            $html,
            'Open requests is not a link to the open filter.'
        );

        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.requests.index'), '/').'"[^>]*aria-pressed="true"/',
            $html,
            'Total on file is not a link that clears the filter and is marked active.'
        );
    }

    #[Test]
    public function the_slots_card_is_a_real_button(): void
    {
        $html = $this->actingAs($this->resident())
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<button\s+type="button"/',
            $html,
            'Slots remaining is not a real button.'
        );
        $this->assertStringContainsString("open-modal', 'new-request", $html);
    }

    /**
     * Keyboard users have to be able to see where they are, so the focus ring
     * cannot be left to the browser default on a card that sets its own border.
     */
    #[Test]
    public function the_cards_have_a_visible_focus_ring_and_an_active_outline(): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => 'open']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('focus-visible:ring-2', $html);
        $this->assertStringContainsString('ring-gold', $html);
        $this->assertStringContainsString('aria-pressed="true"', $html);
    }

    #[Test]
    public function the_active_card_is_the_one_matching_the_url(): void
    {
        $resident = $this->resident();
        $this->oneOfEachStatus($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => 'claimed']))
            ->assertOk()
            ->getContent();

        // Total on file is the unfiltered card, so it must not be marked pressed
        // while a filter is on.
        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.requests.index'), '/').'"[^>]*aria-pressed="false"/',
            $html,
            'Total on file is still marked active while a filter is on.'
        );
    }

    // ---------------------------------------------------------------------
    // Slots
    // ---------------------------------------------------------------------

    #[Test]
    public function the_slots_card_is_disabled_at_zero(): void
    {
        $resident = $this->resident();

        // Three open requests fills the allowance.
        foreach (range(1, 3) as $ignored) {
            DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);
        }

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('None left', $html);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringNotContainsString("open-modal', 'new-request", $html);
    }

    /**
     * A pending resident is not simply refused: the card explains, and the
     * explanation carries the way out, so they are not left hunting for why.
     */
    #[Test]
    public function a_pending_resident_is_shown_the_lock_and_the_way_out(): void
    {
        $resident = $this->pendingResident();

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString("open-modal', 'requests-locked", $html);
        $this->assertStringContainsString('New requests are locked', $html);
        $this->assertStringContainsString(
            route('account.edit', ['tab' => 'verification']),
            $html,
            'The lock does not offer the way out.'
        );
        $this->assertStringNotContainsString("open-modal', 'new-request", $html);
    }

    // ---------------------------------------------------------------------
    // The chips
    // ---------------------------------------------------------------------

    #[Test]
    public function every_chip_is_a_link_to_a_real_url(): void
    {
        $resident = $this->resident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('aria-label="Filter requests"', $html);

        foreach (['All', 'Open', 'Processing', 'Ready for pickup', 'Completed', 'Rejected'] as $label) {
            $this->assertStringContainsString('>'.$label.'</a>', $html, "The {$label} chip is missing.");
        }

        // "All" is the unparameterised URL, so the unfiltered page has one
        // canonical address rather than two that mean the same thing.
        $this->assertStringContainsString('href="'.route('resident.requests.index').'"', $html);
    }

    #[Test]
    public function the_chips_agree_with_the_cards(): void
    {
        $resident = $this->resident();
        DocumentRequest::factory()->underReview()->create(['user_id' => $resident->id]);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => 'under_review']))
            ->assertOk()
            ->getContent();

        // Under review is both a chip and the status behind "Processing", and the
        // chip and the card must be talking about the same thing.
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('resident.requests.index', ['status' => 'under_review']), '/').'"[^>]*aria-current="page"/',
            $html,
            'The Processing chip is not marked as the current filter.'
        );
    }

    /**
     * An empty filter has to explain itself. "No requests yet" is a lie when the
     * resident has four on file and is looking at Completed.
     */
    #[Test]
    public function an_empty_filter_says_so_and_offers_a_way_out(): void
    {
        $resident = $this->resident();
        DocumentRequest::factory()->submitted()->create(['user_id' => $resident->id]);

        $html = $this->actingAs($resident)
            ->get(route('resident.requests.index', ['status' => 'claimed']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nothing in this filter', $html);
        $this->assertStringContainsString('Show all my requests', $html);
        $this->assertStringNotContainsString('No requests yet', $html);
    }

    // ---------------------------------------------------------------------
    // Scoping
    // ---------------------------------------------------------------------

    /**
     * The filter is a parameter on a page holding somebody's own data, so it has
     * to narrow their rows and nobody else's.
     */
    #[Test]
    public function a_filter_never_reveals_another_residents_requests(): void
    {
        $mine = $this->resident();
        $theirs = $this->resident();

        DocumentRequest::factory()->claimed()->create(['user_id' => $mine->id]);
        $foreign = DocumentRequest::factory()->claimed()->create(['user_id' => $theirs->id]);

        $html = $this->actingAs($mine)
            ->get(route('resident.requests.index', ['status' => 'claimed']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($foreign->reference_number, $html);
    }

    #[Test]
    public function the_unfiltered_list_is_still_scoped_to_the_signed_in_resident(): void
    {
        $mine = $this->resident();
        $theirs = $this->resident();

        $foreign = DocumentRequest::factory()->submitted()->create(['user_id' => $theirs->id]);
        DocumentRequest::factory()->submitted()->create(['user_id' => $mine->id]);

        $html = $this->actingAs($mine)
            ->get(route('resident.requests.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($foreign->reference_number, $html);
    }

    /**
     * The figure shown on the card carrying a given label.
     *
     * Read out of the rendered card rather than asserted as a literal string,
     * because Blade puts newlines between the tag name and its attributes and a
     * literal match would be testing the compiler's whitespace rather than the
     * number the resident reads.
     */
    private function figureIn(string $html, string $label): string
    {
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($label, '/').'/',
            $html,
            "The {$label} card is missing."
        );

        // The figure sits above the label inside the same card, so take the
        // nearest tabular-nums value before the label.
        $before = substr($html, 0, (int) strpos($html, $label));

        preg_match_all('/tabular-nums"[^>]*>\s*([\d,]+)\s*<\/p>/', $before, $matches);

        return (string) end($matches[1]);
    }

    /**
     * Whether a status belongs in a given filter.
     *
     * Written out here rather than reusing the controller's own list, because a
     * test that asks the code under test what the answer is proves nothing.
     */
    private function candidateIsExpected(string $filter, string $status): bool
    {
        return match ($filter) {
            'open' => in_array($status, [
                DocumentRequest::STATUS_SUBMITTED,
                DocumentRequest::STATUS_UNDER_REVIEW,
                DocumentRequest::STATUS_READY,
            ], true),
            'under_review' => $status === DocumentRequest::STATUS_UNDER_REVIEW,
            'ready_for_pickup' => $status === DocumentRequest::STATUS_READY,
            'claimed' => $status === DocumentRequest::STATUS_CLAIMED,
            'rejected' => $status === DocumentRequest::STATUS_REJECTED,
            default => false,
        };
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function statusFilters(): array
    {
        return [
            'open' => ['open', 3],
            'under review' => ['under_review', 1],
            'ready' => ['ready_for_pickup', 1],
            'claimed' => ['claimed', 1],
            'rejected' => ['rejected', 1],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function statusFilterExpectations(): array
    {
        return self::statusFilters();
    }

    /**
     * One request in each of the five states, so a filter either shows its own
     * or shows nothing.
     */
    private function oneOfEachStatus(User $resident): void
    {
        foreach (DocumentRequest::STATUSES as $status) {
            DocumentRequest::factory()->create([
                'user_id' => $resident->id,
                'document_type_id' => DocumentType::factory()->create()->id,
                'status' => $status,
            ]);
        }
    }

    private function resident(): User
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
