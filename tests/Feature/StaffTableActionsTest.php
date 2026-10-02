<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every staff, Kagawad and Admin table: an Actions column, and nothing centred.
 *
 * Two separate defects, asserted together because they were fixed together.
 *
 * ONE WAY IN, AND IT IS NAMED.
 *
 * Rows used to be clickable (an x-data openRow() on the table wrapper, an
 * x-on:click on every <tr>), the primary cell's name was a link, and a chevron
 * sat beside it. That is three ways into one record and none of them named it: a
 * <tr> is not focusable and is not a link, so middle-click and "open in new tab"
 * did nothing on it; the chevron was aria-hidden; and the link's text was the same
 * string as the cell it sat in.
 *
 * A row is now reached through the Actions column, where every control is an
 * <x-staff.icon-action> with an aria-label that names the record it acts on
 * ("View Ana Pending Oldest"). These tests read the rendered DOM for that reason:
 * an array of link definitions cannot say whether a control ended up with a
 * destination, and a test on the Blade source would pass while the column was
 * never rendered.
 *
 * NOTHING IS CENTRED.
 *
 * Alignment was reported fixed and was not. It had been re-measured at 1280 only,
 * and only on text-align of th and td — which was genuinely left, and which is why
 * it read as fixed. The cells that were actually off were never text-align at all:
 *
 *     align => 'right' on a column definition  ->  text-right on the <th>
 *     class="text-right" on a numeric cell     ->  the figures sat right
 *     justify-end on the Actions cell          ->  the column read right-aligned
 *
 * The first is worth calling out: `align` was a real prop of <x-staff.table>, and
 * it was also a dead one on the shared component, which reads $column['class']. So
 * 'right' was honoured here and ignored there, which is how the same table could
 * be right-aligned on one screen and not on another.
 *
 * The test below allows justify-center DEEP inside a cell, because that is how a
 * glyph or an initial is centred in a fixed-size box, and removing it would break
 * the icon. It does not allow it on the cell or on the cell's own wrapper, which
 * is where it actually moved text.
 */
class StaffTableActionsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The tables that end in an Actions column, and who may open each.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tablesWithActions(): array
    {
        return [
            'verifications' => ['secretary', 'staff.verifications.index', '/staff/verifications'],
            'residents' => ['secretary', 'staff.residents.index', '/staff/residents'],
            'requests' => ['secretary', 'staff.requests.index', '/staff/requests'],
            'batches' => ['secretary', 'staff.batches.index', '/staff/batches'],
            'announcements' => ['secretary', 'staff.announcements.index', '/staff/announcements'],
            'accounts' => ['admin', 'staff.accounts.index', '/staff/accounts'],
            'sectors' => ['admin', 'staff.sectors.index', '/staff/sectors'],
        ];
    }

    /**
     * The two tables that must NOT have one, and why.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tablesWithoutActions(): array
    {
        return [
            // Audit Logs keeps its Context disclosure. There is no record to open:
            // the row IS the record, and an "Actions" column here would hold a View
            // button that goes nowhere.
            'audit' => ['admin', 'staff.audit.index', '/staff/audit'],
            // Read-only by decision 3, and there is no staff resident detail page a
            // Kagawad is allowed to open. An Actions column would be a column of
            // controls that cannot do anything.
            'kagawad residents' => ['kagawad', 'kagawad.residents.index', '/kagawad/residents'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function allTables(): array
    {
        return array_merge(self::tablesWithActions(), self::tablesWithoutActions());
    }

    // -----------------------------------------------------------------
    // the Actions column
    // -----------------------------------------------------------------

    /**
     * The last column is Actions, and it is the last one.
     *
     * Asserted as the final header rather than "contains", because a column added
     * after Actions is how a table ends up with two action columns and a reader
     * with nowhere to look for either.
     */
    #[Test]
    #[DataProvider('tablesWithActions')]
    public function the_table_ends_with_an_actions_column(string $state, string $route): void
    {
        $headers = $this->headers($this->page($state, $route));

        $this->assertNotSame([], $headers, 'The page rendered no table.');
        $this->assertSame(
            'Actions',
            end($headers),
            "[{$route}] should end with an Actions column, found: ".implode(' | ', $headers)
        );
    }

    #[Test]
    #[DataProvider('tablesWithoutActions')]
    public function the_table_has_no_actions_column(string $state, string $route): void
    {
        $this->assertNotContains(
            'Actions',
            $this->headers($this->page($state, $route)),
            "[{$route}] must not have an Actions column."
        );
    }

    /**
     * Every control in the Actions column is named, and it goes somewhere.
     *
     * "Somewhere" means an href on a link, or a type on a button that is inside a
     * form with an action. A button with neither navigates nowhere and submits
     * nowhere, and it is the failure mode that survives a screenshot.
     */
    #[Test]
    #[DataProvider('tablesWithActions')]
    public function every_action_has_an_accessible_name_and_a_destination(string $state, string $route): void
    {
        $xpath = $this->xpath($this->page($state, $route));

        // Only the icon actions, identified by the glyph they always contain. The
        // confirm dialog lives in the same cell and its own buttons carry no
        // aria-label, because they are named by their visible text; sweeping them in
        // here would fail on a control that is correctly labelled.
        $actions = $xpath->query('//table//td[last()]//a[i] | //table//td[last()]//button[i]');
        $this->assertGreaterThan(0, $actions->length, "[{$route}] rendered an Actions cell with nothing in it.");

        foreach ($actions as $action) {
            $this->assertInstanceOf(DOMElement::class, $action);
            $label = trim($action->getAttribute('aria-label'));
            $this->assertNotSame('', $label, 'An action in '.implode(' | ', $this->headers($this->page($state, $route))).' has no aria-label.');

            // A title as well: sighted readers get the tooltip, and it is the only
            // thing naming the control for anyone not using a screen reader.
            $this->assertNotSame('', trim($action->getAttribute('title')), "[{$label}] has no tooltip.");

            if ($action->tagName === 'a') {
                $this->assertNotSame('', trim((string) $action->getAttribute('href')), "[{$label}] is a link with no destination.");

                continue;
            }

            $this->assertContains(
                $action->getAttribute('type'),
                ['submit', 'button'],
                "[{$label}] is a button of an unusable type."
            );

            if ($action->getAttribute('type') === 'submit') {
                $form = $this->enclosing($action, 'form');
                $this->assertNotNull($form, "[{$label}] submits but is not inside a form.");
                $this->assertNotSame('', trim((string) $form->getAttribute('action')), "[{$label}] is in a form with no action.");
            }
        }
    }

    /**
     * The two exception tables offer no actions at all.
     *
     * The opposite assertion to the one above, and stated separately because a
     * shared provider would have to assert both directions at once and a test that
     * asserts "there is at least one action" and "there are no actions" for the
     * same element is a test that cannot be read.
     *
     * Audit Logs has no record to open — the row is the record. The Kagawad resident
     * list is read-only by decision 3, and there is no staff resident page a Kagawad
     * may open. An Actions column on either would be a column of controls that
     * cannot do anything, which is the thing this whole change removed.
     */
    #[Test]
    #[DataProvider('tablesWithoutActions')]
    public function the_exception_tables_offer_no_actions(string $state, string $route): void
    {
        $xpath = $this->xpath($this->page($state, $route));

        $this->assertSame(
            0,
            $xpath->query('//table//td[last()]//a[i] | //table//td[last()]//button[i]')->length,
            "[{$route}] should offer no icon actions."
        );
    }

    /**
     * The glyph is decorative; the control carries the name.
     */
    #[Test]
    #[DataProvider('tablesWithActions')]
    public function the_glyph_is_hidden_from_assistive_technology(string $state, string $route): void
    {
        $xpath = $this->xpath($this->page($state, $route));

        foreach ($xpath->query('//table//td[last()]//i') as $glyph) {
            $this->assertInstanceOf(DOMElement::class, $glyph);
            $this->assertSame(
                'true',
                $glyph->getAttribute('aria-hidden'),
                'An icon-action glyph is not aria-hidden, so it is read before the control it is in.'
            );
        }
    }

    /**
     * Nothing is centred.
     *
     * The rule, precisely: no cell, and no wrapper the cell owns directly, may
     * carry a centring or right-alignment utility. Deeper down, justify-center is
     * allowed, because that is how a glyph sits in a 36x36 box, but text-center and
     * text-right are not allowed anywhere inside a cell, at any depth, because
     * those move the cell's actual words.
     */
    #[Test]
    #[DataProvider('allTables')]
    public function no_table_cell_or_its_wrapper_is_centred(string $state, string $route): void
    {
        $xpath = $this->xpath($this->page($state, $route));

        $tables = $xpath->query('//table');
        $this->assertGreaterThan(0, $tables->length, "[{$route}] rendered no table to check.");

        foreach ($tables as $table) {
            foreach ($xpath->query('.//th | .//td', $table) as $cell) {
                $this->assertInstanceOf(DOMElement::class, $cell);

                $own = $this->classes($cell);
                $this->assertNoCentring($own, $route, 'cell <'.$cell->tagName.'>');

                $wrapper = $cell->firstElementChild;
                if ($wrapper instanceof DOMElement) {
                    $this->assertNoCentring($this->classes($wrapper), $route, 'wrapper <'.$wrapper->tagName.'>');
                }

                // Text alignment, at every depth inside the cell.
                foreach ($xpath->query('.//*', $cell) as $node) {
                    $this->assertInstanceOf(DOMElement::class, $node);
                    $bad = array_intersect($this->classes($node), ['text-center', 'text-right']);
                    $this->assertSame(
                        [],
                        array_values($bad),
                        "[{$route}] has ".implode(' ', $bad).' on <'.$node->tagName.'> inside a table cell.'
                    );
                }
            }
        }
    }

    /**
     * The table itself declares left alignment, rather than relying on the UA default.
     *
     * `<th>` is `text-align: center` in the browser's own stylesheet. A td is not,
     * but a th is, which is why the first cell of a row used to read centred under
     * a left-aligned header. The rule has to be an author rule on the th, so the
     * component carries it — and this is the test that says so.
     */
    #[Test]
    public function the_staff_table_declares_left_alignment_on_its_own_cells(): void
    {
        $source = (string) file_get_contents(
            app('view')->getFinder()->find('components.staff.table')
        );

        $this->assertStringContainsString('[&_th]:text-left', $source, 'The th rule is gone; the UA default is centre.');
        $this->assertStringContainsString('[&_td]:text-left', $source);

        // The dead `align` key must not be READ. Asserted on the read, not on the
        // word: the component's own comment explains that the key was removed and
        // why, so a substring search for 'align' matches that explanation and fails
        // forever.
        $this->assertStringNotContainsString("\$column['align']", $source, 'The dead align column prop is read again.');
    }

    // -----------------------------------------------------------------
    // the things that had to go
    // -----------------------------------------------------------------

    /**
     * No row is clickable any more, anywhere.
     */
    #[Test]
    #[DataProvider('allTables')]
    public function no_row_is_clickable(string $state, string $route): void
    {
        $html = $this->page($state, $route);

        $this->assertStringNotContainsString('openRow', $html, "[{$route}] still wires a row click.");

        $xpath = $this->xpath($html);
        foreach ($xpath->query('//tbody//tr') as $row) {
            $this->assertInstanceOf(DOMElement::class, $row);
            $this->assertStringNotContainsString(
                'x-on:click',
                $row->getAttribute('x-on:click'),
                "[{$route}] still has a click handler on a row."
            );
        }
    }

    /**
     * A name or a reference number is plain text, not a link.
     *
     * A row of reference numbers that are all links reads as a wall of links and
     * gives a screen reader nothing to tell them apart by. The Actions column is the
     * way in.
     */
    #[Test]
    #[DataProvider('allTables')]
    public function the_primary_cell_is_not_a_link(string $state, string $route): void
    {
        $xpath = $this->xpath($this->page($state, $route));

        foreach ($xpath->query('//table//tbody//tr/th[1]//a') as $link) {
            $this->fail("[{$route}] has a link in the first cell of a row: ".trim($link->textContent));
        }

        $this->assertTrue(true);
    }

    /**
     * The chevron is gone from the component, not merely unused.
     */
    #[Test]
    public function the_person_cell_no_longer_offers_a_chevron_or_a_href(): void
    {
        $source = (string) file_get_contents(
            app('view')->getFinder()->find('components.staff.person-cell')
        );

        // Asserted on the props list and on the markup, not on the whole file: the
        // component's own comment explains why these were removed and says so in
        // prose, so a substring check over the source would match its own
        // explanation and fail forever.
        $props = substr($source, 0, (int) strpos($source, '@php'));

        $this->assertStringNotContainsString('chevron', $props, 'The chevron prop is back.');
        $this->assertStringNotContainsString("'href'", $props, 'The href prop is back.');

        $body = substr($source, (int) strpos($source, '@php'));

        $this->assertStringNotContainsString('<a ', $body, 'A person cell renders an anchor again.');
        $this->assertStringNotContainsString('$href', $body);
        $this->assertStringNotContainsString('arrow-right', $body, 'The chevron glyph is back.');
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @param  list<string>  $classes
     */
    private function assertNoCentring(array $classes, string $route, string $where): void
    {
        $bad = array_intersect($classes, ['text-center', 'text-right', 'justify-center', 'justify-end', 'justify-between', 'justify-items-center']);

        $this->assertSame(
            [],
            array_values($bad),
            "[{$route}] has ".implode(' ', $bad)." on the $where."
        );
    }

    /**
     * @return list<string>
     */
    private function classes(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    }

    private function enclosing(DOMElement $node, string $tag): ?DOMElement
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->tagName === $tag) {
                return $parent;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function headers(string $html): array
    {
        $headers = [];

        foreach ($this->xpath($html)->query('//table//thead//th') as $th) {
            $this->assertInstanceOf(DOMElement::class, $th);
            $headers[] = trim($th->textContent);
        }

        return $headers;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function page(string $state, string $route, array $query = []): string
    {
        [$user] = $this->area($state, $route);

        return $this->actingAs($user)->get(route($route, $query))->assertOk()->getContent();
    }

    /**
     * A signed-in user of the right role, and enough rows for the table to render.
     *
     * @return array{0: User}
     */
    private function area(string $state, string $route): array
    {
        $sector = Sector::first() ?? Sector::factory()->create();
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector->update(['kagawad_user_id' => $kagawad->id]);

        DocumentType::factory()->create();
        $resident = User::factory()->resident()->create();

        $profile = ResidentProfile::factory()->pending()->create([
            'user_id' => $resident->id, 'sector_id' => $sector->id,
        ]);
        $profile->forceFill(['created_at' => now()->subDay()])->save();

        $request = DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $batch = ReliefBatch::factory()->createdBy(
            User::factory()->secretary()->create(['is_verified' => true])
        )->create();
        ReliefDistribution::factory()->forSector($sector)->forBatch($batch)->create([
            'household_number' => $profile->household_number,
        ]);

        $announcement = Announcement::factory()->publishedAndPinned()->create();

        AuditLog::factory()->action('resident.verified')->create([
            'user_id' => $kagawad->id, 'description' => 'Someone was verified',
        ]);

        unset($request, $announcement);

        return [match ($state) {
            'admin' => User::factory()->admin()->create(['is_verified' => true]),
            'kagawad' => $kagawad,
            default => User::factory()->secretary()->create(['is_verified' => true]),
        }];
    }
}
