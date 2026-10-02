<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The sidebar's shell: three blocks, and the middle one is the scroller.
 *
 * F4 in docs/STAFF_UI_AUDIT.md. The `<aside>` is `fixed inset-y-0 h-[100dvh]` and
 * holds exactly three children — the logo, the `<nav>`, the user block. Only the
 * middle one scrolls, so the logo above and the user block below stay put.
 *
 * These assertions exist because that structure is invisible to every other test
 * here, and it is easy to break by accident. A single stray `</div>` after the
 * group loop closed the `<nav>` early: the user block stopped being a sibling of
 * the nav and became its child, the flex column then gave the nav the full
 * remaining height, and at 1280x500 the user block was pushed out of the viewport
 * entirely. Every test still passed, because each of them looked either at the
 * links inside the nav or at the text - never at whether the nav ended where the
 * sidebar said it did.
 */
class StaffSidebarShellTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function roleProvider(): array
    {
        return [
            'admin' => [User::ROLE_ADMIN, 'staff.dashboard'],
            'secretary' => [User::ROLE_SECRETARY, 'staff.dashboard'],
            'kagawad' => [User::ROLE_KAGAWAD, 'kagawad.dashboard'],
        ];
    }

    /**
     * The sidebar is the logo, the nav, and the user block — in that order, no more.
     *
     * Asserted as a list of tag names so an extra wrapper div fails. A fourth child
     * would mean something is being appended outside the scroller, which is how the
     * user block ended up scrolling away in the first place.
     */
    #[Test]
    #[DataProvider('roleProvider')]
    public function the_sidebar_is_exactly_the_logo_the_nav_and_the_user_block(string $role, string $home): void
    {
        $aside = $this->sidebar($role, $home);

        $this->assertSame(
            ['div', 'nav', 'div'],
            $this->childTags($aside),
            "[{$role}] sidebar should hold the logo, the nav, and the user block, and nothing else."
        );
    }

    /**
     * The nav is a direct child of the sidebar, not nested one level deeper.
     *
     * This is the assertion that would have caught the stray closing tag. Nesting
     * does not change what renders - the markup still looks right in a browser - but
     * it changes the flex column the sidebar is built from, and with it whether the
     * user block survives a short viewport.
     */
    #[Test]
    #[DataProvider('roleProvider')]
    public function the_navigating_surfac_is_a_direct_child_of_the_sidebar(string $role, string $home): void
    {
        $aside = $this->sidebar($role, $home);

        $navs = (new \DOMXPath($aside->ownerDocument))->query('./nav[@aria-label="Portal"]', $aside);

        $this->assertSame(
            1,
            $navs->length,
            "[{$role}] should have exactly one nav directly inside the sidebar, not one buried in a wrapper."
        );
    }

    /**
     * The nav scrolls; the sidebar as a whole does not.
     *
     * `min-h-0` is load-bearing and not decoration: a flex child defaults to
     * `min-height: auto`, which refuses to shrink below its content, so the nav
     * would push the user block out of the viewport instead of scrolling.
     */
    #[Test]
    #[DataProvider('roleProvider')]
    public function the_sidebar_is_pinned_and_only_its_navigating_middle_scrolls(string $role, string $home): void
    {
        $aside = $this->sidebar($role, $home);

        $this->assertStringContainsString('inset-y-0', $aside->getAttribute('class'));
        $this->assertStringContainsString('h-[100dvh]', $aside->getAttribute('class'));
        $this->assertStringContainsString('flex-col', $aside->getAttribute('class'));

        $nav = (new \DOMXPath($aside->ownerDocument))->query('./nav', $aside)->item(0);
        $this->assertInstanceOf(\DOMElement::class, $nav, "[{$role}] sidebar has no nav.");
        $class = $nav->getAttribute('class');

        $this->assertStringContainsString('overflow-y-auto', $class, "[{$role}] nav must be the scroller.");
        $this->assertStringContainsString('min-h-0', $class, "[{$role}] nav needs min-h-0 or it cannot shrink.");
        $this->assertStringContainsString('flex-1', $class, "[{$role}] nav must take the space between the logo and the user block.");

        // The logo and the user block must not scroll away with it.
        $elements = $this->childElements($aside);

        foreach ([0 => 'logo', 2 => 'user block'] as $index => $what) {
            $this->assertArrayHasKey($index, $elements, "[{$role}] sidebar is missing its {$what}.");

            $this->assertStringContainsString(
                'shrink-0',
                $elements[$index]->getAttribute('class'),
                "[{$role}] {$what} must not shrink, or the nav pushes it out of view."
            );
        }
    }

    /**
     * The main column is offset by the width of the sidebar.
     *
     * The document is the scroller, so this offset is the only thing keeping the
     * content clear of a `fixed` sidebar. Without it the two overlap.
     */
    #[Test]
    #[DataProvider('roleProvider')]
    public function the_main_column_is_offset_by_the_sidebar_width(string $role, string $home): void
    {
        $this->assertStringContainsString(
            'md:pl-64',
            $this->appLayoutClass(),
            'The app shell must pad the main column by the width of the fixed sidebar.'
        );
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * The sidebar's element children, in order.
     *
     * Filtered to elements because the blank lines between the blocks in the Blade
     * source are childNodes too: indexing raw childNodes lands on a DOMText and
     * fails with a type error instead of the assertion that was meant to run.
     *
     * @return array<int, \DOMElement>
     */
    private function childElements(\DOMElement $aside): array
    {
        $elements = [];

        foreach ($aside->childNodes as $node) {
            if ($node instanceof \DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * @return list<string>
     */
    private function childTags(\DOMElement $aside): array
    {
        return array_map(
            fn (\DOMElement $element): string => $element->tagName,
            array_values($this->childElements($aside)),
        );
    }

    private function sidebar(string $role, string $home): \DOMElement
    {
        $user = match ($role) {
            User::ROLE_ADMIN => User::factory()->admin()->create(['is_verified' => true]),
            User::ROLE_KAGAWAD => User::factory()->kagawad()->create(['is_verified' => true]),
            default => User::factory()->secretary()->create(['is_verified' => true]),
        };

        $html = $this->actingAs($user)->get(route($home))->assertOk()->getContent();

        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html);

        $aside = (new \DOMXPath($document))->query('//aside')->item(0);

        $this->assertNotNull($aside, "[{$role}] rendered no sidebar.");

        return $aside;
    }

    /**
     * The app shell's own class list, read from the source.
     *
     * A view's compiled path is a cache detail, so the file is located through the
     * view factory rather than hard-coded. The class is asserted rather than the
     * rendered offset, because the point is the declaration: `md:pl-64` is the only
     * thing keeping the content clear of a `fixed` sidebar, and it has to be in the
     * source for Tailwind to emit the rule at all.
     */
    private function appLayoutClass(): string
    {
        $path = app('view')->getFinder()->find('layouts.app');

        $this->assertIsString($path);

        $source = (string) file_get_contents($path);

        $this->assertNotSame('', trim($source));

        return $source;
    }
}
