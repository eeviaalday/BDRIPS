<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ReliefDistribution;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\ReadsDashboardWidgets;
use Tests\TestCase;

/**
 * The staff dashboard as a working screen.
 *
 * Three separate things are being pinned here, and it is worth saying why each
 * one has its own failure mode:
 *
 *  - Visibility. A Secretary must get the working widgets and must NOT get the
 *    Admin strip, because the strip links three routes that are behind
 *    role:admin. A strip that leaked would not 500 - it would render three links
 *    that 403, which looks exactly like a broken product.
 *  - The empty database. A dashboard that assumes data exists is fine right up
 *    until the first install with no residents, where it 500s on the screen a
 *    brand new Secretary sees first.
 *  - Query count. Asserted as a *constant* rather than a ceiling, because a
 *    ceiling passes forever while an N+1 quietly grows inside it. The property
 *    that actually matters is that the count does not change when rows are
 *    added, and that is what is measured.
 */
class StaffDashboardWidgetsTest extends TestCase
{
    use ReadsDashboardWidgets;
    use RefreshDatabase;

    // ---------------------------------------------------------------- presence

    #[Test]
    public function the_stale_building_notice_is_gone(): void
    {
        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertDontSee('The rest of the staff area is still being built');
    }

    #[Test]
    public function the_header_greets_by_first_name_with_todays_date(): void
    {
        $response = $this->actingAsStaff(['name' => 'Ana Bautista'])->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Here is what is waiting on you.');
        $response->assertSee(now('Asia/Manila')->format('l, j F Y'));

        // Scoped to the heading on purpose. The full name is *supposed* to appear
        // elsewhere - the top bar shows who is signed in - so asserting the whole
        // page does not contain it would be asserting a lie. What matters is that
        // the greeting itself is addressed to a first name.
        $this->assertMatchesRegularExpression(
            '/<h1[^>]*>\s*Good (morning|afternoon|evening), Ana\s*<\/h1>/',
            $response->getContent()
        );
    }

    #[Test]
    public function the_greeting_handles_a_user_with_no_space_in_their_name(): void
    {
        // Str::before on a single-word name returns the whole name, which is
        // right, but it is the kind of thing that breaks on a mononym.
        $response = $this->actingAsStaff(['name' => 'Tala'])->get(route('staff.dashboard'));

        $response->assertOk();
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Good \w+, Tala\s*<\/h1>/', $response->getContent());
    }

    #[Test]
    public function the_greeting_follows_the_manila_clock(): void
    {
        // The greeting is computed on the server from Asia/Manila, so it is
        // pinned here rather than asserted loosely: a greeting that used the app
        // timezone would say "morning" at 8pm in Manila and the test would still
        // pass if it only ever checked that a greeting appeared.
        $cases = [
            ['07:30', 'Good morning'],
            ['11:59', 'Good morning'],
            ['12:00', 'Good afternoon'],
            ['17:59', 'Good afternoon'],
            ['18:00', 'Good evening'],
            ['23:00', 'Good evening'],
        ];

        foreach ($cases as [$time, $expected]) {
            $this->travelTo(Carbon::parse("2026-03-10 {$time}:00", 'Asia/Manila'));

            $response = $this->actingAsStaff()->get(route('staff.dashboard'));

            $response->assertOk();
            $response->assertSee($expected, escape: false);
        }
    }

    // ------------------------------------------------------------- the widgets

    #[Test]
    public function a_secretary_sees_every_working_widget(): void
    {
        $this->seedQueues();

        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Needs attention');
        $response->assertSee('Latest pending verifications');
        $response->assertSee('Requests awaiting decision');
        $response->assertSee('Ready for pickup');
        $response->assertSee('Relief right now');
        $response->assertSee('Quick actions');
        $response->assertSee('Open a relief batch');
        $response->assertSee('New announcement');
        $response->assertSee('View residents');
    }

    #[Test]
    public function the_stat_cards_link_to_the_queues_they_count(): void
    {
        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee(route('staff.verifications.index', ['status' => 'pending']), escape: false);
        $response->assertSee(route('staff.requests.index', ['status' => 'queue']), escape: false);
        $response->assertSee(route('staff.requests.index', ['status' => 'ready_for_pickup']), escape: false);
        $response->assertSee(route('staff.batches.index'), escape: false);
    }

