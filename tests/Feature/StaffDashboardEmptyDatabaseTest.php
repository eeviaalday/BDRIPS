<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\DocumentType;
use App\Models\ReliefBatch;
use App\Models\ResidentProfile;
use App\Models\Sector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\ReadsDashboardWidgets;
use Tests\TestCase;

/**
 * The dashboard against a database with nothing in it.
 *
 * Separate from StaffDashboardWidgetsTest because the fixture is the point: the
 * tests here build no records at all. An empty install is the first thing a new
 * Secretary ever sees, and it is the case a dashboard written against seeded
 * data breaks in.
 */
class StaffDashboardEmptyDatabaseTest extends TestCase
{
    use ReadsDashboardWidgets;
    use RefreshDatabase;

    #[Test]
    public function the_database_really_is_empty(): void
    {
        // Guards the guard. If a factory leaked into a base class, every
        // assertion below would pass for the wrong reason.
        $this->assertSame(0, ResidentProfile::query()->count());
        $this->assertSame(0, DocumentRequest::query()->count());
        $this->assertSame(0, ReliefBatch::query()->count());
        $this->assertSame(0, AuditLog::query()->count());

        // Sectors are deliberately NOT asserted empty: the migration seeds the
        // barangay's 17 sectors, so they are part of a fresh install rather than
        // leftover data. They are reference data, not something anybody created.
        $this->assertGreaterThan(0, Sector::query()->count());
    }

    #[Test]
    public function a_secretary_gets_a_working_dashboard_with_no_data(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY, 'name' => 'Liza Natividad']);

        $response = $this->actingAs($secretary)->get(route('staff.dashboard'));

        $response->assertOk();

        // Header
        $response->assertSee('Liza');
        $response->assertSee('Here is what is waiting on you.');

        // Every widget's own empty state.
        $response->assertSee('No registrations waiting.');
        $response->assertSee('No requests waiting.');
        $response->assertSee('Nothing ready for pickup.');
        $response->assertSee('No active relief batch.');

        // And the way out of the empty relief card.
        $response->assertSee(route('staff.batches.create'), escape: false);

        // Quick actions are still reachable on a first login.
        $response->assertSee(route('staff.announcements.create'), escape: false);
        $response->assertSee(route('staff.residents.index'), escape: false);
    }

    #[Test]
    public function the_stat_cards_all_read_zero_rather_than_blank(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);
        $html = $this->actingAs($secretary)->get(route('staff.dashboard'))->getContent();

        foreach (['Verifications pending', 'Requests to review', 'Ready for pickup', 'Active relief batches'] as $label) {
            $this->assertSame(
                '0',
                $this->statCardValue($html, $label),
                "The [{$label}] card did not show 0 on an empty database."
            );
        }

        // And no waiting hint, because nothing is waiting.
        $this->assertStringNotContainsString('oldest waiting', $html);
    }

    #[Test]
    public function an_admin_gets_the_strip_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $response = $this->actingAs($admin)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('For admins');
        $response->assertSee('Nothing logged yet.');

        // Every role still listed, at zero - the accounts card must not collapse
        // to an empty box just because the counts are all zero.
        $html = $response->getContent();
        foreach (User::roles() as $role) {
            $this->assertStringContainsString(ucfirst($role), $html);
        }
    }

    #[Test]
    public function an_empty_dashboard_issues_a_bounded_number_of_queries(): void
    {
        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($secretary)->get(route('staff.dashboard'))->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        // A ceiling is only a smoke alarm here; the load-bearing assertion is the
        // constant-count test in StaffDashboardWidgetsTest. This one just says
        // the empty path is not doing per-widget work it should skip.
        $this->assertLessThan(30, $count, "The empty dashboard issued {$count} queries.");
    }

    #[Test]
    public function a_dashboard_with_data_but_no_batches_does_not_divide_by_zero(): void
    {
        // The narrow case: work to do, nothing to report progress on.
        $sector = Sector::factory()->create();
        $documentType = DocumentType::factory()->create();
        $user = User::factory()->create();

        ResidentProfile::factory()->pending()->for($user)->for($sector)->create();
        DocumentRequest::factory()->submitted()->forResident($user)->for($documentType)->create();
        DocumentRequest::factory()->ready()->forResident($user)->for($documentType)->create();

        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);

        $response = $this->actingAs($secretary)->get(route('staff.dashboard'));

        $response->assertOk();
        // The populated widgets render...
        $response->assertDontSee('No registrations waiting.');
        $response->assertDontSee('No requests waiting.');
        $response->assertDontSee('Nothing ready for pickup.');
        // ...and the empty one still renders as an empty state.
        $response->assertSee('No active relief batch.');
        $this->assertStringNotContainsString('Infinity', $response->getContent());
        $this->assertStringNotContainsString('NaN', $response->getContent());
    }

    #[Test]
    public function an_active_batch_whose_distributions_were_all_deleted_still_renders(): void
    {
        // The batchProgress array is keyed by id; a batch with no distributions
        // never appears in the grouped query, so the key is only there because it
        // is seeded. If the seeding were removed this is what would break.
        $batch = ReliefBatch::factory()->create(['title' => 'Abandoned batch']);

        $secretary = User::factory()->create(['role' => User::ROLE_SECRETARY]);

        $response = $this->actingAs($secretary)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Abandoned batch');
        $response->assertSee('0 / 0', escape: false);
        $this->assertStringNotContainsString('NaN', $response->getContent());
    }
}
