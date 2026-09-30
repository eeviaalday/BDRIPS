<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo notices for the public portal.
 *
 * The homepage features exactly Announcement::FEATURED_ON_HOMEPAGE notices, so
 * this seeder tops the table up to that many published notices rather than
 * assuming an empty one. It is safe to re-run: a notice that already exists is
 * left alone, and nothing is created once the featured count is met.
 *
 * Re-running also finishes a demo install that was seeded before the location
 * and pin columns existed, so an older demo gains the featured block too. It
 * never takes a pin away: the one notice the demo pins is only pinned on an
 * install where nothing is pinned at all, so a notice the staff pinned
 * themselves survives.
 */
class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $author = $this->author();

        foreach ($this->notices() as $notice) {
            $existing = Announcement::where('title', $notice['title'])->first();

            // The original two demo notices predate the category column. Filling
            // them in keeps the demo grid showing a spread of tags instead of
            // the default one on every card.
            if ($existing) {
                $existing->update([
                    'category' => $notice['category'],
                    'location' => $existing->location ?? ($notice['location'] ?? null),
                    'pinned_at' => $existing->pinned_at ?? $this->demoPin($notice),
                ]);

                continue;
            }

            if ($this->publishedCount() >= Announcement::FEATURED_ON_HOMEPAGE) {
                break;
            }

            Announcement::create([
                'title' => $notice['title'],
                'body' => $notice['body'],
                'category' => $notice['category'],
                'location' => $notice['location'] ?? null,
                'published_at' => now()->subDays($notice['days_ago']),
                'pinned_at' => $this->demoPin($notice),
                'created_by_user_id' => $author?->id,
            ]);
        }
    }

    /**
     * The sample notices, newest last. Categories are spread on purpose so the
     * three featured cards never show the same tag or the same photograph. Only
     * the first carries a location, and only the first is pinned: the public
     * page has one featured block, so a second pin would simply be ignored.
     *
     * @return array<int, array{title: string, body: string, category: string, days_ago: int, location?: string, pinned?: bool}>
     */
    private function notices(): array
    {
        return [
            [
                'title' => 'Scheduled Financial Aid Distribution',
                'body' => 'The financial assistance distribution for qualified senior citizens and PWDs will take place on the second Friday of this month at the Barangay Hall, 8:00 AM to 4:00 PM. Please bring your valid IDs and Household Reference Numbers.',
                'category' => 'senior',
                'days_ago' => 2,
                'location' => 'Barangay Hall',
                'pinned' => true,
            ],
            [
                'title' => 'Typhoon Relief Distribution Schedule',
                'body' => "Relief goods for the 'Typhoon Relief Drive' will be distributed per sector starting this week. Kagawads will log household claims. Check the Transparency Board online using your household number.",
                'category' => 'relief',
                'days_ago' => 1,
            ],
            [
                'title' => 'Community Clean-Up Drive This Saturday',
                'body' => 'Volunteers are meeting at the basketball court at 6:00 AM on Saturday for the monthly clean-up drive. Gloves and sacks are provided; please bring your own drinking water and wear comfortable shoes.',
                'category' => 'community',
                'days_ago' => 0,
            ],
        ];
    }

    /**
     * The pin stamp to give a demo notice.
     *
     * Only when nothing is pinned yet, and only for the one notice that is
     * meant to be: this runs on every re-seed, and the featured block belongs to
     * whichever notice the barangay pinned by hand if there already is one.
     */
    private function demoPin(array $notice): ?Carbon
    {
        if (! ($notice['pinned'] ?? false) || Announcement::whereNotNull('pinned_at')->exists()) {
            return null;
        }

        return now();
    }

    private function publishedCount(): int
    {
        return Announcement::whereNotNull('published_at')->count();
    }

    /**
     * The staff member credited as the author of the demo notices. Absent on a
     * database that has not been seeded with users yet, in which case the
     * notices are simply left uncredited.
     */
    private function author(): ?User
    {
        return User::query()->whereIn('role', ['secretary', 'admin'])->first();
    }
}