    #[Test]
    public function the_stat_cards_count_the_queues_they_name(): void
    {
        $sector = Sector::factory()->create();
        $user = User::factory()->create();

        // 2 pending registrations, 1 submitted request, 1 ready, 1 active batch.
        ResidentProfile::factory()->count(2)->pending()->for($user)->for($sector)->create();
        DocumentRequest::factory()->count(1)->submitted()->create();
        DocumentRequest::factory()->count(1)->ready()->create();
        ReliefBatch::factory()->count(1)->create();

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        // Expected counts come from the same predicates the controller uses, so a
        // change to what "pending" means fails here instead of silently making
        // the card wrong.
        $expected = $this->expectedQueueCounts();

        $this->assertSame(2, $expected['Verifications pending'], 'The fixture did not build two pending registrations.');
        $this->assertSame(1, $expected['Requests to review']);
        $this->assertSame(1, $expected['Ready for pickup']);
        $this->assertSame(1, $expected['Active relief batches']);

        foreach ($expected as $label => $count) {
            $this->assertSame(
                (string) $count,
                $this->statCardValue($html, $label),
                "The [{$label}] card disagrees with the database."
            );
        }
    }

    #[Test]
    public function the_verifications_card_hints_at_how_long_the_oldest_has_waited(): void
    {
        $user = User::factory()->create();
        $sector = Sector::factory()->create();

        // 3 days ago, in Manila terms.
        $old = ResidentProfile::factory()->pending()->for($user)->for($sector)->create();
        $old->forceFill(['created_at' => now('Asia/Manila')->subDays(3)])->save();

        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('oldest waiting 3 days', escape: false);
    }

    #[Test]
    public function the_verifications_card_does_not_singularise_or_invent_a_wait(): void
    {
        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        // Nothing pending: no hint at all, rather than "oldest waiting 0 days".
        $response->assertDontSee('oldest waiting', escape: false);
    }

    #[Test]
    public function the_pending_list_shows_at_most_five_oldest_first_and_links_to_review(): void
    {
        $sector = Sector::factory()->create(['sector_name' => 'Poblacion']);

        foreach (range(1, 7) as $offset) {
            $user = User::factory()->create(['name' => "Resident {$offset}"]);
            $profile = ResidentProfile::factory()->pending()->for($user)->for($sector)->create(['household_number' => "HH-{$offset}"]);
            $profile->forceFill(['created_at' => now()->subDays(10 - $offset)])->save();
        }

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        // The five oldest are 1..5; 6 and 7 are cut off the list.
        $this->assertStringContainsString('HH-5', $html);
        $this->assertStringNotContainsString('HH-6', $html);
        $this->assertStringNotContainsString('HH-7', $html);

        // Sector and submitted date are both rendered.
        $this->assertStringContainsString('Poblacion', $html);

        $first = ResidentProfile::where('verification_status', ResidentProfile::VERIFICATION_PENDING)
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        $this->assertStringContainsString(route('staff.verifications.show', $first), $html);
    }

    #[Test]
    public function the_awaiting_list_shows_reference_resident_document_and_status(): void
    {
        $documentType = DocumentType::factory()->create(['label' => 'Barangay Clearance']);
        $request = DocumentRequest::factory()->submitted()
            ->forResident(User::factory()->create(['name' => 'Rosa Lim']))
            ->for($documentType)
            ->create(['reference_number' => 'BR-2026-0001']);

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        $this->assertStringContainsString('BR-2026-0001', $html);
        $this->assertStringContainsString('Rosa Lim', $html);
        $this->assertStringContainsString('Barangay Clearance', $html);
        // The status badge's own wording, not just a colour.
        $this->assertMatchesRegularExpression('/Submitted/i', $html);
        $this->assertStringContainsString(route('staff.requests.show', $request), $html);
    }

    #[Test]
    public function the_ready_list_shows_reference_and_resident(): void
    {
        DocumentRequest::factory()->ready()
            ->forResident(User::factory()->create(['name' => 'Dan Oribello']))
            ->create(['reference_number' => 'BR-2026-0002']);

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        $this->assertStringContainsString('BR-2026-0002', $html);
        $this->assertStringContainsString('Dan Oribello', $html);
    }

    #[Test]
    public function relief_now_shows_progress_as_collected_over_total_for_each_active_batch(): void
    {
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create(['title' => 'October rice']);

        // 4 households, 3 collected.
        foreach (range(1, 4) as $n) {
            $distribution = ReliefDistribution::factory()->forBatch($batch)->forSector($sector)->create([
                'household_number' => "H-{$n}",
            ]);
            $n <= 3 ? $distribution->forceFill(['status' => ReliefDistribution::STATUS_RECEIVED])->save() : null;
        }

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        $this->assertStringContainsString('October rice', $html);
        $this->assertStringContainsString('3 / 4', $html);
        $this->assertStringContainsString(route('staff.batches.show', $batch), $html);
    }

