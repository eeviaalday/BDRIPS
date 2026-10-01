<?php

namespace Tests\Feature;

use App\View\Components\PublicLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The footer is a full-bleed page footer: a deep blue band that runs to both
 * screen edges, with the barangay photograph on its right fading in through a
 * gradient, and every link and the privacy notice still inside it.
 *
 * These tests pin both halves of that: the structure and the overlays, which are
 * what a later edit could quietly undo, and the content, which is functional.
 */
class PublicFooterBannerTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_footer_is_a_full_width_band_rather_than_a_panel(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertMatchesRegularExpression(
            '/<footer class="[^"]*\bbg-slate-900\b[^"]*">/',
            $footer,
            'The footer is no longer the deep blue page band.',
        );

        // A rounded, inset card is what the full-bleed footer replaced: no
        // rounded corners, no inner width cap on the band itself, no shadow.
        $this->assertStringNotContainsString('rounded-3xl', $footer);
        $this->assertStringNotContainsString('rounded-2xl', $footer);
        $this->assertStringNotContainsString('shadow-', $footer);

        // The only wrapper is the content one, so the band reaches the edges.
        $this->assertSame(
            1,
            preg_match('/<div class="[^"]*\bmax-w-7xl\b[^"]*">/', $footer, $wrapper),
            'The footer no longer centres its content in a max-w-7xl wrapper.',
        );

        $this->assertStringContainsString('px-4 py-8 sm:px-6 sm:py-10', $footer);
    }

    public function test_the_footer_keeps_compact_vertical_padding(): void
    {
        preg_match_all('/\b(?:sm:|lg:|md:|xl:)?py-(\d+)\b/', $this->footer($this->homepage()), $padding);

        $this->assertNotEmpty($padding[1], 'The footer no longer declares any vertical padding.');

        $this->assertLessThanOrEqual(
            10,
            max(array_map('intval', $padding[1])),
            'The footer still carries vertical padding taller than py-10.',
        );
    }

    public function test_the_photograph_sits_behind_the_band_on_the_right(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertStringContainsString('src="'.asset(config('bdrpis.images.footer')).'"', $footer);

        // The layer is absolute, behind the content and clipped to the band.
        $this->assertStringContainsString('absolute inset-0 -z-10 overflow-hidden', $footer);

        // object-right, not object-center: the composition is on the right.
        $this->assertStringContainsString('object-cover object-right', $footer);
    }

    public function test_the_blue_runs_across_the_band_into_the_photograph(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertMatchesRegularExpression(
            '/bg-gradient-to-r from-slate-950 via-slate-900\/\d+ to-slate-900\/\d+/',
            $footer,
            'The horizontal blue-to-image gradient is gone.',
        );

        // The transparency has to be a partial one: a fully clear right-hand
        // stop would put the link text straight onto the photograph.
        preg_match('/bg-gradient-to-r from-slate-950 via-slate-900\/\d+ to-slate-900\/(\d+)/', $footer, $stop);

        $this->assertGreaterThan(0, (int) $stop[1], 'The gradient clears completely on the right.');
    }

    public function test_a_second_scrim_keeps_the_lower_text_readable(): void
    {
        // The columns and the copyright bar sit in the lower half of the band,
        // which is where a landscape photograph is brightest. The vertical
        // overlay is what carries them, on desktop and more so on a phone where
        // the horizontal gradient's clear end covers most of the width.
        $this->assertStringContainsString(
            'bg-gradient-to-t from-slate-950',
            $this->footer($this->homepage()),
            'The legibility scrim over the lower half of the band is gone.',
        );
    }

    public function test_there_is_no_divider_between_the_text_and_the_photograph(): void
    {
        $footer = $this->footer($this->homepage());

        // No vertical rules, and no photo column sized as a sibling of the
        // content: the image is a background layer now, so the two can never
        // meet along a visible edge.
        $this->assertStringNotContainsString('border-l', $footer);
        $this->assertStringNotContainsString('border-r', $footer);
        $this->assertStringNotContainsString('w-[30%]', $footer);
    }

    public function test_the_footer_falls_back_to_neutral_artwork_when_the_photo_is_missing(): void
    {
        config()->set('bdrpis.images.footer', 'images/this-photograph-does-not-exist.jpg');

        $footer = $this->footer($this->homepage());

        $this->assertStringNotContainsString('this-photograph-does-not-exist', $footer);
        $this->assertStringContainsString('src="'.asset(config('bdrpis.image_fallbacks.footer')).'"', $footer);
    }

    public function test_the_four_content_columns_are_retained(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertStringContainsString('lg:grid-cols-4', $footer);

        foreach (['E-Services', 'Explore', 'Contact'] as $heading) {
            $this->assertMatchesRegularExpression('/>\s*'.preg_quote($heading, '/').'\s*</', $footer);
        }

        $this->assertStringContainsString('Barangay Linasin', $footer);
        $this->assertStringContainsString('BDRPIS', $footer);
    }

    public function test_the_footer_keeps_every_destination_link(): void
    {
        $footer = $this->footer($this->homepage());

        $hrefs = [
            route('services.index'),
            route('transparency.index'),
            route('about'),
            route('contact'),
            route('about').'#officials',
            route('announcements.index'),
            route('resident.requests.index'),
            route('services.index').'#how-it-works',
        ];

        foreach ($hrefs as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $footer, "The footer no longer links to [{$href}].");
        }

        foreach (config('bdrpis.services') as $service) {
            $this->assertStringContainsString(e($service['title']), $footer);
        }

        $this->assertStringContainsString(asset('images/Zone 1_logo.png'), $footer);
    }

    public function test_the_footer_keeps_the_contact_channels(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertStringContainsString('mailto:floria123@yahoo.com', $footer);
        $this->assertStringContainsString('tel:09663320659', $footer);
        $this->assertStringContainsString('09663320659', $footer);
        $this->assertStringContainsString('Zone 1, Bangued, Abra', $footer);
    }

    public function test_the_footer_prints_the_contact_number_once(): void
    {
        $footer = $this->footer($this->homepage());

        // The office line and the hotline are the same number for this barangay,
        // so it is printed under one heading rather than twice.
        $this->assertStringContainsString('Contact No.', $footer);
        $this->assertStringNotContainsString('Emergency hotline', $footer);

        $this->assertSame(
            1,
            substr_count($footer, 'tel:'),
            'The contact number is linked more than once in the footer.',
        );
    }

    public function test_the_footer_links_to_the_official_facebook_page(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertStringContainsString(
            'href="'.PublicLayout::FACEBOOK_PAGE.'"',
            $footer,
            'The footer no longer links to the official Facebook page.',
        );

        // The only link in the footer that leaves the site, so it needs a tab of
        // its own and must not hand the new page a reference back to this one.
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(PublicLayout::FACEBOOK_PAGE, '/').'"[^>]*target="_blank"[^>]*rel="noopener noreferrer"/',
            $footer,
            'The Facebook link does not open safely in a new tab.',
        );

        $this->assertStringContainsString('aria-label="Barangay Linasin on Facebook"', $footer);
    }

    public function test_the_privacy_notice_is_held_on_one_line_from_xl_up(): void
    {
        $footer = $this->footer($this->homepage());

        $this->assertStringContainsString('xl:whitespace-nowrap', $footer);

        // Forced at every width instead, the notice would push the page sideways
        // on a phone, where it has to wrap.
        $this->assertDoesNotMatchRegularExpression(
            '/class="[^"]*(?<!xl:)\bwhitespace-nowrap\b/',
            $footer,
            'The notice is nowrap at every width, which would overflow a narrow screen.',
        );
    }

    public function test_the_footer_keeps_the_privacy_notice_and_copyright(): void
    {
        $footer = $this->footer($this->homepage());

        // Rendered as the &copy; entity, so compare against the entity.
        $this->assertStringContainsString('&copy; '.date('Y').' Barangay Linasin', $footer);
        $this->assertStringContainsString('Data Privacy Act of 2012 (R.A. 10173)', $footer);
    }

    public function test_the_footer_appears_on_every_public_page(): void
    {
        foreach (['home', 'services.index', 'about', 'announcements.index', 'contact'] as $name) {
            $footer = $this->footer($this->get(route($name))->assertOk()->getContent());

            $this->assertStringContainsString('max-w-7xl', $footer, "The [{$name}] page lost the footer band.");
        }
    }

    private function homepage(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    /**
     * The footer element, so a page heading can never satisfy a footer assertion.
     */
    private function footer(string $content): string
    {
        $start = strpos($content, '<footer');
        $end = strpos($content, '</footer>');

        $this->assertIsInt($start, 'The page no longer renders a footer.');
        $this->assertIsInt($end, 'The footer is not closed.');

        return substr($content, $start, $end - $start);
    }
}
