<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every staff list shows everything until a filter is chosen.
 *
 * A page with no query string is not "a page with the default filter applied" — it
 * is a page showing every record there is. Two lists broke that:
 *
 *  - Resident Verification defaulted ?status to `pending`, so a profile that had
 *    already been verified or rejected was not on the page at all;
 *  - Document Requests defaulted to the `queue`, so anything approved, collected
 *    or rejected was absent.
 *
 * Both looked correct, because both narrowed to a *real* subset and the empty
 * state and the counts still worked. What they could not do was show the reader
 * that anything was missing.
 *
 * So each test here seeds one record of every status the filter can ask for, then
 * requests the bare route and asserts every one of them is present. A test that
 * only asserted "the pending one is here" would have passed against the old
 * default too, which is the whole problem.
 */
class StaffFiltersDefaultToAllTest extends TestCase
{
    use RefreshDatabase;

    /**
     * One record of every verification state.
     *
     * The factory has states for pending and verified but not for rejected, so that
     * one is written directly rather than leaving a status unrepresented - which is
     * the only way a defaulting filter could pass this file unnoticed.
     *
     * @return array{pending: ResidentProfile, verified: ResidentProfile, rejected: ResidentProfile}
     */
    private function everyVerificationState(): array
    {
        $rejected = ResidentProfile::factory()->create(['household_number' => 'HH-2003']);
        $rejected->forceFill(['verification_status' => ResidentProfile::VERIFICATION_REJECTED])->save();

        return [
            'pending' => ResidentProfile::factory()->pending()->create(['household_number' => 'HH-2001']),
            'verified' => ResidentProfile::factory()->create(['household_number' => 'HH-2002']),
            'rejected' => $rejected,
        ];
    }

    #[Test]
    public function resident_verification_with_no_query_string_shows_every_status(): void
    {
        $profiles = $this->everyVerificationState();

        $html = $this->staffPage('secretary', 'staff.verifications.index');

        foreach ($profiles as $state => $profile) {
            $this->assertStringContainsString(
                $profile->household_number,
                $html,
                "A {$state} profile is missing from the verification page with no filter applied."
            );
        }
    }

    #[Test]
    public function resident_verification_still_offers_the_pending_shortcut(): void
    {
        $profiles = $this->everyVerificationState();

        $html = $this->staffPage('secretary', 'staff.verifications.index', ['status' => 'pending']);

        $this->assertStringContainsString($profiles['pending']->household_number, $html);
        $this->assertStringNotContainsString($profiles['verified']->household_number, $html);
        $this->assertStringNotContainsString($profiles['rejected']->household_number, $html);
    }

    #[Test]
    public function the_verification_select_says_all_and_not_pending_review(): void
    {
        $this->everyVerificationState();

        $this->assertStringContainsString(
            '<option value="" selected>All</option>',
            $this->staffPage('secretary', 'staff.verifications.index'),
            'The status filter must open on All, or the select and the list disagree.'
        );
    }

    /**
     * @return list<DocumentRequest>
     */
    private function everyRequestStatus(): array
    {
        DocumentType::factory()->create();

        $resident = User::factory()->resident()->create();

        return [
            DocumentRequest::factory()->forResident($resident)->submitted()->create(),
            DocumentRequest::factory()->forResident($resident)->underReview()->create(),
            DocumentRequest::factory()->forResident($resident)->ready()->create(),
            DocumentRequest::factory()->forResident($resident)->claimed()->create(),
            DocumentRequest::factory()->forResident($resident)->rejected()->create(),
        ];
    }

    /**
     * @param  list<DocumentRequest>  $requests
     */
    #[Test]
    public function document_requests_with_no_query_string_shows_every_status(): void
    {
        $requests = $this->everyRequestStatus();

        $html = $this->staffPage('secretary', 'staff.requests.index');

        foreach ($requests as $request) {
            $this->assertStringContainsString(
                $request->reference_number,
                $html,
                'A request is missing from the queue with no filter applied.'
            );
        }
    }