    #[Test]
    public function relief_now_renders_a_zero_bar_for_a_batch_with_no_distributions(): void
    {
        ReliefBatch::factory()->create(['title' => 'Just opened']);

        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        // The failure this guards is a division by zero, or a missing key.
        $response->assertOk();
        $response->assertSee('Just opened');
        $response->assertSee('0 / 0', escape: false);
    }

    #[Test]
    public function relief_now_shows_at_most_three_active_batches(): void
    {
        foreach (range(1, 5) as $n) {
            ReliefBatch::factory()->create(['title' => "Batch {$n}"]);
        }

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        $this->assertStringContainsString('Batch 1', $html);
        $this->assertStringNotContainsString('Batch 4', $html);
        $this->assertStringNotContainsString('Batch 5', $html);
    }

    #[Test]
    public function a_closed_batch_is_not_relief_right_now(): void
    {
        ReliefBatch::factory()->closed()->create(['title' => 'Finished batch']);

        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Finished batch');
    }

    // ------------------------------------------------------------- admin strip

    #[Test]
    public function a_secretary_does_not_see_the_admin_strip(): void
    {
        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertDontSee('For admins');
        $response->assertDontSee('Recent activity');
        $response->assertDontSee(route('staff.audit.index'), escape: false);
        $response->assertDontSee(route('staff.accounts.index'), escape: false);
        $response->assertDontSee(route('staff.sectors.index'), escape: false);
        $response->assertDontSee(route('staff.settings.index'), escape: false);
    }

    #[Test]
    public function an_admin_sees_the_admin_strip(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        AuditLog::factory()->count(3)->create();

        $response = $this->actingAs($admin)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('For admins');
        $response->assertSee('Recent activity');
        $response->assertSee('Accounts');
        $response->assertSee(route('staff.audit.index'), escape: false);
        $response->assertSee(route('staff.accounts.index'), escape: false);
        $response->assertSee(route('staff.sectors.index'), escape: false);
        $response->assertSee(route('staff.settings.index'), escape: false);

        // The strip is four cards. The fifth used to be System health, linking to
        // the removed System Status page.
        //
        // Counted inside the strip's own markup, NOT the whole document: every one
        // of these routes also appears in the sidebar and the phone bottom bar, so
        // counting the page as a whole gives 12 for four cards - a number that
        // would have "passed" any count of 4x the truth.
        $html = $response->getContent();

        $this->assertSame(
            1,
            preg_match('#<section[^>]*aria-labelledby="admin-strip".*?</section>#s', $html, $strip),
            'The admin strip section is gone.'
        );

        $this->assertSame(
            4,
            preg_match_all('#<a [^>]*href="#', $strip[0]),
            'The admin strip should have exactly four cards.'
        );
    }

    #[Test]
    public function an_admin_sees_the_working_widgets_as_well_as_the_strip(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Needs attention');
        $response->assertSee('Relief right now');
        $response->assertSee('Quick actions');
        $response->assertSee('For admins');
    }

    #[Test]
    public function the_role_counts_cover_every_role_even_at_zero(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $html = $this->actingAs($admin)->get(route('staff.dashboard'))->getContent();

        foreach (User::roles() as $role) {
            $this->assertStringContainsString(ucfirst($role), $html, "Role [{$role}] missing from the accounts card.");
        }
    }

    // --------------------------------------------------------- empty database

    #[Test]
    public function the_dashboard_renders_on_an_empty_database(): void
    {
        $response = $this->actingAsStaff()->get(route('staff.dashboard'));

        $response->assertOk();

        // Every widget's empty state, not just "the page did not blow up".
        $response->assertSee('No registrations waiting.');
        $response->assertSee('No requests waiting.');
        $response->assertSee('Nothing ready for pickup.');
        $response->assertSee('No active relief batch.');
    }

    #[Test]
    public function the_admin_strip_renders_on_an_empty_database(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('For admins');
        $response->assertSee('Nothing logged yet.');
    }

    // ---------------------------------------------------------- query budget

    #[Test]
    public function the_query_count_does_not_grow_with_the_number_of_rows(): void
    {
        // The property that matters. Not "under 40 queries" - a ceiling that an
        // N+1 hides inside - but: adding rows must not add queries. Run with one
        // of everything, then with twenty, and the two counts must be equal.
        $queriesWith = function (int $n) use (&$counts): int {
            $this->seedQueues($n);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAsStaff()->get(route('staff.dashboard'))->assertOk();

            $count = count(DB::getQueryLog());

            DB::disableQueryLog();

            return $count;
        };

        $small = $queriesWith(1);
        $this->refreshDatabase();
        $large = $queriesWith(20);

        $this->assertSame(
            $small,
            $large,
            "The dashboard issued {$large} queries with 20 rows and {$small} with 1. "
            .'A count that rises with the data is an N+1.'
        );
    }

