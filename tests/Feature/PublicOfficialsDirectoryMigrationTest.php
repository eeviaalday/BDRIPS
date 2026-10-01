<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What is left of /directory, and what is deliberately gone.
 *
 * The public tracking page was folded into this page so that the officials, the
 * executive roles and the sector Kagawads all lived under one address. That is
 * still true of the address: /directory is a permanent redirect to
 * /about#officials, the anchor is there, and the scroll margin that keeps the
 * sticky navbar off the heading is still on the section.
 *
 * It is no longer true of the content. The executive roles, the public
 * information line and the sector Kagawads have been removed from this page, on
 * the grounds that the Barangay Officials and SK Council carousels already carry
 * the officials this barangay publishes. So this file now guards two opposite
 * things at once, which is worth saying plainly: the address and its anchor must
 * keep working, and the content that left must not creep back.
 *
 * The settings behind the removed blocks - punong_barangay, secretary_name and
 * emergency_hotline - are untouched and still editable in the admin area. Nothing
 * was deleted to take those sections off the page.
 */
class PublicOfficialsDirectoryMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_directory_address_permanently_redirects_to_the_officials_section(): void
    {
        $response = $this->get(route('public.directory'));

        $response->assertStatus(301);
        $response->assertRedirect(route('about').'#officials');
    }

    #[Test]
    public function the_officials_section_exists_and_is_a_section_element(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        preg_match('/<section[^>]*id="officials"[^>]*>/', $content, $tag);

        $this->assertNotEmpty($tag, 'The about page no longer renders an officials section.');
    }

    #[Test]
    public function the_officials_anchor_is_not_hidden_under_the_sticky_header(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        preg_match('/<section[^>]*id="officials"[^>]*>/', $content, $tag);

        $this->assertNotEmpty($tag, 'The about page no longer renders an officials section.');

        // Without a scroll offset the browser aligns the top of the section with
        // the top of the viewport and the sticky header covers its first row. The
        // offset has to sit on the very element the link targets, not on a child.
        $this->assertMatchesRegularExpression(
            '/class="[^"]*\bscroll-mt-\d+/',
            $tag[0],
            'The officials section needs a scroll margin so the sticky header does not cover it.',
        );
    }

    #[Test]
    public function the_header_and_the_footer_both_point_at_the_officials_section(): void
    {
        $expected = 'href="'.route('about').'#officials"';

        $content = $this->get(route('home'))->assertOk()->getContent();

        // Twice on every page: the desktop bar and the mobile menu, plus the
        // footer. Counting is left to the navigation tests; this pins the target.
        $this->assertStringContainsString($expected, $content);
        $this->assertStringNotContainsString('href="'.route('public.directory').'"', $content);
    }

    #[Test]
    public function the_homepage_quick_services_card_builds_the_fragment_into_its_link(): void
    {
        $link = collect(config('bdrpis.quick_services'))
            ->firstWhere('title', 'Barangay Officials');

        $this->assertNotNull($link, 'The quick services grid no longer offers the officials.');
        $this->assertSame('about', $link['route']);
        $this->assertSame('officials', $link['fragment'] ?? null);

        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'href="'.route('about').'#officials"',
            $content,
            'The quick services card does not build the fragment into its href.',
        );
    }

    #[Test]
    public function no_public_page_links_to_the_retired_address(): void
    {
        foreach (['home', 'services.index', 'about', 'announcements.index', 'contact'] as $name) {
            $content = $this->get(route($name))->assertOk()->getContent();

            $this->assertStringNotContainsString(
                'href="'.route('public.directory').'"',
                $content,
                "[{$name}] still links to the retired directory page.",
            );
        }
    }

    #[Test]
    public function the_officials_navigation_entry_is_not_marked_as_the_current_tab(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Officials is a shortcut to a section of this page, not a tab of its
        // own. Flagging it as current as well would put two entries in the
        // header claiming to be where you are.
        preg_match_all('/<a\s[^>]*aria-current="page"[^>]*>/', $content, $matches);

        foreach ($matches[0] as $link) {
            $this->assertStringContainsString('href="'.route('about').'"', $link);
        }
    }

    #[Test]
    public function the_standalone_directory_view_no_longer_exists(): void
    {
        // The content was moved, not copied. A leftover view would be dead code
        // that makes it look as though the page is still there.
        $this->assertFileDoesNotExist(resource_path('views/public/directory.blade.php'));
    }

    // ---------------------------------------------------------------------
    // What was taken off the page
    // ---------------------------------------------------------------------

    /**
     * The other half of the file's job.
     *
     * These blocks left the page deliberately, so nothing here is asserting that
     * information still exists - it is asserting that the removal holds, and that
     * removing it from the page did not quietly take the settings with it.
     */
    #[Test]
    public function the_executive_hotline_and_sector_blocks_are_not_on_the_page(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        foreach ([
            'executive-heading' => 'the Barangay Executive section',
            'kagawad-heading' => 'the Sector Kagawads section',
            'Barangay Executive' => 'the executive heading',
            'Sector Kagawads' => 'the Kagawad heading',
            'Emergency Hotline' => 'the public information line',
            'Public information line' => 'the public information line copy',
            // Not asserted by name: both titles are also positions in the
            // officials data the carousels read, so finding them here would be
            // finding the preserved sections, not a section coming back.
            'executive-heading' => 'the Barangay Executive section',
            'mt-14' => 'the top margin the removed sections carried',
            'Unassigned' => 'a sector with nobody assigned',
        ] as $needle => $what) {
            $this->assertStringNotContainsString(
                $needle,
                $content,
                "[{$what}] is back on the About page. It was removed on purpose."
            );
        }
    }

    /**
     * The sectors table is not read by this page any more, and no sector is named
     * anywhere on it - a resident reads a Kagawad's name from their own relief
     * card, not from a public roster.
     */
    #[Test]
    public function no_sector_or_kagawad_assignment_is_printed_on_the_about_page(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'SENTINEL SECTOR NAME']);
        $sector->update(['kagawad_user_id' => User::factory()->kagawad()->create([
            'name' => 'SENTINEL KAGAWAD NAME',
        ])->id]);

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringNotContainsString('SENTINEL SECTOR NAME', $content);
        $this->assertStringNotContainsString('SENTINEL KAGAWAD NAME', $content);
    }

    /**
     * The settings the removed blocks read are still in the table and still
     * editable. Taking a section off a page is not a reason to delete the data
     * behind it, and the admin Settings screen still manages every one.
     */
    #[Test]
    public function the_settings_behind_the_removed_blocks_are_untouched(): void
    {
        foreach (['punong_barangay', 'secretary_name', 'emergency_hotline'] as $key) {
            Setting::set($key, 'SENTINEL SETTING VALUE');

            $this->assertSame(
                'SENTINEL SETTING VALUE',
                Setting::get($key),
                "The [{$key}] setting is gone. Removing a section must not delete the data behind it."
            );
        }
    }

    /**
     * And the officials this page does publish still come from where they always
     * did, so the removal cannot be mistaken for the carousels going quiet.
     */
    #[Test]
    public function the_two_official_councils_are_still_the_page_officials(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        foreach (['barangay', 'sk'] as $group) {
            $people = config('bdrpis.officials.'.$group.'.people');

            $this->assertNotEmpty($people, "The [{$group}] council has no people configured.");

            foreach ($people as $person) {
                $this->assertStringContainsString(
                    e($person['name']),
                    $content,
                    "The [{$group}] council lost {$person['name']} when the other sections were removed."
                );
            }
        }
    }
}
