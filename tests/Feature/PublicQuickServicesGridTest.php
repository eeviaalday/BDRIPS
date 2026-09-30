<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicQuickServicesGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_homepage_renders_the_quick_services_heading(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Quick Services', $content);
        $this->assertStringContainsString('id="services"', $content);
    }

    public function test_the_homepage_no_longer_renders_the_document_service_catalogue(): void
    {
        // Scoped to the page body: the site-wide footer still lists the document
        // titles as links into /services, which is intended.
        $content = $this->mainRegion($this->get(route('home'))->assertOk()->getContent());

        $this->assertStringNotContainsString('Supported Document Services', $content);

        // The individual document types moved off the homepage entirely; they
        // used to render as a four-card grid here.
        foreach (config('bdrpis.services') as $service) {
            $this->assertStringNotContainsString(
                e($service['title']),
                $content,
                "Document [{$service['title']}] is still rendered on the homepage.",
            );
        }

        // And so did the request call-to-action that sat under the cards.
        $this->assertStringNotContainsString('Create a resident account', $content);
        $this->assertStringNotContainsString('document request form', $content);
    }

    public function test_the_document_catalogue_still_exists_on_the_services_page(): void
    {
        $content = $this->get(route('services.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Supported Document Services', $content);

        foreach (config('bdrpis.services') as $service) {
            $this->assertStringContainsString(e($service['title']), $content);
        }
    }

    public function test_exactly_the_five_configured_quick_service_cards_render(): void
    {
        $this->assertCount(5, config('bdrpis.quick_services'));

        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (config('bdrpis.quick_services') as $card) {
            $this->assertStringContainsString(
                e($card['title']),
                $content,
                "Quick service card [{$card['title']}] is missing from the homepage.",
            );
        }
    }

    public function test_every_card_renders_its_configured_description(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (config('bdrpis.quick_services') as $card) {
            if (blank($card['description'] ?? null)) {
                continue;
            }

            $this->assertStringContainsString(
                e($card['description']),
                $content,
                "Quick service card [{$card['title']}] is missing its description.",
            );
        }
    }

    public function test_every_card_points_at_a_registered_route(): void
    {
        foreach (config('bdrpis.quick_services') as $card) {
            $this->assertTrue(
                Route::has($card['route']),
                "Quick service card [{$card['title']}] points at unregistered route [{$card['route']}].",
            );
        }
    }

    public function test_every_card_href_resolves_to_a_real_page(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (config('bdrpis.quick_services') as $card) {
            $this->assertStringContainsString(
                'href="'.route($card['route']).'"',
                $content,
                "Quick service card [{$card['title']}] does not link to [{$card['route']}].",
            );
        }
    }

    public function test_cards_are_reachable_for_a_guest_or_redirect_to_login(): void
    {
        foreach (config('bdrpis.quick_services') as $card) {
            $response = $this->get(route($card['route']));

            $this->assertContains(
                $response->getStatusCode(),
                [200, 302],
                "Quick service card [{$card['title']}] leads to an unexpected status.",
            );

            // A redirect must be a redirect to the login page, never a 403 or 404.
            if ($response->getStatusCode() === 302) {
                $response->assertRedirect(route('login'));
            }
        }
    }

    public function test_the_resident_verification_card_is_behind_the_login_gate(): void
    {
        // There is deliberately no public resident lookup, so this card is the one
        // destination a guest cannot read directly. Pin that down so the gate is
        // not removed by accident.
        $this->get(route('resident.verification.status'))
            ->assertRedirect(route('login'));
    }

    public function test_each_card_renders_its_configured_badge_colours(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (config('bdrpis.quick_services') as $card) {
            [$background, $foreground] = explode(' ', $card['badge']);

            $this->assertStringContainsString(
                'rounded-full '.$background.' '.$foreground,
                $content,
                "Quick service card [{$card['title']}] is missing its badge colours.",
            );
        }
    }

    public function test_the_five_cards_render_five_distinct_icons(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The icon component silently substitutes a document glyph for an unknown
        // name, so a typo would be invisible. Five cards must therefore produce
        // five different glyphs; a fallback would collapse the count.
        preg_match_all('/<span class="[^"]*h-12 w-12[^"]*">(.*?)<\/span>/s', $this->quickServicesBand($content), $badges);

        $glyphs = [];

        foreach ($badges[1] as $badge) {
            preg_match('/<svg.*?>(.*?)<\/svg>/s', $badge, $glyph);

            $glyphs[md5(trim($glyph[1] ?? ''))] = true;
        }

        $this->assertCount(
            5,
            $glyphs,
            'The five quick service cards did not render five distinct icons.',
        );
    }

    public function test_the_new_icon_glyphs_are_available_in_the_icon_set(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        foreach (['id-badge', 'gift', 'grid'] as $name) {
            $rendered = trim((string) $this->renderIcon($name));
            $document = trim((string) $this->renderIcon('document'));

            $this->assertNotSame(
                $document,
                $rendered,
                "Icon [{$name}] fell back to the document glyph, so it is not in the icon set.",
            );
        }

        $this->assertStringContainsString('<svg', $content);
    }

    public function test_the_redundant_more_services_card_is_gone(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertNull(
            collect(config('bdrpis.quick_services'))->firstWhere('title', 'More Services'),
            'The redundant More Services card is still configured.',
        );

        $this->assertStringNotContainsString('View all services', $content);
        $this->assertStringNotContainsString('More Services', $content);

        // It was a duplicate call to action: Document Requests already points here.
        $this->assertStringContainsString(route('services.index'), $content);
    }

    public function test_the_grid_uses_the_responsive_column_ladder(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/class="[^"]*grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5[^"]*"/',
            $content,
            'The quick services grid is missing its responsive column ladder.',
        );
    }

    public function test_the_band_keeps_the_surface_tone_so_the_hero_wave_still_meets(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The hero's wave divider is filled with text-surface. If this band were
        // switched to plain white, a seam would appear along that curve.
        $this->assertStringContainsString('text-surface', $content);
        $this->assertMatchesRegularExpression(
            '/<section\s+id="services"\s+class="bg-surface"/',
            $content,
            'The quick services band no longer uses the surface tone.',
        );
    }

    public function test_every_card_is_a_single_fully_clickable_link(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // One anchor per card, each carrying an accessible name, rather than a
        // title link plus a separate arrow link with a two-step tap target.
        preg_match_all('/<a href="[^"]+"\s+aria-label="[^"]+"/', $this->quickServicesBand($content), $anchors);

        $this->assertCount(5, $anchors[0]);
    }

    public function test_the_quick_services_band_uses_tighter_top_padding(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(
            'pt-8 sm:pt-10 lg:pt-12',
            $content,
            'The quick services band no longer tightens its top padding.',
        );

        // The shared default must still be available to every other band, and the
        // quick services band must not have dragged it along.
        $this->assertStringContainsString(
            'pt-14 sm:pt-16 lg:pt-20',
            $this->announcementsBand($content),
            'The announcements band lost the default x-shell top padding.',
        );
    }

    public function test_the_hero_fold_constant_still_matches_the_band_padding(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        // The hero's min-height is derived from the band below it:
        //   80px header + band top padding + 16px eyebrow
        //   + 12px eyebrow-to-title margin + 6px clearance
        //
        // Tightening the band's padding moves the eyebrow, so the constant has to
        // move with it or the heading starts peeking above the fold. Both halves
        // are read out of the rendered markup and the arithmetic is recomputed,
        // so changing either value without the other fails this test.
        preg_match('/lg:min-h-\[calc\(100vh-(\d+)px\)\]/', $content, $height);
        preg_match('/lg:pt-(\d+)/', $this->quickServicesBand($content), $padding);

        $this->assertNotEmpty($height, 'The hero no longer carries a viewport-relative min-height.');
        $this->assertNotEmpty($padding, 'The quick services band no longer overrides its top padding.');

        // Tailwind's default spacing step is 0.25rem, so "lg:pt-12" is 12 * 4px.
        $expected = 80 + ((int) $padding[1] * 4) + 16 + 12 + 6;

        $this->assertSame(
            $expected,
            (int) $height[1],
            "The hero fold constant is out of step with the band's lg:pt-{$padding[1]} padding.",
        );
    }

    public function test_the_heading_no_longer_renders_an_eyebrow_or_a_subtitle(): void
    {
        $band = $this->quickServicesBand($this->get(route('home'))->assertOk()->getContent());

        // Both were noise: the eyebrow only named the band it sat in, and the
        // sentence described a list of links rather than telling the reader
        // anything about the barangay.
        $this->assertStringNotContainsString('Quick Access', $band);
        $this->assertStringNotContainsString('Jump straight to the part of the portal you need.', $band);

        $this->assertMatchesRegularExpression(
            '/<h2 class="[^"]*">\s*Quick Services/',
            $band,
            'The quick services title is no longer the first line of the band.',
        );
    }

    public function test_the_title_lands_on_the_floating_callouts_view_details_line(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();
        $band = $this->quickServicesBand($content);
        $hero = substr($content, 0, strpos($content, 'id="services"'));

        // The callout hangs -bottom-N below the hero's bottom edge and closes
        // with p-N of padding under its "View Details" button, so that button's
        // baseline sits (hang - padding) below the hero. The band's top padding
        // has to be the same distance for the two to read as one line.
        preg_match('/class="absolute -bottom-(\d+)[^"]*"/', $hero, $hang);
        preg_match('/<div class="p-(\d+)">/', $hero, $padding);
        preg_match('/lg:pt-(\d+)/', $band, $topPadding);

        $this->assertNotEmpty($hang, 'The floating callout no longer hangs below the hero.');
        $this->assertNotEmpty($padding, 'The floating callout no longer wraps its "View Details" button.');
        $this->assertStringContainsString(
            'View Details',
            substr($hero, strpos($hero, 'class="p-'.$padding[1].'"')),
            'The padded block measured above is not the one holding the "View Details" button.',
        );

        // Tailwind's spacing step is 0.25rem, so a -bottom-16 is 16 * 4px.
        $this->assertSame(
            (((int) $hang[1]) - ((int) $padding[1])) * 4,
            ((int) $topPadding[1]) * 4,
            "The band's lg:pt-{$topPadding[1]} no longer lines the title up with the callout's View Details line.",
        );
    }

    public function test_the_title_carries_no_margin_of_its_own(): void
    {
        $band = $this->quickServicesBand($this->get(route('home'))->assertOk()->getContent());

        // The title is the first thing in the band, so a top margin here would
        // push it off the band padding that the alignment above depends on.
        preg_match('/<h2 class="([^"]*)">\s*Quick Services/', $band, $heading);

        $this->assertNotEmpty($heading, 'The quick services title no longer renders as a heading.');
        $this->assertDoesNotMatchRegularExpression(
            '/(?:^|\s)mt-[\d.]+/',
            $heading[1],
            'The quick services title has a top margin, so it no longer sits on the band padding.',
        );
    }

    public function test_tailwind_scans_the_config_directory_for_class_names(): void
    {
        // The badge colours live in config/bdrpis.php, not in a Blade file, so
        // Tailwind has to be told to look there. Without this glob a colour used
        // only in config compiles to nothing and the circle renders unstyled,
        // while the same class appearing in a Blade file works by coincidence.
        $config = file_get_contents(base_path('tailwind.config.js'));

        $this->assertStringContainsString(
            './config/*.php',
            $config,
            'tailwind.config.js no longer scans config/, so config-driven utility classes will not compile.',
        );
    }

    public function test_every_configured_badge_colour_is_a_generated_utility(): void
    {
        $css = $this->compiledStylesheet();

        if ($css === null) {
            $this->markTestSkipped('No compiled stylesheet found; run `npm run build` to verify emitted utilities.');
        }

        $source = (string) file_get_contents(config_path('bdrpis.php'));

        foreach (config('bdrpis.quick_services') as $card) {
            foreach (explode(' ', $card['badge']) as $utility) {
                $this->assertStringContainsString(
                    $utility,
                    $source,
                    "Badge utility [{$utility}] is not present in config/bdrpis.php.",
                );

                $this->assertMatchesRegularExpression(
                    '/\.'.preg_quote($utility, '/').'(?![\w-])/',
                    $css,
                    "Badge utility [{$utility}] for [{$card['title']}] is missing from the compiled stylesheet.",
                );
            }
        }
    }

    public function test_the_quick_services_grid_exposes_no_resident_data(): void
    {
        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('id-photo', $content);
        $this->assertStringNotContainsString(
            route('staff.resident-verifications.id-photo', ['residentProfile' => 1]),
            $content,
        );
    }

    /**
     * The quick services band, from its section open tag up to the announcements
     * band that follows it, so footer links cannot contaminate the assertions.
     */
    private function quickServicesBand(string $content): string
    {
        $start = strpos($content, 'id="services"');
        $end = strpos($content, 'id="announcements"');

        $this->assertIsInt($start, 'The homepage no longer renders a services band.');
        $this->assertIsInt($end, 'The homepage no longer renders an announcements band after services.');

        return substr($content, $start, $end - $start);
    }

    /**
     * The announcements band, which must keep the shared default padding.
     */
    private function announcementsBand(string $content): string
    {
        $start = strpos($content, 'id="announcements"');
        $end = strpos($content, '</footer>');

        $this->assertIsInt($start, 'The homepage no longer renders an announcements band.');

        return substr($content, $start, $end > $start ? $end - $start : null);
    }

    /**
     * The page body, excluding the site-wide footer, whose E-Services column
     * intentionally lists the document titles on every public page.
     */
    private function mainRegion(string $content): string
    {
        preg_match('/<main id="main-content">(.*)<\/main>/s', $content, $matches);

        $this->assertNotEmpty($matches, 'The public layout did not render a main region.');

        return $matches[1];
    }

    private function renderIcon(string $name): string
    {
        return (string) $this->blade('<x-icon :name="$name" />', ['name' => $name]);
    }

    /**
     * The emitted CSS bundle, resolved through the Vite manifest, or null when no
     * build is present.
     */
    private function compiledStylesheet(): ?string
    {
        $manifest = public_path('build/manifest.json');

        if (! is_file($manifest)) {
            return null;
        }

        $entries = json_decode((string) file_get_contents($manifest), true);

        foreach ($entries as $entry) {
            $file = $entry['file'] ?? '';

            if (str_ends_with($file, '.css') && is_file(public_path('build/'.$file))) {
                return (string) file_get_contents(public_path('build/'.$file));
            }
        }

        return null;
    }
}