    #[Test]
    public function the_admin_strip_query_count_does_not_grow_with_the_number_of_rows(): void
    {
        $queriesWith = function (int $n): int {
            $this->seedQueues($n);
            AuditLog::factory()->count($n)->create();
            $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($admin)->get(route('staff.dashboard'))->assertOk();

            $count = count(DB::getQueryLog());

            DB::disableQueryLog();

            return $count;
        };

        $small = $queriesWith(1);
        $this->refreshDatabase();
        $large = $queriesWith(20);

        $this->assertSame($small, $large, 'The Admin strip is issuing per-row queries.');
    }

    #[Test]
    public function the_relief_progress_bar_exposes_its_value_to_assistive_technology(): void
    {
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create();

        $distribution = ReliefDistribution::factory()->forBatch($batch)->forSector($sector)->create(['household_number' => 'H-1']);
        $distribution->forceFill(['status' => ReliefDistribution::STATUS_RECEIVED])->save();

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        // Blade's :aria-valuenow shorthand is a *component* feature. On a plain
        // <div> it is emitted verbatim as the literal text ":aria-valuenow", the
        // bar still looks right, and a browser drops the unknown attribute
        // without logging anything - so the page is visually perfect and a screen
        // reader is told nothing. This is the only assertion that catches it.
        $this->assertStringNotContainsString(':aria-valuenow', $html);
        $this->assertMatchesRegularExpression('/role="progressbar"[^>]*aria-valuenow="100"/', $html);
    }

    #[Test]
    public function a_partly_collected_batch_reports_a_partial_value(): void
    {
        $sector = Sector::factory()->create();
        $batch = ReliefBatch::factory()->create();

        foreach (range(1, 4) as $n) {
            $distribution = ReliefDistribution::factory()->forBatch($batch)->forSector($sector)->create([
                'household_number' => "H-{$n}",
            ]);

            if ($n <= 1) {
                $distribution->forceFill(['status' => ReliefDistribution::STATUS_RECEIVED])->save();
            }
        }

        $html = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();

        // 1 of 4 is 25%. Getting this wrong is what a 0 or a 100 would be.
        $this->assertMatchesRegularExpression('/role="progressbar"[^>]*aria-valuenow="25"/', $html);
    }

    #[Test]
    public function a_secretary_and_an_admin_get_the_same_working_widgets(): void
    {
        $this->seedQueues();

        $secretary = $this->actingAsStaff()->get(route('staff.dashboard'))->getContent();
        $admin = $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->get(route('staff.dashboard'))->getContent();

        // The queues are the same queues, so the figures must agree. If a widget
        // were ever quietly made Admin-only this is where it would show.
        foreach ([
            'Verifications pending',
            'Requests to review',
            'Ready for pickup',
            'Active relief batches',
        ] as $label) {
            $this->assertSame(
                $this->statCardValue($secretary, $label),
                $this->statCardValue($admin, $label),
                "The [{$label}] card differs between roles."
            );
        }
    }

    #[Test]
    public function a_resident_and_a_kagawad_still_cannot_reach_the_dashboard(): void
    {
        foreach ([User::ROLE_RESIDENT, User::ROLE_KAGAWAD] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get(route('staff.dashboard'))
                ->assertForbidden();
        }
    }

    // ----------------------------------------------------------------- helpers

    /**
     * One of everything the dashboard reads, $n of each.
     */
    private function seedQueues(int $n = 1): void
    {
        $sector = Sector::factory()->create();
        $documentType = DocumentType::factory()->create();

        $batch = ReliefBatch::factory()->create();

        for ($i = 0; $i < $n; $i++) {
            $user = User::factory()->create();

            ResidentProfile::factory()->pending()->for($user)->for($sector)->create();
            DocumentRequest::factory()->submitted()->forResident($user)->for($documentType)->create();
            DocumentRequest::factory()->ready()->forResident($user)->for($documentType)->create();
            ReliefDistribution::factory()->received()->forBatch($batch)->forSector($sector)->create([
                'household_number' => "HH-{$i}",
            ]);
        }
    }

    private function actingAsStaff(array $attributes = []): self
    {
        return $this->actingAs(
            User::factory()->create(array_merge(['role' => User::ROLE_SECRETARY], $attributes))
        );
    }
}
