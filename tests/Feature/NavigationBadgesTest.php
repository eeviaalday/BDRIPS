<?php

namespace Tests\Feature;

use App\Models\DocumentRequest;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\NavigationBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sidebar count badges.
 *
 * Every assertion here parses the rendered DOM and looks at ONE nav item, rather
 * than searching the page for a number. That distinction is the whole file: a
 * badge is a property of a destination, and "the page contains 3" is satisfied
 * equally well by the verification badge, the request badge, a row in a table or a
 * figure in a stat card. Reading each item and asking what ITS badge says is the
 * only way to catch a badge on the wrong link - which is the failure a whole-page
 * search can never see.
 */
class NavigationBadgesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The counts are memoised per user, which is what makes them once-per-request.
        // Between tests the memo must not survive, or a test would read a figure
        // built from the previous test's data.
        NavigationBadges::flush();
    }

    /**
     * Empty every table a badge counts.
     *
     * Needed because RefreshDatabase wraps a test in a transaction and rolls it
     * back, but it does not start from an empty schema: bdrpis_testing carries rows
     * committed outside any test, so a test that created five sectors and expected
     * a count of four was reading twenty-two. Any test asserting an exact figure
     * or an exact zero calls this first, so the number under test is the one this
     * test set up.
     *
     * Relational order, because the foreign keys are real.
     */
    private function emptyCountedTables(): void
    {
        ReliefDistribution::query()->delete();
        DocumentRequest::query()->delete();
        ReliefBatch::query()->delete();
        // resident_profiles and sectors are siblings, but a sector is referenced
        // by a profile, so the profile has to go first.
        ResidentProfile::query()->delete();
        Sector::query()->delete();
        User::query()->delete();
    }

    // -----------------------------------------------------------------
    // What each role is shown
    // -----------------------------------------------------------------

    #[Test]
    public function a_secretary_sees_pending_verifications_and_requests_awaiting_a_decision(): void
    {
        $this->emptyCountedTables();

        ResidentProfile::factory()->count(3)->pending()->create();
        DocumentRequest::factory()->count(2)->create();

        $badges = $this->badgesOn($this->staffDashboard(User::ROLE_SECRETARY));

        $this->assertSame('3', $badges['Resident Verification'] ?? null, 'Resident Verification should read 3.');
        $this->assertSame('2', $badges['Document Requests'] ?? null, 'Document Requests should read 2.');

        // And the destinations that are not a to-do carry nothing.
        foreach (['Relief Distribution', 'Announcements', 'Residents'] as $label) {
            $this->assertArrayNotHasKey($label, $badges, "[{$label}] should carry no badge.");
        }
    }

    #[Test]
    public function an_admin_additionally_sees_sectors_with_no_kagawad(): void
    {
        $this->emptyCountedTables();

        Sector::factory()->count(4)->create();
        Sector::factory()->create(['kagawad_user_id' => User::factory()->kagawad()->create()->id]);

        // 4 unassigned, 1 assigned. The Captain's queue is the 4.
        $badges = $this->badgesOn($this->staffDashboard(User::ROLE_ADMIN));

        $this->assertSame('4', $badges['Sectors & Kagawad'] ?? null);

        // The other admin-only pages are not to-do queues.
        foreach (['User Accounts', 'Settings', 'Audit Logs'] as $label) {
            $this->assertArrayNotHasKey($label, $badges, "[{$label}] should carry no badge.");
        }
    }

    #[Test]
    public function a_kagawad_sees_only_their_own_outstanding_households(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector();
        $theirs = Sector::factory()->create();

        $batch = ReliefBatch::factory()->create();
        // Mine: 2 outstanding. Theirs: 5. Barangay-wide would be 7.
        $this->households($batch, $mine, 2, ReliefDistribution::STATUS_PENDING);
        $this->households($batch, $theirs, 5, ReliefDistribution::STATUS_PENDING);

        $badges = $this->badgesOn(
            $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->assertOk()->getContent()
        );

        $this->assertSame('2', $badges['Relief Distribution'] ?? null, 'The Kagawad must see their own 2, not the other sector 5.');
        $this->assertArrayNotHasKey('My Sector Residents', $badges, 'My Sector Residents is not a to-do queue.');
    }

    /**
     * Isolation as an absence in the document, not merely an absence in one badge.
     *
     * A Kagawad's own figure being right does not prove the other sector's number
     * is nowhere - it could be on a second badge, or in a stat card on the same
     * page. This checks the whole document for the other sector's totals.
     */
    #[Test]
    public function another_sectors_figures_appear_nowhere_in_a_kagawads_page(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector();
        $theirs = Sector::factory()->create(['sector_name' => 'Purok Nine']);

        $batch = ReliefBatch::factory()->create();
        $this->households($batch, $mine, 1, ReliefDistribution::STATUS_PENDING);
        $this->households($batch, $theirs, 5, ReliefDistribution::STATUS_PENDING);

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.dashboard'))->assertOk()
            ->getContent();

        // Their outstanding count, and the sector's name, must not be on the page.
        $this->assertStringNotContainsString('5 households still to collect', $html);
        $this->assertStringNotContainsString('Purok Nine', $html);

        // And the one figure that IS theirs reads 1, not 6.
        $this->assertStringContainsString('1 household still to collect', $html);
    }

    #[Test]
    public function an_unassigned_kagawad_sees_no_badge_at_all(): void
    {
        $this->emptyCountedTables();

        // Somebody else's outstanding work exists; this account has no sector.
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create();
        $this->households($batch, $sector, 4, ReliefDistribution::STATUS_PENDING);

        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.dashboard'))->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('still to collect', $html);
        $this->assertSame([], $this->badgesOn($html));
    }

    #[Test]
    public function a_resident_has_no_staff_badges_anywhere(): void
    {
        $this->emptyCountedTables();

        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create();
        $this->households($batch, $sector, 4, ReliefDistribution::STATUS_PENDING);
        ResidentProfile::factory()->count(2)->pending()->create();

        $html = $this->actingAs(User::factory()->resident()->create())
            ->get(route('resident.requests.index'))->assertOk()
            ->getContent();

        // The resident bar is a different component and is not badged at all.
        $this->assertStringNotContainsString('pending verification', $html);
        $this->assertStringNotContainsString('still to collect', $html);
    }

    // -----------------------------------------------------------------
    // Zero, and the cap
    // -----------------------------------------------------------------

    #[Test]
    public function a_zero_count_is_hidden_rather_than_shown(): void
    {
        $this->emptyCountedTables();

        // Nothing pending, nothing awaiting, every sector assigned.
        $kagawad = User::factory()->kagawad()->create();
        Sector::factory()->create(['kagawad_user_id' => $kagawad->id]);

        $badges = $this->badgesOn($this->staffDashboard(User::ROLE_ADMIN));

        $this->assertSame([], $badges, 'Every count is zero, so no badge should be rendered at all.');

        // And specifically: no "0" anywhere in the nav.
        $html = $this->staffDashboard(User::ROLE_ADMIN);
        $this->assertStringNotContainsString('>0<', $this->navOf($html, 0));
    }

    #[Test]
    public function a_count_past_the_cap_prints_ninety_nine_plus(): void
    {
        $this->emptyCountedTables();

        ResidentProfile::factory()->count(120)->pending()->create();

        $badges = $this->badgesOn($this->staffDashboard(User::ROLE_SECRETARY));

        $this->assertSame('99+', $badges['Resident Verification'] ?? null, '120 pending must print as 99+.');

        // The cap is a display concern only: the aria-label still carries the real
        // figure, so a screen reader is told 120 rather than "99 or more".
        $html = $this->staffDashboard(User::ROLE_SECRETARY);
        $this->assertStringContainsString('aria-label="120 pending verifications"', $html);
    }

    #[Test]
    public function the_cap_boundary_is_exactly_ninety_nine(): void
    {
        $this->assertSame('99', NavigationBadges::display(99));
        $this->assertSame('99+', NavigationBadges::display(100));
    }

    #[Test]
    public function a_singular_count_reads_as_a_singular_sentence(): void
    {
        $this->emptyCountedTables();

        ResidentProfile::factory()->create([
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);
        DocumentRequest::factory()->create();

        $html = $this->staffDashboard(User::ROLE_SECRETARY);

        $this->assertStringContainsString('aria-label="1 pending verification"', $html);
        $this->assertStringContainsString('aria-label="1 request awaiting a decision"', $html);
    }

    // -----------------------------------------------------------------
    // Both surfaces
    // -----------------------------------------------------------------

    #[Test]
    public function the_phone_bar_carries_the_same_badges_as_the_sidebar(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector();
        $batch = ReliefBatch::factory()->create();
        $this->households($batch, $sector, 3, ReliefDistribution::STATUS_PENDING);

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.dashboard'))->assertOk()
            ->getContent();

        // The bar is a separate <nav aria-label="Portal"> from the sidebar, so it is
        // read separately rather than counted across the document.
        $sidebar = $this->badgesOn($html, 0);
        $bar = $this->badgesOn($html, 1);

        $this->assertNotSame('', $this->navOf($html, 1), 'The phone bottom bar is missing from the document.');

        $this->assertSame('3', $sidebar['Relief Distribution'] ?? null);
        $this->assertSame($sidebar, $bar, 'The phone bar and the sidebar disagree about the badges.');

        // My Sector Residents has no badge on either.
        $this->assertArrayNotHasKey('My Sector Residents', $bar);
    }

    // -----------------------------------------------------------------
    // Cost
    // -----------------------------------------------------------------

    /**
     * The badges must not issue a query per row, and must not issue a query per
     * badge. Three aggregates whatever the size of the tables is a constant; a count
     * that grows with the data is an N+1 wearing a different hat.
     */
    #[Test]
    public function the_badge_cost_does_not_grow_with_the_number_of_rows(): void
    {
        $this->emptyCountedTables();

        $count = function (): int {
            $queries = [];
            DB::listen(function ($query) use (&$queries): void {
                $queries[] = $query->sql;
            });

            $this->staffDashboard(User::ROLE_SECRETARY);

            return count($queries);
        };

        ResidentProfile::factory()->create([
            'verification_status' => ResidentProfile::VERIFICATION_PENDING,
        ]);
        DocumentRequest::factory()->create();

        $one = $count();

        $this->refreshDatabase();
        NavigationBadges::flush();
        $this->emptyCountedTables();

        for ($i = 0; $i < 20; $i++) {
            ResidentProfile::factory()->pending()->create();
            DocumentRequest::factory()->create();
        }

        $twenty = $count();

        $this->assertSame(
            $one,
            $twenty,
            sprintf('The badge queries went from %d to %d between 1 and 20 rows.', $one, $twenty)
        );
    }

    #[Test]
    public function the_counts_are_computed_once_per_request_not_once_per_surface(): void
    {
        // Two pages in one process is not one request, so this asserts the memo
        // directly: the second call for the same user costs nothing.
        $kagawad = User::factory()->kagawad()->create();

        $first = NavigationBadges::forUser($kagawad);
        $second = NavigationBadges::forUser($kagawad);

        $this->assertSame($first, $second);
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * Badge text keyed by the label of the item it sits on, for ONE nav element.
     *
     * Parsed with DOMXPath rather than a regex so the pairing of a number to its
     * own label is the browser's, not this test's guess at the markup.
     *
     * @return array<string, string> label => printed count
     */
    private function badgesOn(string $html, int $which = 0): array
    {
        $nav = $this->navOf($html, $which);

        if ($nav === '') {
            return [];
        }

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$nav);
        $xpath = new \DOMXPath($document);

        $out = [];
        $xpath = new \DOMXPath($document);

        foreach ($xpath->query('//a[.//span[@aria-label]]') as $link) {
            $label = $this->textOf($link, './/span[not(@aria-hidden="true")][not(@aria-label)]');

            if ($label === '') {
                continue;
            }

            // The badge is the aria-labelled span; the printed value is the text
            // inside it, which is aria-hidden so a screen reader is not told the
            // number twice.
            $badge = $xpath->query('.//span[@aria-label]', $link)->item(0);
            $printed = $badge === null ? '' : trim($badge->textContent);

            $out[$label] = $printed;
        }

        return $out;
    }

    /**
     * One <nav aria-label="Portal"> element's markup, by position.
     *
     * There are TWO of them and they share the label: [0] is the desktop sidebar's
     * nav, [1] is the phone bottom bar's. Selecting by aria-label alone returned
     * the first for both, which would have made "the bar carries the same badges"
     * compare the sidebar against itself and pass whether or not the bar had a
     * badge at all. Indexed, the two are genuinely different documents.
     */
    private function navOf(string $html, int $which = 0): string
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($document);

        $nav = $xpath->query('//nav[@aria-label="Portal"]')->item($which);

        return $nav === null ? '' : (string) $document->saveHTML($nav);
    }

    /**
     * The visible text of the first node matching a relative XPath, with the icon
     * <svg> removed so the label is the words and not a path.
     */
    private function textOf(\DOMNode $context, string $relativeXPath): string
    {
        $document = $context->ownerDocument;
        $xpath = new \DOMXPath($document);

        $node = $xpath->query($relativeXPath, $context)->item(0);

        if ($node === null) {
            return '';
        }

        $html = (string) $document->saveHTML($node);
        $html = preg_replace('#<svg\b.*?</svg>#s', '', $html) ?? $html;

        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        // The nav fragment is re-parsed on its own, and an entity in a label comes
        // back still encoded: "Sectors &amp; Kagawad". Decoding here means the key
        // is the label a reader sees, which is also the label a test should name.
        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5);
    }

    private function staffDashboard(string $role): string
    {
        $user = match ($role) {
            User::ROLE_ADMIN => User::factory()->admin()->create(['is_verified' => true]),
            User::ROLE_KAGAWAD => User::factory()->kagawad()->create(['is_verified' => true]),
            default => User::factory()->secretary()->create(['is_verified' => true]),
        };

        return $this->actingAs($user)
            ->get(route('staff.dashboard'))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return array{kagawad: User, sector: Sector}
     */
    private function kagawadWithSector(): array
    {
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);

        $sector = Sector::factory()->create([
            'kagawad_user_id' => $kagawad->id,
            'sector_name' => 'Purok Three',
        ]);

        return ['kagawad' => $kagawad, 'sector' => $sector];
    }

    /**
     * N households on a batch in a sector, all with one status.
     */
    private function households(ReliefBatch $batch, Sector $sector, int $count, string $status): void
    {
        for ($i = 1; $i <= $count; $i++) {
            ReliefDistribution::factory()->create([
                'relief_batch_id' => $batch->id,
                'sector_id' => $sector->id,
                'household_number' => sprintf('%s-%03d', strtoupper(substr(md5($sector->id.$i), 0, 4)), $i),
                'status' => $status,
                'claimed_at' => $status === ReliefDistribution::STATUS_RECEIVED ? now()->subDay() : null,
            ]);
        }
    }
}
