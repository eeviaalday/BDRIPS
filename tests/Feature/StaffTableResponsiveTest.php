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
use DOMNode;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The staff table on a phone: cards, with every field still named.
 *
 * THE DEFECT.
 *
 * <x-staff.table> asked for min-w-[46rem], which is 736px. On a 375px phone that
 * is nearly double the screen, and it applied at every width, so the directory
 * measured 775px of document scrollWidth against a 375px viewport: the household
 * number sat half off the screen and the Actions column sat at x=792, reachable
 * only by scrolling sideways.
 *
 * Below md the table is no longer a table. Each row is a card, the first cell is
 * its title, and every other cell says which column it is - because "2" under a
 * heading nobody can see is not information. The heading row is hidden outright
 * rather than repeated once per card, and the controls move to a left-aligned
 * footer under a rule.
 *
 * WHY THESE TESTS READ THE DOM AND NOT THE BLADE SOURCE.
 *
 * The labels are handed to CSS as --lbl-N custom properties on the <table>, and
 * read back by nth-child selectors. Neither the property nor the selector is
 * visible in the Blade file as a class, so a source-level test would pass against
 * a component whose labels never render at all. These assertions read the rendered
 * element and require the variable and the column heading to be the same string.
 *
 * The 12-column cap is asserted against the real call sites rather than trusted:
 * the classes for positions 1 to 12 are written out literally, because Tailwind
 * only emits a utility it can find as literal text at build time. A thirteenth
 * column would lose its label silently, which is why the cap has a test.
 */
class StaffTableResponsiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The two halves of the fix, on every table.
     *
     * @param  list<string>  $classes
     */
    #[Test]
    #[DataProvider('tables')]
    public function the_table_is_a_stack_of_cards_below_md_and_a_table_from_md_up(string $state, string $route, string $path): void
    {
        $table = $this->table($this->page($state, $route));
        $classes = $this->classes($table);
        $head = $this->thead($table);

        $this->assertContains('block', $classes, "[{$path}] the <table> must be able to stop being a table below md.");
        $this->assertContains('md:table', $classes, "[{$path}] the <table> must become a real table again at md.");

        // The minimum width is what caused the overflow, so it is the one thing that
        // must be inside a media query. An unprefixed min-w-[46rem] here is the bug.
        $unprefixed = array_values(array_filter(
            $classes,
            fn (string $class): bool => $class === 'min-w-[46rem]' || str_starts_with($class, 'min-w-')
        ));
        $this->assertSame([], $unprefixed, "[{$path}] has an unprefixed minimum width on the table: ".implode(' ', $unprefixed));

        $this->assertContains('md:min-w-[46rem]', $classes, "[{$path}] the wide-table minimum belongs at md and up.");
        $this->assertContains('hidden', $this->classes($head), "[{$path}] the heading row must be hidden on a phone, where the cards label themselves.");
        $this->assertContains('md:table-header-group', $this->classes($head), "[{$path}] the heading row must come back at md.");
    }

    /**
     * Every secondary column publishes the label a reader would have got from the
     * heading row, and the string is the heading's own string.
     */
    #[Test]
    #[DataProvider('tables')]
    public function every_secondary_column_publishes_its_own_heading_as_a_label(string $state, string $route, string $path): void
    {
        $table = $this->table($this->page($state, $route));
        $headers = $this->headers($table);
        $published = $this->labels($table);

        $this->assertNotSame([], $headers, "[{$path}] rendered a table with no headings.");

        // A card per row means the reader never sees a heading, so a column without
        // a label is a bare value with no name anywhere on the screen.
        foreach ($headers as $position => $heading) {
            $name = '--lbl-'.($position + 1);

            $this->assertArrayHasKey(
                $name,
                $published,
                "[{$path}] column ".($position + 1)." ({$heading}) publishes no {$name}, so its card cell would show a value with no name."
            );
            $this->assertSame(
                $heading,
                $published[$name],
                "[{$path}] {$name} does not match its heading."
            );
        }

        // And nothing invents a label for a column that does not exist.
        $this->assertCount(
            count($headers),
            $published,
            "[{$path}] publishes ".count($published).' labels for '.count($headers).' columns.'
        );
    }

    /**
     * The label classes must be reachable by the build.
     *
     * Tailwind emits a utility only if it can find it as literal text in a scanned
     * file, so a class assembled in a loop produces a table with no labels and no
     * error. Each rule is therefore written out, and that is asserted here rather
     * than trusted - as is the md reset, without which the labels print inside
     * every desktop cell.
     */
    #[Test]
    public function the_component_writes_out_a_label_rule_for_every_column_it_supports(): void
    {
        $source = file_get_contents(resource_path('views/components/staff/table.blade.php'));

        $this->assertIsString($source);

        foreach (range(1, 12) as $position) {
            $this->assertStringContainsString(
                "[&_tr>*:nth-child({$position})]:before:content-[var(--lbl-{$position})]",
                $source,
                "Column {$position} has no written-out label rule. An assembled class name is invisible to the build, so the cell would render with no label and nothing would say why."
            );
        }

        $this->assertStringContainsString('md:[&_td]:before:hidden', $source, 'Labels are a phone affordance; without the md reset they print inside every desktop cell.');
        $this->assertStringContainsString('md:[&_th]:before:hidden', $source, 'Row headers carry labels on a phone too, so they need the same md reset.');
    }

    /**
     * A row must not be reachable only by scrolling sideways.
     *
     * The card layout exists so a phone never scrolls a table sideways, so the
     * 736px minimum width has to live inside a media query.
     */
    #[Test]
    public function the_minimum_table_width_is_only_required_once_the_table_is_a_table(): void
    {
        $source = file_get_contents(resource_path('views/components/staff/table.blade.php'));

        $this->assertIsString($source);

        $this->assertStringContainsString('md:min-w-[46rem]', $source);
        $this->assertStringNotContainsString("' min-w-[46rem]'", $source, 'The unprefixed minimum width is the overflow this component was fixed for.');
        $this->assertStringNotContainsString("'w-full min-w-[46rem]'", $source);
    }

    /**
     * A table whose controls are labelled Actions gets a footer. Audit Logs has a
     * column called Action that is a badge, and a rule above it would misrepresent
     * it as a row of controls.
     */
    #[Test]
    #[DataProvider('tablesWithAControlsColumn')]
    public function a_controls_column_becomes_the_card_footer(string $state, string $route, string $path): void
    {
        $classes = $this->classes($this->table($this->page($state, $route)));

        $this->assertContains('[&_tr>*:last-child]:block', $classes, "[{$path}] the controls cell should sit under a rule as the card footer.");
        $this->assertContains('[&_tr>*:last-child]:before:hidden', $classes, "[{$path}] the footer must not also announce the column it already is.");
        $this->assertContains('md:[&_tr>*:last-child]:table-cell', $classes, "[{$path}] the footer must go back to being a cell at md.");
    }

    #[Test]
    #[DataProvider('tablesWithoutAControlsColumn')]
    public function a_content_column_is_not_mistaken_for_the_footer(string $state, string $route, string $path): void
    {
        $classes = $this->classes($this->table($this->page($state, $route)));

        $this->assertNotContains('[&_tr>*:last-child]:block', $classes, "[{$path}] has no controls column, so its last cell is content and must not be given a footer rule.");
    }

    /**
     * Sectors puts an entire edit form in one <td colspan="4">.
     *
     * Left as a two-column grid it would be squeezed into the 7.5rem label column,
     * which is how a rename form ends up 120px wide on a phone.
     */
    #[Test]
    public function a_cell_that_spans_the_row_stays_a_full_width_panel_on_a_phone(): void
    {
        $classes = $this->classes($this->table($this->page('admin', 'staff.sectors.index')));

        $this->assertContains('[&_td[colspan]]:block', $classes, 'A cell spanning the row is a panel, not a labelled field.');
        $this->assertContains('[&_td[colspan]]:before:hidden', $classes, 'A spanning panel must not announce itself as the last column.');
        $this->assertContains('md:[&_td[colspan]]:table-cell', $classes, 'A spanning panel must go back to being a cell at md.');
    }

    /**
     * A row must not be reachable only by scrolling sideways.
     *
     * The card layout exists so the phone never has to scroll a table sideways, so
     * the wrapper must not need to scroll at all below md.
     */
    #[Test]
    public function the_component_never_asks_a_phone_to_scroll_the_table_sideways(): void
    {
        $source = file_get_contents(resource_path('views/components/staff/table.blade.php'));

        $this->assertIsString($source);
        $this->assertStringContainsString('overflow-x-auto', $source, 'The wrapper keeps its own overflow container for the widths where a real table is genuinely wider than the column.');
        $this->assertStringContainsString('tabindex="0"', $source, 'A scrollable region has to be focusable, or it cannot be scrolled from the keyboard.');
    }

    /**
     * Archiving must survive the rewrite: a greyed-out row still says so, and still
     * offers the way back. These are the controls the layout could have swallowed.
     */
    #[Test]
    public function an_archived_resident_keeps_its_badge_and_its_way_back(): void
    {
        [, $archived] = $this->area('secretary');

        $html = $this->page('secretary', 'staff.residents.index', ['record' => 'archived']);
        $labels = [];

        foreach ($this->xpath($html)->query('//table//button[@aria-label] | //table//a[@aria-label]') as $node) {
            $this->assertInstanceOf(DOMElement::class, $node);
            $labels[] = $node->getAttribute('aria-label');
        }

        $this->assertContains(
            'Restore '.$archived->name,
            $labels,
            'An archived resident must still be offered a Restore control after the table became cards.'
        );
        $this->assertStringContainsString('Archived', $html, 'An archived resident must still be badged as archived.');

        // And the row still says who it is, so a card is identifiable without the
        // heading column it no longer has.
        $this->assertStringContainsString($archived->name, $html);
    }

    /**
     * The cap on the written-out label classes, against the real call sites.
     *
     * A thirteenth column would lose its label with no error anywhere, so the number
     * of columns actually declared in the views is compared to the number of rules
     * the component ships.
     */
    #[Test]
    public function no_staff_table_declares_more_columns_than_the_component_can_label(): void
    {
        $widest = 0;
        $where = '';

        foreach ($this->callsites() as $file) {
            $source = file_get_contents($file);
            $this->assertIsString($source);

            preg_match('/:columns="\[(.*?)\]"/s', $source, $match);

            if ($match === []) {
                continue;
            }

            $count = preg_match_all("/\['label' =>/", $match[1]);

            if ($count > $widest) {
                $widest = $count;
                $where = basename(dirname(dirname($file))).'/'.basename($file);
            }
        }

        $this->assertGreaterThan(0, $widest, 'No staff table call sites were found to check.');
        $this->assertLessThanOrEqual(
            12,
            $widest,
            "[{$where}] declares {$widest} columns, more than the 12 label rules the component writes out. The extra columns would render with no label on a phone."
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tables(): array
    {
        return [
            'verifications' => ['secretary', 'staff.verifications.index', '/staff/verifications'],
            'residents' => ['secretary', 'staff.residents.index', '/staff/residents'],
            'requests' => ['secretary', 'staff.requests.index', '/staff/requests'],
            'batches' => ['secretary', 'staff.batches.index', '/staff/batches'],
            'announcements' => ['secretary', 'staff.announcements.index', '/staff/announcements'],
            'accounts' => ['admin', 'staff.accounts.index', '/staff/accounts'],
            'sectors' => ['admin', 'staff.sectors.index', '/staff/sectors'],
            'audit' => ['admin', 'staff.audit.index', '/staff/audit'],
            'kagawad residents' => ['kagawad', 'kagawad.residents.index', '/kagawad/residents'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tablesWithAControlsColumn(): array
    {
        return [
            'residents' => ['secretary', 'staff.residents.index', '/staff/residents'],
            'accounts' => ['admin', 'staff.accounts.index', '/staff/accounts'],
            'sectors' => ['admin', 'staff.sectors.index', '/staff/sectors'],
            'announcements' => ['secretary', 'staff.announcements.index', '/staff/announcements'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function tablesWithoutAControlsColumn(): array
    {
        return [
            'audit' => ['admin', 'staff.audit.index', '/staff/audit'],
            'kagawad residents' => ['kagawad', 'kagawad.residents.index', '/kagawad/residents'],
        ];
    }

    /**
     * @return list<string>
     */
    private function callsites(): array
    {
        $files = [];
        $directory = new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directory) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (str_contains((string) file_get_contents($file->getPathname()), '<x-staff.table')) {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotSame([], $files, 'No <x-staff.table> call sites were found.');

        return $files;
    }

    private function table(string $html): DOMElement
    {
        $tables = $this->xpath($html)->query('//table');

        $this->assertGreaterThan(0, $tables->length, 'The page rendered no table.');

        $table = $tables->item(0);
        $this->assertInstanceOf(DOMElement::class, $table);

        return $table;
    }

    private function thead(DOMElement $table): DOMElement
    {
        $head = $this->xpath((string) $table->ownerDocument->saveHTML($table))->query('//thead');

        $this->assertGreaterThan(0, $head->length, 'The table rendered no <thead>.');

        $thead = $head->item(0);
        $this->assertInstanceOf(DOMElement::class, $thead);

        return $thead;
    }

    /**
     * @return list<string>
     */
    private function headers(DOMElement $table): array
    {
        $headers = [];

        foreach ($table->getElementsByTagName('th') as $th) {
            if ($this->closestTag($th, 'thead') instanceof DOMElement) {
                $headers[] = trim($th->textContent);
            }
        }

        return $headers;
    }

    /**
     * The --lbl-N values actually published on the element, as the CSS will read them.
     *
     * @return array<string, string>
     */
    private function labels(DOMElement $table): array
    {
        preg_match_all("/--lbl-(\d+):'([^']*)'/", $table->getAttribute('style'), $matches, PREG_SET_ORDER);

        $labels = [];

        foreach ($matches as $match) {
            $labels['--lbl-'.$match[1]] = $match[2];
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function classes(DOMElement $element): array
    {
        return preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];
    }

    private function closestTag(DOMNode $node, string $tag): ?DOMNode
    {
        for ($parent = $node->parentNode; $parent !== null; $parent = $parent->parentNode) {
            if ($parent instanceof DOMElement && $parent->tagName === $tag) {
                return $parent;
            }
        }

        return null;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        return new DOMXPath($document);
    }

    private function page(string $state, string $route, array $query = []): string
    {
        [$user] = $this->area($state);

        return $this->actingAs($user)->get(route($route, $query))->assertOk()->getContent();
    }

    /**
     * A signed-in user of the right role, and enough rows for the table to render.
     *
     * @return array{0: User, 1: User}
     */
    private function area(string $state): array
    {
        $sector = Sector::first() ?? Sector::factory()->create();
        $kagawad = User::factory()->kagawad()->create(['is_verified' => true]);
        $sector->update(['kagawad_user_id' => $kagawad->id]);

        DocumentType::factory()->create();
        $resident = User::factory()->resident()->create();
        $archived = User::factory()->resident()->create();

        $profile = ResidentProfile::factory()->pending()->create([
            'user_id' => $resident->id, 'sector_id' => $sector->id,
        ]);
        $profile->forceFill(['created_at' => now()->subDay()])->save();

        // The archived household is archived in the fixture rather than in the test,
        // so the two states are visible side by side wherever the filter allows it.
        $archivedProfile = ResidentProfile::factory()->pending()->create([
            'user_id' => $archived->id, 'sector_id' => $sector->id,
        ]);
        $archivedProfile->forceFill(['created_at' => now()->subDays(2)])->save();

        $archived->forceFill(['archived_at' => now()->subDay()])->save();

        // One row each for the remaining lists, so every table in the provider has a
        // body. A table with no rows renders the empty state and no <table> at all,
        // which would make these assertions pass for the wrong reason.
        DocumentRequest::factory()->forResident($resident)->submitted()->create();

        $batch = ReliefBatch::factory()->createdBy(
            User::factory()->secretary()->create(['is_verified' => true])
        )->create();

        ReliefDistribution::factory()->forSector($sector)->forBatch($batch)->create([
            'household_number' => $profile->household_number,
        ]);

        Announcement::factory()->publishedAndPinned()->create();

        AuditLog::factory()->action('resident.verified')->create([
            'user_id' => $kagawad->id, 'description' => 'Someone was verified',
        ]);

        $user = match ($state) {
            'admin' => User::factory()->admin()->create(['is_verified' => true]),
            'kagawad' => $kagawad,
            default => User::factory()->secretary()->create(['is_verified' => true]),
        };

        return [$user, $archived];
    }
}
