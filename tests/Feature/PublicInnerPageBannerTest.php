<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every inner public page opens with the same banner: one photograph, a dark
 * blue gradient over it, a white headline and a breadcrumb trail underneath.
 *
 * The component is shared, so these tests read the rendered markup of each page
 * rather than the component in isolation - that is what catches a page quietly
 * dropping back to its own treatment.
 */
class PublicInnerPageBannerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The pages that must carry the standardised banner, with the headline and
     * the breadcrumb label each one ends on.
     *
     * The last two sit behind a sign-in (see self::SIGNED_IN) but share this
     * component with the open pages, so they belong in the same sweep.
     *
     * The transparency and request-tracking pages were removed with the areas
     * they belonged to and are stub routes for now, so they have no banner to
     * assert and are not in the sweep.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function bannerProvider(): array
    {
        return [
            'services' => ['services.index', 'Supported Document Services', 'Services'],
            'about' => ['about', 'About Our Barangay', 'About Us'],
            'announcements' => ['announcements.index', 'Community Announcements', 'Announcements'],
            'contact' => ['contact', 'Contact Us', 'Contact Us'],
            'directory' => ['public.directory', 'Officials Directory', 'Officials Directory'],
        ];
    }

    #[DataProvider('bannerProvider')]
    public function test_each_inner_page_opens_with_the_standard_banner(string $route, string $title, string $crumb): void
    {
        $banner = $this->banner($this->page($route));

        $this->assertStringContainsString(asset(config('bdrpis.images.page_banner')), $banner);
        $this->assertStringContainsString('object-cover object-[center_72%]', $banner);

        // One horizontal gradient over the photograph: dense slate on the left
        // where the white headline, breadcrumb and description sit, thinning to
        // fully clear on the right so the hall is unobstructed. There is no flat
        // tint on top of it - that is what used to bury the building.
        $this->assertStringContainsString(
            'bg-gradient-to-r from-slate-950/80 via-slate-900/60 to-transparent',
            $banner,
        );
        $this->assertStringNotContainsString(
            'absolute inset-0 bg-slate-900/40',
            $banner,
            'A flat tint is laid over the photograph again.',
        );

        $this->assertStringContainsString('text-3xl font-extrabold', $banner);
        $this->assertStringContainsString($title, $banner);

        $this->assertStringContainsString('aria-label="Breadcrumb"', $banner);
        $this->assertStringContainsString('Home', $banner);
        $this->assertStringContainsString($crumb, $banner);
    }

    #[DataProvider('bannerProvider')]
    public function test_the_breadcrumb_starts_at_home_and_ends_on_the_page_itself(string $route): void
    {
        $content = $this->page($route);

        $banner = $this->banner($content);

        // Home is the one crumb that is a link; the page you are on is not, so a
        // breadcrumb can never offer a link away from the current page.
        $this->assertStringContainsString('href="'.route('home').'"', $banner);
        $this->assertSame(
            1,
            substr_count($banner, 'aria-current="page"'),
            'The breadcrumb does not mark exactly one current page.',
        );

        // The trail belongs to the banner and does not leak into the page body.
        $this->assertSame(1, substr_count($content, 'aria-label="Breadcrumb"'));
    }

    #[DataProvider('bannerProvider')]
    public function test_the_banner_is_compact_and_bounded(string $route): void
    {
        $banner = $this->banner($this->page($route));

        // A padding ladder rather than one fixed size. Read as separate
        // utilities: the wrapper also carries text-left.
        foreach (['px-4', 'py-10', 'sm:px-6', 'md:px-8', 'md:py-14'] as $utility) {
            $this->assertStringContainsString($utility, $banner, "The banner has lost its [{$utility}] padding.");
        }

        // The floor is what makes the pages match: content of different lengths
        // is centred inside the same band rather than producing seven different
        // heights. The ceiling is a backstop for a pathological title, not a
        // height anything real reaches - the tallest case, this page's two
        // buttons wrapping on a 320px phone, needs 348px.
        $this->assertStringContainsString(
            'flex min-h-[300px] max-h-[360px] items-center overflow-hidden bg-slate-900 text-white',
            $banner,
            'The banner no longer holds a bounded height with centred content.',
        );

        $this->assertStringNotContainsString('min-h-[380px]', $banner);
        $this->assertStringNotContainsString('md:min-h-[440px]', $banner);
    }

    #[DataProvider('bannerProvider')]
    public function test_the_banner_is_a_single_full_bleed_section(string $route): void
    {
        $banner = $this->banner($this->page($route));

        $this->assertStringContainsString(asset(config('bdrpis.images.page_banner')), $banner);

        // The photograph is one layer across the whole band, not a box beside
        // the text. A grid, a half-width column or a contained image all read as
        // a dark panel with a picture stuck to one side, which is not this.
        $this->assertStringNotContainsString('grid-cols-2', $banner);
        $this->assertStringNotContainsString('w-1/2', $banner);
        $this->assertStringNotContainsString('object-contain', $banner, 'The photograph is letterboxed into a box again.');
        $this->assertStringNotContainsString('object-right', $banner);

        $this->assertStringContainsString(
            'pointer-events-none absolute inset-0 -z-10 overflow-hidden',
            $banner,
            'The photograph is no longer a full-bleed layer behind the content.',
        );
        $this->assertStringContainsString('h-full w-full object-cover', $banner);

        // The one gradient, across the whole band: dense on the left where the
        // white headline, breadcrumb and description sit, clear on the right.
        $this->assertStringContainsString(
            'bg-gradient-to-r from-slate-950/80 via-slate-900/60 to-transparent',
            $banner,
        );
    }

    #[DataProvider('bannerProvider')]
    public function test_the_banner_keeps_the_roof_and_the_sign_in_frame(string $route): void
    {
        $banner = $this->banner($this->page($route));

        // object-cover has to take a horizontal slice of a 1919x820 photograph
        // in a roughly 4:1 band, so the slice is a choice. Measured on this
        // photograph, the top half is sky and the building runs from about 50%
        // down to 95%, with the signage band at 85-95%: a centred crop keeps rows
        // 22-77% and cuts the sign off, this one keeps rows 45-100%.
        $this->assertStringContainsString(
            'object-cover object-[center_72%]',
            $banner,
            'The photograph is cropped without a chosen position, so the building is cut again.',
        );
    }

    #[DataProvider('bannerProvider')]
    public function test_the_banner_text_is_strictly_left_aligned(string $route): void
    {
        $banner = $this->banner($this->page($route));

        // Left-bound column inside the page shell, pinned left at every width.
        $this->assertStringContainsString(
            'class="mx-auto w-full max-w-shell px-4 py-10 text-left sm:px-6 md:px-8 md:py-14"',
            $banner,
        );
        $this->assertStringContainsString('class="max-w-3xl text-left"', $banner);

        // The section is a row flex with one in-flow child, so items-center
        // centres the block vertically without touching the text alignment.
        $this->assertStringNotContainsString('text-center', $banner, 'Something in the banner is centre-aligned.');

        // Checked on the wrappers rather than the whole banner: a button carries
        // its own justify-center to line its icon up with its label, which is
        // the button's business and not the banner's text alignment.
        preg_match_all('/class="([^"]*(?:max-w-shell|max-w-3xl)[^"]*)"/', $banner, $wrappers, PREG_SET_ORDER);

        foreach ($wrappers as $wrapper) {
            $this->assertStringNotContainsString('justify-center', $wrapper[1], 'The banner text block is pushed to the middle.');
            $this->assertStringNotContainsString('text-center', $wrapper[1]);
        }
    }

    public function test_every_inner_page_banner_shares_one_height(): void
    {
        // Read the height floor back out of each page so a page that quietly
        // gave the banner its own padding cannot slip through: the number has
        // to be the same on all of them.
        $bounds = [];

        foreach (array_keys(self::bannerProvider()) as $name) {
            $route = self::bannerProvider()[$name][0];

            $banner = $this->banner($this->page($route));

            preg_match('/min-h-\[(\d+)px\] max-h-\[(\d+)px\]/', $banner, $matches);

            $this->assertNotEmpty($matches, "The [{$name}] banner has no height bounds.");

            $bounds[$name] = $matches[1].'-'.$matches[2];
        }

        $this->assertCount(
            1,
            array_unique($bounds),
            'The inner page banners no longer share one height: '.implode(', ', array_map(
                fn (string $name, string $bounds): string => "{$name}={$bounds}px",
                array_keys($bounds),
                $bounds,
            )),
        );
    }

    public function test_the_banner_falls_back_to_neutral_artwork_when_the_photo_is_missing(): void
    {
        config()->set('bdrpis.images.page_banner', 'images/this-photograph-does-not-exist.jpg');

        $banner = $this->banner($this->get(route('about'))->assertOk()->getContent());

        $this->assertStringNotContainsString('this-photograph-does-not-exist', $banner);
        $this->assertStringContainsString(
            'src="'.asset(config('bdrpis.image_fallbacks.page_banner')).'"',
            $banner,
        );
    }

    public function test_the_configured_banner_photograph_is_a_real_image_on_disk(): void
    {
        $path = public_path(config('bdrpis.images.page_banner'));

        $this->assertFileExists($path, 'The standardised banner photograph is missing from public/.');

        // A renamed or half-written file can leave something on disk that the
        // browser will not render, so the bytes themselves are checked.
        $this->assertIsArray(
            @getimagesize($path),
            'The configured banner photograph is not a readable image.',
        );
    }

    public function test_no_page_still_passes_an_eyebrow_to_the_banner(): void
    {
        // The eyebrow was replaced by the breadcrumb trail. A page still passing
        // one would either render nothing, or reintroduce a second label above
        // the headline. Only the banner's own opening tag is read here: the
        // section headings in each page body still use eyebrows.
        foreach (['services', 'about', 'announcements', 'contact', 'directory'] as $view) {
            $source = file_get_contents(resource_path("views/public/{$view}.blade.php"));

            $this->assertSame(
                1,
                preg_match('/<x-page-header\b(.*?)>/s', $source, $tag),
                "[{$view}] no longer opens with a single banner component.",
            );

            $this->assertStringNotContainsString('eyebrow=', $tag[1], "[{$view}] still passes an eyebrow to the banner.");
        }
    }

    /**
     * Render one of the provider's pages and hand back its markup.
     *
     * Every page here is requested as a guest, which is the harder case, since
     * that is what a first-time visitor actually receives.
     */
    private function page(string $route): string
    {
        return $this->get(route($route))->assertOk()->getContent();
    }

    /**
     * The banner section, from its opening tag to the end of the page's first
     * section, so an assertion cannot be satisfied by content further down.
     */
    private function banner(string $content): string
    {
        $start = strpos($content, '<main id="main-content">');
        $end = strpos($content, '</section>', $start === false ? 0 : $start);

        $this->assertIsInt($start, 'The page has no main region.');
        $this->assertIsInt($end, 'The page no longer opens with a banner section.');

        return substr($content, $start, $end - $start);
    }
}
