<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicAnnouncementGridTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_band_header_keeps_only_the_title_and_subtitle(): void
    {
        $header = $this->announcementsHeader($this->homepage());

        $this->assertStringContainsString('Latest News and Announcements', $header);
        $this->assertStringContainsString(
            'Notices, schedules, and updates published by Barangay Linasin.',
            $header,
        );

        // The eyebrow only repeated the name of the section it sat above, and the
        // previous wording of both lines went with it.
        $this->assertStringNotContainsString('Latest Announcements', $header);
        $this->assertStringNotContainsString('Notices and updates published by the barangay.', $header);
        $this->assertStringNotContainsString('tracking-[0.18em]', $header, 'The section eyebrow is still rendered.');
    }

    public function test_the_band_header_is_left_aligned(): void
    {
        $header = $this->announcementsHeader($this->homepage());

        $this->assertStringContainsString(
            'text-left',
            $header,
            'The announcements header no longer pins its alignment.',
        );

        $this->assertStringNotContainsString(
            'text-center',
            $header,
            'Something in the announcements header is still centre-aligned.',
        );
    }

    public function test_the_cards_render_in_a_three_column_grid(): void
    {
        Announcement::factory()->published()->count(3)->create();

        $band = $this->announcementsBand($this->homepage());

        $this->assertMatchesRegularExpression(
            '/class="[^"]*grid gap-6 md:grid-cols-2 lg:grid-cols-3[^"]*"/',
            $band,
            'The announcements grid no longer reaches three columns on desktop.',
        );

        $this->assertCount(3, $this->cardElements($band));
    }

    public function test_each_card_shows_a_photograph_its_category_and_its_title(): void
    {
        Announcement::factory()->published()->create([
            'title' => 'Barangay Health Drive on Saturday',
            'category' => 'health',
        ]);

        $card = $this->cardElements($this->announcementsBand($this->homepage()))[0];

        // The photograph, the category tag and the headline are the three things
        // that make the grid readable at a glance.
        $this->assertStringContainsString('<img src="'.asset(config('bdrpis.images.community.health')).'"', $card);
        $this->assertStringContainsString('Barangay Health Drive on Saturday', $card);

        $this->assertMatchesRegularExpression(
            '/<span class="(?=[^"]*uppercase)(?=[^"]*text-blue-700)[^"]*">\s*Health/',
            $card,
            'The category tag is not rendered as an uppercase blue label.',
        );

        $this->assertMatchesRegularExpression(
            '/<h3 class="(?=[^"]*uppercase)(?=[^"]*font-bold)[^"]*">\s*Barangay Health Drive on Saturday/',
            $card,
            'The headline is not rendered as an uppercase bold title.',
        );
    }

    public function test_a_card_shows_the_photograph_staff_attached_in_preference_to_the_category_one(): void
    {
        $path = 'announcements/community-meeting.jpg';

        $announcement = Announcement::factory()->published()->withImage($path)->create([
            'category' => 'health',
        ]);

        $card = $this->cardElements($this->announcementsBand($this->homepage()))[0];

        $this->assertStringEndsWith('/storage/'.$path, $announcement->imageUrl());
        $this->assertStringContainsString('src="'.$announcement->imageUrl().'"', $card);
        $this->assertStringNotContainsString(config('bdrpis.images.community.health'), $card);
    }

    public function test_a_card_without_a_photograph_falls_back_to_its_category_image(): void
    {
        $categories = ['community' => 'community.cleanup', 'relief' => 'community.relief', 'general' => 'community.meeting'];

        foreach (array_keys($categories) as $category) {
            Announcement::factory()->published()->create(['category' => $category]);
        }

        $sources = array_map(
            $this->cardImageSource(...),
            $this->cardElements($this->announcementsBand($this->homepage())),
        );

        $this->assertSame(
            array_map(fn (string $image): string => asset(config('bdrpis.images.'.$image)), array_values($categories)),
            $sources,
        );
    }

    public function test_a_card_falls_back_to_the_neutral_artwork_when_its_photograph_is_missing(): void
    {
        config()->set('bdrpis.images.community.health', 'images/this-photograph-does-not-exist.jpg');

        Announcement::factory()->published()->create(['category' => 'health']);

        $card = $this->cardElements($this->announcementsBand($this->homepage()))[0];

        $this->assertStringNotContainsString('this-photograph-does-not-exist', $card);
        $this->assertStringContainsString(
            'src="'.asset(config('bdrpis.image_fallbacks.community.health')).'"',
            $card,
        );
    }

    public function test_a_card_with_no_category_renders_the_default_tag_and_photograph(): void
    {
        Announcement::factory()->published()->create(['category' => null]);

        $card = $this->cardElements($this->announcementsBand($this->homepage()))[0];

        $this->assertStringContainsString('General', $card);
        $this->assertStringContainsString(
            'src="'.asset(config('bdrpis.images.community.meeting')).'"',
            $card,
        );
    }

    public function test_no_announcement_ever_borrows_the_barangay_hall_photograph(): void
    {
        // The hall is where a notice is published from, not what a notice is
        // about, so it is not a card image: every category, the default key and
        // the neutral artwork behind them all have to stay clear of it.
        $forbidden = [
            config('bdrpis.images.about'),
            config('bdrpis.image_fallbacks.about'),
        ];

        $keys = array_map(
            fn (array $category): string => $category['image'],
            config('bdrpis.announcement_categories'),
        );

        $keys[] = config('bdrpis.announcement_default_image');

        foreach ($keys as $key) {
            $this->assertNotContains(config('bdrpis.images.'.$key), $forbidden, "Key [{$key}] points at the barangay hall.");
            $this->assertNotContains(config('bdrpis.image_fallbacks.'.$key), $forbidden, "Key [{$key}] falls back to the barangay hall.");
        }
    }

    public function test_the_band_features_at_most_three_announcements(): void
    {
        Announcement::factory()->published()->count(5)->create();

        $this->assertCount(
            Announcement::FEATURED_ON_HOMEPAGE,
            $this->cardElements($this->announcementsBand($this->homepage())),
            'The homepage grid must hold exactly the featured number of notices.',
        );
    }

    public function test_the_homepage_shows_the_most_recent_notices_first(): void
    {
        foreach (range(1, 3) as $daysAgo) {
            Announcement::factory()->published()->create([
                'title' => 'Notice '.(4 - $daysAgo),
                'published_at' => now()->subDays($daysAgo),
            ]);
        }

        $titles = array_map(
            $this->cardTitle(...),
            $this->cardElements($this->announcementsBand($this->homepage())),
        );

        $this->assertSame(['Notice 3', 'Notice 2', 'Notice 1'], $titles);
    }

    public function test_every_configured_category_resolves_to_a_label_and_an_existing_photograph(): void
    {
        foreach (config('bdrpis.announcement_categories') as $key => $category) {
            $this->assertNotEmpty($category['label'], "Category [{$key}] has no label.");

            $path = config('bdrpis.images.'.$category['image']);

            $this->assertNotEmpty($path, "Category [{$key}] points at no configured photograph.");
            $this->assertFileExists(public_path($path), "Category [{$key}] points at a missing photograph.");
        }
    }

    private function homepage(): string
    {
        return $this->get(route('home'))->assertOk()->getContent();
    }

    /**
     * The announcements band, from its section open tag to the end of the page
     * body, so the footer cannot contaminate the assertions.
     */
    private function announcementsBand(string $content): string
    {
        $start = strpos($content, 'id="announcements"');
        $end = strpos($content, '</footer>');

        $this->assertIsInt($start, 'The homepage no longer renders an announcements band.');

        return substr($content, $start, $end > $start ? $end - $start : null);
    }

    /**
     * Just the band's header block, up to the card grid, so an alignment
     * assertion can neither be satisfied nor broken by the cards below it.
     */
    private function announcementsHeader(string $content): string
    {
        $band = $this->announcementsBand($content);
        $end = strpos($band, 'class="mt-10"');

        $this->assertIsInt($end, 'The announcements band no longer renders a card grid below its header.');

        return substr($band, 0, $end);
    }

    /**
     * The card elements of the band, in render order.
     *
     * @return array<int, string>
     */
    private function cardElements(string $band): array
    {
        preg_match_all('/<article.*?<\/article>/s', $band, $matches);

        return $matches[0];
    }

    private function cardImageSource(string $card): string
    {
        preg_match('/<img src="([^"]+)"/', $card, $matches);

        return $matches[1] ?? '';
    }

    private function cardTitle(string $card): string
    {
        preg_match('/<h3[^>]*>\s*(.*?)\s*<\/h3>/s', $card, $matches);

        return trim($matches[1] ?? '');
    }
}
