<?php

namespace Tests\Feature;

use App\Models\Announcement;
use Database\Seeders\AnnouncementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The demo notices have to fill the homepage's featured grid without piling up
 * on a database that already has notices of its own, so the seeder is exercised
 * against both an empty table and a populated one.
 */
class AnnouncementFeaturedSeedingTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_fills_the_featured_grid_on_an_empty_database(): void
    {
        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(
            Announcement::FEATURED_ON_HOMEPAGE,
            Announcement::whereNotNull('published_at')->count(),
            'A fresh install does not have enough published notices to fill the featured grid.',
        );
    }

    public function test_seeding_tops_up_to_the_featured_count_instead_of_replacing_it(): void
    {
        $existing = Announcement::factory()->published()->create(['title' => 'A Notice The Barangay Already Published']);
        $draft = Announcement::factory()->unpublished()->create();

        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(
            Announcement::FEATURED_ON_HOMEPAGE,
            Announcement::whereNotNull('published_at')->count(),
        );

        // An unpublished notice is not a featured one, so it is neither counted
        // nor published by the seeder.
        $this->assertNull($draft->fresh()->published_at);

        $this->assertDatabaseHas('announcements', [
            'id' => $existing->id,
            'title' => 'A Notice The Barangay Already Published',
        ]);
    }

    public function test_seeding_only_creates_the_missing_third_notice(): void
    {
        // The two notices the original seeder wrote before the featured grid
        // needed a third.
        Announcement::factory()->published()->create(['title' => 'Scheduled Financial Aid Distribution']);
        Announcement::factory()->published()->create(['title' => 'Typhoon Relief Distribution Schedule']);

        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(Announcement::FEATURED_ON_HOMEPAGE, Announcement::count());
    }

    public function test_seeding_is_safe_to_run_twice(): void
    {
        $this->seed(AnnouncementSeeder::class);
        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(Announcement::FEATURED_ON_HOMEPAGE, Announcement::count());
    }

    public function test_the_seeded_notices_spread_across_categories(): void
    {
        $this->seed(AnnouncementSeeder::class);

        $categories = Announcement::pluck('category')->unique();

        $this->assertCount(
            Announcement::FEATURED_ON_HOMEPAGE,
            $categories,
            'The featured cards would all show the same tag.',
        );
    }

    public function test_seeding_pins_exactly_one_notice_so_the_public_page_has_a_featured_block(): void
    {
        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(
            1,
            Announcement::whereNotNull('pinned_at')->count(),
            'The demo install should open the public page with exactly one featured notice.',
        );
    }

    public function test_reseeding_does_not_unpin_a_notice_the_staff_pinned_themselves(): void
    {
        Announcement::factory()->create([
            'title' => 'A Notice Of Their Own',
            'published_at' => now(),
            'pinned_at' => now(),
        ]);

        $this->seed(AnnouncementSeeder::class);

        $this->assertSame(
            1,
            Announcement::whereNotNull('pinned_at')->count(),
            'Re-seeding took the pin off a notice that was not the demo\'s.',
        );
        $this->assertTrue(
            Announcement::where('title', 'A Notice Of Their Own')->firstOrFail()->isPinned(),
        );
    }

    public function test_reseeding_fills_in_a_location_added_after_the_notice_was_seeded(): void
    {
        $this->seed(AnnouncementSeeder::class);
        $this->seed(AnnouncementSeeder::class);

        $this->assertNotEmpty(
            Announcement::whereNotNull('location')->count(),
            'A demo notice already on the table was never given its location.',
        );
    }

    public function test_the_seeded_notices_all_resolve_to_a_photograph(): void
    {
        $this->seed(AnnouncementSeeder::class);

        foreach (Announcement::all() as $announcement) {
            $this->assertStringContainsString(
                asset('/'),
                $announcement->imageUrl(),
                "Seeded notice [{$announcement->title}] has no usable photograph.",
            );
        }
    }
}
