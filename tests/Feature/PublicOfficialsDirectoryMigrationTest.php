<?php

namespace Tests\Feature;

use App\Models\Sector;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The standalone /directory page is gone. Its unique content - the executive
 * roles, the public information line and the live sector Kagawads - now lives in
 * a section of the About page, which is where the header, the footer and the
 * homepage send people.
 *
 * These tests exist so that the consolidation cannot quietly become deletion.
 * Each one pins content that used to be on the old page, and every one of them
 * reads the About page rather than trusting the old address to still be served.
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
    public function the_emergency_hotline_from_settings_is_shown(): void
    {
        Setting::factory()->withKey('emergency_hotline', '0917 000 0000')->create();

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Emergency Hotline', $content);
        $this->assertStringContainsString('0917 000 0000', $content);
        $this->assertStringContainsString('tel:09170000000', $content);
    }

    #[Test]
    public function the_executive_roles_from_settings_are_shown(): void
    {
        Setting::factory()->withKey('punong_barangay', 'Hon. Juan Dela Cruz')->create();
        Setting::factory()->withKey('secretary_name', 'Maria Santos')->create();

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Punong Barangay', $content);
        $this->assertStringContainsString('Hon. Juan Dela Cruz', $content);
        $this->assertStringContainsString('Barangay Secretary', $content);
        $this->assertStringContainsString('Maria Santos', $content);
    }

    #[Test]
    public function a_sector_kagawad_is_listed_with_their_purok(): void
    {
        $kagawad = User::factory()->kagawad()->create(['name' => 'Ana Reyes']);
        Sector::factory()->create([
            'sector_name' => 'Sector 3',
            'purok_name' => 'Purok 1',
            'kagawad_user_id' => $kagawad->id,
        ]);

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Ana Reyes', $content);
        $this->assertStringContainsString('Sector Kagawad', $content);
        $this->assertStringContainsString('Sector 3', $content);
        $this->assertStringContainsString('Purok 1', $content);
    }

    #[Test]
    public function a_sector_with_nobody_assigned_says_so_rather_than_rendering_blank(): void
    {
        Sector::factory()->create([
            'sector_name' => 'Sector 9',
            'purok_name' => 'Purok 4',
            'kagawad_user_id' => null,
        ]);

        $content = $this->get(route('about'))->assertOk()->getContent();

        // A silently missing sector is indistinguishable from a rendering bug.
        // An unstaffed one has to declare itself on the page.
        $this->assertStringContainsString('Sector 9', $content);
        $this->assertStringContainsString('Vacant', $content);
        $this->assertStringContainsString('Unassigned', $content);
    }

    #[Test]
    public function sectors_are_listed_from_the_database_not_from_a_template(): void
    {
        // The whole point of reading them live is that the admin area can change
        // an assignment without anyone editing a page. A sector that exists only
        // in the database has to reach the public page.
        Sector::factory()->create(['sector_name' => 'Sector 11', 'purok_name' => 'Purok 7']);

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Sector 11', $content);
    }

    #[Test]
    public function an_unpublished_sector_list_explains_itself(): void
    {
        // The test database ships with sectors already in it, so the empty branch
        // only opens up once they are gone. This runs inside RefreshDatabase's
        // transaction, so the deletion is rolled back with the rest of the test
        // and never reaches the seeded data.
        Sector::query()->delete();

        $this->assertSame(0, Sector::count(), 'Could not clear the sectors to reach the empty branch.');

        $content = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Sector directory is being established', $content);
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
}
