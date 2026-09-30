<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicMultiPageNavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The standalone public pages and the header link that reaches them.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pageProvider(): array
    {
        return [
            'services' => ['services.index', '/services', 'Supported Document Services'],
            'about' => ['about', '/about', 'About Our Barangay'],
            'announcements' => ['announcements.index', '/announcements', 'Community Announcements'],
            'contact' => ['contact', '/contact', 'Contact Us'],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function activeStateProvider(): array
    {
        return [
            'services' => ['services.index', 'Services'],
            'about' => ['about', 'About'],
            'announcements' => ['announcements.index', 'Announcements'],
            'contact' => ['contact', 'Contact'],
        ];
    }

    public function test_the_named_public_routes_are_registered(): void
    {
        foreach (['home', 'services.index', 'about', 'announcements.index', 'contact'] as $name) {
            $this->assertNotNull(
                route($name, absolute: false),
                "Route [{$name}] is not registered.",
            );
        }
    }

    public function test_the_header_renders_every_primary_link_as_a_route(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (['Home', 'Services', 'About', 'Announcements', 'Contact'] as $label) {
            $this->assertMatchesRegularExpression(
                '/>\s*'.preg_quote($label, '/').'\s*</',
                $content,
                "Header link [{$label}] is missing.",
            );
        }
    }

    public function test_the_header_no_longer_renders_the_map_link(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/>\s*Map\s*</',
            $content,
            'The Map navigation link should have been removed.',
        );
    }

    public function test_the_header_uses_no_in_page_section_anchors(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The single-page scroll is gone, so no primary destination should be
        // reachable only as a fragment of the homepage.
        $this->assertStringNotContainsString(route('home').'#services', $content);
        $this->assertStringNotContainsString(route('home').'#about', $content);
        $this->assertStringNotContainsString(route('home').'#contact', $content);
    }

    #[DataProvider('pageProvider')]
    public function test_each_public_page_responds_and_renders_its_own_banner(
        string $routeName,
        string $uri,
        string $bannerTitle,
    ): void {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee($bannerTitle);
    }

    #[DataProvider('pageProvider')]
    public function test_each_public_page_is_addressable_at_its_own_uri(
        string $routeName,
        string $uri,
    ): void {
        $this->get($uri)->assertOk();
    }

    #[DataProvider('activeStateProvider')]
    public function test_the_header_marks_the_current_page_as_active(
        string $routeName,
        string $label,
    ): void {
        $content = $this->get(route($routeName))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*aria-current="page"[^>]*>\s*'.preg_quote($label, '/').'\s*</',
            $content,
            "Header link [{$label}] is not marked active on the [{$routeName}] page.",
        );
    }

    public function test_only_the_current_header_tab_is_marked_active(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        // Each primary link appears twice: once in the desktop bar and once in the
        // mobile menu. Only the About pair should be flagged.
        $this->assertSame(
            2,
            preg_match_all('/<a\s[^>]*aria-current="page"[^>]*>/', $content),
            'Expected exactly the two About links (desktop and mobile) to be active.',
        );

        preg_match_all('/<a\s[^>]*aria-current="page"[^>]*>/', $content, $matches);

        $this->assertStringContainsString(
            'href="'.route('about').'"',
            $matches[0][0],
            'The active link should point at the current page route.',
        );
    }

    public function test_the_homepage_keeps_only_the_hero_services_and_announcements(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('id="hero"', $content);
        $this->assertStringContainsString('id="services"', $content);
        $this->assertStringContainsString('id="announcements"', $content);

        // The sections that moved to their own pages must be gone from home.
        foreach (['about', 'how-it-works', 'community', 'officials', 'transparency', 'contact'] as $anchor) {
            $this->assertStringNotContainsString(
                'id="'.$anchor.'"',
                $content,
                "Homepage still renders the [{$anchor}] section.",
            );
        }
    }

    public function test_the_homepage_links_out_to_the_standalone_pages(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (['services.index', 'about', 'announcements.index', 'contact'] as $name) {
            $this->assertStringContainsString(route($name), $content);
        }
    }

    public function test_the_services_page_carries_the_relocated_walkthrough(): void
    {
        $content = $this->get(route('services.index'))->assertOk()->getContent();

        $this->assertStringContainsString('id="how-it-works"', $content);
        $this->assertStringContainsString('Five Simple Steps', $content);

        foreach (config('bdrpis.steps') as $step) {
            // Compare against the escaped title: "&" renders as "&amp;" in the markup.
            $this->assertStringContainsString(e($step['title']), $content);
        }

        // It must not be duplicated back onto the homepage.
        $this->assertStringNotContainsString('id="how-it-works"', $this->get(route('home'))->getContent());
    }

    public function test_the_about_page_carries_the_history_and_community_content(): void
    {
        $content = $this->get(route('about'))->assertOk()->getContent();

        foreach (['history', 'vision', 'mission'] as $key) {
            $block = config('bdrpis.about.'.$key);

            $this->assertStringContainsString(e($block['title']), $content);
            $this->assertStringContainsString(e($block['body']), $content);
        }

        $this->assertStringContainsString('Community Activities', $content);

        foreach (config('bdrpis.activities') as $activity) {
            $this->assertStringContainsString(e($activity['title']), $content);
        }

        // The about page embeds the two official councils itself, in carousels.
        // It used to hand off to the directory with a button, and that link is
        // gone: the directory is still its own page, reachable from the header
        // and the footer, but a reader no longer has to leave /about to meet
        // their officials. Scoped to the page body, because the site header and
        // footer both link to the directory on purpose.
        preg_match('/<main id="main-content">(.*)<\/main>/s', $content, $body);

        $this->assertStringNotContainsString('View the full official directory', $body[1] ?? $content);
        $this->assertStringNotContainsString(route('public.directory'), $body[1] ?? $content);
    }

    public function test_the_contact_page_renders_three_panels_of_contact_information(): void
    {
        $content = $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringContainsString('mailto:', $content);
        $this->assertStringContainsString('tel:', $content);

        // The three panels, in order: how to reach the hall, what to bring, and
        // the relief lookup.
        $this->assertStringContainsString('Contact the Barangay Hall', $content);
        $this->assertStringContainsString('Before you visit', $content);
        $this->assertStringContainsString('Verify Relief Records', $content);

        // Side by side on a wide screen, stacked on a phone.
        $this->assertStringContainsString('grid grid-cols-1 items-stretch gap-6 lg:grid-cols-3', $content);
    }

    public function test_the_contact_page_gives_the_relief_panel_one_way_in_rather_than_a_form(): void
    {
        $content = $this->get(route('contact'))->assertOk()->getContent();

        // There is no household lookup to submit, on this page or any other, so
        // the panel is the single way in to the relief information. A second copy
        // of a form here would drift from the board.
        $this->assertStringNotContainsString('name="household_number"', $content);
        $this->assertStringNotContainsString('transparency/search', $content);
        $this->assertStringContainsString(route('transparency.index'), $content);
    }

    public function test_the_contact_page_sends_a_guest_through_to_the_login_screen(): void
    {
        $content = $this->get(route('contact'))->assertOk()->getContent();

        // Both actions lead behind a sign-in, and a guest has to be offered the
        // way in rather than a link to a page that will bounce them.
        $this->assertStringContainsString('href="'.route('login').'"', $content);
        $this->assertStringContainsString('Track a Request', $content);
        $this->assertStringContainsString('Log In to Verify Status', $content);

        // A signed-in resident is sent to the pages themselves.
        $signedIn = $this->actingAs(User::factory()->resident()->create())
            ->get(route('contact'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('href="'.route('requests.track').'"', $signedIn);
        $this->assertStringContainsString('href="'.route('transparency.index').'"', $signedIn);
        $this->assertStringContainsString('Check Status', $signedIn);
    }

    public function test_the_contact_page_no_longer_offers_a_second_services_link(): void
    {
        $content = $this->get(route('contact'))->assertOk()->getContent();

        // The before-you-visit panel keeps one action. The old pair repeated the
        // header's own Services link and gave the card two weighted exits.
        $this->assertStringNotContainsString('Document Services', $content);
    }

    public function test_the_household_lookup_is_only_reachable_behind_a_sign_in(): void
    {
        // There is no public household-number search at all, which is a stronger
        // answer than the one this test used to assert. A public endpoint that
        // said "is HH-0142 on a relief list" confirms which household numbers are
        // real, and household numbers are short enough to enumerate. The board
        // therefore carries no lookup form, and a resident reads their own record
        // through resident.relief, which is scoped by the route rather than by a
        // parameter they supply.
        $board = $this->get(route('transparency.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="household_number"', $board);
        $this->assertStringNotContainsString('transparency/search', $board);
        $this->assertStringContainsString(route('login'), $board, 'A guest is offered the way in.');

        // And the route itself is gone, so there is nothing left to enumerate.
        $this->assertFalse(
            collect(app('router')->getRoutes())->contains(fn ($route) => $route->uri() === 'transparency/search'),
            'A public household-number lookup must not exist.'
        );
    }

    public function test_no_link_still_points_at_a_removed_homepage_anchor(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/href="([^"]+)"/', $content, $matches);

        // "#main-content" is the accessibility skip link and "#how-it-works" now
        // belongs to the services page, so only fragments on the home URL itself
        // would be leftovers from the single-page scroll.
        foreach ($matches[1] as $href) {
            $this->assertStringNotContainsString(
                route('home').'#',
                $href,
                "A link still points at a removed homepage anchor [{$href}].",
            );
        }
    }
}
