<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The relief page's figures, its filter, and the two spacing problems the tables
 * had.
 *
 * The spacing ones are here because they are invisible in a unit test of logic
 * and obvious on a phone. The chip row sat flush against a bordered table, and
 * the first column did not line up with its header - the second of which has a
 * cause worth recording, so the assertion is on the cause as well as the effect.
 */
class ResidentReliefFilterTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The figures
    // ---------------------------------------------------------------------

    /**
     * Counted over every batch the household has been on, never over the rows the
     * table is showing. A count that changed when a chip was pressed would be a
     * figure that lies.
     */
    #[Test]
    #[DataProvider('reliefFilters')]
    public function the_figures_do_not_change_when_a_chip_is_pressed(?string $status): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.relief', $status === null ? [] : ['status' => $status]))
            ->assertOk()
            ->getContent();

        $this->assertSame('3', $this->figureIn($html, 'Relief batches'));
        $this->assertSame('2', $this->figureIn($html, 'Waiting to receive'));
        $this->assertSame('1', $this->figureIn($html, 'Received by my household'));
    }

    #[Test]
    public function the_three_figures_are_real_links_that_filter(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.relief'), '/').'"[^>]*aria-pressed="true"/',
            $html,
            'Relief batches does not clear the filter and claim to be active.'
        );
        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.relief', ['status' => 'pending']), '/').'"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<a\s+href="'.preg_quote(route('resident.relief', ['status' => 'received']), '/').'"/',
            $html
        );
    }

    // ---------------------------------------------------------------------
    // The filter
    // ---------------------------------------------------------------------

    #[Test]
    public function each_chip_shows_only_its_own_state(): void
    {
        $resident = $this->verifiedResident();
        $pending = $this->pendingDistribution($resident, ReliefBatch::factory()->create(['title' => 'Pending batch']));
        $received = $this->receivedDistribution($resident, ReliefBatch::factory()->create(['title' => 'Received batch']));

        $pendingHtml = $this->actingAs($resident)
            ->get(route('resident.relief', ['status' => 'pending']))->assertOk()->getContent();
        $this->assertStringContainsString('Pending batch', $pendingHtml);
        $this->assertStringNotContainsString('Received batch', $pendingHtml);

        $receivedHtml = $this->actingAs($resident)
            ->get(route('resident.relief', ['status' => 'received']))->assertOk()->getContent();
        $this->assertStringContainsString('Received batch', $receivedHtml);
        $this->assertStringNotContainsString('Pending batch', $receivedHtml);
    }

    #[Test]
    public function an_unrecognised_chip_shows_everything(): void
    {
        $resident = $this->verifiedResident();
        $this->pendingDistribution($resident, ReliefBatch::factory()->create(['title' => 'Still listed']));

        $html = $this->actingAs($resident)
            ->get(route('resident.relief', ['status' => 'nonsense']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Still listed', $html);
        $this->assertStringNotContainsString('Nothing in this filter', $html);
    }

    #[Test]
    public function an_empty_filter_explains_itself_and_offers_a_way_out(): void
    {
        $resident = $this->verifiedResident();
        $this->pendingDistribution($resident);

        $html = $this->actingAs($resident)
            ->get(route('resident.relief', ['status' => 'received']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Nothing in this filter', $html);
        $this->assertStringContainsString('Show all my relief', $html);
    }

    // ---------------------------------------------------------------------
    // What was taken out
    // ---------------------------------------------------------------------

    #[Test]
    public function the_two_banner_lines_are_gone(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringNotContainsString('These are the records for household', $html);
        $this->assertStringNotContainsString('Batches going out now', $html);
    }

    /**
     * One section, not two lists of the same thing from opposite directions.
     */
    #[Test]
    public function there_is_one_section_and_it_is_the_households_own(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('My household', $html);
        $this->assertStringNotContainsString('What my household has received', $html);
    }

    // ---------------------------------------------------------------------
    // The table
    // ---------------------------------------------------------------------

    #[Test]
    public function the_table_has_the_four_requested_columns(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        foreach (['Batch', 'Distribution date', 'My household', 'Collected by'] as $column) {
            $this->assertMatchesRegularExpression('/>\s*'.preg_quote($column, '/').'\s*</', $html, "The {$column} column is missing.");
        }
    }

    /**
     * The first column is a th, and the browser centres a th by default. Tailwind
     * preflight does not reset that, so the cell has to say text-left or it sits
     * in the middle of its own column while every td beside it is left.
     */
    #[Test]
    public function the_first_column_cells_are_left_aligned_with_their_header(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);
        DocumentRequest::factory()->forResident($resident)->create();

        foreach (['resident.relief' => 'relief', 'resident.requests.index' => 'requests'] as $route => $label) {
            $html = $this->actingAs($resident)
                ->get(route($route))
                ->assertOk()
                ->getContent();

            $this->assertMatchesRegularExpression(
                '/<th scope="row" class="[^"]*\btext-left\b/',
                $html,
                "The {$label} table's row header is not explicitly left-aligned, so the browser centres it."
            );
        }
    }

    /**
     * The items a batch includes are one column of text on the batch cell. Split
     * into tags, so a household can see at a glance what is in the box.
     */
    #[Test]
    public function the_batch_items_are_split_into_tags(): void
    {
        $resident = $this->verifiedResident();
        $batch = ReliefBatch::factory()->create([
            'items_included' => ['5kg Rice', '6 Canned Goods', '3 Instant Noodles'],
        ]);
        $this->pendingDistribution($resident, $batch);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('5kg Rice', $html);
        $this->assertStringContainsString('6 Canned Goods', $html);
        $this->assertStringContainsString('rounded-full bg-navy-50', $html);
    }

    /**
     * The one thing a resident asking about a pending row cannot work out for
     * themselves.
     */
    #[Test]
    public function a_pending_row_in_an_active_batch_says_what_to_bring(): void
    {
        $resident = $this->verifiedResident();
        $this->pendingDistribution($resident, ReliefBatch::factory()->create(['status' => ReliefBatch::STATUS_ACTIVE]));

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('Bring a valid ID when claiming', $html);
    }

    #[Test]
    public function a_received_row_names_who_signed_and_when(): void
    {
        $resident = $this->verifiedResident();
        $this->receivedDistribution($resident);

        $html = $this->actingAs($resident)->get(route('resident.relief'))->assertOk()->getContent();

        $this->assertStringContainsString('Maria Santos', $html);
    }

    // ---------------------------------------------------------------------
    // Spacing
    // ---------------------------------------------------------------------

    /**
     * Flush against a bordered table, the chip row reads as part of the table and
     * stops looking switchable.
     */
    #[Test]
    public function there_is_a_gap_between_the_chips_and_the_table(): void
    {
        $resident = $this->verifiedResident();
        $this->threeDistributions($resident);

        // The requests table renders only when there is a request, or the page
        // shows its empty state and there is no table to measure a gap against.
        DocumentRequest::factory()->forResident($resident)->create();

        foreach (['resident.relief', 'resident.requests.index'] as $route) {
            $html = $this->actingAs($resident)->get(route($route))->assertOk()->getContent();

            $chips = (int) strpos($html, 'aria-label="Filter');
            $this->assertNotSame(0, $chips, "{$route} has no chip bar.");

            $table = (int) strpos($html, '<table');
            $this->assertGreaterThan($chips, $table, "{$route} has no table after its chips.");

            // The wrapper that separates them carries the spacing.
            $between = substr($html, $chips, $table - $chips);
            $this->assertMatchesRegularExpression(
                '/mt-4/',
                $between,
                "{$route} has no gap between its chips and the table."
            );
        }
    }

    // ---------------------------------------------------------------------
    // The locked state
    // ---------------------------------------------------------------------

    #[Test]
    public function a_pending_resident_still_gets_the_locked_page(): void
    {
        $html = $this->actingAs($this->pendingResident())
            ->get(route('resident.relief'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Relief records unlock', $html);

        // No figures, no chips, no table: a pending resident is told why rather
        // than shown a filtered-looking empty page.
        $this->assertStringNotContainsString('Relief batches', $html);
        $this->assertStringNotContainsString('aria-label="Filter', $html);
    }

    // ---------------------------------------------------------------------
    // The track dialog
    // ---------------------------------------------------------------------

    /**
     * The dialog replaced the single-request page, so it has to carry everything
     * that page carried. Missing one field would be losing it quietly.
     */
    #[Test]
    public function the_track_dialog_carries_everything_the_request_page_did(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->create([
            'purpose' => 'For a medical appointment at the provincial hospital.',
            'status' => DocumentRequest::STATUS_REJECTED,
        ]);
        $request->update(['rejection_reason' => 'Please state the receiving office instead.']);

        $html = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        foreach ([
            'Stated purpose' => $request->purpose,
            'Submitted' => 'Submitted',
            'Please state the receiving office instead.' => 'the rejection reason',
            'Rejected' => 'the status badge',
        ] as $needle => $what) {
            $this->assertStringContainsString($needle, $html, "The dialog is missing {$what}.");
        }

        // The timeline, which was the biggest thing on the old page.
        $this->assertStringContainsString('x-show="tracked ===', $html);
    }

    /**
     * One dialog, and Close is the only thing in it that is an action. A second
     * link out of the dialog is what made the separate page look necessary.
     */
    #[Test]
    public function the_track_dialog_offers_only_close(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->create();

        $html = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('View full request', $html);
        $this->assertStringNotContainsString('resident.requests.show', $html);
        $this->assertStringContainsString('Close', $html);
    }

    #[Test]
    public function a_ready_request_tells_the_resident_where_to_collect_it(): void
    {
        $resident = $this->verifiedResident();
        DocumentRequest::factory()->forResident($resident)->ready()->create();

        $html = $this->actingAs($resident)->get(route('resident.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Your document is ready', $html);
        $this->assertStringContainsString('Barangay Hall', $html);
    }

    #[Test]
    public function the_old_request_page_redirects_and_nothing_links_to_it(): void
    {
        $resident = $this->verifiedResident();
        $request = DocumentRequest::factory()->forResident($resident)->create();

        $this->actingAs($resident)
            ->get(route('resident.requests.show', $request))
            ->assertRedirect(route('resident.requests.index'));

        $this->assertStringNotContainsString(
            'resident.requests.show',
            $this->actingAs($resident)->get(route('resident.requests.index'))->getContent()
        );
    }

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array{0: string|null}>
     */
    public static function reliefFilters(): array
    {
        return [
            'all' => [null],
            'pending' => ['pending'],
            'received' => ['received'],
        ];
    }

    /**
     * The figure on the card carrying a given label.
     */
    private function figureIn(string $html, string $label): string
    {
        $this->assertStringContainsString($label, $html, "The {$label} card is missing.");

        $before = substr($html, 0, (int) strpos($html, $label));

        preg_match_all('/tabular-nums"[^>]*>\s*([\d,]+)\s*<\/p>/', $before, $matches);

        return (string) end($matches[1]);
    }

    private function threeDistributions(User $resident): void
    {
        $this->pendingDistribution($resident);
        $this->pendingDistribution($resident);
        $this->receivedDistribution($resident);
    }

    private function pendingDistribution(User $resident, ?ReliefBatch $batch = null): ReliefDistribution
    {
        return ReliefDistribution::factory()->create([
            'relief_batch_id' => ($batch ?? ReliefBatch::factory()->create([
                'status' => ReliefBatch::STATUS_ACTIVE,
            ]))->getKey(),
            'household_number' => $resident->residentProfile->household_number,
            'sector_id' => $resident->residentProfile->sector_id,
            'status' => ReliefDistribution::STATUS_PENDING,
        ]);
    }

    private function receivedDistribution(User $resident, ?ReliefBatch $batch = null): ReliefDistribution
    {
        $kagawad = User::factory()->kagawad()->create(['name' => 'Maria Santos']);

        return ReliefDistribution::factory()->received($kagawad)->create([
            'relief_batch_id' => ($batch ?? ReliefBatch::factory()->create([
                'status' => ReliefBatch::STATUS_CLOSED,
            ]))->getKey(),
            'household_number' => $resident->residentProfile->household_number,
            'sector_id' => $resident->residentProfile->sector_id,
        ]);
    }

    private function verifiedResident(): User
    {
        $user = User::factory()->resident()->create(['is_verified' => true]);

        ResidentProfile::factory()->create([
            'user_id' => $user->getKey(),
            'sector_id' => Sector::factory()->create()->getKey(),
            'household_number' => 'HH-1001',
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
            'household_number' => 'HH-1002',
        ]);

        return $user;
    }
}
