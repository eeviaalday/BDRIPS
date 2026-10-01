<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The Pin and Unpin controls on the announcements list.
 *
 * Two things are being pinned down here, and they are not the same thing.
 *
 * The first is the control itself. It used to render an Unpin form on every row,
 * disabled it exactly when the notice was pinned, and post it to the unpin route.
 * So the single state in which unpinning was possible was the state in which the
 * button was dead, and an unpinned notice offered an enabled Unpin against
 * something that was not pinned. These tests read the rendered row rather than
 * the source, because the source could be correct and the row still wrong.
 *
 * The second is that Pin and Unpin reach the same places they always did. Pin is
 * a link, not a POST, and deliberately so: staff.announcements.pin validates
 * published_at as required and after:now, and the public page only reads pinned
 * notices that are also live, so a pin without a date is refused by design. A
 * POST from the list would fail that validation every single time. The date is
 * collected on the edit form, so that is where the link goes.
 */
class StaffAnnouncementPinControlTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // What the control says, per state
    // ---------------------------------------------------------------------

    #[Test]
    public function a_pinned_announcement_offers_an_enabled_unpin(): void
    {
        $announcement = Announcement::factory()->publishedAndPinned()->create(['title' => 'Row under test']);

        $button = $this->buttonLabelled($announcement, 'Unpin');

        $this->assertNotNull(
            $button,
            'A pinned announcement does not offer an Unpin control.'
        );

        $this->assertFalse(
            $button->hasAttribute('disabled'),
            'Unpin is disabled on a pinned announcement, which is the one state in '
            .'which unpinning is the action to take.'
        );

        $this->assertSame(
            'button',
            $button->tagName,
            'Unpin is not a real control.'
        );
    }

    #[Test]
    public function the_unpin_control_posts_to_the_unpin_route(): void
    {
        $announcement = Announcement::factory()->publishedAndPinned()->create(['title' => 'Row under test']);

        $unpin = $this->buttonLabelled($announcement, 'Unpin');

        $this->assertNotNull($unpin, 'A pinned announcement offers no Unpin control.');

        $form = $this->enclosingForm($unpin);

        $this->assertNotNull($form, 'The Unpin control is not inside a form.');

        $this->assertSame(
            route('staff.announcements.unpin', $announcement),
            $form->getAttribute('action'),
            'Unpin does not post to the unpin route.'
        );

        $this->assertSame('POST', $form->getAttribute('method'));
    }

    #[Test]
    public function an_unpinned_announcement_offers_pin_and_not_unpin(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Row under test',
            'pinned_at' => null,
        ]);

        $this->assertNull(
            $this->buttonLabelled($announcement, 'Unpin'),
            'An unpinned announcement offers Unpin, which would be refused.'
        );

        $link = $this->linkLabelled($announcement, 'Pin');

        $this->assertNotNull(
            $link,
            'An unpinned announcement does not offer a Pin control.'
        );

        $this->assertFalse(
            $link->hasAttribute('disabled'),
            'The Pin control is disabled.'
        );
    }

    /**
     * Pin is a link to the edit form rather than a POST, because the route
     * requires a publication date. Asserted so that a future "simplification"
     * into a bare POST cannot reintroduce a button that always fails validation.
     */
    #[Test]
    public function the_pin_control_points_at_the_pin_form_on_the_edit_page(): void
    {
        $announcement = Announcement::factory()->create([
            'title' => 'Row under test',
            'pinned_at' => null,
        ]);

        $link = $this->linkLabelled($announcement, 'Pin');

        $this->assertNotNull($link, 'The Pin control is missing.');

        $this->assertSame(
            route('staff.announcements.edit', $announcement).'#pin-and-publish',
            $link->getAttribute('href'),
            'Pin does not lead to the place a pin is actually set.'
        );
    }

    #[Test]
    public function neither_control_leaves_a_dead_disabled_button_on_the_page(): void
    {
        Announcement::factory()->publishedAndPinned()->create(['title' => 'Row one']);
        Announcement::factory()->create(['title' => 'Row two', 'pinned_at' => null]);

        $html = $this->index();

        // The previous markup disabled the button while pinned; nothing on this
        // page may reintroduce a disabled Pin or Unpin.
        $this->assertStringNotContainsString(
            'disabled:opacity-40',
            $html,
            'A disabled Pin or Unpin is back on the page.'
        );
    }

    // ---------------------------------------------------------------------
    // The transitions themselves
    // ---------------------------------------------------------------------

    #[Test]
    public function an_unpinned_announcement_can_be_pinned_and_the_row_flips(): void
    {
        $staff = $this->staff();
        $announcement = Announcement::factory()->create([
            'title' => 'Row under test',
            'pinned_at' => null,
        ]);

        $this->assertFalse($announcement->fresh()->isPinned());

        $this->actingAs($staff)
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => now()->addDay()->startOfMinute()->toDateTimeString(),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertTrue(
            $announcement->fresh()->isPinned(),
            'The announcement is not pinned after the pin action.'
        );

        // And the row now offers Unpin, still enabled.
        $unpin = $this->buttonLabelled($announcement, 'Unpin', $staff);

        $this->assertNotNull($unpin, 'The pinned row offers no Unpin.');
        $this->assertFalse($unpin->hasAttribute('disabled'), 'Unpin is disabled after pinning.');
        $this->assertNull(
            $this->linkLabelled($announcement, 'Pin', $staff),
            'The pinned row still offers Pin.'
        );
    }

    #[Test]
    public function a_pinned_announcement_can_be_unpinned_and_the_row_flips(): void
    {
        $staff = $this->staff();
        $announcement = Announcement::factory()->publishedAndPinned()->create(['title' => 'Row under test']);

        $this->assertTrue($announcement->fresh()->isPinned());

        $this->actingAs($staff)
            ->from(route('staff.announcements.index'))
            ->post(route('staff.announcements.unpin', $announcement))
            ->assertRedirect(route('staff.announcements.index'))
            ->assertSessionHas('success');

        $this->assertFalse(
            $announcement->fresh()->isPinned(),
            'The announcement is still pinned after the unpin action.'
        );

        $this->assertNotNull(
            $this->linkLabelled($announcement, 'Pin', $staff),
            'The unpinned row offers no Pin.'
        );
        $this->assertNull(
            $this->buttonLabelled($announcement, 'Unpin', $staff),
            'The unpinned row still offers Unpin.'
        );
    }

    /**
     * Unpinning lifts the notice off the top; it must not withdraw it. Asserted
     * here as well as in AnnouncementsTest because the control is what a Secretary
     * actually presses, and a control that quietly unpublished a notice would be
     * the worst version of this bug.
     */
    #[Test]
    public function unpinning_from_the_list_does_not_unpublish(): void
    {
        $staff = $this->staff();
        $announcement = Announcement::factory()->publishedAndPinned()->create(['title' => 'Row under test']);

        $this->actingAs($staff)->post(route('staff.announcements.unpin', $announcement));

        $announcement->refresh();

        $this->assertFalse($announcement->isPinned());
        $this->assertTrue(
            $announcement->isPublished(),
            'Unpinning from the list withdrew the notice.'
        );
    }

    /**
     * Unpinning something that is not pinned is still refused by the controller,
     * which is the rule the disabled button was standing in for. Now that the
     * button only renders where it works, the refusal is belt and braces rather
     * than the only thing standing between a Secretary and a confusing 409.
     */
    #[Test]
    public function unpinning_something_already_unpinned_is_still_refused(): void
    {
        $announcement = Announcement::factory()->create(['pinned_at' => null]);

        $this->actingAs($this->staff())
            ->post(route('staff.announcements.unpin', $announcement))
            ->assertStatus(409);
    }

    // ---------------------------------------------------------------------
    // Feedback
    // ---------------------------------------------------------------------

    #[Test]
    public function the_list_reports_a_successful_unpin(): void
    {
        $announcement = Announcement::factory()->publishedAndPinned()->create(['title' => 'Row under test']);

        $this->actingAs($this->staff())
            ->from(route('staff.announcements.index'))
            ->post(route('staff.announcements.unpin', $announcement));

        $this->assertStringContainsString(
            'Unpinned.',
            $this->index(),
            'The list did not report the unpin.'
        );
    }

    /**
     * pin() flashes "Scheduled and pinned." and returns to wherever it was posted
     * from, which for the Pin link is this form. The form rendered errors only, so
     * the message was flashed into a page that never showed it.
     */
    #[Test]
    public function the_edit_form_reports_a_successful_pin(): void
    {
        $announcement = Announcement::factory()->create(['pinned_at' => null]);

        $this->actingAs($this->staff())
            ->from(route('staff.announcements.edit', $announcement))
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => now()->addDay()->startOfMinute()->toDateTimeString(),
            ]);

        $this->assertStringContainsString(
            'Scheduled and pinned.',
            $this->editPage($announcement),
            'The edit form did not report the pin.'
        );
    }

    /**
     * A failed pin has to say so on the page the Pin link leads to, rather than
     * failing silently.
     *
     * A date in the past is used rather than no date at all, so this exercises the
     * message the form's own hint is written against ("A future date schedules
     * it") rather than the generic required-field wording.
     */
    #[Test]
    public function the_edit_form_reports_a_pin_that_was_refused(): void
    {
        $announcement = Announcement::factory()->create(['pinned_at' => null]);

        $this->actingAs($this->staff())
            ->from(route('staff.announcements.edit', $announcement))
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => now()->subDay()->toDateTimeString(),
            ]);

        $this->assertStringContainsString(
            'A scheduled notice has to be in the future.',
            $this->editPage($announcement),
            'The refused pin was not reported on the form.'
        );

        $this->assertFalse(
            $announcement->fresh()->isPinned(),
            'A refused pin still pinned the announcement.'
        );
    }

    /**
     * The anchor the Pin link targets has to exist, or the link lands at the top
     * of the edit page instead of on the pin control.
     */
    #[Test]
    public function the_edit_form_has_the_anchor_the_pin_link_targets(): void
    {
        $announcement = Announcement::factory()->create(['pinned_at' => null]);

        $this->assertStringContainsString(
            'id="pin-and-publish"',
            $this->editPage($announcement),
            'The edit form has no #pin-and-publish anchor for the Pin link.'
        );
    }

    // ---------------------------------------------------------------------
    // Authorization is unchanged
    // ---------------------------------------------------------------------

    #[Test]
    #[DataProvider('unauthorisedRoleProvider')]
    public function only_a_secretary_or_an_admin_may_pin_or_unpin(string $role): void
    {
        $announcement = Announcement::factory()->create(['pinned_at' => null]);

        $user = User::factory()->create(['role' => $role, 'is_verified' => true]);

        $this->actingAs($user)
            ->post(route('staff.announcements.pin', $announcement), [
                'published_at' => now()->addDay()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('staff.announcements.unpin', $announcement))
            ->assertForbidden();

        $this->assertFalse($announcement->fresh()->isPinned());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unauthorisedRoleProvider(): array
    {
        return [
            'kagawad' => ['kagawad'],
            'resident' => ['resident'],
        ];
    }

    #[Test]
    public function a_guest_may_not_reach_the_list(): void
    {
        $this->get(route('staff.announcements.index'))->assertRedirect(route('login'));
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function index(?User $as = null): string
    {
        return ($as ? $this->actingAs($as) : $this->actingAs($this->staff()))
            ->get(route('staff.announcements.index'))
            ->assertOk()
            ->getContent();
    }

    private function editPage(Announcement $announcement): string
    {
        return $this->actingAs($this->staff())
            ->get(route('staff.announcements.edit', $announcement))
            ->assertOk()
            ->getContent();
    }

    /**
     * The <tr> index (1-based, as XPath counts) of the row carrying this
     * announcement's title.
     *
     * Matched on the title rather than by position, because a test that grabs
     * "the first row" passes against a page where every row renders the same
     * control - which is exactly what the bug was.
     */
    private function rowIndexFor(Announcement $announcement, ?User $as = null): int
    {
        $xpath = $this->xpath($this->index($as));

        foreach ($xpath->query('//tbody/tr') as $position => $row) {
            if (str_contains($row->textContent, $announcement->title)) {
                return $position + 1;
            }
        }

        $this->fail(sprintf('No row for "%s" on the announcements list.', $announcement->title));
    }

    /**
     * Elements of one kind inside this announcement's row.
     *
     * Every query runs against a single parsed document, so the nodes returned
     * belong to the tree their ancestors live in and a parent walk finds the
     * enclosing <form>. Re-parsing a fragment per query would hand back nodes from
     * a different document, and every ancestor lookup after that would silently
     * find nothing.
     *
     * @return list<DOMElement>
     */
    private function inRow(Announcement $announcement, string $xpathExpression, ?User $as = null): array
    {
        $xpath = $this->xpath($this->index($as));

        $found = [];

        // The row path and the search path have to be concatenated INSIDE one set
        // of parentheses. Wrapping the row first and appending ".//button"
        // afterwards is not a legal XPath expression.
        $expression = sprintf('(//tbody/tr[%d]%s)', $this->rowIndexFor($announcement, $as), $xpathExpression);

        foreach ($xpath->query($expression) as $node) {
            if ($node instanceof DOMElement) {
                $found[] = $node;
            }
        }

        return $found;
    }

    /**
     * A <button> in this row whose visible text is exactly the label. Exact
     * rather than substring, so "Unpin" is not satisfied by a row that only
     * offers "Pin".
     */
    private function buttonLabelled(Announcement $announcement, string $label, ?User $as = null): ?DOMElement
    {
        foreach ($this->inRow($announcement, '//button', $as) as $button) {
            if ($this->visibleText($button) === $label) {
                return $button;
            }
        }

        return null;
    }

    private function linkLabelled(Announcement $announcement, string $label, ?User $as = null): ?DOMElement
    {
        foreach ($this->inRow($announcement, '//a', $as) as $link) {
            if ($this->visibleText($link) === $label) {
                return $link;
            }
        }

        return null;
    }

    private function enclosingForm(DOMElement $control): ?DOMElement
    {
        for ($node = $control->parentNode; $node !== null; $node = $node->parentNode) {
            if ($node instanceof DOMElement && $node->tagName === 'form') {
                return $node;
            }
        }

        return null;
    }

    /**
     * The label a reader sees.
     *
     * textContent rather than the direct text nodes, because the button component
     * wraps its slot in a <span> and several buttons here now go through it - so
     * reading only the direct children returned an empty string for every control
     * on the page and every label assertion passed vacuously against nothing.
     */
    private function visibleText(DOMElement $element): string
    {
        return trim(preg_replace('/\s+/', ' ', $element->textContent) ?? '');
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;

        // The page is a UTF-8 document with no XML declaration, so encoding has
        // to be supplied or the text comparisons in here start failing on any
        // multibyte character in a title.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML('<?xml encoding="UTF-8">'.$html);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    private function staff(): User
    {
        return User::factory()->secretary()->create(['is_verified' => true]);
    }
}
