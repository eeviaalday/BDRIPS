<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use App\Support\KagawadSectorResidents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A Kagawad's own-sector resident list.
 *
 * The page is read-only and scoped, and both of those are properties that a
 * screenshot cannot prove. So the tests below are organised around the two ways
 * this could be wrong:
 *
 *  1. Someone else's resident leaks in. Asserted against the raw HTML rather than
 *     against the links, because a row hidden with CSS or an unrendered @foreach
 *     branch is still in the page source, and the household number is the thing
 *     being protected.
 *  2. The page grows a query per row. Asserted by counting queries at one resident
 *     and at twenty, because an N+1 on a field-based table is invisible at one row.
 */
class KagawadSectorResidentsTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------
    // (a) scoping: only this Kagawad's own sectors
    // ------------------------------------------------------------------

    #[Test]
    public function an_assigned_kagawad_sees_only_the_residents_of_their_own_sector(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $mine = $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $theirs = $this->residentIn(Sector::factory()->create(['sector_name' => 'Purok 9']), 'HH-9001', 'Rene Villanueva');

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringContainsString('HH-1001', $html);

        $this->assertStringNotContainsString('Rene Villanueva', $html);
        $this->assertStringNotContainsString('HH-9001', $html);
        $this->assertStringNotContainsString('Purok 9', $html);
    }

    /**
     * (b) The same guarantee, stated against the page source rather than the
     * links, because "not linked" and "not present" are different claims and only
     * the second one is worth anything here.
     */
    #[Test]
    public function a_resident_from_another_sector_is_absent_from_the_html_not_merely_unlinked(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $other = Sector::factory()->create(['sector_name' => 'Purok 9']);
        $this->residentIn($other, 'HH-9001', 'Rene Villanueva', 'Villanueva', 'Rene');
        $this->residentIn($other, 'HH-9002', 'Cristina Del Mundo', 'Del Mundo', 'Cristina');

        $response = $this->actingAs($kagawad)->get(route('kagawad.residents.index'));
        $response->assertOk();

        $html = $response->getContent();

        // Every one of these strings, checked against the whole document.
        foreach (['Rene', 'Villanueva', 'Cristina', 'Del Mundo', 'HH-9001', 'HH-9002', 'Purok 9'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "[{$needle}] leaked into the page source.");
        }
    }

    /**
     * A Kagawad holding two sectors is currently impossible, and the reason is a
     * schema constraint rather than anything on this page.
     *
     * sectors.kagawad_user_id is UNIQUE
     * (2026_09_22_025934_create_sectors_table.php:15), so one Kagawad can hold at
     * most one sector. ROLES.md owner decision 3 says "their assigned sector(s)",
     * and the plural is an open question recorded there.
     *
     * This stays skipped rather than deleted, so that if the index is ever dropped
     * the test starts running and says so. The behaviour it would cover is tested
     * directly against KagawadSectorResidents below, which takes the sector list
     * as an argument and so is not subject to the constraint.
     */
    #[Test]
    public function a_kagawad_with_two_sectors_sees_both_and_still_nothing_else(): void
    {
        $this->markTestSkipped(
            'The owner has decided a Kagawad holds exactly one sector, and '
            .'sectors.kagawad_user_id is UNIQUE, so this state cannot be set up. '
            .'The multi-sector logic is covered directly against the support class in '
            .'KagawadSectorResidentsTest::the_sector_filter_offers_exactly_the_sectors_it_is_given. '
            .'See ROLES.md, "Code vs ROLES.md".'
        );
    }

    #[Test]
    public function a_single_sector_kagawad_is_offered_no_sector_filter(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        // A one-option filter is a control that cannot do anything.
        $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index'))
            ->assertOk()
            ->assertDontSee('name="sector_id"', escape: false);
    }

    /**
     * The multi-sector behaviour, tested against the support class rather than
     * through HTTP.
     *
     * The end-to-end version is skipped above because sectors.kagawad_user_id is
     * UNIQUE. This reaches the same code with the sector list supplied directly,
     * so the logic is still covered: given two sectors, both are offered as filter
     * options and residents from both are returned, while a third sector nobody
     * assigned is in neither.
     */
    #[Test]
    public function the_sector_filter_offers_exactly_the_sectors_it_is_given(): void
    {
        $first = Sector::factory()->create(['sector_name' => 'Purok 3']);
        $second = Sector::factory()->create(['sector_name' => 'Purok 4']);
        $stranger = Sector::factory()->create(['sector_name' => 'Purok 9']);

        $this->assertSame([], KagawadSectorResidents::filterOptions([$first->id]), 'One sector offers no filter.');
        $this->assertSame([], KagawadSectorResidents::filterOptions([]), 'No sector offers no filter.');

        $options = KagawadSectorResidents::filterOptions([$first->id, $second->id]);

        $this->assertSame(
            [$first->id => 'Purok 3', $second->id => 'Purok 4'],
            $options,
        );
        $this->assertArrayNotHasKey($stranger->id, $options, 'A sector nobody was given must not be offered.');

        // And the scoping itself holds for a two-sector list.
        $this->residentIn($first, 'HH-1001', 'Ana Bautista');
        $this->residentIn($second, 'HH-1002', 'Ben Dagohoy');
        $this->residentIn($stranger, 'HH-9001', 'Rene Villanueva');

        $profiles = KagawadSectorResidents::paginate(
            [$first->id, $second->id],
            null,
            ['q' => '', 'sector_id' => null, 'status' => ''],
        );

        $numbers = $profiles->pluck('household_number')->all();

        sort($numbers);
        $this->assertSame(['HH-1001', 'HH-1002'], $numbers);
    }

    #[Test]
    public function the_batch_selector_defaults_to_the_latest_open_batch_not_the_latest_closed_one(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $open = $this->batch('September rice', now()->subDays(20));
        $this->distribution($open, $sector, 'HH-1001', ReliefDistribution::STATUS_PENDING);

        // A closed batch with a LATER distribution_date than the open one. The
        // seeder's own data has this shape, and a page that opens on it shows a
        // column of collected dates for goods that went out months ago.
        $closed = ReliefBatch::factory()->closed()->create([
            'title' => 'August rice (closed)',
            'distribution_date' => now()->subDay()->toDateString(),
        ]);
        $this->distribution($closed, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        // The open batch is offered, the closed one still selectable.
        $this->assertStringContainsString('September rice', $html);
        $this->assertStringContainsString('August rice (closed)', $html);

        // And the default is the open one: the resident is outstanding on it, so
        // "Still to receive" is 1 and no collected date is shown.
        $this->assertSame(1, $this->statValue($html, 'Still to receive'));
        $this->assertStringNotContainsString(now()->subDays(2)->format('j M Y'), $html);
    }

    /**
     * Row by row, not page by page.
     *
     * Written because a page-level assertion cannot see this class of bug. The
     * status subselect was once uncorrelated, so it returned the first checklist
     * row's status for every resident and a mixed list rendered as if the whole
     * sector had collected. Every page-level check still passed: the page did
     * contain the word "Received", the count on the stat card was right, and the
     * status FILTER was right because it used a correctly correlated whereExists.
     * Only the badge on an individual row was wrong.
     *
     * So this asserts inside each <tr>, keyed on the household number, which is
     * the only way to tell "the page mentions Received" from "this resident
     * received".
     */
    #[Test]
    public function each_row_carries_its_own_relief_status_and_not_the_first_rows(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $collected = $this->residentIn($sector, 'HH-1001', 'Alma Reyes');
        $waiting = $this->residentIn($sector, 'HH-1002', 'Ben Sagaba');
        $elsewhere = $this->residentIn($sector, 'HH-1003', 'Cara Lim');

        $batch = $this->batch('November rice', now());
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);
        $this->distribution($batch, $sector, 'HH-1002', ReliefDistribution::STATUS_PENDING);
        // HH-1003 is verified but absent from the checklist entirely.

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        $collectedRow = $this->tableRow($html, 'HH-1001');
        $this->assertStringContainsString('Received', $collectedRow, 'The collected household should read as received.');
        $this->assertStringNotContainsString('Not yet received', $collectedRow);
        $this->assertStringContainsString(now()->subDays(2)->format('j M Y'), $collectedRow, 'The collected date belongs to this row.');

        $waitingRow = $this->tableRow($html, 'HH-1002');
        $this->assertStringContainsString('Not yet received', $waitingRow, 'The waiting household should read as outstanding.');
        $this->assertStringNotContainsString('Received', $waitingRow, 'The waiting household must not inherit the collected status.');
        $this->assertStringNotContainsString(now()->subDays(2)->format('j M Y'), $waitingRow, 'A household that has not collected has no date.');

        $elsewhereRow = $this->tableRow($html, 'HH-1003');
        $this->assertStringContainsString('Not on this batch', $elsewhereRow, 'A verified resident off the checklist is neither received nor outstanding.');
        $this->assertStringNotContainsString('Received', $elsewhereRow);
        $this->assertStringNotContainsString('Not yet received', $elsewhereRow);
    }

    /**
     * The same three residents through the status filter, which is the other half
     * of the same claim: the filter and the badge must agree.
     */
    #[Test]
    public function the_received_filter_keeps_exactly_the_rows_the_table_calls_received(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $this->residentIn($sector, 'HH-1001', 'Alma Reyes');
        $this->residentIn($sector, 'HH-1002', 'Ben Sagaba');
        $this->residentIn($sector, 'HH-1003', 'Cara Lim');

        $batch = $this->batch('November rice', now());
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);
        $this->distribution($batch, $sector, 'HH-1002', ReliefDistribution::STATUS_PENDING);

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['status' => 'received']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('HH-1001', $html);
        $this->assertStringNotContainsString('HH-1002', $html);
        $this->assertStringNotContainsString('HH-1003', $html, 'Off the checklist is not received.');

        $this->assertStringContainsString('Received', $this->tableRow($html, 'HH-1001'));
    }

    #[Test]
    public function a_sector_filter_naming_a_sector_the_kagawad_does_not_hold_cannot_widen_the_list(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $stranger = Sector::factory()->create(['sector_name' => 'Purok 9']);
        $this->residentIn($stranger, 'HH-9001', 'Rene Villanueva');

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['sector_id' => $stranger->id]))
            ->assertOk()
            ->getContent();

        // An id outside their sectors is dropped rather than obeyed, so a
        // hand-edited query string shows their own list and leaks nothing.
        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringNotContainsString('Rene Villanueva', $html);
    }

    // ------------------------------------------------------------------
    // (c) the unassigned Kagawad
    // ------------------------------------------------------------------

    #[Test]
    public function an_unassigned_kagawad_sees_the_empty_state_and_not_an_unscoped_list(): void
    {
        $kagawad = $this->kagawad();

        // Real residents exist, in a sector that belongs to nobody this account
        // can see. The point is that none of it is reachable.
        $stranger = Sector::factory()->create(['sector_name' => 'Purok 9']);
        $this->residentIn($stranger, 'HH-9001', 'Rene Villanueva');
        $this->residentIn($stranger, 'HH-9002', 'Cristina Del Mundo');

        $response = $this->actingAs($kagawad)->get(route('kagawad.residents.index'));

        $response->assertOk();
        $response->assertSee('No sector assigned yet');
        $response->assertSee('You have not been assigned a sector yet.');
        $response->assertSee('The Barangay Captain (Admin) assigns sectors from Sectors & Kagawad.');

        $html = $response->getContent();

        $this->assertStringNotContainsString('Rene Villanueva', $html);
        $this->assertStringNotContainsString('Cristina Del Mundo', $html);
        $this->assertStringNotContainsString('HH-9001', $html);
        $this->assertStringNotContainsString('HH-9002', $html);
        $this->assertStringNotContainsString('Purok 9', $html);

        // No table, no filter bar, no figures: the branch that would render them
        // never ran.
        $this->assertStringNotContainsString('name="q"', $html);
        $this->assertStringNotContainsString('Residents in your sector', $html);
    }

    #[Test]
    public function an_unassigned_kagawad_runs_no_resident_query_at_all(): void
    {
        $kagawad = $this->kagawad();
        $sector = Sector::factory()->create();
        $this->residentIn($sector, 'HH-9001', 'Rene Villanueva');

        $queries = $this->countQueriesFor($kagawad);

        // No read of the resident table at all. The controller returns before any
        // query runs, so a read here would mean the scope was resolved and then
        // not applied.
        foreach ($queries as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('resident_profiles', $sql);
        }

        /*
         * One relief_distributions count does run, and it comes from the navigation
         * badge in layouts/app rather than from this page - the layout asks "how many
         * still to collect" for the bottom bar on every staff page.
         *
         * It is asserted rather than waved through, because it is the one query on
         * this screen that touches another table, and it has to be scoped too. The
         * empty sector list compiles to "where 0 = 1", which matches nothing. If
         * that ever stops being true, this is the assertion that catches it.
         */
        foreach ($queries as $sql) {
            if (stripos($sql, 'relief_distributions') !== false) {
                $this->assertStringContainsString(
                    '0 = 1',
                    $sql,
                    'A relief query ran for an unassigned Kagawad without being scoped to nothing: '.$sql,
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // (d) who may open it
    // ------------------------------------------------------------------

    #[Test]
    public function a_resident_is_refused_the_kagawad_resident_list(): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->get(route('kagawad.residents.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_secretary_is_refused_the_kagawad_resident_list(): void
    {
        $this->actingAs(User::factory()->secretary()->create())
            ->get(route('kagawad.residents.index'))
            ->assertForbidden();
    }

    #[Test]
    public function the_captain_is_refused_the_kagawad_resident_list(): void
    {
        // Decision 1 gives an Admin everything a Secretary has, not a Kagawad's
        // area. The role areas do not overlap in either direction, and this is
        // the assertion that keeps that true as areas are added.
        $this->actingAs(User::factory()->admin()->create())
            ->get(route('kagawad.residents.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('kagawad.residents.index'))->assertRedirect(route('login'));
    }

    #[Test]
    public function the_route_offers_no_write_verb_at_all(): void
    {
        // "Read-only" is asserted at the route table rather than inside a method,
        // so it cannot be lost by a later edit that forgets to check.
        $verbs = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => $route->getName() === 'kagawad.residents.index');

        $this->assertCount(1, $verbs, 'Expected exactly one kagawad.residents.index route.');
        $this->assertSame(['GET', 'HEAD'], $verbs->first()->methods());
    }

    // ------------------------------------------------------------------
    // (e) filters
    // ------------------------------------------------------------------

    #[Test]
    public function the_batch_selector_defaults_to_the_latest_batch_the_kagawad_has_rows_on(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $older = $this->batch('October rice', now()->subDays(9));
        $newer = $this->batch('November rice', now()->subDays(2));

        // Only the newer batch has one of this Kagawad's households on it.
        $this->distribution($newer, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);

        // A batch belonging entirely to another sector must not become the default.
        $strangerBatch = $this->batch('Foreign batch', now());
        $this->distribution($strangerBatch, Sector::factory()->create(), 'HH-9999', ReliefDistribution::STATUS_RECEIVED);

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('November rice', $html, 'The latest own-sector batch should be the default.');
        $this->assertStringNotContainsString('Foreign batch', $html);
        $this->assertStringNotContainsString('October rice', $html, 'A batch with no rows of theirs is not offered.');

        $this->assertStringContainsString('Received', $html);
        $this->assertStringNotContainsString('Foreign batch', $html);
    }

    #[Test]
    public function choosing_a_different_batch_changes_whose_status_is_shown(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $october = $this->batch('October rice', now()->subDays(9));
        $november = $this->batch('November rice', now()->subDays(2));

        $this->distribution($october, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);
        $this->distribution($november, $sector, 'HH-1001', ReliefDistribution::STATUS_PENDING);

        // Default is November, where she is outstanding.
        $default = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();
        $this->assertSame(1, $this->statValue($default, 'Still to receive'));

        // Explicitly October, where she collected - and the collected date proves
        // the status came from that batch and not the default one.
        $chosen = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['batch_id' => $october->id]))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, $this->statValue($chosen, 'Still to receive'));
        $this->assertStringContainsString(now()->subDays(2)->format('j M Y'), $chosen);
        $this->assertStringContainsString('October rice', $chosen);
    }

    #[Test]
    public function a_batch_id_outside_the_kagawads_own_batches_falls_back_instead_of_widening(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $mine = $this->batch('November rice', now());
        $this->distribution($mine, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);

        $foreign = $this->batch('Foreign batch', now()->addDay());
        $this->distribution($foreign, Sector::factory()->create(), 'HH-9999', ReliefDistribution::STATUS_RECEIVED);

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['batch_id' => $foreign->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Foreign batch', $html);
        $this->assertStringContainsString('November rice', $html);
    }

    #[Test]
    public function the_status_filter_narrows_the_list_to_received_residents(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $this->residentIn($sector, 'HH-1002', 'Ben Dagohoy');

        $batch = $this->batch('November rice', now());
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);
        $this->distribution($batch, $sector, 'HH-1002', ReliefDistribution::STATUS_PENDING);

        $received = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['status' => 'received']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ana Bautista', $received);
        $this->assertStringNotContainsString('Ben Dagohoy', $received);

        // "All" shows both.
        $all = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Ana Bautista', $all);
        $this->assertStringContainsString('Ben Dagohoy', $all);
    }

    #[Test]
    public function a_status_value_nobody_expected_does_not_widen_the_filter(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $this->residentIn($sector, 'HH-1002', 'Ben Dagohoy');

        $html = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['status' => "anything' OR 1=1 --"]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringContainsString('Ben Dagohoy', $html);
    }

    #[Test]
    public function search_matches_a_name_or_a_household_number(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $this->residentIn($sector, 'HH-1002', 'Ben Dagohoy');

        $byName = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['q' => 'Bautista']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Ana Bautista', $byName);
        $this->assertStringNotContainsString('Ben Dagohoy', $byName);

        $byNumber = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['q' => 'HH-1002']))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Ben Dagohoy', $byNumber);
        $this->assertStringNotContainsString('Ana Bautista', $byNumber);
    }

    #[Test]
    public function a_filter_matching_nothing_offers_the_way_back(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['q' => 'Nobody At All']))
            ->assertOk()
            ->assertSee('No residents match these filters')
            ->assertSee('Clear filters');
    }

    // ------------------------------------------------------------------
    // (f) privacy
    // ------------------------------------------------------------------

    #[Test]
    public function no_sensitive_field_name_or_value_appears_on_the_page(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $profile = $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $profile->forceFill([
            'national_or_voter_id' => '1234567890123',
            'address' => '117 Katipunan Street, Purok 3',
            'id_photo_path' => 'id-photos/ana.png',
            'rejection_reason' => 'Illegible photocopied ID',
        ])->save();

        $batch = $this->batch('November rice', now());
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED, 'Somebody Else');

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        // Values that must never reach a Kagawad.
        foreach (['1234567890123', 'Katipunan', 'id-photos', 'Illegible'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "[{$needle}] leaked onto the page.");
        }

        // Column names too: if a future edit selects address or the national id,
        // the attribute name shows up in the subselect alias even when the value
        // happens to be empty.
        foreach (['national_or_voter_id', 'id_photo_path', 'rejection_reason', 'email', 'birth'] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "[{$needle}] appears in the page source.");
        }

        // And the collector's name is not the Kagawad's business either.
        $this->assertStringNotContainsString('Somebody Else', $html);
    }

    #[Test]
    public function the_query_selects_only_the_five_permitted_fields(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $sql = $this->firstResidentQuery($kagawad);

        $this->assertNotNull($sql, 'No resident_profiles query was run.');

        foreach (['national_or_voter_id', 'id_photo_path', 'rejection_reason', 'address'] as $column) {
            $this->assertStringNotContainsString($column, $sql, "The query selects [{$column}].");
        }
    }

    #[Test]
    public function no_row_offers_a_link_or_an_edit_control(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        // No staff resident detail page, which is role:admin,secretary and would
        // 403 anyway - but the point is that the row offers nowhere to go.
        $this->assertStringNotContainsString(route('staff.residents.show', [$sector->id]), $html);
        $this->assertStringNotContainsString('/staff/residents/', $html);

        // No form, and nothing that could post.
        $this->assertStringNotContainsString('<form method="POST"', substr($html, strpos($html, 'Residents in your sector')));
    }

    #[Test]
    public function an_unverified_resident_shows_not_verified_and_no_relief_status(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $pending = $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $pending->forceFill(['verification_status' => ResidentProfile::VERIFICATION_PENDING])->save();

        $this->residentIn($sector, 'HH-1002', 'Ben Dagohoy');

        // Even though the pending resident IS on the checklist as received, they
        // get no relief status: an unverified profile is not on a distribution.
        $batch = $this->batch('November rice', now());
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);
        $this->distribution($batch, $sector, 'HH-1002', ReliefDistribution::STATUS_PENDING);

        $html = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringContainsString('Not verified', $html);
        $this->assertStringContainsString('Ben Dagohoy', $html);
        $this->assertStringContainsString('Verified', $html);

        // Row by row. Ana is on the checklist as received and still gets no status,
        // and Ben is the only household the table calls outstanding.
        $anaRow = $this->tableRow($html, 'HH-1001');
        $this->assertStringContainsString('Not verified', $anaRow);
        $this->assertStringContainsString('No relief status', $anaRow);
        $this->assertStringNotContainsString('Received', $anaRow);
        $this->assertStringNotContainsString('Not yet received', $anaRow);

        $benRow = $this->tableRow($html, 'HH-1002');
        $this->assertStringContainsString('Not yet received', $benRow);
    }

    // ------------------------------------------------------------------
    // (g) no N+1
    // ------------------------------------------------------------------

    #[Test]
    public function the_query_count_does_not_grow_with_the_number_of_residents(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $batch = $this->batch('November rice', now());

        $this->residentIn($sector, 'HH-1001', 'Ana Bautista');
        $this->distribution($batch, $sector, 'HH-1001', ReliefDistribution::STATUS_RECEIVED);

        /*
         * Re-read the user before each measurement.
         *
         * Without this the second count comes out one LOWER than the first, which
         * looks like an improvement and is not: the same User instance is reused
         * across both requests, so the second one finds reliefKagawadSectors
         * already memoised on the model and skips a query. Every real request
         * starts from a freshly hydrated user, so that is the shape worth
         * comparing.
         */
        $one = $this->countQueriesFor(User::findOrFail($kagawad->getKey()));

        // Nineteen more, all on the checklist, so the status subselect has to do
        // real work on every one of them.
        for ($i = 2; $i <= 20; $i++) {
            $this->residentIn($sector, sprintf('HH-10%02d', $i), 'Resident Number '.$i);
            $this->distribution($batch, $sector, sprintf('HH-10%02d', $i), ReliefDistribution::STATUS_RECEIVED);
        }

        $twenty = $this->countQueriesFor(User::findOrFail($kagawad->getKey()));

        $this->assertSame(
            count($one),
            count($twenty),
            sprintf(
                'Query count changed from %d to %d between 1 and 20 residents - that is an N+1.%s',
                count($one),
                count($twenty),
                PHP_EOL.implode(PHP_EOL, array_diff($twenty, $one)),
            ),
        );
    }

    #[Test]
    public function the_resident_list_is_paginated(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        for ($i = 1; $i <= 25; $i++) {
            $this->residentIn($sector, sprintf('HH-10%02d', $i), 'Resident Number '.$i);
        }

        $first = $this->actingAs($kagawad)->get(route('kagawad.residents.index'))->assertOk();

        $first->assertSee('Showing');
        $first->assertSee('of');
        // 20 on the page, 25 in the sector: the range and the total must both be
        // on screen, or "20 rows" reads as "20 residents".
        $first->assertSee('20');
        $first->assertSee('25');

        // Resident 21 is on page two, not page one.
        $first->assertDontSee('Resident Number 21');
        $first->assertSee('Resident Number 20');

        $second = $this->actingAs($kagawad)
            ->get(route('kagawad.residents.index', ['page' => 2]))
            ->assertOk();
        $second->assertSee('Resident Number 21');
    }

    // ------------------------------------------------------------------
    // helpers
    // ------------------------------------------------------------------

    private function kagawad(): User
    {
        return User::factory()->kagawad()->create(['name' => 'Rosa Mendoza']);
    }

    /**
     * @return array{kagawad: User, sector: Sector}
     */
    private function kagawadWithSector(string $name): array
    {
        $kagawad = $this->kagawad();
        $sector = Sector::factory()->create([
            'kagawad_user_id' => $kagawad->id,
            'sector_name' => $name,
        ]);

        return ['kagawad' => $kagawad, 'sector' => $sector];
    }

    /**
     * A verified resident profile in a given sector.
     *
     * The name is split into surname and first name explicitly so a test can
     * assert on a single part of it, which is how the leak assertions stay
     * readable.
     */
    private function residentIn(
        Sector $sector,
        string $householdNumber,
        string $fullName,
        ?string $surname = null,
        ?string $firstName = null,
    ): ResidentProfile {
        [$firstName, $surname] = $firstName !== null
            ? [$firstName, $surname]
            : [explode(' ', $fullName, 2)[0] ?? $fullName, explode(' ', $fullName, 2)[1] ?? $fullName];

        $user = User::factory()->create([
            'name' => trim("{$firstName} {$surname}"),
            'email' => str($firstName.'.'.$surname)->lower()->slug('.')->value().'@example.test',
        ]);

        return ResidentProfile::factory()->create([
            'user_id' => $user->id,
            'sector_id' => $sector->id,
            'household_number' => $householdNumber,
            'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function batch(string $title, $date): ReliefBatch
    {
        return ReliefBatch::factory()->create([
            'title' => $title,
            'status' => ReliefBatch::STATUS_ACTIVE,
            'distribution_date' => $date instanceof \DateTimeInterface
                ? $date->format('Y-m-d')
                : (string) $date,
        ]);
    }

    private function distribution(
        ReliefBatch $batch,
        Sector $sector,
        string $householdNumber,
        string $status,
        ?string $receivedBy = null,
    ): ReliefDistribution {
        return ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id,
            'sector_id' => $sector->id,
            'household_number' => $householdNumber,
            'status' => $status,
            'received_by_name' => $receivedBy,
            'claimed_at' => $status === ReliefDistribution::STATUS_RECEIVED ? now()->subDays(2) : null,
        ]);
    }

    /**
     * Every SQL statement the page runs, with bindings inlined.
     *
     * @return list<string>
     */
    private function countQueriesFor(User $user): array
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $this->actingAs($user)->get(route('kagawad.residents.index'))->assertOk();

        return $queries;
    }

    private function firstResidentQuery(User $user): ?string
    {
        foreach ($this->countQueriesFor($user) as $sql) {
            if (str_contains($sql, 'resident_profiles')) {
                return $sql;
            }
        }

        return null;
    }

    /**
     * The table row containing a given string, as markup.
     *
     * Read from the desktop table only. The phone cards duplicate the same three
     * values a second time, so a page-level search cannot say which rendering it
     * found, and a per-row assertion against the whole document would match the
     * card before the row.
     */
    private function tableRow(string $html, string $needle): string
    {
        $document = new \DOMDocument;
        $this->assertTrue(
            @$document->loadHTML('<?xml encoding="utf-8" ?><body>'.$html.'</body>'),
            'Could not parse the page as HTML.',
        );

        $xpath = new \DOMXPath($document);
        $rows = $xpath->query('//table//tbody/tr');

        $this->assertNotFalse($rows);

        foreach ($rows as $row) {
            if (str_contains($row->textContent, $needle)) {
                return $document->saveHTML($row);
            }
        }

        $this->fail(sprintf('No table row contains [%s].', $needle));
    }

    /**
     * The figure on a stat card, read back out of the rendered page.
     *
     * Read from the markup rather than from the view data because the whole point
     * is that the number a Kagawad reads is the number the query produced. The
     * stat card renders the value in its own <p> immediately before the label, so
     * the two are matched as a pair.
     */
    private function statValue(string $html, string $label): int
    {
        $pattern = '#<p class="text-3xl font-extrabold tabular-nums tracking-tight text-navy-900">\s*([0-9,]+)\s*</p>\s*</div>\s*<p class="mt-3 text-sm font-semibold text-navy-700">'
            .preg_quote($label, '#').'</p>#';

        $this->assertSame(
            1,
            preg_match($pattern, $html, $matches),
            sprintf('No stat card labelled [%s] was found on the page.', $label),
        );

        return (int) str_replace(',', '', $matches[1]);
    }
}
