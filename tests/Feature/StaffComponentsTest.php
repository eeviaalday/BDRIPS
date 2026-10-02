<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The staff component set.
 *
 * Rendered in isolation, with the markup read back out of the compiled view rather
 * than compared against a hand-written copy of it. A test that greps the source for
 * a class name passes when the class is on an element nobody can reach.
 *
 * The alignment test is the one that matters most. The bug these components exist to
 * fix was that <th scope="row"> is centred by the browser's own default stylesheet,
 * which beats anything inherited from the header row - so the name in each row sat
 * centred under a left-aligned header while every other cell was left-aligned. A
 * source-level assertion cannot catch that; only the emitted rule can.
 */
class StaffComponentsTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // Table
    // ---------------------------------------------------------------------

    #[Test]
    public function the_table_renders_a_caption_a_header_and_a_body(): void
    {
        $html = $this->renderSomething('staff.table', [
            'columns' => [['label' => 'Resident'], ['label' => 'Status']],
            'caption' => 'Resident directory',
        ], '<tr><th scope="row">Ana Bautista</th><td>Pending</td></tr>');

        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//table')->length, 'The table did not render.');
        $this->assertSame('Resident directory', trim($xpath->query('//caption')->item(0)?->textContent ?? ''));
        $this->assertSame('Resident', trim($xpath->query('//thead//th')->item(0)?->textContent ?? ''));
        $this->assertSame('Ana Bautista', trim($xpath->query('//tbody//th')->item(0)?->textContent ?? ''));
    }

    /**
     * THE ALIGNMENT FIX.
     *
     * The class that forces the row header back to the left has to be on the tbody,
     * as a child variant, because that is what makes it an author rule ON the th and
     * therefore stronger than the browser's own `th { text-align: center }`.
     */
    #[Test]
    public function the_table_forces_the_row_header_to_the_left(): void
    {
        $html = $this->renderSomething('staff.table', [
            'columns' => [['label' => 'Resident']],
            'caption' => 'Directory',
        ], '<tr><th scope="row">Ana</th></tr>');

        $this->assertStringContainsString(
            '[&amp;_th]:text-left',
            $html,
            'The row header is not forced left, so the browser will centre the name in '
            .'every row while the header above it stays left-aligned.'
        );
    }

    #[Test]
    public function the_table_applies_cell_padding_from_one_place(): void
    {
        $html = $this->renderSomething('staff.table', ['columns' => [['label' => 'A']]], '<tr><td>x</td></tr>');

        // Callers should not have to repeat px-4 py-3, and if the component stops
        // supplying it every cell in every table loses its alignment at once.
        $this->assertStringContainsString('[&amp;_td]:px-4', $html);
        $this->assertStringContainsString('[&amp;_td]:py-3', $html);
        $this->assertStringContainsString('[&amp;_th]:px-4', $html);
    }

    #[Test]
    public function the_table_scrolls_sideways_on_a_narrow_screen(): void
    {
        $html = $this->renderSomething('staff.table', ['columns' => [['label' => 'A']]], '<tr><td>x</td></tr>');

        $this->assertStringContainsString('overflow-x-auto', $html);
        $this->assertStringContainsString('min-w-[46rem]', $html);
        $this->assertStringContainsString('role="region"', $html, 'The scroll wrapper is not focusable/labelled.');
    }

    #[Test]
    public function the_table_left_aligns_every_header_whatever_a_column_asks_for(): void
    {
        /*
         * The `align` column key is gone, and this is the test that replaces the one
         * which used to pin it.
         *
         * It passed an 'align' => 'right' column and asserted text-right on the
         * <th>, so it looked like the component had a working alignment prop. It
         * did - and the shared x-data-table it was built beside does not, because
         * that one reads a "class" key. So the same key was honoured in one
         * component and silently ignored in the other, which is how a numeric column
         * came out right-aligned on some tables and not on others while both looked
         * correct in isolation.
         *
         * Rather than assert the key is ignored, this passes it and requires the
         * header to be left anyway. A right-aligned column is now something a view
         * cannot ask for at all, so the mistake cannot come back quietly.
         */
        $html = $this->renderSomething('staff.table', [
            'columns' => [['label' => 'Households', 'align' => 'right']],
        ], '<tr><td class="tabular-nums">3</td></tr>');

        $headers = $this->nodes($html, '//thead//th');

        $this->assertNotEmpty($headers, 'No header rendered.');

        foreach ($headers as $header) {
            $this->assertStringContainsString('text-left', $header->getAttribute('class'));
            $this->assertStringNotContainsString(
                'text-right',
                $header->getAttribute('class'),
                'A header is right-aligned, so a column can still opt out of left.'
            );
        }
    }

    #[Test]
    public function the_table_falls_back_to_the_empty_state_when_there_are_no_rows(): void
    {
        $html = $this->renderSomething('staff.table', [
            'columns' => [['label' => 'Resident']],
            'emptyTitle' => 'No residents match these filters',
            'emptyDescription' => 'Try a different search.',
            'emptyIcon' => 'users',
        ], '');

        $this->assertSame(0, $this->xpath($html)->query('//table')->length, 'An empty table rendered.');
        $this->assertStringContainsString('No residents match these filters', $html);
        $this->assertStringContainsString('Try a different search.', $html);
    }

    #[Test]
    public function the_empty_state_offers_the_way_out_when_given_one(): void
    {
        $html = $this->renderSomething('staff.empty-state', [
            'title' => 'No residents match these filters',
            'description' => 'Try a different search.',
            'actionLabel' => 'Clear filters',
            'actionHref' => '/staff/residents',
        ]);

        $this->assertStringContainsString('Clear filters', $html);
        $this->assertStringContainsString('href="/staff/residents"', $html);
    }

    #[Test]
    public function the_empty_state_carries_no_action_when_none_is_given(): void
    {
        $html = $this->renderSomething('staff.empty-state', ['title' => 'Nothing here yet']);

        $this->assertSame(0, $this->xpath($html)->query('//a')->length, 'The empty state invented a link.');
    }

    // ---------------------------------------------------------------------
    // Status badge
    // ---------------------------------------------------------------------

    /**
     * Every state the staff area shows. If one is missing from the map it renders
     * as "Unknown", which is how a new state would be noticed.
     */
    #[Test]
    #[DataProvider('staffStatusProvider')]
    public function the_badge_renders_a_word_for_every_staff_status(string $status, string $expected): void
    {
        $html = $this->renderSomething('staff.status-badge', ['status' => $status, 'size' => 'sm']);

        $this->assertStringContainsString(
            $expected,
            $html,
            sprintf('The %s badge does not say what it means.', $status)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function staffStatusProvider(): array
    {
        return [
            // verification
            'verification pending' => ['pending', 'Pending'],
            'verification verified' => ['verified', 'Verified'],
            'verification rejected' => ['rejected', 'Rejected'],
            // requests
            'request submitted' => ['submitted', 'Submitted'],
            'request under review' => ['under_review', 'Under review'],
            'request ready' => ['ready_for_pickup', 'Ready for pickup'],
            'request collected' => ['claimed', 'Collected'],
            'request rejected' => ['rejected', 'Rejected'],
            // batches
            'batch active' => ['active', 'Active'],
            'batch closed' => ['closed', 'Closed'],
            // announcements
            'announcement draft' => ['draft', 'Draft'],
            'announcement scheduled' => ['scheduled', 'Scheduled'],
            'announcement published' => ['published', 'Published'],
            // accounts
            'account active' => ['active', 'Active'],
            'account restricted' => ['restricted', 'Restricted'],
        ];
    }

    /**
     * Colour is never the only cue: a dot plus a word, both present on every badge.
     */
    #[Test]
    public function the_badge_carries_a_word_and_a_dot_not_colour_alone(): void
    {
        $html = $this->renderSomething('staff.status-badge', ['status' => 'verified']);

        $this->assertStringContainsString('rounded-full', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html, 'The dot is announced as a character.');
        $this->assertStringContainsString('Verified', $html);
    }

    #[Test]
    public function the_badge_can_drop_the_dot_for_a_tag(): void
    {
        $html = $this->renderSomething('staff.status-badge', ['status' => 'neutral', 'label' => 'Rice', 'dot' => false]);

        $this->assertStringContainsString('Rice', $html);
        $this->assertStringNotContainsString('h-1.5 w-1.5', $html, 'The dot rendered despite dot=false.');
    }

    /**
     * A label override changes the wording, not the colour: "3 pending" is still
     * one state, with the same colour and the same dot.
     */
    #[Test]
    public function the_badge_accepts_a_label_without_inventing_a_state(): void
    {
        $html = $this->renderSomething('staff.status-badge', ['status' => 'active', 'label' => '3 pending']);

        $this->assertStringContainsString('3 pending', $html);
        $this->assertStringNotContainsString('>Active<', $html);
    }

    #[Test]
    public function an_unknown_status_is_visible_rather_than_invisible(): void
    {
        $html = $this->renderSomething('staff.status-badge', ['status' => 'something-new']);

        $this->assertStringContainsString('Unknown', $html);
    }

    // ---------------------------------------------------------------------
    // Person cell
    // ---------------------------------------------------------------------

    #[Test]
    public function the_person_cell_shows_initials_a_name_and_a_second_line(): void
    {
        $html = $this->renderSomething('staff.person-cell', [
            'name' => 'Ana Bautista',
            'secondary' => 'ana@example.test',
        ]);

        $this->assertStringContainsString('AB', $html, 'No initials.');
        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringContainsString('ana@example.test', $html);
    }

    #[Test]
    public function the_person_cell_takes_one_initial_from_each_of_two_names(): void
    {
        // The rule, not an accident of the test's own name.
        $this->assertStringContainsString('MA', $this->renderSomething('staff.person-cell', ['name' => 'Maria Angela Cruz']));
        $this->assertStringContainsString('C', $this->renderSomething('staff.person-cell', ['name' => 'Cruz']));
    }

    #[Test]
    public function the_person_cell_initials_are_hidden_from_a_screen_reader(): void
    {
        $html = $this->renderSomething('staff.person-cell', ['name' => 'Ana Bautista']);

        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    #[Test]
    public function the_person_cell_is_plain_text_and_never_a_link(): void
    {
        /*
         * The name is no longer a link, and the props that made it one are gone.
         *
         * The cell used to take an `href` and a `chevron`, and a <tr> around it was
         * clickable as well. That gave one record three ways in and no single one of
         * them named the record: the <tr> was not focusable, the chevron was
         * aria-hidden, and the link's text was the same string as the cell it sat in.
         *
         * A row is now opened by one control in the Actions column whose accessible
         * name says which record it opens. So this asserts the absence: there is no
         * way to make a person cell a link any more.
         */
        $html = $this->renderSomething('staff.person-cell', ['name' => 'Ana Bautista']);

        $this->assertStringContainsString('Ana Bautista', $html);
        $this->assertStringNotContainsString('<a href', $html, 'A person cell must not render a link.');

        // And the props are gone, so a caller passing them gets them ignored rather
        // than silently reintroducing a second way into the row.
        $withHref = $this->renderSomething('staff.person-cell', ['name' => 'Ana', 'href' => '/x']);

        // A stale caller passing href: it is no longer a declared prop, so it lands
        // on the wrapper span as an inert attribute. A span with an href is not a
        // link, does not focus, and does not navigate - which is the point. The
        // assertion is on the absence of an anchor rather than on the absence of
        // the string, because a span carrying href is ugly but harmless, and
        // pretending otherwise would be asserting on a string rather than on
        // behaviour.
        $this->assertStringNotContainsString('<a ', $withHref, 'A person cell must not render an anchor.');
        $this->assertStringNotContainsString('ml-auto', $withHref, 'The chevron is gone.');
    }

    #[Test]
    public function the_person_cell_still_renders_initials_and_the_second_line(): void
    {
        // The part that did not change, kept so the simplification above cannot
        // quietly take the initials or the secondary line with it.
        $html = $this->renderSomething('staff.person-cell', [
            'name' => 'Ana Bautista', 'secondary' => 'ana@example.test', 'mono' => true,
        ]);

        $this->assertStringContainsString('aria-hidden="true"', $html, 'The initials must stay decorative.');
        $this->assertStringContainsString('AB', $html);
        $this->assertStringContainsString('ana@example.test', $html);
        $this->assertStringContainsString('font-mono', $html);
    }

    // ---------------------------------------------------------------------
    // Stat card and row
    // ---------------------------------------------------------------------

    #[Test]
    public function the_stat_card_shows_a_figure_a_label_and_an_icon(): void
    {
        $html = $this->renderSomething('staff.stat-card', ['label' => 'Waiting', 'value' => 7, 'icon' => 'clock']);

        $this->assertStringContainsString('Waiting', $html);
        $this->assertStringContainsString('7', $html);
        $this->assertStringContainsString('<svg', $html, 'The icon did not render.');
    }

    #[Test]
    public function the_stat_card_renders_a_link_when_given_a_destination(): void
    {
        $html = $this->renderSomething('staff.stat-card', ['label' => 'Waiting', 'value' => 7, 'href' => '/staff/verifications']);

        $this->assertSame(1, $this->xpath($html)->query('//a')->length, 'The card is not a real link.');
    }

    #[Test]
    public function the_stat_card_shows_a_hint_when_given_one(): void
    {
        $html = $this->renderSomething('staff.stat-card', ['label' => 'Waiting', 'value' => 7, 'hint' => 'Needs a decision']);

        $this->assertStringContainsString('Needs a decision', $html);
    }

    #[Test]
    public function the_stat_row_is_two_across_on_a_phone_and_four_on_a_desktop(): void
    {
        $html = $this->renderSomething('staff.stat-row', [], '<div>card</div>');

        $this->assertStringContainsString('grid-cols-2', $html);
        $this->assertStringContainsString('md:grid-cols-4', $html);
    }

    // ---------------------------------------------------------------------
    // Filter bar
    // ---------------------------------------------------------------------

    #[Test]
    public function the_filter_bar_is_a_get_form(): void
    {
        $html = $this->renderSomething('staff.filter-bar', ['action' => '/staff/residents'], '');

        $this->assertStringContainsString('method="GET"', $html);
        $this->assertStringContainsString('action="/staff/residents"', $html);
    }

    #[Test]
    public function the_clear_link_appears_only_while_something_is_filtered(): void
    {
        $off = $this->renderSomething('staff.filter-bar', ['action' => '/x', 'hasFilters' => false, 'clearHref' => '/x']);
        $on = $this->renderSomething('staff.filter-bar', ['action' => '/x', 'hasFilters' => true, 'clearHref' => '/x']);

        $this->assertStringNotContainsString('Clear filters', $off, 'Clear is offered on an unfiltered list.');
        $this->assertStringContainsString('Clear filters', $on);
    }

    #[Test]
    public function the_filter_select_is_wide_enough_for_its_longest_option(): void
    {
        // "Awaiting a decision" is the longest option on the requests screen, and the
        // audit measured that select with exactly 0px to spare before this component
        // existed.
        $html = $this->renderSomething('staff.filter-select', [
            'name' => 'status',
            'label' => 'Show',
            'options' => ['queue' => 'Awaiting a decision', 'claimed' => 'Collected'],
        ]);

        preg_match('/min-width:\s*(\d+)ch/', $html, $matches);

        $this->assertNotEmpty($matches, 'The select has no width floor at all.');

        $this->assertGreaterThanOrEqual(
            mb_strlen('Awaiting a decision'),
            (int) $matches[1],
            'The select is narrower than its own longest option.'
        );
    }

    #[Test]
    public function the_filter_select_never_constrains_itself_to_something_too_narrow(): void
    {
        $html = $this->renderSomething('staff.filter-select', [
            'name' => 'role',
            'label' => 'Role',
            'options' => ['resident' => 'Resident'],
            'allLabel' => 'Every role',
        ]);

        // w-full was what made these collapse into the slack beside a search box.
        $this->assertStringNotContainsString('w-full', $html);
    }

    #[Test]
    public function the_filter_select_labels_values_through_the_mapper(): void
    {
        $html = $this->renderSomething('staff.filter-select', [
            'name' => 'role',
            'label' => 'Role',
            'options' => ['kagawad', 'resident'],
            'mapLabel' => fn (string $value): string => ucfirst($value),
        ]);

        $this->assertStringContainsString('>Kagawad<', $html);
        $this->assertStringNotContainsString('>kagawad<', $html);
    }

    #[Test]
    public function the_filter_select_keeps_the_current_value_selected(): void
    {
        $html = $this->renderSomething('staff.filter-select', [
            'name' => 'status',
            'label' => 'Show',
            'options' => ['queue' => 'Queue', 'claimed' => 'Collected'],
            'value' => 'claimed',
        ]);

        $this->assertMatchesRegularExpression(
            '/<option value="claimed"[^>]*selected/s',
            $html,
            'The current filter is not marked selected.'
        );
    }

    #[Test]
    public function the_filter_date_is_wide_enough_for_a_whole_date(): void
    {
        $html = $this->renderSomething('staff.filter-date', ['name' => 'from', 'label' => 'From']);

        $this->assertStringContainsString('type="date"', $html);
        $this->assertStringContainsString('min-w-[9.5rem]', $html, 'A date filter narrower than a whole date.');
    }

    // ---------------------------------------------------------------------
    // Pagination
    // ---------------------------------------------------------------------

    #[Test]
    public function the_pagination_footer_summarises_the_range(): void
    {
        $paginator = User::query()->paginate(2, ['*'], 'page', 3);

        $html = $this->renderSomething('staff.pagination', ['paginator' => $paginator]);

        $this->assertStringContainsString('Showing', $html);
        $this->assertStringContainsString('&ndash;', $html, 'No range separator.');
        $this->assertStringContainsString(number_format($paginator->total()), $html);
    }

    #[Test]
    public function the_pagination_footer_is_absent_when_there_is_nothing_to_page(): void
    {
        User::factory()->create();

        $html = $this->render('staff.pagination', ['paginator' => User::query()->paginate(20)]);

        $this->assertSame('', trim($html), 'A single page grew a footer reading Showing 1-1 of 1.');
    }

    // ---------------------------------------------------------------------
    // Applied to real pages
    // ---------------------------------------------------------------------

    /**
     * The components are only worth anything if the pages actually use them. These
     * are the six tables the audit found misaligned, checked for the thing that
     * made them misaligned.
     */
    #[Test]
    #[DataProvider('staffListPageProvider')]
    public function every_staff_list_page_uses_the_staff_table(string $path, callable $seed): void
    {
        $seed($this);

        $html = $this->actingAs(User::factory()->admin()->create(['is_verified' => true]))
            ->get(route($path))
            ->assertOk()
            ->getContent();

        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//table')->length, $path.' rendered no table.');
        $this->assertStringContainsString(
            '[&amp;_th]:text-left',
            $html,
            $path.' does not force its row headers left, so the name column is centred.'
        );
    }

    /**
     * @return array<string, array{0: string, 1: callable}>
     */
    public static function staffListPageProvider(): array
    {
        return [
            'residents' => ['staff.residents.index', function (self $t): void {
                $profile = ResidentProfile::factory()->create([
                    'sector_id' => Sector::factory()->create()->id,
                    'verification_status' => ResidentProfile::VERIFICATION_VERIFIED,
                ]);
            }],
            'verifications' => ['staff.verifications.index', function (self $t): void {
                ResidentProfile::factory()->create([
                    'sector_id' => Sector::factory()->create()->id,
                    'verification_status' => ResidentProfile::VERIFICATION_PENDING,
                ]);
            }],
            'requests' => ['staff.requests.index', function (self $t): void {
                DocumentRequest::factory()->create([
                    'document_type_id' => DocumentType::factory()->create()->id,
                    'status' => DocumentRequest::STATUS_SUBMITTED,
                ]);
            }],
            'batches' => ['staff.batches.index', function (self $t): void {
                ReliefBatch::factory()->create();
            }],
            'announcements' => ['staff.announcements.index', function (self $t): void {
                Announcement::factory()->create();
            }],
            'accounts' => ['staff.accounts.index', function (self $t): void {
                User::factory()->create();
            }],
        ];
    }

    #[Test]
    public function no_staff_page_hand_writes_a_table_a_badge_or_a_stat_card(): void
    {
        // The acceptance criterion, asserted rather than asserted-about-in-prose.
        $offenders = [];

        $paths = array_merge(
            glob(resource_path('views/staff/**/*.blade.php')) ?: [],
            glob(resource_path('views/staff/*.blade.php')) ?: [],
            glob(resource_path('views/kagawad/**/*.blade.php')) ?: [],
        );

        foreach (array_unique($paths) as $path) {
            $source = (string) file_get_contents($path);

            foreach (['<table', 'x-data-table', 'x-stat-card', 'x-status-badge', 'x-empty-state'] as $needle) {
                if (str_contains($source, $needle)) {
                    $offenders[] = str_replace(resource_path('views/'), '', $path).' -> '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, "A staff page defines its own: \n".implode("\n", $offenders));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * Render a component on its own and hand back its markup.
     *
     * Through Blade::render rather than View::make('components.…'), because the
     * component is used as <x-staff.table> and that is the form worth testing: the
     * tag, its attribute binding and its slot, exactly as a page writes them.
     *
     * @param  array<string, mixed>  $props
     */
    private function render(string $tag, array $props = [], string $slot = ''): string
    {
        $bindings = '';

        foreach (array_keys($props) as $key) {
            $bindings .= ' :'.$key.'="$'.$key.'"';
        }

        return (string) Blade::render('<x-'.$tag.$bindings.'>'.$slot.'</x-'.$tag.'>', $props);
    }

    /**
     * As render(), but insists the component produced something - for every case
     * except the one where rendering nothing is the correct answer.
     */
    private function renderSomething(string $tag, array $props = [], string $slot = ''): string
    {
        $html = $this->render($tag, $props, $slot);

        $this->assertNotSame('', trim($html), $tag.' rendered nothing.');

        return $html;
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        $document->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>');

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function nodes(string $html, string $expression): array
    {
        $found = [];

        foreach ($this->xpath($html)->query($expression) as $node) {
            if ($node instanceof DOMElement) {
                $found[] = $node;
            }
        }

        return $found;
    }
}
