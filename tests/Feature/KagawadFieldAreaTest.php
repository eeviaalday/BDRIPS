<?php

namespace Tests\Feature;

use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Kagawad area as a field tool: the dashboard, the batch list and the
 * checklist, plus the one guarantee that outranks all of them.
 *
 * The guarantee is the sector split. A Kagawad's access is a data filter rather
 * than a yes/no, which means every new figure and every new widget is a new way
 * to leak a neighbouring purok's household list. So the majority of this file is
 * negative: build a batch with households in two sectors, look as one Kagawad,
 * and assert the other sector's numbers are not merely unlinked but absent from
 * the markup - because a number that is merely unlinked is still a number that
 * leaked, and it is in the page source that anyone can read.
 *
 * A second theme is the copy. The old unassigned state told a Kagawad to go to
 * "Sectors & Kagawad", which is behind role:admin, and mentioned a "Captain",
 * who is not a role in this system at all. Those are pinned here so they cannot
 * come back.
 */
class KagawadFieldAreaTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ scope

    #[Test]
    public function an_unassigned_kagawad_sees_the_empty_state_and_no_figures(): void
    {
        $kagawad = $this->kagawad();

        // A complete batch exists. The point is that none of it is reachable.
        $other = Sector::factory()->create(['sector_name' => 'Purok 9']);
        $this->newBatchWithHouseholds('October rice', [$other->id => ['H-1', 'H-2']]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        $response->assertSee('No sector assigned yet');
        $response->assertSee('You have not been assigned a sector yet.');

        // Nothing from the other sector, in any form.
        $response->assertDontSee('October rice');
        $response->assertDontSee('H-1');
        $response->assertDontSee('Purok 9');
    }

    #[Test]
    public function the_unassigned_state_offers_no_link_to_a_page_the_kagawad_cannot_open(): void
    {
        $response = $this->actingAs($this->kagawad())->get(route('kagawad.dashboard'));

        $response->assertOk();

        // /staff/sectors is role:admin. Following the old copy's advice is a 403.
        $response->assertDontSee(route('staff.sectors.index'), escape: false);

        // And the only escape offered is the relief list, which is the Kagawad's
        // own area and renders its own empty state.
        $response->assertDontSee(route('staff.batches.index'), escape: false);
    }

    #[Test]
    public function the_unassigned_relief_page_says_the_same_thing_as_the_dashboard(): void
    {
        $kagawad = $this->kagawad();

        foreach ([route('kagawad.dashboard'), route('kagawad.relief'), route('kagawad.residents.index')] as $url) {
            $response = $this->actingAs($kagawad)->get($url);

            $response->assertOk();
            $response->assertSee('No sector assigned yet');
            $response->assertSee('You have not been assigned a sector yet.');
            // Raw ampersand: assertSee escapes the needle for you, so passing an
            // already-escaped "&amp;" here would be looking for "&amp;amp;".
            $response->assertSee('The Barangay Captain (Admin) assigns sectors from Sectors & Kagawad.');
        }
    }

    #[Test]
    public function every_kagawad_page_names_the_captain_but_still_links_to_no_admin_only_page(): void
    {
        $kagawad = $this->kagawad();
        $batch = $this->newBatchWithHouseholds('October rice', [Sector::factory()->create()->id => ['H-1']]);

        // The three pages that render the shared unassigned-sector component.
        $unassignedPages = [
            route('kagawad.dashboard'),
            route('kagawad.relief'),
            route('kagawad.residents.index'),
        ];

        foreach ($unassignedPages as $url) {
            $html = $this->actingAs($kagawad)->get($url)->assertOk()->getContent();

            /*
             * The Captain IS the admin role - ROLES.md owner decision 1 - and assigning
             * a Kagawad to a sector is one of the five admin-only destinations. So the
             * unassigned state is right to name the Captain, and the copy fix put that
             * name back deliberately. This assertion used to require the word to be
             * absent, on the grounds that it invented a role; it does not.
             */
            $this->assertStringContainsStringIgnoringCase('captain', $html, "[{$url}] should name the Captain.");
        }

        /*
         * Naming the page is still not linking to it, and that holds on every Kagawad
         * page including the batch checklist.
         *
         * kagawad.relief.show is deliberately absent from the loop above. For an
         * unassigned Kagawad it does not render the unassigned-sector component at all:
         * it renders its own "none of this batch's households are in your sectors"
         * empty state, because the question it answers is different - not "you have no
         * sector" but "this particular batch is not yours". Asserting the Captain there
         * would be asserting a sentence the page has no reason to print.
         */
        $everyPage = array_merge($unassignedPages, [route('kagawad.relief.show', $batch)]);

        foreach ($everyPage as $url) {
            $html = $this->actingAs($kagawad)->get($url)->assertOk()->getContent();

            // /staff/sectors is role:admin, so a Kagawad who followed the advice would
            // get a 403. The sentence names "Sectors & Kagawad" and carries no href,
            // which is the whole point of the copy fix.
            $this->assertStringNotContainsString(route('staff.sectors.index'), $html, "[{$url}] links to Sectors & Kagawad.");
        }
    }

    // ------------------------------------------------------------- dashboard

    #[Test]
    public function the_dashboard_greets_by_first_name_and_names_the_sector(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        $firstName = explode(' ', $kagawad->name)[0];
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*Hello, '.preg_quote($firstName, '/').'\s*<\/h1>/',
            $response->getContent()
        );
        $response->assertSee('Purok 3');
    }

    #[Test]
    public function the_stat_cards_are_links(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        $html = $response->getContent();

        foreach (['Active batches', 'Still to collect', 'Collected'] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        /*
         * Each card is an <a> to the relief list, not a div. Counted inside the
         * stat row only: the sidebar and the phone navigation both link to the
         * same route, so a whole-page count would be five and would say nothing
         * about the cards.
         */
        $xpath = $this->dom($html);
        $cardLinks = 0;

        foreach ($xpath->query('//div[contains(@class,"md:grid-cols-4")]/a') as $anchor) {
            $this->assertInstanceOf(DOMElement::class, $anchor);
            if ($anchor->getAttribute('href') === route('kagawad.relief')) {
                $cardLinks++;
            }
        }

        $this->assertSame(3, $cardLinks, 'The three stat cards are not all links to the relief list.');
    }

    #[Test]
    public function active_distributions_shows_only_batches_the_kagawad_has_households_in(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector('Purok 3');
        $theirs = Sector::factory()->create(['sector_name' => 'Purok 9']);

        $mineBatch = $this->newBatchWithHouseholds('My batch', [$mine->id => ['M-1', 'M-2']]);
        $theirBatch = $this->newBatchWithHouseholds('Their batch', [$theirs->id => ['T-1', 'T-2', 'T-3']]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        $response->assertSee('My batch');
        // Not merely unlinked: absent. An "Open checklist" button here would lead
        // to a page saying none of this is yours.
        $response->assertDontSee('Their batch');
        $response->assertDontSee('T-1');
        $response->assertDontSee(route('kagawad.relief.show', $theirBatch), escape: false);
        $response->assertSee(route('kagawad.relief.show', $mineBatch), escape: false);
    }

    #[Test]
    public function the_progress_figure_on_a_batch_is_my_share_not_the_barangay_share(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector('Purok 3');
        $theirs = Sector::factory()->create();

        // 2 of mine pending, 4 of theirs pending. Barangay-wide total is 6.
        $batch = $this->newBatchWithHouseholds('Shared batch', [
            $mine->id => ['M-1', 'M-2'],
            $theirs->id => ['T-1', 'T-2', 'T-3', 'T-4'],
        ]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        $this->assertStringContainsString('0 / 2', $html, 'The bar must read 0 of 2, not 0 of 6.');
        $this->assertStringNotContainsString('0 / 6', $html);
        $this->assertStringNotContainsString('4 still to collect', $html);
    }

    #[Test]
    public function active_distributions_shows_at_most_three_batches(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        foreach (range(1, 5) as $n) {
            $this->newBatchWithHouseholds("Batch {$n}", [$sector->id => ["B{$n}-1"]]);
        }

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        $this->assertStringContainsString('Batch 1', $html);
        $this->assertStringContainsString('Batch 3', $html);
        $this->assertStringNotContainsString('Batch 4', $html);
        $this->assertStringNotContainsString('Batch 5', $html);
    }

    #[Test]
    public function a_closed_batch_is_not_an_active_distribution(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $closed = ReliefBatch::factory()->closed()->create(['title' => 'Finished batch']);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $closed->id,
            'sector_id' => $sector->id,
            'household_number' => 'C-1',
        ]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Finished batch');
    }

    #[Test]
    public function an_assigned_kagawad_with_no_batches_gets_the_assigned_empty_state(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $response = $this->actingAs($kagawad)->get(route('kagawad.dashboard'));

        $response->assertOk();
        // Assigned, so NOT the unassigned wording - the two states need different
        // conversations with the office.
        $response->assertDontSee('No sector assigned yet');
        $response->assertSee('No active distribution');
        $response->assertSee('Purok 3');
    }

    // ------------------------------------------------------------- checklist

    #[Test]
    public function the_checklist_shows_progress_goods_and_the_households(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = ReliefBatch::factory()->create([
            'title' => 'October rice',
            'status' => ReliefBatch::STATUS_ACTIVE,
            'items_included' => ['5kg Rice', '6 Canned Goods'],
        ]);
        ReliefDistribution::factory()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'H-1', 'status' => ReliefDistribution::STATUS_PENDING,
        ]);
        $received = ReliefDistribution::factory()->received()->create([
            'relief_batch_id' => $batch->id, 'sector_id' => $sector->id,
            'household_number' => 'H-2', 'received_by_name' => 'Ana Bautista',
        ]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch));

        $response->assertOk();
        $response->assertSee('October rice');
        // Goods as chips, each one its own element.
        $response->assertSee('5kg Rice');
        $response->assertSee('6 Canned Goods');
        // Overall progress for this sector.
        $response->assertSee('1 / 2 collected');
        // The household that is done shows who signed for it.
        $response->assertSee('Ana Bautista');
        // Tabs, each with its count.
        $response->assertSee('Remaining');
        $response->assertSee('Collected');
        $response->assertSee('All');
    }

    #[Test]
    public function the_remaining_tab_is_the_default_and_the_counts_add_up(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2', 'H-3']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        // Alpine sets aria-selected at runtime, so the server HTML carries the
        // binding rather than a literal aria-selected="true". Asserting on the
        // literal would be asserting something the template never contains.
        $this->assertStringContainsString("tab = 'remaining'", $html);
        $this->assertStringContainsString("x-bind:aria-selected=\"tab === 'remaining'\"", $html);
        // Counts, in the order the tabs are declared.
        $this->assertMatchesRegularExpression('/Remaining.*?>3</s', $html);
        $this->assertMatchesRegularExpression('/Collected.*?>0</s', $html);
        $this->assertMatchesRegularExpression('/All.*?>3</s', $html);
        $this->assertStringContainsString('0 / 3 collected', $html);
    }

    #[Test]
    public function a_collected_household_is_badged_collected_not_ready_for_pickup(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);
        ReliefDistribution::query()->where('relief_batch_id', $batch->id)->update([
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Ana Bautista',
        ]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch));

        $response->assertOk();
        $response->assertSee('Collected');
        // "Ready for pickup" is a *document request* state. A household that has
        // already signed for its goods is not waiting to collect them.
        $this->assertStringNotContainsString('Ready for pickup', $response->getContent());
    }

    #[Test]
    public function there_is_no_undo_control(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);
        ReliefDistribution::query()->where('relief_batch_id', $batch->id)->update([
            'status' => ReliefDistribution::STATUS_RECEIVED,
            'received_by_name' => 'Ana Bautista',
        ]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        // There is no undo endpoint on ReliefController and ROLES.md does not
        // exist, so there is no permission to check and nothing to point at.
        $this->assertStringNotContainsStringIgnoringCase('undo', $html);
        $this->assertStringNotContainsStringIgnoringCase('un-mark', $html);
    }

    #[Test]
    public function the_mark_button_clears_a_44px_tap_target(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        // 44px is the smallest comfortable target for a thumb. The control is now

        // <x-staff.icon-action size="lg">, which spells the box out as h-11 w-11 —

        // 44px exactly — rather than asking for a minimum height and hoping.

        $xpath = $this->dom($html);

        $mark = $xpath->query('//button[contains(@class,"h-11") and contains(@class,"w-11")]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $mark, 'No 44x44 control on the checklist.');

        // The glyph is a font-awesome <i>, so it carries no text of its own — the class
        // is what identifies it. Asserting on textContent would pass on the sr-only
        // label alone and never check the icon at all.
        $glyph = $xpath->query('.//i[contains(@class,"fa-check")]', $mark)->length;
        $this->assertSame(1, $glyph, 'The mark control does not draw the check glyph.');
        $icon = $xpath->query('.//i', $mark)->item(0);
        $this->assertInstanceOf(DOMElement::class, $icon);
        $this->assertSame('true', $icon->getAttribute('aria-hidden'), 'The glyph must be aria-hidden; the control carries the name.');

        $this->assertStringContainsString(

            'Mark H-1 collected',

            (string) $mark->getAttribute('aria-label'),

            'The 44px control is not named as the mark action.'

        );
    }

    #[Test]
    public function an_irreversible_mark_is_confirmed_before_it_is_sent(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        // Mark, then Confirm, in the same row. The confirm is inline rather than a
        // modal because a modal per household is forty dialogs per distribution.
        $this->assertStringContainsString('Mark H-1 collected', $html);
        $this->assertStringContainsString('Confirm', $html);
        $this->assertStringContainsString('x-on:click="confirming = false"', $html);
    }

    #[Test]
    public function the_first_tap_only_reveals_the_confirm_and_does_not_post(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        $xpath = $this->dom($html);

        $mark = null;
        $confirm = null;
        foreach ($xpath->query('//button') as $button) {
            $this->assertInstanceOf(DOMElement::class, $button);
            // Matched on the accessible name rather than the visible text: the mark
            // control is an icon with an sr-only name, so there is no visible label
            // left to match on, and a test reading textContent would silently stop
            // finding the button instead of failing.
            if (str_contains((string) $button->getAttribute('aria-label'), 'collected')) {
                $mark = $button;
            }
            if (trim($button->textContent) === 'Confirm') {
                $confirm = $button;
            }
        }

        $this->assertNotNull($mark, 'No mark-collected button.');
        $this->assertNotNull($confirm, 'No Confirm button.');

        // The trap: as type="submit" the first tap posted the form, the confirm was
        // never reachable, and an irreversible record became one unconfirmed click.
        $this->assertSame('button', $mark->getAttribute('type'), 'Mark collected must not submit on the first tap.');
        $this->assertStringContainsString('confirming = true', (string) $mark->getAttribute('x-on:click'));

        $this->assertSame('submit', $confirm->getAttribute('type'), 'Confirm is what actually posts.');
    }

    #[Test]
    public function the_scroll_position_is_preserved_across_a_mark(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2', 'H-3']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent();

        // Written on submit, read and cleared on load. Without both halves it is
        // either a scroll that never restores or one that restores forever.
        $this->assertStringContainsString("sessionStorage.setItem('kagawad-checklist-scroll'", $html);
        $this->assertStringContainsString("sessionStorage.getItem('kagawad-checklist-scroll')", $html);
        $this->assertStringContainsString("sessionStorage.removeItem('kagawad-checklist-scroll')", $html);
    }

    #[Test]
    public function the_checklist_never_shows_another_sectors_households(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector('Purok 3');
        $theirs = Sector::factory()->create(['sector_name' => 'Purok 9']);

        $batch = $this->newBatchWithHouseholds('Shared batch', [
            $mine->id => ['M-1', 'M-2'],
            $theirs->id => ['SECRET-1', 'SECRET-2'],
        ]);

        $response = $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch));

        $response->assertOk();
        $response->assertSee('M-1');
        $response->assertSee('Purok 3');
        // The other purok's list, in the page source, not just off-screen.
        $response->assertDontSee('SECRET-1');
        $response->assertDontSee('SECRET-2');
        $response->assertDontSee('Purok 9');
        $this->assertStringNotContainsString('2 / 4', $response->getContent());
    }

    #[Test]
    public function a_kagawad_cannot_mark_another_sectors_household(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector('Purok 3');
        $theirs = Sector::factory()->create();

        $batch = $this->newBatchWithHouseholds('Shared batch', [$theirs->id => ['T-1']]);
        $foreign = ReliefDistribution::query()->where('relief_batch_id', $batch->id)->firstOrFail();

        $this->actingAs($kagawad)
            ->post(route('kagawad.relief.received', [$batch, $foreign->id]))
            ->assertNotFound();

        $this->assertSame(ReliefDistribution::STATUS_PENDING, $foreign->fresh()->status);
    }

    // ---------------------------------------------------------- the badge

    #[Test]
    public function the_sidebar_badge_counts_only_my_sector(): void
    {
        ['kagawad' => $kagawad, 'sector' => $mine] = $this->kagawadWithSector('Purok 3');
        $theirs = Sector::factory()->create();

        $this->newBatchWithHouseholds('Shared batch', [
            $mine->id => ['M-1', 'M-2'],
            $theirs->id => ['T-1', 'T-2', 'T-3', 'T-4'],
        ]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        $this->assertStringContainsString('still to collect', $html);
        // Mine is 2, theirs is 4, barangay-wide is 6. The badge must read 2.
        $this->assertMatchesRegularExpression('/aria-label="2 households still to collect"/', $html);
        $this->assertStringNotContainsString('4 households still to collect', $html);
        $this->assertStringNotContainsString('6 households still to collect', $html);
    }

    #[Test]
    public function the_badge_disappears_when_nothing_is_outstanding(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);
        ReliefDistribution::query()->where('relief_batch_id', $batch->id)->update([
            'status' => ReliefDistribution::STATUS_RECEIVED,
        ]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        // A badge reading 0 is a nag about nothing.
        $this->assertStringNotContainsString('still to collect', $html);
    }

    #[Test]
    public function a_secretary_is_not_offered_the_kagawad_relief_badge(): void
    {
        $secretary = User::factory()->secretary()->create();

        // A pending household in a real sector, so the figure EXISTS and the badge is
        // still absent. Without the data this would pass on an empty queue and prove
        // nothing about the role.
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create();
        ReliefDistribution::factory()->forBatch($batch)->forSector($sector)->create();

        $html = $this->actingAs($secretary)->get(route('staff.dashboard'))->getContent();

        // Relief Distribution is not a Secretary to-do, and in any case it is not in
        // their list at all - the label must not appear anywhere in the document.
        $this->assertStringNotContainsString('still to collect', $html);
    }

    // ------------------------------------------------- the phone navigation

    #[Test]
    public function a_kagawad_has_a_navigation_that_survives_a_phone(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        // The sidebar is hidden below md, so without a second navigation a phone
        // user cannot reach anything at all.
        $this->assertStringContainsString('md:hidden', $html);
        $this->assertStringContainsString('aria-label="Portal"', $html);
        // The three destinations a Kagawad has.
        $this->assertStringContainsString(route('kagawad.dashboard'), $html);
        $this->assertStringContainsString(route('kagawad.relief'), $html);
        // Tap targets.
        $this->assertStringContainsString('min-h-[56px]', $html);
    }

    #[Test]
    public function the_phone_navigation_shows_only_this_roles_links(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');

        $html = $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->getContent();

        $this->assertStringNotContainsString(route('staff.accounts.index'), $html);
        $this->assertStringNotContainsString(route('staff.audit.index'), $html);
        $this->assertStringNotContainsString(route('staff.sectors.index'), $html);
    }

    // -------------------------------------------------------- query budget

    #[Test]
    public function the_dashboard_query_count_does_not_grow_with_rows(): void
    {
        $queriesWith = function (int $n): int {
            ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
            $this->newBatchWithHouseholds('October rice', [$sector->id => array_map(
                fn (int $i): string => "H-{$i}",
                range(1, $n)
            )]);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($kagawad)->get(route('kagawad.dashboard'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $small = $queriesWith(1);
        $this->refreshDatabase();
        $large = $queriesWith(25);

        $this->assertSame($small, $large, "Dashboard issued {$large} queries with 25 households and {$small} with 1.");
    }

    #[Test]
    public function the_checklist_query_count_does_not_grow_with_rows(): void
    {
        $queriesWith = function (int $n): array {
            ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
            $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => array_map(
                fn (int $i): string => "H-{$i}",
                range(1, $n)
            )]);

            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return [$count, $batch];
        };

        [$small, $batch] = $queriesWith(1);
        $this->refreshDatabase();
        [$large, $batch] = $queriesWith(25);

        $this->assertSame($small, $large, "Checklist issued {$large} queries with 25 households and {$small} with 1.");
    }

    // ------------------------------------------------------------- structure

    #[Test]
    public function the_batches_table_renders_below_md_as_cards_not_a_scrolling_table(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1']]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief'))->getContent();

        $xpath = $this->dom($html);

        // One of the two renderings is display:none at every width, so a phone
        // never gets the 46rem table it would have to scroll sideways.
        $cardList = $xpath->query('//ul[contains(@class,"md:hidden")]/li')->length;

        // The card is an <li>, not an <a>. The whole card used to be one link, which
        // made the card's entire text the link's accessible name and left nowhere to
        // put a control. It is now a container with the same Open-checklist icon
        // action the table row uses, which is what this counts.
        $cardActions = $xpath->query('//ul[contains(@class,"md:hidden")]/li//a[@aria-label]')->length;
        $this->assertGreaterThan(
            0,
            $cardActions,
            'A phone card has no action, so a Kagawad on a phone cannot open a checklist.'
        );
        $table = $xpath->query('//table')->item(0);

        $this->assertGreaterThan(0, $cardList, 'No stacked-card rendering for phones.');
        $this->assertInstanceOf(DOMElement::class, $table, 'No table rendering for tablet and up.');

        /*
         * Walked up rather than counting parents: the table sits inside an
         * overflow-x-auto div inside the component's own wrapper, and libxml's
         * HTML4 parser is free to guess at that nesting, so "two levels up" is a
         * bet on its guess. "Some ancestor carries hidden md:block" is the actual
         * requirement and does not care how deep it is.
         */
        $hiddenAtMobile = false;
        for ($node = $table->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node instanceof DOMElement && str_contains((string) $node->getAttribute('class'), 'hidden md:block')) {
                $hiddenAtMobile = true;
                break;
            }
        }

        $this->assertTrue($hiddenAtMobile, 'The table is not hidden below md, so a phone gets the scrolling table.');
    }

    #[Test]
    public function the_batches_table_shows_a_progress_column_and_a_status_badge(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2']]);
        ReliefDistribution::query()->where('relief_batch_id', $batch->id)
            ->where('household_number', 'H-1')->update(['status' => ReliefDistribution::STATUS_RECEIVED]);

        $html = $this->actingAs($kagawad)->get(route('kagawad.relief'))->getContent();

        $xpath = $this->dom($html);

        $headers = [];
        foreach ($xpath->query('//table//thead//th') as $th) {
            $this->assertInstanceOf(DOMElement::class, $th);
            $headers[] = trim($th->textContent);
        }

        $this->assertSame(['Batch', 'Date', 'Households', 'Collected', 'Remaining', 'Progress', 'Status', 'Actions'], $headers);

        // 1 of 2 is 50%.
        $this->assertStringContainsString('50%', $html);
        $this->assertStringContainsString('aria-valuenow="50"', $html);
        $this->assertStringContainsString('Active', $html);
    }

    #[Test]
    public function every_progress_bar_exposes_its_value(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2']]);
        ReliefDistribution::query()->where('relief_batch_id', $batch->id)
            ->where('household_number', 'H-1')->update(['status' => ReliefDistribution::STATUS_RECEIVED]);

        foreach ([route('kagawad.dashboard'), route('kagawad.relief'), route('kagawad.relief.show', $batch)] as $url) {
            $html = $this->actingAs($kagawad)->get($url)->getContent();

            $xpath = $this->dom($html);

            foreach ($xpath->query('//*[@role="progressbar"]') as $bar) {
                $this->assertInstanceOf(DOMElement::class, $bar);
                $this->assertSame('0', $bar->getAttribute('aria-valuemin'), "[{$url}] progressbar has no aria-valuemin.");
                $this->assertSame('100', $bar->getAttribute('aria-valuemax'), "[{$url}] progressbar has no aria-valuemax.");
                // The literal-shorthand bug: Blade leaves ":aria-valuenow" in the
                // markup on a plain element, and a browser drops it silently.
                $this->assertNotSame('', $bar->getAttribute('aria-valuenow'), "[{$url}] progressbar has no aria-valuenow.");
                $this->assertNotNull($bar->getAttribute('aria-label'), "[{$url}] progressbar has no aria-label.");
            }
        }
    }

    #[Test]
    public function the_alpine_expressions_are_all_parseable(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2']]);

        foreach ([
            route('kagawad.dashboard'),
            route('kagawad.relief'),
            route('kagawad.relief.show', $batch),
        ] as $url) {
            $xpath = $this->dom($this->actingAs($kagawad)->get($url)->getContent());

            foreach ($xpath->query('//*[@x-data]') as $scope) {
                $this->assertInstanceOf(DOMElement::class, $scope);

                /*
                 * Alpine evaluates x-data, x-init and friends by building a function
                 * around them, so a // comment inside one of those expressions runs to
                 * the end of its line and swallows whatever closes it. In practice
                 * that means "Unexpected token 'const'" and a control that is present,
                 * visible, and completely inert - the page renders, the HTML is right,
                 * and every server test passes.
                 *
                 * It happened twice on this page, in x-data and then in x-init, and
                 * both times only a browser found it. So: no line comments, and no
                 * empty x-data, in any Alpine expression on any Kagawad page.
                 */
                $this->assertNotSame(
                    '',
                    trim($scope->getAttribute('x-data')),
                    "[{$url}] has an empty x-data, which is not a valid expression."
                );
            }

            /*
             * Every element, then every attribute, checked in PHP rather than with a
             * clever XPath: an attribute name like x-on:click reads as a namespaced
             * name to XPath ("Undefined namespace prefix"), and the one that matters
             * most is exactly the one that cannot be written in a predicate.
             */
            foreach ($xpath->query('//*') as $element) {
                if (! $element instanceof DOMElement) {
                    continue;
                }

                foreach ($element->attributes as $attribute) {
                    if (! str_starts_with($attribute->name, 'x-')) {
                        continue;
                    }

                    $this->assertDoesNotMatchRegularExpression(
                        '/(^|[^:])\/\//',
                        $attribute->value,
                        "[{$url}] has a // line comment inside {$attribute->name}, which Alpine cannot evaluate."
                    );

                    /*
                     * A handler written as statements - const then if, or two
                     * newline-separated calls - is not an expression, and this
                     * Alpine build evaluates these attributes as
                     * `with (scope) { __self.result = <here> }` with no fallback to a
                     * statement body. So it throws ("Unexpected token 'const'",
                     * "Unexpected string") and the handler never runs. Confirmed in
                     * the browser: the extracted attribute throws in the expression
                     * form and is fine in the arrow-body form, and it is the
                     * expression form that is used.
                     *
                     * A multi-line handler must therefore be an IIFE, which is a
                     * single expression and starts with "(". This asserts exactly
                     * that, for every statement-bearing Alpine attribute.
                     */
                    if (str_starts_with($attribute->name, 'x-on:') || in_array($attribute->name, ['x-init', 'x-effect'], true)) {
                        $value = trim($attribute->value);

                        if (str_contains($value, "\n")) {
                            $this->assertMatchesRegularExpression(
                                '/^\(/',
                                $value,
                                "[{$url}] {$attribute->name} is written as statements over several lines; Alpine evaluates it as one expression, so it needs an IIFE."
                            );
                        }
                    }
                }
            }
        }
    }

    #[Test]
    public function the_toast_ref_points_at_the_styled_element_and_not_its_wrapper(): void
    {
        ['kagawad' => $kagawad, 'sector' => $sector] = $this->kagawadWithSector('Purok 3');
        $batch = $this->newBatchWithHouseholds('October rice', [$sector->id => ['H-1', 'H-2']]);

        $xpath = $this->dom($this->actingAs($kagawad)->get(route('kagawad.relief.show', $batch))->getContent());

        $toast = $xpath->query('//*[@role="status"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $toast, 'No live region for the toast.');

        // The x-init does $refs.toast.textContent = last, which REPLACES an
        // element's children. With the ref on the wrapper that wiped the styled
        // <p> and left bare unstyled text on the page - the message showed and
        // nothing looked wrong. The ref has to be on the element being filled.
        $ref = $xpath->query('//*[@role="status"]//*[@x-ref="toast"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $ref, 'No x-ref="toast" inside the live region.');
        $this->assertSame(
            'p',
            $ref->tagName,
            'x-ref="toast" must be on the <p> that gets the text, not on the wrapper around it.'
        );
        $this->assertStringContainsString('hidden', (string) $ref->getAttribute('class'));
        $this->assertStringContainsString('rounded-full', (string) $ref->getAttribute('class'));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Parse rendered HTML.
     *
     * libxml's parser is HTML4 and objects to HTML5 elements like <aside>, so its
     * errors are muted rather than allowed to throw. It still builds a usable
     * tree; the only cost is that it guesses at some tags, and nothing here
     * depends on a guess.
     */
    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

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
     * @param  array<int, list<string>>  $householdsBySector
     */
    private function newBatchWithHouseholds(string $title, array $householdsBySector): ReliefBatch
    {
        $batch = ReliefBatch::factory()->create([
            'title' => $title,
            'status' => ReliefBatch::STATUS_ACTIVE,
        ]);

        foreach ($householdsBySector as $sectorId => $numbers) {
            foreach ($numbers as $number) {
                ReliefDistribution::factory()->create([
                    'relief_batch_id' => $batch->id,
                    'sector_id' => $sectorId,
                    'household_number' => $number,
                    'status' => ReliefDistribution::STATUS_PENDING,
                ]);
            }
        }

        return $batch->fresh();
    }
}
