<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicImageryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every image key referenced by the public pages, flattened to a list.
     *
     * @return array<int, string>
     */
    private function configuredImageKeys(): array
    {
        return [
            'hero',
            'about',
            'footer',
            ...array_column(config('bdrpis.activities'), 'image'),
        ];
    }

    public function test_public_pages_render_the_configured_photographs(): void
    {
        // The homepage now carries only the hero; the about photograph and the
        // activity gallery moved to /about with the rest of that content.
        $home = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(asset(config('bdrpis.images.hero')), $home);
        $this->assertStringContainsString(asset('images/Zone 1_logo.png'), $home);

        // The hero's "Our Barangay" callout card still shows the hall
        // photograph even though the about *section* moved to /about.
        $this->assertStringContainsString(asset(config('bdrpis.images.about')), $home);

        $about = $this->get(route('about'))->assertOk()->getContent();

        // The about page's history photograph is the square hall picture served
        // straight from public/, so it is checked here rather than through the
        // image map; the wider "about" key stays in use by the homepage callout
        // asserted above.
        $this->assertStringContainsString(asset('images/barangay-hall.png'), $about);

        foreach (config('bdrpis.activities') as $activity) {
            $this->assertStringContainsString(
                asset(config('bdrpis.images.'.$activity['image'])),
                $about,
                "Activity [{$activity['title']}] did not render its configured photograph.",
            );
        }
    }

    public function test_homepage_falls_back_to_neutral_artwork_when_a_photograph_is_missing(): void
    {
        config()->set('bdrpis.images.hero', 'images/this-photograph-does-not-exist.jpg');

        $content = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(asset(config('bdrpis.image_fallbacks.hero')), $content);
        $this->assertStringNotContainsString('images/this-photograph-does-not-exist.jpg', $content);
    }

    public function test_every_configured_public_photograph_exists_on_disk(): void
    {
        foreach ($this->configuredImageKeys() as $key) {
            $path = config('bdrpis.images.'.$key);

            $this->assertFileExists(public_path($path), "Configured image [{$path}] is missing from public/.");
        }
    }

    public function test_officials_directory_renders_no_photographs(): void
    {
        $content = $this->get(route('public.directory'))->assertOk()->getContent();

        preg_match('/<main id="main-content">(.*)<\/main>/s', $content, $matches);
        $main = $matches[1] ?? '';

        // Every page now opens with a decorative banner photograph, so the
        // assertion is scoped to the directory body that follows it. What it
        // still guards is that no official is ever shown a photograph: the
        // listing renders neutral initials instead.
        $body = str_contains($main, '</section>')
            ? substr($main, strpos($main, '</section>'))
            : $main;

        $this->assertStringNotContainsString('<img', $body);
        $this->assertStringNotContainsString('id-photo', $body);
    }
}
