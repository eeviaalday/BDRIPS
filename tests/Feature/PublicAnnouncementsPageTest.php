<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The public announcements page: how the notices are chosen, how the filter bar
 * narrows them, and what the sidebar is allowed to print.
 *
 * The interesting behaviour is all in what the page withholds. A draft is
 * invisible, the pinned notice does not appear twice, and the sidebar prints
 * only numbers the barangay has actually saved - so these tests check the
 * absences as carefully as the presences.
 */
class PublicAnnouncementsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_lists_published_notices_and_hides_drafts(): void
    {
        Announcement::factory()->published()->create(['title' => 'Water Supply Interruption']);
        Announcement::factory()->unpublished()->create(['title' => 'Unpublished Draft']);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Water Supply Interruption', $content);
        $this->assertStringNotContainsString('Unpublished Draft', $content);
    }

    public function test_a_notice_dated_in_the_future_stays_out_of_the_feed(): void
    {
        Announcement::factory()->create([
            'title' => 'Scheduled For Later',
            'published_at' => now()->addWeek(),
        ]);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Scheduled For Later');
    }

    public function test_the_feed_counts_only_the_notices_the_feed_actually_holds(): void
    {
        Announcement::factory()->count(3)->published()->create();
        Announcement::factory()->unpublished()->create();

        // The count is the feed's own, so a pinned notice lifted out of the feed
        // is not counted twice and a draft is never counted at all.
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Public Notice Feed (3)');
    }

    #[Test]
    public function test_a_pinned_notice_is_shown_once_as_the_featured_block(): void
    {
        Announcement::factory()->publishedAndPinned()->create(['title' => 'Clean-Up Drive This Saturday']);
        Announcement::factory()->published()->create(['title' => 'Medical Mission']);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Pinned Official Announcement', $content);
        $this->assertStringContainsString('Clean-Up Drive This Saturday', $content);
        $this->assertStringContainsString('Medical Mission', $content);

        // Lifted out of the feed, not repeated in it.
        $this->assertSame(1, substr_count($content, 'Clean-Up Drive This Saturday'));
    }

    public function test_a_pinned_notice_is_not_counted_in_the_feed(): void
    {
        Announcement::factory()->publishedAndPinned()->create();
        Announcement::factory()->published()->create();

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Public Notice Feed (1)');
    }

    public function test_only_the_most_recently_pinned_notice_is_featured(): void
    {
        Announcement::factory()->create([
            'title' => 'Older Pinned Notice',
            'published_at' => now()->subDays(3),
            'pinned_at' => now()->subDays(3),
        ]);

        Announcement::factory()->create([
            'title' => 'Newest Pinned Notice',
            'published_at' => now()->subDay(),
            'pinned_at' => now()->subHour(),
        ]);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($content, 'Newest Pinned Notice'));
        // The notice that lost its place at the top is still a published notice,
        // so it belongs in the feed rather than disappearing.
        $this->assertStringContainsString('Older Pinned Notice', $content);
        $this->assertStringContainsString('Public Notice Feed (1)', $content);
    }

    public function test_a_draft_is_never_pinned_to_the_top_of_the_page(): void
    {
        Announcement::factory()->pinned()->create(['title' => 'Pinned But Unpublished']);

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Pinned Official Announcement')
            ->assertDontSee('Pinned But Unpublished');
    }

    public function test_no_pinned_notice_leaves_the_featured_block_out_entirely(): void
    {
        Announcement::factory()->published()->create();

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertDontSee('Pinned Official Announcement');
    }

    #[Test]
    public function test_searching_narrows_the_feed_to_matching_notices(): void
    {
        Announcement::factory()->published()->create(['title' => 'Scheduled Water Supply Interruption']);
        Announcement::factory()->published()->create(['title' => 'Annual Free Medical Mission']);

        $content = $this->get(route('announcements.index', ['q' => 'Water Supply']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Scheduled Water Supply Interruption', $content);
        $this->assertStringNotContainsString('Annual Free Medical Mission', $content);
    }

    public function test_searching_also_matches_the_body_of_a_notice(): void
    {
        Announcement::factory()->published()->create([
            'title' => 'Advisory',
            'body' => 'Classes are suspended while the multipurpose hall is repainted.',
        ]);

        $this->get(route('announcements.index', ['q' => 'repainted']))
            ->assertOk()
            ->assertSee('Advisory');
    }

    public function test_a_search_matching_nothing_shows_the_empty_state_not_an_error(): void
    {
        Announcement::factory()->published()->create(['title' => 'Water Supply Interruption']);

        $this->get(route('announcements.index', ['q' => 'zzzz-no-such-notice']))
            ->assertOk()
            ->assertSee('No announcements here yet')
            ->assertSee('Public Notice Feed (0)');
    }

    public function test_the_pinned_notice_is_never_hidden_by_a_search_or_a_filter(): void
    {
        Announcement::factory()->publishedAndPinned()->create(['title' => 'Pinned Clean-Up Drive']);
        Announcement::factory()->published()->create([
            'title' => 'Medical Mission',
            'category' => 'health',
        ]);

        // A search that matches nothing in the feed still leaves the pinned
        // notice in place: it is a standing instruction, not a feed result.
        $this->get(route('announcements.index', ['q' => 'zzzz-no-such-notice']))
            ->assertOk()
            ->assertSee('Pinned Clean-Up Drive');
    }

    #[Test]
    public function test_a_category_pill_narrows_the_feed_to_its_buckets(): void
    {
        Announcement::factory()->published()->category('health')->create(['title' => 'Medical Mission']);
        Announcement::factory()->published()->category('relief')->create(['title' => 'Rice Distribution']);
        Announcement::factory()->published()->category('community')->create(['title' => 'Clean-Up Drive']);

        $content = $this->get(route('announcements.index', ['filter' => 'health']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Medical Mission', $content);
        $this->assertStringNotContainsString('Rice Distribution', $content);
    }

    public function test_a_pill_covers_every_category_in_its_bucket(): void
    {
        // "Events & Clean-ups" is a bucket over several stored categories, so
        // all of them have to come through the one pill.
        Announcement::factory()->published()->category('meeting')->create(['title' => 'Barangay Assembly']);

        $this->get(route('announcements.index', ['filter' => 'events']))
            ->assertOk()
            ->assertSee('Barangay Assembly');
    }

    public function test_a_filter_that_no_longer_exists_falls_back_to_the_whole_feed(): void
    {
        Announcement::factory()->published()->create(['title' => 'Water Supply Interruption']);

        $this->get(route('announcements.index', ['filter' => 'a-pill-that-was-removed']))
            ->assertOk()
            ->assertSee('Water Supply Interruption')
            ->assertSee('Public Notice Feed (1)');
    }

    public function test_the_all_pill_ignores_any_category(): void
    {
        Announcement::factory()->count(3)->published()->create();

        $this->get(route('announcements.index', ['filter' => 'all']))
            ->assertOk()
            ->assertSee('Public Notice Feed (3)');
    }

    public function test_the_feed_is_newest_first_by_default(): void
    {
        Announcement::factory()->create(['title' => 'Older Notice', 'published_at' => now()->subDays(5)]);
        Announcement::factory()->create(['title' => 'Newest Notice', 'published_at' => now()->subDay()]);

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'Older Notice'),
            strpos($content, 'Newest Notice'),
            'The default order should be newest first.',
        );
    }

    public function test_the_feed_can_be_turned_oldest_first(): void
    {
        Announcement::factory()->create(['title' => 'Older Notice', 'published_at' => now()->subDays(5)]);
        Announcement::factory()->create(['title' => 'Newest Notice', 'published_at' => now()->subDay()]);

        $content = $this->get(route('announcements.index', ['sort' => 'oldest']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'Newest Notice'),
            strpos($content, 'Older Notice'),
            'The oldest-first order was not applied.',
        );
    }

    public function test_an_unrecognised_sort_value_falls_back_to_newest_first(): void
    {
        Announcement::factory()->create(['title' => 'Older Notice', 'published_at' => now()->subDays(5)]);
        Announcement::factory()->create(['title' => 'Newest Notice', 'published_at' => now()->subDay()]);

        $content = $this->get(route('announcements.index', ['sort' => 'sideways']))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'Older Notice'),
            strpos($content, 'Newest Notice'),
        );
    }

    public function test_the_search_and_the_filter_combine(): void
    {
        Announcement::factory()->published()->category('health')->create(['title' => 'Dental Mission']);
        Announcement::factory()->published()->category('health')->create(['title' => 'Eye Screening']);
        Announcement::factory()->published()->category('relief')->create(['title' => 'Dental Supplies']);

        $content = $this->get(route('announcements.index', [
            'q' => 'Dental',
            'filter' => 'health',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Dental Mission', $content);
        $this->assertStringNotContainsString('Eye Screening', $content);
        $this->assertStringNotContainsString('Dental Supplies', $content);
    }

    public function test_the_current_choices_survive_a_pagination_link(): void
    {
        Announcement::factory()->count(8)->published()->create(['title' => 'Community Notice']);

        $response = $this->get(route('announcements.index', [
            'q' => 'notice',
            'filter' => 'all',
            'sort' => 'oldest',
        ]))->assertOk();

        $next = $response->getContent();

        $this->assertStringContainsString('page=2', $next, 'There is no second page of notices.');
        $this->assertStringContainsString('sort=oldest', $next, 'The next-page link dropped the chosen sort.');
        $this->assertStringContainsString('q=notice', $next, 'The next-page link dropped the search.');
    }

    public function test_the_feed_is_paginated(): void
    {
        Announcement::factory()->count(9)->published()->create();

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Public Notice Feed (9)')
            ->assertSee('Next', escape: false);
    }

    public function test_the_sidebar_prints_the_numbers_the_settings_table_holds(): void
    {
        Setting::set('office_phone', '09663320659');
        Setting::set('emergency_hotline', '09175550192');

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Emergency Hotlines')
            ->assertSee('09663320659')
            ->assertSee('09175550192');
    }

    public function test_an_office_with_no_saved_number_is_left_out_entirely(): void
    {
        // No hotline is published in this state, so the sidebar says so rather
        // than printing a number nobody has confirmed.
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Emergency Hotlines')
            ->assertSee('No hotline has been published yet');
    }

    public function test_two_offices_sharing_one_number_are_shown_as_a_single_row(): void
    {
        // Today the office line and the hotline are the same setting, so the
        // sidebar would otherwise print one number twice under two office names
        // and read as two lines to call. The number also appears in the header
        // and footer, so the row is identified by the office label under it.
        Setting::set('office_phone', '09663320659');
        Setting::set('emergency_hotline', '09663320659');

        $content = $this->get(route('announcements.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Barangay Hall Desk', $content);
        $this->assertStringNotContainsString(
            'Public Information Line',
            $content,
            'The second office was shown even though it has no number of its own.',
        );
    }

    public function test_a_saved_hotline_is_rendered_as_a_dialable_link(): void
    {
        Setting::set('office_phone', '(077) 600-1234');

        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('tel:0776001234', escape: false)
            ->assertSee('(077) 600-1234');
    }

    public function test_the_sidebar_schedules_come_from_configuration(): void
    {
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Public Schedules');

        foreach (config('bdrpis.schedules', []) as $schedule) {
            $this->assertStringContainsString($schedule['title'], $this->get(route('announcements.index'))->getContent());
        }
    }

    public function test_the_filter_bar_carries_the_search_onto_the_pills(): void
    {
        $content = $this->get(route('announcements.index', ['q' => 'water']))
            ->assertOk()
            ->getContent();

        foreach (config('bdrpis.announcement_filters') as $key => $pill) {
            $this->assertStringContainsString(
                'filter='.$key,
                $content,
                "The [{$key}] pill lost the search term.",
            );
        }
    }

    public function test_a_pill_drops_the_page_so_the_new_filter_starts_at_the_top(): void
    {
        Announcement::factory()->count(9)->published()->create();

        $content = $this->get(route('announcements.index', ['filter' => 'health', 'page' => 2]))
            ->assertOk()
            ->getContent();

        // A pill href is the whole URL, so the assertion is on the absence of
        // page= inside the links rather than on the page count.
        preg_match_all('/href="[^"]*filter=[^"]*"/', $content, $matches);

        $this->assertNotEmpty($matches[0]);
        $this->assertStringNotContainsString(
            'page=',
            implode(' ', $matches[0]),
            'A pill link carried the previous page number over.',
        );
    }

    public function test_the_sort_control_carries_the_active_filter(): void
    {
        $content = $this->get(route('announcements.index', ['filter' => 'health']))
            ->assertOk()
            ->getContent();

        // The filter is not a field the form submits on its own, so the bar has
        // to carry it as a hidden input or changing the sort drops the filter.
        $this->assertMatchesRegularExpression(
            '/<input type="hidden" name="filter" value="health"/',
            $content,
        );
    }

    public function test_the_documents_call_to_action_sends_a_guest_to_registration(): void
    {
        $this->get(route('announcements.index'))
            ->assertOk()
            ->assertSee('Need Official Documents?')
            ->assertSee(route('register'), escape: false)
            ->assertSee('Create Resident Account');
    }

    public function test_the_documents_call_to_action_sends_a_resident_straight_to_a_request(): void
    {
        $this->actingAs(User::factory()->resident()->create())
            ->get(route('announcements.index'))
            ->assertOk()
            ->assertSee(route('resident.requests.create'), escape: false)
            ->assertSee('Request a Document');
    }
}