    /**
     * @param  list<DocumentRequest>  $requests
     */
    #[Test]
    public function document_requests_still_offers_the_queue_shortcut(): void
    {
        $requests = $this->everyRequestStatus();

        $html = $this->staffPage('secretary', 'staff.requests.index', ['status' => 'queue']);

        $this->assertStringContainsString($requests[0]->reference_number, $html);
        $this->assertStringContainsString($requests[1]->reference_number, $html, 'under_review is part of the queue');
        $this->assertStringNotContainsString($requests[2]->reference_number, $html);
        $this->assertStringNotContainsString($requests[3]->reference_number, $html);
    }

    /**
     * @param  list<DocumentRequest>  $requests
     */
    #[Test]
    public function the_requests_select_says_everything(): void
    {
        $this->everyRequestStatus();

        $this->assertStringContainsString(
            '<option value="" selected>Everything</option>',
            $this->staffPage('secretary', 'staff.requests.index'),
        );
    }

    /**
     * Awaiting a decision first, then newest.
     *
     * The ordering is asserted rather than assumed, and with dates that make the
     * two candidate orders contradict each other: the oldest record overall is a
     * claimed one, so oldest-first would put it first and newest-first would put
     * the newest submitted one first. Only "actionable first, then newest" puts
     * the two submitted rows at the top in that order.
     *
     * @param  list<DocumentRequest>  $requests
     */
    #[Test]
    public function requests_needing_a_decision_come_first_then_newest(): void
    {
        $requests = $this->everyRequestStatus();
        $resident = $requests[0]->user;

        // Oldest record in the table, but decided: must not lead.
        $requests[3]->forceFill(['created_at' => now()->subDays(30)])->save();
        // Two queued, deliberately the other way round from their creation order.
        $requests[0]->forceFill(['created_at' => now()->subDays(2)])->save();
        $requests[1]->forceFill(['created_at' => now()->subDays(1)])->save();

        $order = $this->rowOrder(
            $this->staffPage('secretary', 'staff.requests.index'),
            array_map(fn (DocumentRequest $r): string => $r->reference_number, $requests),
        );

        $this->assertSame(
            [$requests[1]->reference_number, $requests[0]->reference_number],
            array_slice($order, 0, 2),
            'The queue should lead with the two requests awaiting a decision, newest first.'
        );

        $this->assertGreaterThan(
            1,
            array_search($requests[3]->reference_number, $order, true),
            'The oldest record is a claimed one, so it must sit below both queued requests.'
        );

        unset($resident);
    }

    #[Test]
    public function batches_with_no_query_string_shows_open_and_closed(): void
    {
        $active = ReliefBatch::factory()->create(['title' => 'September rice']);
        $closed = ReliefBatch::factory()->closed()->create(['title' => 'October goods']);

        $html = $this->staffPage('secretary', 'staff.batches.index');

        $this->assertStringContainsString($active->title, $html);
        $this->assertStringContainsString($closed->title, $html);
        $this->assertStringContainsString('<option value="" selected>Everything</option>', $html);
    }

    /**
     * The Show filter on Batches had no control at all, so ?status=active
     * narrowed the list invisibly and there was no way back.
     */
    #[Test]
    public function batches_offers_a_control_for_a_parameter_that_already_existed(): void
    {
        ReliefBatch::factory()->create(['title' => 'September rice']);
        ReliefBatch::factory()->closed()->create(['title' => 'October goods']);

        $this->assertStringContainsString(
            '<option value="" selected>Everything</option>',
            $this->staffPage('secretary', 'staff.batches.index'),
        );

        $closedOnly = $this->staffPage('secretary', 'staff.batches.index', ['status' => 'closed']);

        $this->assertStringContainsString('October goods', $closedOnly);
        $this->assertStringNotContainsString('September rice', $closedOnly);
    }

    /**
     * Open batches lead, because a closed batch is finished.
     */
    #[Test]
    public function open_batches_are_listed_before_closed_ones(): void
    {
        $closed = ReliefBatch::factory()->closed()->create(['title' => 'Older title, closed']);
        $active = ReliefBatch::factory()->create(['title' => 'Newer title, open']);

        // created_at is the default newest-first key, so without the state ordering
        // the active batch would be first anyway and the test would prove nothing.
        $closed->forceFill(['created_at' => now()])->save();
        $active->forceFill(['created_at' => now()->subDays(9)])->save();

        $order = $this->rowOrder(
            $this->staffPage('secretary', 'staff.batches.index'),
            [$active->title, $closed->title],
        );

        $this->assertSame([$active->title, $closed->title], $order);
    }

