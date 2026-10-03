<?php

namespace Tests\Feature;

use App\Models\DocumentDelivery;
use App\Models\DocumentRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * What the desk sees about digital copies.
 *
 * WHY THE STAFF SIDE HAS ITS OWN FILE
 *
 * The download itself is the resident's business. This is the other half: the
 * office has to be able to answer "who has a copy of this document, and when",
 * and to find the requests where a copy went out but nobody was ever collected.
 * That second question is the one the whole feature feeds, so it gets a filter of
 * its own and a test.
 *
 * The query-count test at the end is here rather than at the bottom of the
 * resident file because the thing it protects is this page.
 */
class StaffDocumentDeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // The numbers on the queue
    // ---------------------------------------------------------------------

    #[Test]
    public function the_queue_reports_copies_issued_and_how_many_requests_they_went_against(): void
    {
        // One resident took two copies and another took one: the two figures have to
        // say 3 and 2, not 3 and 3, because "three downloads" on its own would read
        // as three residents when it was two.
        $first = DocumentRequest::factory()->ready()->create();
        $second = DocumentRequest::factory()->ready()->create();

        DocumentDelivery::factory()->count(2)->forRequest($first)->create();
        DocumentDelivery::factory()->forRequest($second)->create();

        $content = $this->actingAsStaff()->get(route('staff.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('Digital copies issued', $content);
        $this->assertStringContainsString('across 2 requests', $content);
    }

    #[Test]
    public function a_row_says_how_many_copies_went_out_against_it(): void
    {
        $request = DocumentRequest::factory()->ready()->create();
        DocumentDelivery::factory()->count(2)->forRequest($request)->create();

        DocumentRequest::factory()->claimed()->create();

        $content = $this->actingAsStaff()->get(route('staff.requests.index'))->assertOk()->getContent();

        $this->assertStringContainsString('2 digital copies issued', $content);
        // A row nobody downloaded says nothing at all, rather than "0 digital
        // copies issued" on every row that has not been touched.
        $this->assertStringNotContainsString('0 digital copies issued', $content);
    }

    #[Test]
    public function a_request_with_no_copies_shows_no_badge_and_no_history(): void
    {
        $request = DocumentRequest::factory()->ready()->create();

        $queue = $this->actingAsStaff()->get(route('staff.requests.index'))->assertOk()->getContent();
        $record = $this->actingAsStaff()->get(route('staff.requests.show', $request))->assertOk()->getContent();

        $this->assertStringNotContainsString('digital copies issued', $queue);
        $this->assertStringNotContainsString('Digital copies issued', $record);
    }

    // ---------------------------------------------------------------------
    // Finding the copies that were not collected
    // ---------------------------------------------------------------------

    /**
     * The reason the filter exists, as a pair: a request that was downloaded but
     * never collected is worth the desk's attention, and a claimed one is not.
     */
    #[Test]
    public function the_downloaded_filter_finds_ready_and_unclaimed_requests_and_leaves_the_claimed_alone(): void
    {
        $ready = DocumentRequest::factory()->ready()->create(['reference_number' => 'BRGY-FILTER-READY']);
        $unclaimed = DocumentRequest::factory()->unclaimed()->create(['reference_number' => 'BRGY-FILTER-UNCLAIMED']);
        $claimed = DocumentRequest::factory()->claimed()->create(['reference_number' => 'BRGY-FILTER-CLAIMED']);

        foreach ([$ready, $unclaimed, $claimed] as $request) {
            DocumentDelivery::factory()->forRequest($request)->create();
        }

        $content = $this->actingAsStaff()
            ->get(route('staff.requests.index', ['status' => 'downloaded']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('BRGY-FILTER-READY', $content);
        $this->assertStringContainsString('BRGY-FILTER-UNCLAIMED', $content);
        $this->assertStringNotContainsString('BRGY-FILTER-CLAIMED', $content);
    }

    #[Test]
    public function the_downloaded_filter_leaves_out_requests_nobody_downloaded(): void
    {
        $undownloaded = DocumentRequest::factory()->ready()->create(['reference_number' => 'BRGY-NO-COPY']);

        $content = $this->actingAsStaff()
            ->get(route('staff.requests.index', ['status' => 'downloaded']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('BRGY-NO-COPY', $content);
    }

    /**
     * A filter that has to be chosen from a list of statuses is a filter somebody
     * will end up with a wrong value in a bookmarked URL.
     */
    #[Test]
    public function an_unknown_status_filter_is_not_widened_into_everything(): void
    {
        $ready = DocumentRequest::factory()->ready()->create(['reference_number' => 'BRGY-KNOWN']);
        DocumentRequest::factory()->submitted()->create(['reference_number' => 'BRGY-OTHER']);

        $content = $this->actingAsStaff()
            ->get(route('staff.requests.index', ['status' => 'not-a-status']))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('BRGY-KNOWN', $content);
        $this->assertStringNotContainsString('BRGY-OTHER', $content);
    }

    // ---------------------------------------------------------------------
    // The record page
    // ---------------------------------------------------------------------

    #[Test]
    public function the_record_page_lists_each_copy_newest_first(): void
    {
        $request = DocumentRequest::factory()->ready()->create();

        DocumentDelivery::factory()->forRequest($request)->downloadedAt('2026-01-05 09:00:00')->create();
        DocumentDelivery::factory()->forRequest($request)->downloadedAt('2026-03-17 14:30:00')->create();

        $content = $this->actingAsStaff()->get(route('staff.requests.show', $request))->assertOk()->getContent();

        $march = strpos($content, '17 Mar 2026');
        $january = strpos($content, '5 Jan 2026');

        $this->assertIsInt($march);
        $this->assertIsInt($january);
        $this->assertLessThan($january, $march);
    }

    /**
     * The history says who and when, and nothing about how.
     *
     * The delivery row has no password and no birthdate on it to leak, so this
     * asserts the absence of both from the page - the birthdate is the easiest of
     * the two to have dragged in for convenience.
     */
    #[Test]
    public function the_history_names_the_resident_and_not_their_password_or_birthdate(): void
    {
        $resident = User::factory()->resident()->create([
            'name' => 'Juan Dela Cruz Santos',
            'date_of_birth' => '1994-03-17',
        ]);

        $request = DocumentRequest::factory()->ready()->forResident($resident)->create();
        DocumentDelivery::factory()->forRequest($request)->create();

        $content = $this->actingAsStaff()->get(route('staff.requests.show', $request))->assertOk()->getContent();

        $this->assertStringContainsString('Juan Dela Cruz Santos', $content);
        $this->assertStringNotContainsString('03/17/94S', $content);
    }

    #[Test]
    public function a_resident_cannot_reach_the_staff_record_of_someone_elses_copy_history(): void
    {
        $resident = User::factory()->resident()->create(['is_verified' => true]);
        $request = DocumentRequest::factory()->ready()->create();
        DocumentDelivery::factory()->forRequest($request)->create();

        $this->actingAs($resident)->get(route('staff.requests.show', $request))->assertForbidden();
    }

    // ---------------------------------------------------------------------
    // It has to stay cheap
    // ---------------------------------------------------------------------

    /**
     * The count is a subquery on the page, not a loop over the rows.
     *
     * Twenty requests is two pages' worth at the page size, and the queue is the
     * page the desk opens all day. A per-row count would make this page's cost
     * grow with the queue, which is the one thing a queue page must not do - so the
     * number of queries is pinned instead of the shape of the code being trusted.
     */
    #[Test]
    public function the_queue_costs_the_same_whether_it_shows_one_request_or_twenty(): void
    {
        $this->seedTenRequests();

        $queries = $this->countQueriesForTheQueue();

        $this->seedTenMoreRequests();

        $this->assertSame(
            $queries,
            $this->countQueriesForTheQueue(),
            'The queue page issues a different number of queries for twenty requests than for ten.'
        );
    }

    private function countQueriesForTheQueue(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAsStaff()->get(route('staff.requests.index'))->assertOk();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    private function seedTenRequests(): void
    {
        DocumentRequest::factory()->count(10)->create();
    }

    private function seedTenMoreRequests(): void
    {
        DocumentRequest::factory()->count(10)->create();
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * A signed-in Secretary.
     *
     * Acting and returning together, because a bare get() on the model would be
     * read as an Eloquent "fetch the rows" rather than as a request to the queue.
     */
    private function actingAsStaff(): self
    {
        $this->actingAs(User::factory()->secretary()->create(['is_verified' => true]));

        return $this;
    }
}