    #[Test]
    public function announcements_with_no_query_string_shows_every_state(): void
    {
        $published = Announcement::factory()->published()->create(['title' => 'Published notice']);
        $draft = Announcement::factory()->unpublished()->create(['title' => 'Draft notice']);
        $scheduled = Announcement::factory()->unpublished()->create([
            'title' => 'Scheduled notice', 'published_at' => now()->addWeek(),
        ]);

        $html = $this->staffPage('secretary', 'staff.announcements.index');

        foreach ([$published, $draft, $scheduled] as $notice) {
            $this->assertStringContainsString($notice->title, $html);
        }

        $this->assertStringContainsString('<option value="" selected>Everything</option>', $html);
    }

    /**
     * The old `all` sentinel is gone, but an old bookmark must not empty the list.
     */
    #[Test]
    public function an_old_all_state_bookmark_still_shows_everything(): void
    {
        $published = Announcement::factory()->published()->create(['title' => 'Published notice']);
        $draft = Announcement::factory()->unpublished()->create(['title' => 'Draft notice']);

        $html = $this->staffPage('secretary', 'staff.announcements.index', ['state' => 'all']);

        $this->assertStringContainsString($published->title, $html);
        $this->assertStringContainsString($draft->title, $html);
    }

    /**
     * The reported symptom: the select read "Resident" while the list held several
     * roles, because User::roles() ends with 'resident'.
     */
    #[Test]
    public function the_role_filter_opens_on_all_roles(): void
    {
        $this->userOfEveryRole();

        $html = $this->staffPage('admin', 'staff.accounts.index');

        $this->assertStringContainsString('<option value="" selected>All roles</option>', $html);
        $this->assertStringNotContainsString('<option value="resident" selected>', $html);
    }

    #[Test]
    public function user_accounts_with_no_query_string_shows_every_role(): void
    {
        $users = $this->userOfEveryRole();

        $html = $this->staffPage('admin', 'staff.accounts.index');

        foreach ($users as $user) {
            $this->assertStringContainsString(
                $user->email,
                $html,
                'An account is missing from User Accounts with no role filter applied.'
            );
        }
    }

    #[Test]
    public function the_role_filter_still_narrows(): void
    {
        $this->userOfEveryRole();

        $html = $this->staffPage('admin', 'staff.accounts.index', ['role' => 'kagawad']);

        $this->assertStringContainsString('<option value="kagawad" selected>', $html);
        $this->assertStringContainsString('Kagawad Test', $html);
        $this->assertStringNotContainsString('Admin Test', $html);
    }

    #[Test]
    public function residents_with_no_query_string_shows_every_sector(): void
    {
        $sectors = Sector::orderBy('id')->take(3)->get();

        foreach ($sectors as $sector) {
            ResidentProfile::factory()->create([
                'sector_id' => $sector->id, 'household_number' => 'HH-3'.$sector->id,
            ]);
        }

        $html = $this->staffPage('secretary', 'staff.residents.index');

        foreach ($sectors as $sector) {
            $this->assertStringContainsString(
                'HH-3'.$sector->id,
                $html,
                'A resident is missing from the directory with no sector filter applied.'
            );
        }

        $this->assertStringContainsString('<option value="" selected>All sectors</option>', $html);
    }

    /**
     * The sector filter submits an id, and the id narrows.
     *
     * It never could: the option rendered as value="Purok 5" because the component
     * decided an int key meant "this is a list of values", so the submitted name
     * became 0 and the controller read that as no filter at all.
     */
    #[Test]
    public function the_sector_filter_submits_an_id_and_narrows(): void
    {
        $sectors = Sector::orderBy('id')->take(2)->get();

        ResidentProfile::factory()->create(['sector_id' => $sectors[0]->id, 'household_number' => 'HH-4001']);
        ResidentProfile::factory()->create(['sector_id' => $sectors[1]->id, 'household_number' => 'HH-4002']);

        $unfiltered = $this->staffPage('secretary', 'staff.residents.index');
        $this->assertMatchesRegularExpression(
            '/<option value="'.$sectors[1]->id.'"[^>]*>'.preg_quote($sectors[1]->sector_name, '/').'/',
            $unfiltered,
            'A sector option must submit its id, not its name.'
        );

        $filtered = $this->staffPage('secretary', 'staff.residents.index', ['sector_id' => $sectors[1]->id]);

        $this->assertStringContainsString('HH-4002', $filtered);
        $this->assertStringNotContainsString('HH-4001', $filtered, 'The sector filter must actually narrow.');
    }

    #[Test]
    public function audit_logs_with_no_query_string_shows_every_action(): void
    {
        $one = AuditLog::factory()->action('resident.verified')->create(['description' => 'Ana was verified']);
        $two = AuditLog::factory()->action('relief.batch_closed')->create(['description' => 'September rice closed']);

        $html = $this->staffPage('admin', 'staff.audit.index');

        $this->assertStringContainsString($one->description, $html);
        $this->assertStringContainsString($two->description, $html);
        $this->assertStringContainsString('<option value="" selected>Every action</option>', $html);
        $this->assertStringContainsString('<option value="" selected>Anyone</option>', $html);
    }

    /**
     * The Account filter had the same int-key bug as Sector.
     */
    #[Test]
    public function the_audit_account_filter_submits_an_id_and_narrows(): void
    {
        $one = User::factory()->admin()->create(['name' => 'Alpha Admin']);
        $two = User::factory()->secretary()->create(['name' => 'Beta Secretary']);

        AuditLog::factory()->action('settings.updated')->create([
            'user_id' => $one->id, 'description' => 'Alpha changed the settings',
        ]);
        AuditLog::factory()->action('settings.updated')->create([
            'user_id' => $two->id, 'description' => 'Beta changed the settings',
        ]);

        $filtered = $this->staffPage('admin', 'staff.audit.index', ['actor' => $two->id]);

        $this->assertStringContainsString('Beta changed the settings', $filtered);
        $this->assertStringNotContainsString('Alpha changed the settings', $filtered);
    }

    /**
     * @return list<User>
     */
    private function userOfEveryRole(): array
    {
        return [
            User::factory()->admin()->create(['name' => 'Admin Test', 'email' => 'a1@bdrpis.test']),
            User::factory()->secretary()->create(['name' => 'Secretary Test', 'email' => 's1@bdrpis.test']),
            User::factory()->kagawad()->create(['name' => 'Kagawad Test', 'email' => 'k1@bdrpis.test']),
            User::factory()->resident()->create(['name' => 'Resident Test', 'email' => 'r1@bdrpis.test']),
        ];
    }

    // -----------------------------------------------------------------
    // helpers
    // -----------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $query
     */
    private function staffPage(string $state, string $route, array $query = []): string
    {
        $user = match ($state) {
            'admin' => User::factory()->admin()->create(['is_verified' => true]),
            default => User::factory()->secretary()->create(['is_verified' => true]),
        };

        return $this->actingAs($user)
            ->get(route($route, $query))
            ->assertOk()
            ->getContent();
    }

    /**
     * The order the given strings appear in the rendered table body.
     *
     * Compared as a whole rather than pairwise: pairwise passes for a sort that is
     * merely consistent with the pairs chosen, and the whole list is what actually
     * has to be right.
     *
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function rowOrder(string $html, array $needles): array
    {
        $body = $html;

        if (str_contains($html, '<table')) {
            $start = strpos($html, '<tbody');
            $end = strpos($html, '</tbody>');
            $body = $start !== false && $end !== false ? substr($html, $start, $end - $start) : $html;
        }

        $found = [];

        foreach ($needles as $needle) {
            $at = strpos($body, $needle);

            if ($at !== false) {
                $found[] = ['at' => $at, 'needle' => $needle];
            }
        }

        usort($found, fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        return array_column($found, 'needle');
    }
}
